<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9A
 * =========================================================
 * File    : modules/farm/models/FarmModel.php
 * Purpose : Data access layer for the `farms` table.
 *           Provides farm listing, detail loading, and
 *           summary statistics for the Farm module.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Farm\Models;

use AAM\Core\BaseModel;

class FarmModel extends BaseModel
{
    protected string $table      = 'farms';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = true;

    protected array $fillable = [
        'name', 'location', 'description',
        'farm_type', 'area_hectares',
        'manager_id', 'is_active',
    ];

    // ──────────────────────────────────────────────────────
    // READ — farm list
    // ──────────────────────────────────────────────────────

    /**
     * All active farms with manager name and basic harvest summary.
     *
     * @param array{search?:string, farm_type?:string, status?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function getAllWithSummary(array $filters = []): array
    {
        $where  = [];
        $params = [];

        // Default: exclude hard-deleted records
        $where[] = 'f.deleted_at IS NULL';

        if (!empty($filters['search'])) {
            $where[]          = "(f.name LIKE :search OR f.location LIKE :search OR f.farm_type LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['farm_type'])) {
            $where[]              = 'f.farm_type = :farm_type';
            $params['farm_type']  = $filters['farm_type'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[]             = 'f.is_active = :is_active';
            $params['is_active'] = (int)$filters['status'];
        }

        $whereClause = implode(' AND ', $where);

        return $this->rawQuery(
            "SELECT
                f.*,
                COALESCE(f.farm_code, CONCAT('FARM-', LPAD(f.id, 3, '0'))) AS farm_code,
                CONCAT(u.first_name, ' ', u.last_name) AS manager_name,
                u.phone                                  AS manager_phone,
                (SELECT COUNT(*)
                 FROM harvest_batches hb
                 WHERE hb.farm_id = f.id
                   AND hb.status NOT IN ('depleted','quarantine')) AS active_batches,
                (SELECT COALESCE(SUM(hb.remaining_quantity),0)
                 FROM harvest_batches hb
                 WHERE hb.farm_id = f.id
                   AND hb.status NOT IN ('depleted','quarantine')) AS total_available_qty,
                (SELECT COUNT(*)
                 FROM farm_dispatches fd
                 WHERE fd.farm_id = f.id
                   AND fd.status IN ('pending','in_transit'))       AS pending_dispatches,
                (SELECT MAX(hb.created_at)
                 FROM harvest_batches hb
                 WHERE hb.farm_id = f.id)                           AS last_harvest_at
             FROM farms f
             LEFT JOIN users u ON u.id = f.manager_id
             WHERE {$whereClause}
             ORDER BY f.name ASC",
            $params
        );
    }

    /**
     * Single farm with full details including manager info.
     */
    public function getWithDetails(int $id): ?array
    {
        return $this->rawQueryOne(
            "SELECT
                f.*,
                COALESCE(f.farm_code, CONCAT('FARM-', LPAD(f.id, 3, '0'))) AS farm_code,
                CONCAT(u.first_name, ' ', u.last_name) AS manager_name,
                u.phone   AS manager_phone,
                u.email   AS manager_email,
                u.username AS manager_username
             FROM farms f
             LEFT JOIN users u ON u.id = f.manager_id
             WHERE f.id = :id AND f.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    /**
     * Farm summary statistics for the detail page header.
     */
    public function getSummaryStats(int $farmId): array
    {
        $today  = date('Y-m-d');
        $month1 = date('Y-m-01');
        $month2 = date('Y-m-t');

        $stats = [];

        // Today's harvest qty
        $stats['today_qty'] = (float)($this->rawQueryOne(
            "SELECT COALESCE(SUM(initial_quantity), 0) AS v
             FROM harvest_batches
             WHERE farm_id = :fid AND DATE(harvest_date) = :today",
            ['fid' => $farmId, 'today' => $today]
        )['v'] ?? 0);

        // Monthly production
        $stats['monthly_qty'] = (float)($this->rawQueryOne(
            "SELECT COALESCE(SUM(initial_quantity), 0) AS v
             FROM harvest_batches
             WHERE farm_id = :fid
               AND harvest_date BETWEEN :d1 AND :d2",
            ['fid' => $farmId, 'd1' => $month1, 'd2' => $month2]
        )['v'] ?? 0);

        // Pending dispatch (batches that have qty still available)
        $stats['pending_dispatch'] = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS v FROM harvest_batches
             WHERE farm_id = :fid
               AND status IN ('active','partially_dispatched')",
            ['fid' => $farmId]
        )['v'] ?? 0);

        // Total dispatched this year
        $stats['dispatched_total'] = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS v FROM farm_dispatches
             WHERE farm_id = :fid
               AND status NOT IN ('cancelled')
               AND YEAR(dispatch_date) = YEAR(NOW())",
            ['fid' => $farmId]
        )['v'] ?? 0);

        // Recent activity: last harvest date
        $stats['last_activity'] = $this->rawQueryOne(
            "SELECT MAX(created_at) AS v FROM harvest_batches WHERE farm_id = :fid",
            ['fid' => $farmId]
        )['v'] ?? null;

        return $stats;
    }

    /**
     * Return distinct farm types in use.
     *
     * @return string[]
     */
    public function getDistinctTypes(): array
    {
        $rows = $this->rawQuery(
            "SELECT DISTINCT farm_type FROM farms
             WHERE farm_type IS NOT NULL AND farm_type != '' AND deleted_at IS NULL
             ORDER BY farm_type ASC"
        );
        return array_column($rows, 'farm_type');
    }

    /**
     * Return all active farms for dropdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveList(): array
    {
        return $this->rawQuery(
            "SELECT id, name, farm_type, location FROM farms
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY name ASC"
        );
    }

    /**
     * Return one farm scoped to manager (farm_manager may only see their own).
     * Returns null if not found or not assigned.
     */
    public function findAssigned(int $farmId, int $userId, string $role): ?array
    {
        if (in_array($role, [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true)) {
            return $this->getWithDetails($farmId);
        }
        $row = $this->getWithDetails($farmId);
        if (!$row) {
            return null;
        }
        // Verify assignment — use positional-safe naming to avoid duplicate param issue in UNION
        $assigned = $this->rawQueryOne(
            "SELECT 1 AS found FROM user_location_assignments
             WHERE user_id = :uid1 AND location_type = 'farm' AND location_id = :fid1
             UNION
             SELECT 1 FROM organization_assignments
             WHERE user_id = :uid2 AND location_type = 'farm'
               AND location_id = :fid2 AND is_active = 1
             LIMIT 1",
            ['uid1' => $userId, 'fid1' => $farmId, 'uid2' => $userId, 'fid2' => $farmId]
        );
        return $assigned ? $row : null;
    }
}
