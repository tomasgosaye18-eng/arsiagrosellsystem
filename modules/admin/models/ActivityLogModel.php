<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File        : modules/admin/models/ActivityLogModel.php
 * Purpose     : Dual-purpose log model.
 *
 *   1. activity_logs  — lightweight operational telemetry
 *      (dashboard recent-activity panel, user session feed)
 *
 *   2. audit_logs     — immutable Business Operational Audit
 *      Trail tracking WHO did WHAT, WHEN, WHERE, and to
 *      WHICH business record across the full AAM workflow.
 *
 * Usage (from any controller):
 *   $log = new ActivityLogModel();
 *
 *   // Dashboard / session telemetry
 *   $log->log('user.login', 'User logged in');
 *
 *   // Business audit entry
 *   $log->logBusiness([
 *       'action'      => ActivityLogModel::ACTION_HARVEST_CREATED,
 *       'module'      => 'farm',
 *       'entity_type' => 'harvest',
 *       'entity_label'=> 'Harvest #142 — Milk',
 *       'record_id'   => 142,
 *       'description' => 'Harvest created: 500L Milk, Farm East',
 *       'farm_id'     => 3,
 *       'old_values'  => null,
 *       'new_values'  => ['quantity' => 500, 'product' => 'Milk'],
 *   ]);
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Admin\Models;

use AAM\Core\BaseModel;
use AAM\Core\SessionManager;

class ActivityLogModel extends BaseModel
{
    protected string $table      = 'activity_logs';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    // =========================================================
    // SECTION A — SECURITY / ACCOUNT ACTIONS
    // (Used internally; NOT shown on Business Audit Trail page)
    // =========================================================
    public const ACTION_LOGIN            = 'user.login';
    public const ACTION_LOGOUT           = 'user.logout';
    public const ACTION_PASSWORD_RESET   = 'password.reset';
    public const ACTION_PASSWORD_CHANGED = 'password.changed';
    public const ACTION_PROFILE_UPDATED  = 'profile.updated';
    public const ACTION_PHOTO_UPLOADED   = 'profile.photo_uploaded';

    // =========================================================
    // SECTION B — BUSINESS OPERATIONAL ACTIONS
    // These are written to audit_logs via logBusiness()
    // =========================================================

    // ── Farm ──────────────────────────────────────────────────
    public const ACTION_HARVEST_CREATED    = 'harvest.created';
    public const ACTION_HARVEST_UPDATED    = 'harvest.updated';
    public const ACTION_HARVEST_CANCELLED  = 'harvest.cancelled';
    public const ACTION_DISPATCH_CREATED   = 'dispatch.created';
    public const ACTION_DISPATCH_UPDATED   = 'dispatch.updated';
    public const ACTION_DISPATCH_CANCELLED = 'dispatch.cancelled';

    // ── Warehouse ─────────────────────────────────────────────
    public const ACTION_STOCK_RECEIVED    = 'stock.received';
    public const ACTION_STOCK_ADJUSTED    = 'stock.adjusted';
    public const ACTION_STOCK_COUNTED     = 'stock.counted';
    public const ACTION_DISCREPANCY_CREATED  = 'discrepancy.created';
    public const ACTION_DISCREPANCY_UPDATED  = 'discrepancy.updated';
    public const ACTION_DISCREPANCY_APPROVED = 'discrepancy.approved';
    public const ACTION_DISCREPANCY_REJECTED = 'discrepancy.rejected';

    // ── Transfers ─────────────────────────────────────────────
    public const ACTION_TRANSFER_CREATED    = 'transfer.created';
    public const ACTION_TRANSFER_APPROVED   = 'transfer.approved';
    public const ACTION_TRANSFER_REJECTED   = 'transfer.rejected';
    public const ACTION_TRANSFER_DISPATCHED = 'transfer.dispatched';
    public const ACTION_TRANSFER_RECEIVED   = 'transfer.received';
    public const ACTION_TRANSFER_CANCELLED  = 'transfer.cancelled';

    // ── Shops ─────────────────────────────────────────────────
    public const ACTION_SALE_CREATED      = 'sale.created';
    public const ACTION_SALE_VOIDED       = 'sale.voided';
    public const ACTION_SALE_CANCELLED    = 'sale.cancelled';
    public const ACTION_RECEIPT_GENERATED = 'receipt.generated';
    public const ACTION_RECEIPT_REPRINTED = 'receipt.reprinted';
    public const ACTION_SHOP_STOCK_ADJUSTED = 'shop.stock.adjusted';
    public const ACTION_SHIFT_OPENED      = 'shift.opened';
    public const ACTION_SHIFT_CLOSED      = 'shift.closed';

    // ── Products ──────────────────────────────────────────────
    public const ACTION_PRODUCT_CREATED     = 'product.created';
    public const ACTION_PRODUCT_UPDATED     = 'product.updated';
    public const ACTION_PRODUCT_PRICE_CHANGED  = 'product.price.changed';
    public const ACTION_PRODUCT_ACTIVATED   = 'product.activated';
    public const ACTION_PRODUCT_DEACTIVATED = 'product.deactivated';

    // ── Users / Admin ─────────────────────────────────────────
    public const ACTION_USER_CREATED        = 'user.created';
    public const ACTION_USER_UPDATED        = 'user.updated';
    public const ACTION_USER_DELETED        = 'user.deleted';
    public const ACTION_USER_ACTIVATED      = 'user.activated';
    public const ACTION_USER_DEACTIVATED    = 'user.deactivated';
    public const ACTION_USER_LOCKED         = 'user.locked';
    public const ACTION_USER_UNLOCKED       = 'user.unlocked';
    public const ACTION_USER_ROLE_CHANGED   = 'user.role.changed';
    public const ACTION_USER_ASSIGNED_SHOP  = 'user.assigned.shop';
    public const ACTION_USER_ASSIGNED_FARM  = 'user.assigned.farm';
    public const ACTION_USER_ASSIGNED_WAREHOUSE = 'user.assigned.warehouse';
    public const ACTION_PERMISSION_CHANGED  = 'permission.changed';

    // =========================================================
    // WRITE — activity_logs (session telemetry)
    // =========================================================

    /**
     * Log a lightweight session/activity event to activity_logs.
     *
     * @param string      $action       Machine-readable action code
     * @param string      $description  Human-readable text
     * @param string|null $subjectType  e.g. 'users'
     * @param int|null    $subjectId    Related entity PK
     * @param int|null    $actorId      NULL = current session user
     */
    public function log(
        string  $action,
        string  $description,
        ?string $subjectType = null,
        ?int    $subjectId   = null,
        ?int    $actorId     = null
    ): void {
        $userId = $actorId ?? SessionManager::userId();

        try {
            $this->db->execute(
                "INSERT INTO activity_logs
                    (user_id, action, description, subject_type, subject_id,
                     ip_address, user_agent, created_at)
                 VALUES
                    (:user_id, :action, :description, :subject_type, :subject_id,
                     :ip, :ua, NOW())",
                [
                    'user_id'      => $userId,
                    'action'       => substr($action, 0, 80),
                    'description'  => substr($description, 0, 255),
                    'subject_type' => $subjectType,
                    'subject_id'   => $subjectId,
                    'ip'           => substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
                    'ua'           => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]
            );
        } catch (\Throwable) {
            // Silently fail — logging must never crash the application
        }
    }

    // =========================================================
    // WRITE — audit_logs (Business Operational Audit Trail)
    // =========================================================

    /**
     * Write an immutable business audit entry to audit_logs.
     *
     * Required keys in $data:
     *   - action      string  ACTION_* constant
     *   - module      string  'farm'|'warehouse'|'transfer'|'shop'|'product'|'user'|'admin'
     *   - description string  Human-readable sentence describing the event
     *
     * Optional keys:
     *   - entity_type   string  e.g. 'harvest', 'sale', 'transfer'
     *   - entity_label  string  e.g. 'Milk — 500L', 'Sale #1042'
     *   - record_id     int     PK of the affected record
     *   - table_affected string e.g. 'harvests'
     *   - old_values    array   Snapshot before the change
     *   - new_values    array   Snapshot after the change
     *   - farm_id       int
     *   - warehouse_id  int
     *   - shop_id       int
     *   - actor_id      int     Override session user
     *   - role_name     string  Override session role
     *
     * @param array<string,mixed> $data
     */
    public function logBusiness(array $data): void
    {
        $userId   = (int) ($data['actor_id'] ?? SessionManager::userId());
        $roleName = $data['role_name'] ?? SessionManager::userRole() ?? null;

        try {
            $this->db->execute(
            "INSERT INTO audit_logs
                (user_id, role_name, action_type, module,
                 entity_type, entity_label, description,
                 table_affected, record_id,
                 old_values, new_values,
                 location_type, farm_id, warehouse_id, shop_id,
                 ip_address, user_agent, action_at)
             VALUES
                (:user_id, :role_name, :action_type, :module,
                 :entity_type, :entity_label, :description,
                 :table_affected, :record_id,
                 :old_values, :new_values,
                 :location_type, :farm_id, :warehouse_id, :shop_id,
                 :ip, :ua, NOW())",
            [
                'user_id'       => $userId ?: null,
                'role_name'     => $roleName ? substr($roleName, 0, 80) : null,
                'action_type'   => substr($data['action'] ?? '', 0, 50),
                'module'        => substr($data['module'] ?? '', 0, 60),
                'entity_type'   => isset($data['entity_type'])  ? substr($data['entity_type'],  0, 80)  : null,
                'entity_label'  => isset($data['entity_label']) ? substr($data['entity_label'], 0, 255) : null,
                'description'   => isset($data['description'])  ? substr($data['description'],  0, 500) : null,
                'table_affected'=> isset($data['table_affected'])? substr($data['table_affected'],0,80) : null,
                'record_id'     => isset($data['record_id'])    ? (int)$data['record_id']               : null,
                'old_values'    => isset($data['old_values'])   ? json_encode($data['old_values'])       : null,
                'new_values'    => isset($data['new_values'])   ? json_encode($data['new_values'])       : null,
                'location_type' => $data['location_type'] ?? $this->inferLocationType($data),
                'farm_id'       => isset($data['farm_id'])      ? (int)$data['farm_id']                 : null,
                'warehouse_id'  => isset($data['warehouse_id']) ? (int)$data['warehouse_id']            : null,
                'shop_id'       => isset($data['shop_id'])      ? (int)$data['shop_id']                 : null,
                'ip'            => substr($_SERVER['REMOTE_ADDR']    ?? '', 0, 45),
                'ua'            => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]
        );
        } catch (\Throwable) {
            // Silently fail — logging must never crash the application
        }
    }

    // =========================================================
    // READ — activity_logs helpers
    // =========================================================

    /**
     * Get the most recent N activity log entries with actor info.
     */
    public function getRecent(int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT al.*,
                    u.username,
                    u.first_name,
                    u.last_name,
                    u.profile_photo
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC
             LIMIT :lim",
            ['lim' => $limit]
        );
    }

    /**
     * Get recent activity of specific action types.
     *
     * @param string[] $actions
     */
    public function getRecentByActions(array $actions, int $limit = 10): array
    {
        if (empty($actions)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($actions), '?'));

        return $this->db->fetchAll(
            "SELECT al.*,
                    u.username,
                    u.first_name,
                    u.last_name,
                    u.profile_photo
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.action IN ({$placeholders})
             ORDER BY al.created_at DESC
             LIMIT {$limit}",
            $actions
        );
    }

    /**
     * Get recent activity for a specific user.
     */
    public function getForUser(int $userId, int $limit = 15): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM activity_logs
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT :lim",
            ['uid' => $userId, 'lim' => $limit]
        );
    }

    /**
     * Paginated activity log for admin view.
     *
     * @return array{data:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public function paginateAdmin(
        int    $page    = 1,
        int    $perPage = 50,
        ?int   $userId  = null,
        string $action  = ''
    ): array {
        $wheres = [];
        $params = [];

        if ($userId !== null) {
            $wheres[]      = 'al.user_id = :uid';
            $params['uid'] = $userId;
        }
        if ($action !== '') {
            $wheres[]         = 'al.action = :action';
            $params['action'] = $action;
        }

        $whereClause = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';
        $offset      = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM activity_logs al {$whereClause}",
            $params
        );

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->db->fetchAll(
            "SELECT al.*,
                    u.username,
                    u.first_name,
                    u.last_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             {$whereClause}
             ORDER BY al.created_at DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    /**
     * Count activity events in the last N hours.
     */
    public function countSince(int $hours = 24): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM activity_logs
             WHERE created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)",
            ['hours' => $hours]
        );
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    /**
     * Infer location_type enum value from provided IDs.
     */
    private function inferLocationType(array $data): ?string
    {
        if (!empty($data['farm_id']))      return 'farm';
        if (!empty($data['warehouse_id'])) return 'warehouse';
        if (!empty($data['shop_id']))      return 'shop';
        return null;
    }
}
