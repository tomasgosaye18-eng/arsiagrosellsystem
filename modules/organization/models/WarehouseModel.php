<?php
/**
 * WarehouseModel — aam_db.warehouses
 * Canonical operational columns: id, name, location, is_active
 * Additional display: warehouse_code, warehouse_name, warehouse_type, capacity
 */
declare(strict_types=1);

namespace AAM\Organization\Models;

use AAM\Core\BaseModel;

class WarehouseModel extends BaseModel
{
    protected string $table      = 'warehouses';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = true;
    protected array  $fillable   = [
        'name', 'warehouse_name', 'warehouse_code', 'warehouse_type',
        'location', 'description',
        'manager_id', 'is_active', 'seed_distribution_enabled',
        'created_by', 'updated_by',
    ];

    // ── List ──────────────────────────────────────────────────────────────────

    public function getAllWithRelations(array $filters = [], int $limit = 15, int $offset = 0): array
    {
        $sql = "SELECT
                    w.id AS warehouse_id,
                    w.id,
                    w.name,
                    COALESCE(w.warehouse_name, w.name)                        AS warehouse_name,
                    COALESCE(w.warehouse_code, CONCAT('WH', LPAD(w.id,3,'0'))) AS warehouse_code,
                    COALESCE(w.warehouse_type, 'CENTRAL')                      AS warehouse_type,
                    w.location,
                    w.description,
                    w.is_active,
                    COALESCE(w.seed_distribution_enabled, 0)                   AS seed_distribution_enabled,
                    w.created_at,
                    w.updated_at,
                    COALESCE(w.capacity_tons, w.capacity) AS capacity_tons,
                    w.capacity_sqm,
                    CONCAT(m.first_name,' ',m.last_name) AS manager_name,
                    m.username AS manager_username,
                    m.phone    AS manager_phone,
                    m.email    AS manager_email
                FROM warehouses w
                LEFT JOIN users m      ON m.id           = w.manager_id
                WHERE w.deleted_at IS NULL";

        $params = [];
        [$sql, $params] = $this->applyFilters($sql, $params, $filters);
        $sql .= " ORDER BY w.name ASC LIMIT :lim OFFSET :off";
        $params['lim'] = $limit;
        $params['off'] = $offset;
        return $this->rawQuery($sql, $params);
    }

    public function countWithFilters(array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) AS n FROM warehouses w WHERE w.deleted_at IS NULL";
        $params = [];
        [$sql, $params] = $this->applyFilters($sql, $params, $filters);
        return (int)($this->rawQueryOne($sql, $params)['n'] ?? 0);
    }

    // ── Single ────────────────────────────────────────────────────────────────

    public function getByIdWithRelations(int $id): ?array
    {
        return $this->rawQueryOne(
            "SELECT w.id AS warehouse_id, w.*,
                    COALESCE(w.warehouse_name, w.name)                          AS warehouse_name,
                    COALESCE(w.warehouse_code, CONCAT('WH-', LPAD(w.id,3,'0'))) AS warehouse_code,
                    COALESCE(w.warehouse_type, 'CENTRAL')                        AS warehouse_type,
                    COALESCE(w.capacity_tons, w.capacity)                        AS capacity_tons,
                    COALESCE(w.seed_distribution_enabled, 0)                     AS seed_distribution_enabled,
                    CONCAT(m.first_name,' ',m.last_name)  AS manager_name,
                    m.username AS manager_username,
                    m.phone    AS manager_phone,
                    m.email    AS manager_email,
                    CONCAT(cb.first_name,' ',cb.last_name) AS creator_name
             FROM warehouses w
             LEFT JOIN users m      ON m.id           = w.manager_id
             LEFT JOIN users cb     ON cb.id           = w.created_by
             WHERE w.id = :id AND w.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    // ── Statistics ────────────────────────────────────────────────────────────

    public function getStatistics(): array
    {
        return $this->rawQueryOne(
            "SELECT COUNT(*) AS total, SUM(is_active=1) AS active, SUM(is_active=0) AS inactive
             FROM warehouses WHERE deleted_at IS NULL"
        ) ?? ['total' => 0, 'active' => 0, 'inactive' => 0];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Generate next unique warehouse code (WH-001, WH-002...).
     */
    public function getNextCode(): string
    {
        $row = $this->rawQueryOne(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(warehouse_code, 4) AS UNSIGNED)), 0) AS mx
             FROM warehouses WHERE warehouse_code REGEXP '^WH-[0-9]+$'"
        );
        $next = ((int)($row['mx'] ?? 0)) + 1;
        $code = 'WH-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        while ($this->codeExists($code)) {
            $next++;
            $code = 'WH-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    public function getWarehouseTypes(): array
    {
        return [
            'CENTRAL'      => 'Central Warehouse',
            'COLD_STORAGE' => 'Cold Storage',
            'DRY_STORAGE'  => 'Dry Storage',
            'DISTRIBUTION' => 'Distribution Center',
            'OTHER'        => 'Other',
        ];
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS n FROM warehouses WHERE name = :n AND deleted_at IS NULL";
        $p   = ['n' => $name];
        if ($excludeId) { $sql .= " AND id != :ex"; $p['ex'] = $excludeId; }
        return (int)($this->rawQueryOne($sql, $p)['n'] ?? 0) > 0;
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS n FROM warehouses WHERE warehouse_code = :c AND deleted_at IS NULL";
        $p   = ['c' => $code];
        if ($excludeId) { $sql .= " AND id != :ex"; $p['ex'] = $excludeId; }
        return (int)($this->rawQueryOne($sql, $p)['n'] ?? 0) > 0;
    }

    public function find(int $id): ?array
    {
        return $this->rawQueryOne("SELECT * FROM warehouses WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    }

    public function softDelete(int $id): bool
    {
        return (bool)$this->rawExecute("UPDATE warehouses SET deleted_at=NOW(), is_active=0 WHERE id=:id", ['id' => $id]);
    }

    public function restoreWarehouse(int $id): bool
    {
        return (bool)$this->rawExecute("UPDATE warehouses SET deleted_at=NULL, is_active=1 WHERE id=:id", ['id' => $id]);
    }

    public function toggleStatus(int $id): bool
    {
        return (bool)$this->rawExecute("UPDATE warehouses SET is_active = NOT is_active WHERE id=:id", ['id' => $id]);
    }

    public function getActiveWarehousesForSelect(): array
    {
        return $this->rawQuery(
            "SELECT id, COALESCE(warehouse_name, name) AS warehouse_name, name, COALESCE(warehouse_code, CONCAT('WH', LPAD(id,3,'0'))) AS warehouse_code, warehouse_type, location
             FROM warehouses WHERE is_active=1 AND deleted_at IS NULL ORDER BY name ASC"
        );
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function applyFilters(string $sql, array $params, array $filters): array
    {
        if (!empty($filters['search'])) {
            $sql .= " AND (w.name LIKE :sl OR w.location LIKE :sl)";
            $params['sl'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['manager_id'])) {
            $sql .= " AND w.manager_id = :mgr";
            $params['mgr'] = (int)$filters['manager_id'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND w.is_active = :active";
            $params['active'] = (int)$filters['is_active'];
        }
        return [$sql, $params];
    }
}
