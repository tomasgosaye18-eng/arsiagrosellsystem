<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/reports/models/AdminReportModel.php
 * Purpose : Data access for System Administration Reports.
 *           Covers:
 *             1. User Report   — users, roles, login history
 *             2. Org Report    — farms, warehouses, shops, assignments
 *             3. Audit Summary — audit_logs breakdown
 *
 * Access  : ROLE_ADMIN + ROLE_GENERAL_MANAGER only
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Reports\Models;

use AAM\Core\BaseModel;

class AdminReportModel extends BaseModel
{
    protected string $table      = 'users';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    // =========================================================
    // SHARED — Summary cards used on the admin reports hub
    // =========================================================

    /**
     * 6 summary card values for the admin reports hub.
     */
    public function getHubSummary(): array
    {
        $db = $this->getDb();
        $s  = [];

        try {
            $s['total_users']    = (int)($db->fetchColumn("SELECT COUNT(*) FROM users WHERE account_status != 'deleted'") ?? 0);
            $s['active_users']   = (int)($db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_active = 1 AND account_status = 'active'") ?? 0);
            $s['locked_users']   = (int)($db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_locked = 1") ?? 0);
            $s['total_locations']= (int)($db->fetchColumn(
                "SELECT (SELECT COUNT(*) FROM farms WHERE deleted_at IS NULL) +
                        (SELECT COUNT(*) FROM warehouses WHERE deleted_at IS NULL) +
                        (SELECT COUNT(*) FROM shops WHERE deleted_at IS NULL)"
            ) ?? 0);
            $s['total_assignments'] = (int)($db->fetchColumn("SELECT COUNT(*) FROM organization_assignments WHERE is_active = 1") ?? 0);
            $s['audit_today']    = (int)($db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE DATE(action_at) = CURDATE()") ?? 0);
        } catch (\Throwable) {}

        return $s;
    }

    // =========================================================
    // 1. USER REPORT
    // =========================================================

    /**
     * Summary cards for the User Report.
     */
    public function getUserSummary(): array
    {
        $db = $this->getDb();
        $s  = [];
        try {
            $s['total']       = (int)($db->fetchColumn("SELECT COUNT(*) FROM users WHERE account_status != 'deleted'") ?? 0);
            $s['active']      = (int)($db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_active = 1 AND account_status = 'active'") ?? 0);
            $s['inactive']    = (int)($db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_active = 0 AND account_status != 'deleted'") ?? 0);
            $s['locked']      = (int)($db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_locked = 1") ?? 0);
            $s['new_this_month'] = (int)($db->fetchColumn(
                "SELECT COUNT(*) FROM users
                 WHERE YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())
                   AND account_status != 'deleted'"
            ) ?? 0);
            $s['failed_logins_today'] = (int)($db->fetchColumn(
                "SELECT COUNT(*) FROM login_history
                 WHERE was_successful = 0 AND DATE(attempted_at) = CURDATE()"
            ) ?? 0);
        } catch (\Throwable) {}
        return $s;
    }

    /**
     * Users broken down by role.
     */
    public function getUsersByRole(): array
    {
        try {
            return $this->rawQuery(
                "SELECT r.display_name AS role_label,
                        r.name         AS role_name,
                        COUNT(u.id)    AS user_count,
                        SUM(CASE WHEN u.is_active = 1 AND u.account_status = 'active' THEN 1 ELSE 0 END) AS active_count,
                        SUM(CASE WHEN u.is_locked = 1 THEN 1 ELSE 0 END) AS locked_count
                 FROM roles r
                 LEFT JOIN users u ON u.role_id = r.id AND u.account_status != 'deleted'
                 WHERE r.is_active = 1
                 GROUP BY r.id, r.display_name, r.name
                 ORDER BY user_count DESC"
            );
        } catch (\Throwable) { return []; }
    }

    /**
     * Paginated user list with role and last login.
     */
    public function paginateUsers(
        int    $page    = 1,
        int    $perPage = 25,
        array  $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, 100);
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildUserFilters($filters);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE {$where}", $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT u.id, u.username, u.employee_id,
                    CONCAT(u.first_name, ' ', COALESCE(u.middle_name,''), ' ', u.last_name) AS full_name,
                    u.email, u.phone,
                    u.is_active, u.is_locked, u.account_status,
                    u.failed_login_attempts,
                    u.last_login_at, u.created_at,
                    r.display_name AS role_label, r.name AS role_name,
                    (SELECT COUNT(*) FROM login_history lh
                     WHERE lh.user_id = u.id AND lh.was_successful = 1) AS total_logins,
                    (SELECT COUNT(*) FROM login_history lh
                     WHERE lh.user_id = u.id AND lh.was_successful = 0
                       AND lh.attempted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS failed_30d
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE {$where}
             ORDER BY u.created_at DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / max(1, $perPage)),
            'from'       => $total > 0 ? $offset + 1 : 0,
            'to'         => min($offset + $perPage, $total),
        ];
    }

    /**
     * Login trend — daily login counts for the last 30 days.
     *
     * @return array{labels: string[], success: int[], failed: int[]}
     */
    public function getLoginTrend(string $dateFrom = '', string $dateTo = ''): array
    {
        $from = $dateFrom ?: date('Y-m-d', strtotime('-29 days'));
        $to   = $dateTo   ?: date('Y-m-d');
        try {
            $rows = $this->rawQuery(
                "SELECT DATE_FORMAT(attempted_at, '%d %b') AS day_label,
                        DATE(attempted_at)                  AS day,
                        SUM(was_successful = 1)             AS success_cnt,
                        SUM(was_successful = 0)             AS failed_cnt
                 FROM login_history
                 WHERE DATE(attempted_at) BETWEEN :from AND :to
                 GROUP BY day, day_label
                 ORDER BY day ASC",
                ['from' => $from, 'to' => $to]
            );
        } catch (\Throwable) { $rows = []; }

        return [
            'labels'  => array_column($rows, 'day_label'),
            'success' => array_map('intval', array_column($rows, 'success_cnt')),
            'failed'  => array_map('intval', array_column($rows, 'failed_cnt')),
        ];
    }

    /**
     * Recent failed login attempts (last 20).
     */
    public function getRecentFailedLogins(int $limit = 20): array
    {
        try {
            return $this->rawQuery(
                "SELECT lh.attempted_at, lh.username_attempted,
                        lh.ip_address, lh.browser, lh.os_name, lh.device_type,
                        lh.failure_reason,
                        u.id AS user_id,
                        CONCAT(u.first_name,' ',u.last_name) AS user_name
                 FROM login_history lh
                 LEFT JOIN users u ON u.id = lh.user_id
                 WHERE lh.was_successful = 0
                 ORDER BY lh.attempted_at DESC
                 LIMIT :lim",
                ['lim' => $limit]
            );
        } catch (\Throwable) { return []; }
    }

    // =========================================================
    // 2. ORGANIZATION REPORT
    // =========================================================

    /**
     * Summary counts for org report cards.
     */
    public function getOrgSummary(): array
    {
        $db = $this->getDb();
        $s  = [];
        try {
            $s['total_farms']      = (int)($db->fetchColumn("SELECT COUNT(*) FROM farms WHERE is_active = 1 AND deleted_at IS NULL") ?? 0);
            $s['total_warehouses'] = (int)($db->fetchColumn("SELECT COUNT(*) FROM warehouses WHERE is_active = 1 AND deleted_at IS NULL") ?? 0);
            $s['total_shops']      = (int)($db->fetchColumn("SELECT COUNT(*) FROM shops WHERE is_active = 1 AND deleted_at IS NULL") ?? 0);
            $s['total_assignments']= (int)($db->fetchColumn("SELECT COUNT(*) FROM organization_assignments WHERE is_active = 1") ?? 0);
            $s['unassigned_staff'] = (int)($db->fetchColumn(
                "SELECT COUNT(*) FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE r.name IN ('farm_manager','warehouse_manager','salesperson')
                   AND u.is_active = 1
                   AND u.account_status = 'active'
                   AND u.id NOT IN (SELECT user_id FROM organization_assignments WHERE is_active = 1)"
            ) ?? 0);
        } catch (\Throwable) {}
        return $s;
    }

    /**
     * Farms with assignment info and harvest summary.
     */
    public function getFarmsWithStaff(): array
    {
        try {
            return $this->rawQuery(
                "SELECT f.id, f.farm_code, f.name, f.farm_type, f.location,
                        f.is_active,
                        CONCAT(mgr.first_name,' ',mgr.last_name) AS manager_name,
                        (SELECT COUNT(*) FROM organization_assignments oa
                         WHERE oa.location_type = 'farm' AND oa.location_id = f.id
                           AND oa.is_active = 1)                 AS staff_count,
                        (SELECT COUNT(*) FROM harvest_batches hb
                         WHERE hb.farm_id = f.id)                AS total_harvests,
                        (SELECT MAX(hb.harvest_date) FROM harvest_batches hb
                         WHERE hb.farm_id = f.id)                AS last_harvest_date
                 FROM farms f
                 LEFT JOIN users mgr ON mgr.id = f.manager_id
                 WHERE f.deleted_at IS NULL
                 ORDER BY f.is_active DESC, f.name ASC"
            );
        } catch (\Throwable) { return []; }
    }

    /**
     * Warehouses with assignment info.
     */
    public function getWarehousesWithStaff(): array
    {
        try {
            return $this->rawQuery(
                "SELECT w.id, w.name, w.location, w.is_active,
                        CONCAT(mgr.first_name,' ',mgr.last_name) AS manager_name,
                        (SELECT COUNT(*) FROM organization_assignments oa
                         WHERE oa.location_type = 'warehouse' AND oa.location_id = w.id
                           AND oa.is_active = 1) AS staff_count,
                        (SELECT COUNT(*) FROM farm_dispatches fd
                         WHERE fd.warehouse_id = w.id
                           AND fd.status IN ('received','partially_received')) AS total_received
                 FROM warehouses w
                 LEFT JOIN users mgr ON mgr.id = w.manager_id
                 WHERE w.deleted_at IS NULL
                 ORDER BY w.is_active DESC, w.name ASC"
            );
        } catch (\Throwable) { return []; }
    }

    /**
     * Shops with assignment info.
     */
    public function getShopsWithStaff(): array
    {
        try {
            return $this->rawQuery(
                "SELECT s.id, s.name, s.location, s.is_active,
                        CONCAT(mgr.first_name,' ',mgr.last_name) AS manager_name,
                        (SELECT COUNT(*) FROM organization_assignments oa
                         WHERE oa.location_type = 'shop' AND oa.location_id = s.id
                           AND oa.is_active = 1) AS staff_count
                 FROM shops s
                 LEFT JOIN users mgr ON mgr.id = s.manager_id
                 WHERE s.deleted_at IS NULL
                 ORDER BY s.is_active DESC, s.name ASC"
            );
        } catch (\Throwable) { return []; }
    }

    /**
     * All active assignments with user and location details.
     */
    public function getAllAssignments(): array
    {
        try {
            return $this->rawQuery(
                "SELECT oa.assignment_id, oa.location_type, oa.location_id,
                        oa.assignment_role, oa.assigned_at, oa.is_active,
                        CONCAT(u.first_name,' ',u.last_name) AS user_name,
                        u.username, u.email,
                        r.display_name AS role_label,
                        CONCAT(ab.first_name,' ',ab.last_name) AS assigned_by_name,
                        CASE oa.location_type
                            WHEN 'farm'      THEN (SELECT name FROM farms      WHERE id = oa.location_id)
                            WHEN 'warehouse' THEN (SELECT name FROM warehouses WHERE id = oa.location_id)
                            WHEN 'shop'      THEN (SELECT name FROM shops      WHERE id = oa.location_id)
                        END AS location_name
                 FROM organization_assignments oa
                 JOIN  users u  ON u.id = oa.user_id
                 LEFT JOIN roles r  ON r.id = u.role_id
                 LEFT JOIN users ab ON ab.id = oa.assigned_by
                 WHERE oa.is_active = 1
                 ORDER BY oa.location_type, location_name, u.first_name"
            );
        } catch (\Throwable) { return []; }
    }

    // =========================================================
    // 3. AUDIT SUMMARY REPORT
    // =========================================================

    /**
     * Audit summary cards.
     */
    public function getAuditSummary(string $dateFrom = '', string $dateTo = ''): array
    {
        [$dateWhere, $dateParams] = $this->buildDateWhere('a.action_at', $dateFrom, $dateTo);
        $db = $this->getDb();
        $s  = [];
        try {
            $s['total_actions']  = (int)($db->fetchColumn(
                "SELECT COUNT(*) FROM audit_logs a WHERE {$dateWhere}", $dateParams
            ) ?? 0);
            $s['unique_users']   = (int)($db->fetchColumn(
                "SELECT COUNT(DISTINCT a.user_id) FROM audit_logs a WHERE {$dateWhere} AND a.user_id IS NOT NULL", $dateParams
            ) ?? 0);
            $s['modules_active'] = (int)($db->fetchColumn(
                "SELECT COUNT(DISTINCT a.module) FROM audit_logs a WHERE {$dateWhere} AND a.module IS NOT NULL AND a.module != ''", $dateParams
            ) ?? 0);
            $s['actions_today']  = (int)($db->fetchColumn(
                "SELECT COUNT(*) FROM audit_logs WHERE DATE(action_at) = CURDATE()"
            ) ?? 0);
        } catch (\Throwable) {}
        return $s;
    }

    /**
     * Actions grouped by module for the period.
     */
    public function getActionsByModule(string $dateFrom = '', string $dateTo = ''): array
    {
        [$dateWhere, $dateParams] = $this->buildDateWhere('a.action_at', $dateFrom, $dateTo);
        try {
            return $this->rawQuery(
                "SELECT a.module, COUNT(*) AS action_count,
                        COUNT(DISTINCT a.user_id) AS unique_users
                 FROM audit_logs a
                 WHERE {$dateWhere}
                   AND a.module IS NOT NULL AND a.module != ''
                   AND a.module NOT IN ('auth','login','security','session')
                 GROUP BY a.module
                 ORDER BY action_count DESC",
                $dateParams
            );
        } catch (\Throwable) { return []; }
    }

    /**
     * Top most active users in the audit log for the period.
     */
    public function getTopActiveUsers(string $dateFrom = '', string $dateTo = '', int $limit = 10): array
    {
        [$dateWhere, $dateParams] = $this->buildDateWhere('a.action_at', $dateFrom, $dateTo);
        $dateParams['lim'] = $limit;
        try {
            return $this->rawQuery(
                "SELECT CONCAT(u.first_name,' ',u.last_name) AS user_name,
                        u.username, a.role_name,
                        COUNT(*) AS action_count,
                        MAX(a.action_at) AS last_action_at
                 FROM audit_logs a
                 JOIN users u ON u.id = a.user_id
                 WHERE {$dateWhere}
                   AND a.user_id IS NOT NULL
                 GROUP BY a.user_id, u.first_name, u.last_name, u.username, a.role_name
                 ORDER BY action_count DESC
                 LIMIT :lim",
                $dateParams
            );
        } catch (\Throwable) { return []; }
    }

    /**
     * Recent audit log entries — paginated.
     */
    public function paginateAuditLog(
        int    $page    = 1,
        int    $perPage = 25,
        array  $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, 100);
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildAuditFilters($filters);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE {$where}", $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT a.id, a.action_type, a.module, a.entity_type, a.entity_label,
                    a.description, a.record_id, a.location_type,
                    a.farm_id, a.warehouse_id, a.shop_id,
                    a.role_name, a.ip_address, a.action_at,
                    CONCAT(u.first_name,' ',u.last_name) AS user_name,
                    u.username,
                    (SELECT name FROM farms      WHERE id = a.farm_id)      AS farm_name,
                    (SELECT name FROM warehouses WHERE id = a.warehouse_id) AS warehouse_name,
                    (SELECT name FROM shops      WHERE id = a.shop_id)      AS shop_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE {$where}
             ORDER BY a.action_at DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / max(1, $perPage)),
            'from'       => $total > 0 ? $offset + 1 : 0,
            'to'         => min($offset + $perPage, $total),
        ];
    }

    /**
     * Daily audit action count for trend chart.
     *
     * @return array{labels: string[], data: int[]}
     */
    public function getAuditTrend(string $dateFrom = '', string $dateTo = ''): array
    {
        $from = $dateFrom ?: date('Y-m-d', strtotime('-29 days'));
        $to   = $dateTo   ?: date('Y-m-d');
        try {
            $rows = $this->rawQuery(
                "SELECT DATE_FORMAT(action_at, '%d %b') AS day_label,
                        DATE(action_at)                  AS day,
                        COUNT(*)                         AS cnt
                 FROM audit_logs
                 WHERE DATE(action_at) BETWEEN :from AND :to
                   AND module NOT IN ('auth','login','security','session')
                 GROUP BY day, day_label
                 ORDER BY day ASC",
                ['from' => $from, 'to' => $to]
            );
        } catch (\Throwable) { $rows = []; }

        return [
            'labels' => array_column($rows, 'day_label'),
            'data'   => array_map('intval', array_column($rows, 'cnt')),
        ];
    }

    /**
     * Dropdown helpers for filters.
     */
    public function getRolesForFilter(): array
    {
        try {
            return $this->rawQuery("SELECT name, display_name FROM roles WHERE is_active = 1 ORDER BY display_name");
        } catch (\Throwable) { return []; }
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    private function buildUserFilters(array $f): array
    {
        $where  = ["u.account_status != 'deleted'"];
        $params = [];

        if (!empty($f['search'])) {
            $where[]       = "(u.username LIKE :q OR u.first_name LIKE :q OR u.last_name LIKE :q OR u.email LIKE :q OR u.employee_id LIKE :q)";
            $params['q']   = '%' . $f['search'] . '%';
        }
        if (!empty($f['role'])) {
            $where[]          = 'r.name = :role';
            $params['role']   = $f['role'];
        }
        if (!empty($f['status'])) {
            if ($f['status'] === 'locked') {
                $where[] = 'u.is_locked = 1';
            } elseif ($f['status'] === 'active') {
                $where[] = "u.is_active = 1 AND u.account_status = 'active'";
            } elseif ($f['status'] === 'inactive') {
                $where[] = "u.is_active = 0";
            } else {
                $where[]            = 'u.account_status = :status';
                $params['status']   = $f['status'];
            }
        }

        return [implode(' AND ', $where), $params];
    }

    private function buildAuditFilters(array $f): array
    {
        $where  = [
            "a.module NOT IN ('auth','login','security','session')",
            "a.action_type NOT IN ('user.login','user.logout','password.reset','password.changed')",
        ];
        $params = [];

        if (!empty($f['module'])) {
            $where[]          = 'a.module = :module';
            $params['module'] = $f['module'];
        }
        if (!empty($f['user_id'])) {
            $where[]          = 'a.user_id = :uid';
            $params['uid']    = (int)$f['user_id'];
        }
        if (!empty($f['date_from'])) {
            $where[]             = 'DATE(a.action_at) >= :dfrom';
            $params['dfrom']     = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $where[]             = 'DATE(a.action_at) <= :dto';
            $params['dto']       = $f['date_to'];
        }
        if (!empty($f['search'])) {
            $where[]             = "(a.description LIKE :q OR a.entity_label LIKE :q OR u.username LIKE :q)";
            $params['q']         = '%' . $f['search'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    private function buildDateWhere(string $col, string $dateFrom, string $dateTo): array
    {
        $where  = ['1=1'];
        $params = [];
        if ($dateFrom !== '') {
            $where[]           = "DATE({$col}) >= :dfrom";
            $params['dfrom']   = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[]           = "DATE({$col}) <= :dto";
            $params['dto']     = $dateTo;
        }
        return [implode(' AND ', $where), $params];
    }
}
