<?php
/**
 * FarmModel — aam_db.farms
 * Canonical columns: id, name, location, farm_type, farm_code, manager_id, is_active
 */
declare(strict_types=1);

namespace AAM\Organization\Models;

use AAM\Core\BaseModel;

class FarmModel extends BaseModel
{
    protected string $table      = 'farms';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = true;
    protected array  $fillable   = [
        'name', 'farm_name', 'farm_code', 'location', 'farm_type', 'description',
        'manager_id', 'is_active',
        'created_by', 'updated_by',
    ];

    // ── List with all display fields ──────────────────────────────────────────

    public function getAllWithRelations(array $filters = [], int $limit = 15, int $offset = 0): array
    {
        $sql = "SELECT
                    f.id                                                    AS farm_id,
                    f.id,
                    f.name,
                    COALESCE(f.farm_name, f.name)                          AS farm_name,
                    COALESCE(f.farm_code, CONCAT('FRM', LPAD(f.id,3,'0'))) AS farm_code,
                    f.farm_type,
                    f.location,
                    f.description,
                    f.is_active,
                    f.created_at,
                    f.updated_at,
                    f.deleted_at,
                    CONCAT(m.first_name,' ',m.last_name)                   AS manager_name,
                    m.username                                              AS manager_username
                FROM farms f
                LEFT JOIN users m     ON m.id  = f.manager_id
                WHERE f.deleted_at IS NULL";

        $params = [];
        [$sql, $params] = $this->applyFilters($sql, $params, $filters);
        $sql .= " ORDER BY f.name ASC LIMIT :lim OFFSET :off";
        $params['lim'] = $limit;
        $params['off'] = $offset;
        return $this->rawQuery($sql, $params);
    }

    public function countWithFilters(array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) AS n FROM farms f WHERE f.deleted_at IS NULL";
        $params = [];
        [$sql, $params] = $this->applyFilters($sql, $params, $filters);
        return (int)($this->rawQueryOne($sql, $params)['n'] ?? 0);
    }

    // ── Single record ─────────────────────────────────────────────────────────

    public function getByIdWithRelations(int $id): ?array
    {
        return $this->rawQueryOne(
            "SELECT
                f.id AS farm_id, f.*,
                COALESCE(f.farm_name, f.name)                          AS farm_name,
                COALESCE(f.farm_code, CONCAT('FARM-', LPAD(f.id,3,'0'))) AS farm_code,
                CONCAT(m.first_name,' ',m.last_name)                   AS manager_name,
                m.username AS manager_username,
                m.phone    AS manager_phone,
                m.email    AS manager_email,
                CONCAT(cb.first_name,' ',cb.last_name)                 AS creator_name
             FROM farms f
             LEFT JOIN users m     ON m.id  = f.manager_id
             LEFT JOIN users cb    ON cb.id = f.created_by
             WHERE f.id = :id AND f.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    // ── Statistics ────────────────────────────────────────────────────────────

    public function getStatistics(): array
    {
        return $this->rawQueryOne(
            "SELECT COUNT(*) AS total,
                    SUM(is_active=1) AS active,
                    SUM(is_active=0) AS inactive
             FROM farms WHERE deleted_at IS NULL"
        ) ?? ['total' => 0, 'active' => 0, 'inactive' => 0];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Generate next unique farm code (FARM-001, FARM-002...).
     * Checks existing codes to ensure uniqueness even with gaps.
     */
    public function getNextCode(): string
    {
        $row = $this->rawQueryOne(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(farm_code, 6) AS UNSIGNED)), 0) AS mx
             FROM farms WHERE farm_code REGEXP '^FARM-[0-9]+$'"
        );
        $next = ((int)($row['mx'] ?? 0)) + 1;
        $code = 'FARM-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        // Ensure uniqueness
        while ($this->codeExists($code)) {
            $next++;
            $code = 'FARM-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    public function getFarmTypes(): array
    {
        return [
            'CROP'         => 'Crop Farm',
            'LIVESTOCK'    => 'Livestock Farm',
            'MIXED'        => 'Mixed Farm',
            'DAIRY'        => 'Dairy Farm',
            'POULTRY'      => 'Poultry Farm',
            'HORTICULTURE' => 'Horticulture Farm',
            'OTHER'        => 'Other',
        ];
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS n FROM farms WHERE name = :n AND deleted_at IS NULL";
        $p   = ['n' => $name];
        if ($excludeId) { $sql .= " AND id != :ex"; $p['ex'] = $excludeId; }
        return (int)($this->rawQueryOne($sql, $p)['n'] ?? 0) > 0;
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS n FROM farms WHERE farm_code = :c AND deleted_at IS NULL";
        $p   = ['c' => $code];
        if ($excludeId) { $sql .= " AND id != :ex"; $p['ex'] = $excludeId; }
        return (int)($this->rawQueryOne($sql, $p)['n'] ?? 0) > 0;
    }

    public function find(int $id): ?array
    {
        return $this->rawQueryOne("SELECT * FROM farms WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    }

    public function softDelete(int $id): bool
    {
        return (bool)$this->rawExecute("UPDATE farms SET deleted_at=NOW(), is_active=0 WHERE id=:id", ['id' => $id]);
    }

    public function restoreFarm(int $id): bool
    {
        return (bool)$this->rawExecute("UPDATE farms SET deleted_at=NULL, is_active=1 WHERE id=:id", ['id' => $id]);
    }

    public function toggleStatus(int $id): bool
    {
        return (bool)$this->rawExecute("UPDATE farms SET is_active = NOT is_active WHERE id=:id", ['id' => $id]);
    }

    public function getActiveFarmsForSelect(): array
    {
        return $this->rawQuery(
            "SELECT id, COALESCE(farm_name, name) AS farm_name, name, COALESCE(farm_code, CONCAT('FRM', LPAD(id,3,'0'))) AS farm_code, farm_type, location
             FROM farms WHERE is_active=1 AND deleted_at IS NULL ORDER BY name ASC"
        );
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function applyFilters(string $sql, array $params, array $filters): array
    {
        if (!empty($filters['search'])) {
            $sql .= " AND (f.name LIKE :sl OR f.location LIKE :sl OR f.farm_type LIKE :sl)";
            $params['sl'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['farm_type'])) {
            $sql .= " AND f.farm_type = :type";
            $params['type'] = $filters['farm_type'];
        }
        if (!empty($filters['manager_id'])) {
            $sql .= " AND f.manager_id = :mgr";
            $params['mgr'] = (int)$filters['manager_id'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND f.is_active = :active";
            $params['active'] = (int)$filters['is_active'];
        }
        return [$sql, $params];
    }
}
