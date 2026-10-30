<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Organization Module
 * =========================================================
 * File    : modules/organization/models/AssignmentModel.php
 * Purpose : Data access for organization_assignments and
 *           assignment_history tables in aam_db.
 *
 * aam_db.users PK is `id` (not `user_id`).
 * aam_db.organization_assignments:
 *   assignment_id, user_id, location_type, location_id,
 *   assignment_role, assigned_by, assigned_at, notes, is_active
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Organization\Models;

use AAM\Core\BaseModel;

class AssignmentModel extends BaseModel
{
    protected string $table      = 'organization_assignments';
    protected string $primaryKey = 'assignment_id';
    protected bool   $softDelete = false;
    protected array  $fillable   = [
        'user_id', 'location_type', 'location_id',
        'assignment_role', 'assigned_by', 'notes', 'is_active',
    ];

    // ─── Active assignment for a user ─────────────────────────────────────────

    public function getActiveForUser(int $userId): ?array
    {
        return $this->rawQueryOne(
            "SELECT oa.*,
                    CONCAT(u.first_name,' ',u.last_name) AS user_name,
                    u.username, u.email
             FROM organization_assignments oa
             LEFT JOIN users u ON u.id = oa.user_id
             WHERE oa.user_id = :uid AND oa.is_active = 1
             LIMIT 1",
            ['uid' => $userId]
        );
    }

    // ─── All active assignments for a location ────────────────────────────────

    public function getActiveForLocation(string $locationType, int $locationId): array
    {
        return $this->rawQuery(
            "SELECT oa.*,
                    CONCAT(u.first_name,' ',u.last_name) AS user_full_name,
                    u.username, u.phone, u.email
             FROM organization_assignments oa
             JOIN  users u ON u.id = oa.user_id
             WHERE oa.location_type = :lt
               AND oa.location_id   = :lid
               AND oa.is_active     = 1
             ORDER BY oa.assigned_at DESC",
            ['lt' => $locationType, 'lid' => $locationId]
        );
    }

    // ─── Paginated list ───────────────────────────────────────────────────────

    public function getAllWithRelations(array $filters = [], int $limit = 15, int $offset = 0): array
    {
        $sql = "SELECT oa.*,
                       CONCAT(u.first_name,' ',u.last_name) AS user_full_name,
                       u.username,
                       u.email AS user_email,
                       CONCAT(ab.first_name,' ',ab.last_name) AS assigned_by_name,
                       CASE oa.location_type
                           WHEN 'farm'      THEN (SELECT name FROM farms      WHERE id = oa.location_id)
                           WHEN 'warehouse' THEN (SELECT name FROM warehouses WHERE id = oa.location_id)
                           WHEN 'shop'      THEN (SELECT name FROM shops      WHERE id = oa.location_id)
                           ELSE CONCAT(oa.location_type,' #', oa.location_id)
                       END AS location_name
                FROM organization_assignments oa
                JOIN  users u  ON u.id = oa.user_id
                LEFT JOIN users ab ON ab.id = oa.assigned_by
                WHERE 1=1";

        $params = [];
        [$sql, $params] = $this->applyFilters($sql, $params, $filters);
        $sql .= " ORDER BY oa.assigned_at DESC LIMIT :lim OFFSET :off";
        $params['lim'] = $limit;
        $params['off'] = $offset;
        return $this->rawQuery($sql, $params);
    }

    public function countWithFilters(array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) AS n FROM organization_assignments oa
                   JOIN users u ON u.id = oa.user_id WHERE 1=1";
        $params = [];
        [$sql, $params] = $this->applyFilters($sql, $params, $filters);
        return (int)($this->rawQueryOne($sql, $params)['n'] ?? 0);
    }

    // ─── Create / reassign ────────────────────────────────────────────────────

    public function createAssignment(
        int     $userId,
        string  $locationType,
        int     $locationId,
        string  $assignmentRole,
        int     $assignedBy,
        string  $action = 'ASSIGNED',
        ?string $notes  = null
    ): int {
        $db = $this->getDb();
        $db->beginTransaction();
        try {
            // Deactivate existing active assignments for this user
            $db->execute(
                "UPDATE organization_assignments SET is_active = 0
                 WHERE user_id = :uid AND is_active = 1",
                ['uid' => $userId]
            );

            // Insert new assignment
            $db->execute(
                "INSERT INTO organization_assignments
                   (user_id, location_type, location_id, assignment_role,
                    assigned_by, notes, is_active)
                 VALUES (:uid, :lt, :lid, :role, :ab, :notes, 1)",
                [
                    'uid'   => $userId,
                    'lt'    => $locationType,
                    'lid'   => $locationId,
                    'role'  => $assignmentRole,
                    'ab'    => $assignedBy,
                    'notes' => $notes,
                ]
            );
            $assignmentId = $db->lastInsertId();

            // ── Sync manager_id on the location table ─────────────────────────
            // Clear this user's previous manager_id on any location they were
            // managing (the deactivation above handles org_assignments but the
            // location table columns need to be cleared too).
            foreach (['farms', 'warehouses', 'shops'] as $tbl) {
                $db->execute(
                    "UPDATE `$tbl` SET manager_id = NULL WHERE manager_id = :uid",
                    ['uid' => $userId]
                );
            }
            // Set manager_id on the new location
            $tableMap = ['farm' => 'farms', 'warehouse' => 'warehouses', 'shop' => 'shops'];
            if (isset($tableMap[$locationType])) {
                $db->execute(
                    "UPDATE `{$tableMap[$locationType]}` SET manager_id = :uid WHERE id = :lid",
                    ['uid' => $userId, 'lid' => $locationId]
                );
            }

            // Log to assignment_history if table exists
            try {
                $db->execute(
                    "INSERT INTO assignment_history
                       (assignment_id, user_id, location_type, location_id,
                        assignment_role, action, performed_by, notes)
                     VALUES (:aid, :uid, :lt, :lid, :role, :action, :pb, :notes)",
                    [
                        'aid'    => $assignmentId,
                        'uid'    => $userId,
                        'lt'     => $locationType,
                        'lid'    => $locationId,
                        'role'   => $assignmentRole,
                        'action' => $action,
                        'pb'     => $assignedBy,
                        'notes'  => $notes,
                    ]
                );
            } catch (\Throwable) {
                // assignment_history table may not exist — silently skip
            }

            $db->commit();
            return $assignmentId;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    // ─── Remove assignment ────────────────────────────────────────────────────

    public function removeAssignment(int $assignmentId, int $performedBy, ?string $notes = null): bool
    {
        $assign = $this->rawQueryOne(
            "SELECT * FROM organization_assignments WHERE assignment_id = :id",
            ['id' => $assignmentId]
        );
        if (!$assign) {
            return false;
        }

        $db = $this->getDb();
        $db->beginTransaction();
        try {
            $db->execute(
                "UPDATE organization_assignments SET is_active = 0
                 WHERE assignment_id = :id",
                ['id' => $assignmentId]
            );

            // ── Clear manager_id on the location table ────────────────────────
            $tableMap = ['farm' => 'farms', 'warehouse' => 'warehouses', 'shop' => 'shops'];
            $tbl = $tableMap[$assign['location_type']] ?? null;
            if ($tbl) {
                $db->execute(
                    "UPDATE `$tbl` SET manager_id = NULL
                     WHERE id = :lid AND manager_id = :uid",
                    ['lid' => $assign['location_id'], 'uid' => $assign['user_id']]
                );
            }

            try {
                $db->execute(
                    "INSERT INTO assignment_history
                       (assignment_id, user_id, location_type, location_id,
                        assignment_role, action, performed_by, notes)
                     VALUES (:aid, :uid, :lt, :lid, :role, 'REMOVED', :pb, :notes)",
                    [
                        'aid'   => $assignmentId,
                        'uid'   => $assign['user_id'],
                        'lt'    => $assign['location_type'],
                        'lid'   => $assign['location_id'],
                        'role'  => $assign['assignment_role'],
                        'pb'    => $performedBy,
                        'notes' => $notes,
                    ]
                );
            } catch (\Throwable) {}

            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->rollback();
            return false;
        }
    }

    // ─── History ─────────────────────────────────────────────────────────────

    public function getHistoryForUser(int $userId, int $limit = 50): array
    {
        try {
            return $this->rawQuery(
                "SELECT ah.*,
                        CONCAT(u.first_name,' ',u.last_name) AS user_name,
                        CONCAT(p.first_name,' ',p.last_name) AS performed_by_name
                 FROM assignment_history ah
                 JOIN  users u ON u.id = ah.user_id
                 LEFT JOIN users p ON p.id = ah.performed_by
                 WHERE ah.user_id = :uid
                 ORDER BY ah.performed_at DESC LIMIT :lim",
                ['uid' => $userId, 'lim' => $limit]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getHistoryForLocation(string $locationType, int $locationId, int $limit = 50): array
    {
        try {
            return $this->rawQuery(
                "SELECT ah.*,
                        CONCAT(u.first_name,' ',u.last_name) AS user_name,
                        u.username,
                        CONCAT(p.first_name,' ',p.last_name) AS performed_by_name
                 FROM assignment_history ah
                 JOIN  users u ON u.id = ah.user_id
                 LEFT JOIN users p ON p.id = ah.performed_by
                 WHERE ah.location_type = :lt AND ah.location_id = :lid
                 ORDER BY ah.performed_at DESC LIMIT :lim",
                ['lt' => $locationType, 'lid' => $locationId, 'lim' => $limit]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    // ─── Statistics ───────────────────────────────────────────────────────────

    public function getStatistics(): array
    {
        return $this->rawQueryOne(
            "SELECT
               (SELECT COUNT(*) FROM organization_assignments WHERE is_active=1)                                AS total_active,
               (SELECT COUNT(*) FROM organization_assignments WHERE is_active=1 AND location_type='farm')      AS farm_assignments,
               (SELECT COUNT(*) FROM organization_assignments WHERE is_active=1 AND location_type='warehouse') AS warehouse_assignments,
               (SELECT COUNT(*) FROM organization_assignments WHERE is_active=1 AND location_type='shop')      AS shop_assignments"
        ) ?? [];
    }

    // ─── Users by role (for assignment form) ─────────────────────────────────

    public function getUsersByRole(string $role): array
    {
        return $this->rawQuery(
            "SELECT u.id AS user_id, u.username,
                    CONCAT(u.first_name,' ',u.last_name) AS full_name,
                    u.phone, u.email,
                    (SELECT COUNT(*) FROM organization_assignments oa
                     WHERE oa.user_id = u.id AND oa.is_active = 1) AS has_active_assignment
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.name = :role AND u.is_active = 1
             ORDER BY u.first_name, u.last_name",
            ['role' => $role]
        );
    }

    public function getUnassignedUsersByRole(string $role): array
    {
        return $this->rawQuery(
            "SELECT u.id AS user_id, u.username,
                    CONCAT(u.first_name,' ',u.last_name) AS full_name,
                    u.phone, u.email
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.name = :role AND u.is_active = 1
               AND u.id NOT IN (
                   SELECT user_id FROM organization_assignments WHERE is_active = 1
               )
             ORDER BY u.first_name, u.last_name",
            ['role' => $role]
        );
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function applyFilters(string $sql, array $params, array $filters): array
    {
        if (!empty($filters['location_type'])) {
            $sql .= " AND oa.location_type = :lt";
            $params['lt'] = $filters['location_type'];
        }
        if (!empty($filters['assignment_role'])) {
            $sql .= " AND oa.assignment_role = :role";
            $params['role'] = $filters['assignment_role'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND oa.is_active = :active";
            $params['active'] = (int)$filters['is_active'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= " AND oa.user_id = :uid";
            $params['uid'] = (int)$filters['user_id'];
        }
        return [$sql, $params];
    }
}
