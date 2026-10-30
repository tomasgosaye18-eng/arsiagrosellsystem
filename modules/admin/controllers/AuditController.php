<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * File    : modules/admin/controllers/AuditController.php
 * Purpose : Business Operational Audit Trail.
 *           Tracks WHO did WHAT, WHEN, WHERE, and to WHICH
 *           business record across the full AAM workflow.
 *           Excludes auth/security/session events — this is
 *           a BUSINESS audit trail, not a security log.
 * Routes  : GET  /admin/audit-logs
 *           GET  /admin/audit-logs/{id}
 *           GET  /admin/audit-logs/export?format=pdf|excel
 * Access  : ROLE_ADMIN + ROLE_GENERAL_MANAGER
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Admin\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;

class AuditController extends BaseController
{
    // ── Module colour/icon map (for UI badges) ─────────────────────────────
    private const MODULE_META = [
        'farm'      => ['color' => 'success',  'icon' => 'bi-tree-fill'],
        'warehouse' => ['color' => 'primary',  'icon' => 'bi-building-fill'],
        'transfer'  => ['color' => 'info',     'icon' => 'bi-arrow-left-right'],
        'shop'      => ['color' => 'warning',  'icon' => 'bi-shop'],
        'product'   => ['color' => 'secondary','icon' => 'bi-box-seam-fill'],
        'user'      => ['color' => 'dark',     'icon' => 'bi-person-fill-gear'],
        'admin'     => ['color' => 'danger',   'icon' => 'bi-shield-fill-check'],
        'system'    => ['color' => 'light',    'icon' => 'bi-cpu-fill'],
    ];

    // ── Action colour map ──────────────────────────────────────────────────
    private const ACTION_COLOR = [
        'created'   => 'success',
        'updated'   => 'warning',
        'deleted'   => 'danger',
        'cancelled' => 'danger',
        'voided'    => 'danger',
        'approved'  => 'success',
        'rejected'  => 'danger',
        'received'  => 'primary',
        'dispatched'=> 'info',
        'adjusted'  => 'warning',
        'counted'   => 'secondary',
        'changed'   => 'warning',
        'activated' => 'success',
        'deactivated'=> 'secondary',
        'assigned'  => 'info',
        'generated' => 'primary',
        'reprinted' => 'secondary',
    ];

    // ──────────────────────────────────────────────────────────────────────
    // LIST — GET /admin/audit-logs
    // ──────────────────────────────────────────────────────────────────────
    public function index(): void
    {
        // ROLE_ADMIN: full view (all modules including user/admin events)
        // ROLE_GENERAL_MANAGER: read-only business audit trail (auth events excluded by buildWheres)
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);

        $db      = Database::getInstance();
        $perPage = 50;
        $page    = max(1, (int) $this->query('page', 1));

        // ── Filters ───────────────────────────────────────────────────────
        $filters = [
            'search'       => trim((string) $this->query('search', '')),
            'module'       => trim((string) $this->query('module', '')),
            'action_type'  => trim((string) $this->query('action_type', '')),
            'user_id'      => (int) $this->query('user_id', 0),
            'role_name'    => trim((string) $this->query('role_name', '')),
            'farm_id'      => (int) $this->query('farm_id', 0),
            'warehouse_id' => (int) $this->query('warehouse_id', 0),
            'shop_id'      => (int) $this->query('shop_id', 0),
            'date_from'    => trim((string) $this->query('date_from', '')),
            'date_to'      => trim((string) $this->query('date_to', '')),
            'entity_type'  => trim((string) $this->query('entity_type', '')),
        ];

        [$wheres, $params] = $this->buildWheres($filters);
        $whereClause = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

        // ── Total count ───────────────────────────────────────────────────
        $total = 0;
        $logs  = [];
        try {
            $total = (int) $db->fetchColumn(
                "SELECT COUNT(*)
                 FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 {$whereClause}",
                $params
            );

            $params['limit']  = $perPage;
            $params['offset'] = ($page - 1) * $perPage;

            $logs = $db->fetchAll(
                "SELECT
                    a.id,
                    a.action_type,
                    a.module,
                    a.entity_type,
                    a.entity_label,
                    a.description,
                    a.record_id,
                    a.old_values,
                    a.new_values,
                    a.location_type,
                    a.farm_id,
                    a.warehouse_id,
                    a.shop_id,
                    a.ip_address,
                    a.action_at,
                    a.role_name,
                    u.username,
                    u.first_name,
                    u.last_name,
                    f.name  AS farm_name,
                    w.name  AS warehouse_name,
                    s.name  AS shop_name
                 FROM audit_logs a
                 LEFT JOIN users      u ON u.id = a.user_id
                 LEFT JOIN farms      f ON f.id = a.farm_id
                 LEFT JOIN warehouses w ON w.id = a.warehouse_id
                 LEFT JOIN shops      s ON s.id = a.shop_id
                 {$whereClause}
                 ORDER BY a.action_at DESC
                 LIMIT :limit OFFSET :offset",
                $params
            );
        } catch (\Throwable $e) {
            // Table may not yet have migrated columns — degrade gracefully
        }

        // ── Dropdown options ──────────────────────────────────────────────
        $filterOptions = $this->loadFilterOptions($db);

        $this->render('admin.audit.index', [
            'pageTitle'    => 'Business Audit Trail',
            'logs'         => $logs,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'totalPages'   => (int) ceil($total / max(1, $perPage)),
            'filters'      => $filters,
            'moduleMeta'   => self::MODULE_META,
            'actionColor'  => self::ACTION_COLOR,
            'filterOptions'=> $filterOptions,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // DETAIL — GET /admin/audit-logs/{id}
    // ──────────────────────────────────────────────────────────────────────
    public function show(string $id): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);

        $log = null;
        try {
            $log = Database::getInstance()->fetchOne(
                "SELECT
                    a.*,
                    u.username, u.first_name, u.last_name,
                    f.name AS farm_name,
                    w.name AS warehouse_name,
                    s.name AS shop_name
                 FROM audit_logs a
                 LEFT JOIN users      u ON u.id = a.user_id
                 LEFT JOIN farms      f ON f.id = a.farm_id
                 LEFT JOIN warehouses w ON w.id = a.warehouse_id
                 LEFT JOIN shops      s ON s.id = a.shop_id
                 WHERE a.id = :id",
                ['id' => (int) $id]
            );
        } catch (\Throwable $e) {}

        if (!$log) {
            $this->flash(FLASH_ERROR, 'Audit record not found.');
            $this->redirect('/admin/audit-logs');
            return;
        }

        $this->render('admin.audit.show', [
            'pageTitle'   => 'Audit Record #' . (int) $id,
            'log'         => $log,
            'moduleMeta'  => self::MODULE_META,
            'actionColor' => self::ACTION_COLOR,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // EXPORT — GET /admin/audit-logs/export?format=excel|pdf
    // ──────────────────────────────────────────────────────────────────────
    public function export(): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);

        $db      = Database::getInstance();
        $format  = strtolower(trim((string) $this->query('format', 'excel')));
        $filters = [
            'search'       => trim((string) $this->query('search', '')),
            'module'       => trim((string) $this->query('module', '')),
            'action_type'  => trim((string) $this->query('action_type', '')),
            'user_id'      => (int) $this->query('user_id', 0),
            'role_name'    => trim((string) $this->query('role_name', '')),
            'farm_id'      => (int) $this->query('farm_id', 0),
            'warehouse_id' => (int) $this->query('warehouse_id', 0),
            'shop_id'      => (int) $this->query('shop_id', 0),
            'date_from'    => trim((string) $this->query('date_from', '')),
            'date_to'      => trim((string) $this->query('date_to', '')),
            'entity_type'  => trim((string) $this->query('entity_type', '')),
        ];

        [$wheres, $params] = $this->buildWheres($filters);
        $whereClause = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

        $logs = [];
        try {
            $logs = $db->fetchAll(
                "SELECT
                    a.action_at,
                    u.username,
                    a.role_name,
                    a.module,
                    a.action_type,
                    a.entity_type,
                    a.entity_label,
                    a.record_id,
                    a.description,
                    f.name  AS farm_name,
                    w.name  AS warehouse_name,
                    s.name  AS shop_name,
                    a.ip_address
                 FROM audit_logs a
                 LEFT JOIN users      u ON u.id = a.user_id
                 LEFT JOIN farms      f ON f.id = a.farm_id
                 LEFT JOIN warehouses w ON w.id = a.warehouse_id
                 LEFT JOIN shops      s ON s.id = a.shop_id
                 {$whereClause}
                 ORDER BY a.action_at DESC
                 LIMIT 10000",
                $params
            );
        } catch (\Throwable $e) {}

        if ($format === 'pdf') {
            $this->exportPdf($logs);
        } else {
            $this->exportExcel($logs);
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /** Build WHERE clauses and params from filter array */
    private function buildWheres(array $f): array
    {
        $wheres = [];
        $params = [];

        // Always exclude security/account/session events — business audit trail only
        $wheres[]  = "a.module NOT IN ('auth', 'login', 'security', 'session')";
        $wheres[]  = "a.action_type NOT IN ('user.login','user.logout','password.reset','password.changed','user.locked','user.unlocked','session.expired')";

        // General Manager sees business operational events only.
        // Admin additionally sees user/admin management events.
        if ($this->userRole() === ROLE_GENERAL_MANAGER) {
            $wheres[] = "a.module NOT IN ('user', 'admin')";
            $wheres[] = "a.action_type NOT LIKE 'user.%'";
            $wheres[] = "a.action_type NOT LIKE 'admin.%'";
        }

        if ($f['search'] !== '') {
            $wheres[]       = "(a.description LIKE :s OR a.entity_label LIKE :s2 OR u.username LIKE :s3 OR a.action_type LIKE :s4)";
            $params['s']    = "%{$f['search']}%";
            $params['s2']   = "%{$f['search']}%";
            $params['s3']   = "%{$f['search']}%";
            $params['s4']   = "%{$f['search']}%";
        }
        if ($f['module'] !== '') {
            $wheres[]         = 'a.module = :module';
            $params['module'] = $f['module'];
        }
        if ($f['action_type'] !== '') {
            $wheres[]              = 'a.action_type LIKE :at';
            $params['at']          = "%{$f['action_type']}%";
        }
        if ($f['user_id'] > 0) {
            $wheres[]          = 'a.user_id = :uid';
            $params['uid']     = $f['user_id'];
        }
        if ($f['role_name'] !== '') {
            $wheres[]             = 'a.role_name = :role_name';
            $params['role_name']  = $f['role_name'];
        }
        if ($f['farm_id'] > 0) {
            $wheres[]           = 'a.farm_id = :farm_id';
            $params['farm_id']  = $f['farm_id'];
        }
        if ($f['warehouse_id'] > 0) {
            $wheres[]                = 'a.warehouse_id = :warehouse_id';
            $params['warehouse_id']  = $f['warehouse_id'];
        }
        if ($f['shop_id'] > 0) {
            $wheres[]          = 'a.shop_id = :shop_id';
            $params['shop_id'] = $f['shop_id'];
        }
        if ($f['date_from'] !== '') {
            $wheres[]              = 'DATE(a.action_at) >= :date_from';
            $params['date_from']   = $f['date_from'];
        }
        if ($f['date_to'] !== '') {
            $wheres[]            = 'DATE(a.action_at) <= :date_to';
            $params['date_to']   = $f['date_to'];
        }
        if ($f['entity_type'] !== '') {
            $wheres[]               = 'a.entity_type = :entity_type';
            $params['entity_type']  = $f['entity_type'];
        }

        return [$wheres, $params];
    }

    /** Load dropdown options for filter panel */
    private function loadFilterOptions(Database $db): array
    {
        $opts = ['users' => [], 'roles' => [], 'farms' => [], 'warehouses' => [], 'shops' => [], 'modules' => [], 'entity_types' => []];
        try {
            $opts['users']        = $db->fetchAll("SELECT id, username, first_name, last_name FROM users WHERE account_status = 'active' AND is_active = 1 ORDER BY first_name");
            $opts['roles']        = $db->fetchAll("SELECT name FROM roles WHERE is_active = 1 ORDER BY name");
            $opts['farms']        = $db->fetchAll("SELECT id, name FROM farms WHERE is_active = 1 ORDER BY name");
            $opts['warehouses']   = $db->fetchAll("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name");
            $opts['shops']        = $db->fetchAll("SELECT id, name FROM shops WHERE is_active = 1 ORDER BY name");
            $opts['modules']      = ['farm', 'harvest', 'dispatch', 'warehouse', 'receiving', 'transfer', 'shop', 'sale', 'product', 'inventory', 'user', 'admin', 'organization'];
            $opts['entity_types'] = $db->fetchAll("SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type");
        } catch (\Throwable $e) {}
        return $opts;
    }

    /** Export as CSV/Excel */
    private function exportExcel(array $logs): void
    {
        $filename = 'business_audit_trail_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Time', 'User', 'Role', 'Module', 'Action', 'Entity', 'Record ID', 'Description', 'Location', 'IP Address']);

        foreach ($logs as $log) {
            $dt       = new \DateTime($log['action_at'] ?? 'now');
            $location = $log['farm_name'] ?? $log['warehouse_name'] ?? $log['shop_name'] ?? '—';
            fputcsv($out, [
                $dt->format('Y-m-d'),
                $dt->format('H:i:s'),
                $log['username'] ?? 'System',
                $log['role_name'] ?? '—',
                ucfirst($log['module'] ?? '—'),
                strtoupper($log['action_type'] ?? '—'),
                ($log['entity_label'] ?? $log['entity_type'] ?? '—'),
                $log['record_id'] ?? '—',
                $log['description'] ?? '—',
                $location,
                $log['ip_address'] ?? '—',
            ]);
        }
        fclose($out);
        exit;
    }
    private function exportPdf(array $logs): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $now = date('F j, Y H:i');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
        echo '<title>Business Audit Trail Export</title>';
        echo '<style>
            body{font-family:Arial,sans-serif;font-size:11px;margin:20px;}
            h2{color:#0d6efd;margin-bottom:4px;}
            p.sub{color:#666;margin-bottom:16px;}
            table{width:100%;border-collapse:collapse;}
            th{background:#0d6efd;color:#fff;padding:5px 6px;text-align:left;font-size:10px;}
            td{padding:4px 6px;border-bottom:1px solid #eee;vertical-align:top;}
            tr:nth-child(even){background:#f8f9fa;}
            @media print{@page{size:A3 landscape;margin:15mm;}}
        </style></head><body>';
        echo '<h2>Business Audit Trail</h2>';
        echo '<p class="sub">Track who performed what operational action across the AAM system &nbsp;|&nbsp; Exported: ' . $now . '</p>';
        echo '<table><thead><tr>
            <th>Date &amp; Time</th><th>User</th><th>Role</th><th>Module</th>
            <th>Action</th><th>Entity</th><th>Record</th><th>Description</th><th>Location</th>
        </tr></thead><tbody>';
        foreach ($logs as $log) {
            $dt       = new \DateTime($log['action_at'] ?? 'now');
            $location = $log['farm_name'] ?? $log['warehouse_name'] ?? $log['shop_name'] ?? '—';
            echo '<tr>';
            echo '<td>' . htmlspecialchars($dt->format('Y-m-d H:i:s')) . '</td>';
            echo '<td>' . htmlspecialchars($log['username'] ?? 'System') . '</td>';
            echo '<td>' . htmlspecialchars($log['role_name'] ?? '—') . '</td>';
            echo '<td>' . htmlspecialchars(ucfirst($log['module'] ?? '—')) . '</td>';
            echo '<td><strong>' . htmlspecialchars(strtoupper($log['action_type'] ?? '—')) . '</strong></td>';
            echo '<td>' . htmlspecialchars($log['entity_label'] ?? $log['entity_type'] ?? '—') . '</td>';
            echo '<td>' . htmlspecialchars((string)($log['record_id'] ?? '—')) . '</td>';
            echo '<td>' . htmlspecialchars($log['description'] ?? '—') . '</td>';
            echo '<td>' . htmlspecialchars($location) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<script>window.onload=function(){window.print();}</script>';
        echo '</body></html>';
        exit;
    }
}
