<?php
/**
 * =========================================================
 * UNIT MODEL
 * =========================================================
 * File: app/modules/admin/models/UnitModel.php
 * Purpose: Unit of Measurement data access layer
 *          Mapped to actual aam_db `units_of_measure` schema (id, name, …)
 * =========================================================
 */

namespace AAM\Admin\Models;

use AAM\Core\BaseModel;

class UnitModel extends BaseModel
{
    protected string $table      = 'units_of_measure';
    protected string $primaryKey = 'id';            // actual PK
    protected bool   $softDelete = true;

    protected array $fillable = [
        'name', 'abbreviation', 'description', 'unit_type',
        'base_unit_id', 'base_multiplier', 'is_active',
    ];

    // ── List with creator ─────────────────────────────────────────────────────
    public function getAllWithCreator(array $filters = [], int $limit = 15, int $offset = 0): array
    {
        $sql = "SELECT
                    u.id            AS unit_id,
                    u.id,
                    u.name          AS unit_name,
                    u.name          AS unit_code,
                    u.abbreviation,
                    u.description,
                    u.unit_type,
                    u.is_active,
                    u.created_at,
                    u.updated_at,
                    u.deleted_at,
                    NULL            AS creator_name,
                    NULL            AS creator_full_name,
                    NULL            AS is_base_unit,
                    (SELECT COUNT(*) FROM products p
                     WHERE p.unit_id = u.id AND p.is_active = 1 AND p.deleted_at IS NULL) AS product_count
                FROM units_of_measure u
                WHERE u.deleted_at IS NULL";

        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (u.name LIKE :search OR u.abbreviation LIKE :search OR u.description LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND u.is_active = :status";
            $params['status'] = (int)$filters['status'];
        }
        if (!empty($filters['unit_type'])) {
            $sql .= " AND u.unit_type = :unit_type";
            $params['unit_type'] = $filters['unit_type'];
        }

        $sql .= " ORDER BY u.unit_type ASC, u.name ASC LIMIT :limit OFFSET :offset";
        $params['limit']  = $limit;
        $params['offset'] = $offset;

        return $this->rawQuery($sql, $params);
    }

    public function countWithFilters(array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) AS total FROM units_of_measure WHERE deleted_at IS NULL";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE :search OR abbreviation LIKE :search OR description LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND is_active = :status";
            $params['status'] = (int)$filters['status'];
        }
        if (!empty($filters['unit_type'])) {
            $sql .= " AND unit_type = :unit_type";
            $params['unit_type'] = $filters['unit_type'];
        }

        $result = $this->rawQuery($sql, $params);
        return (int)($result[0]['total'] ?? 0);
    }

    public function getByIdWithRelations(int $id): ?array
    {
        $result = $this->rawQuery(
            "SELECT u.id AS unit_id, u.id, u.name AS unit_name, u.name AS unit_code,
                    u.abbreviation, u.description, u.unit_type, u.is_active,
                    u.created_at, u.updated_at, u.deleted_at,
                    NULL AS creator_name, NULL AS creator_full_name,
                    NULL AS is_base_unit,
                    (SELECT COUNT(*) FROM products p
                     WHERE p.unit_id = u.id AND p.is_active = 1 AND p.deleted_at IS NULL) AS product_count
             FROM units_of_measure u WHERE u.id = :id AND u.deleted_at IS NULL",
            ['id' => $id]
        );
        return $result[0] ?? null;
    }

    // ── Existence checks ──────────────────────────────────────────────────────
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) AS c FROM units_of_measure WHERE name = :name AND deleted_at IS NULL";
        $params = ['name' => $code];
        if ($excludeId) { $sql .= " AND id != :eid"; $params['eid'] = $excludeId; }
        return (int)($this->rawQuery($sql, $params)[0]['c'] ?? 0) > 0;
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) AS c FROM units_of_measure WHERE name = :name AND deleted_at IS NULL";
        $params = ['name' => $name];
        if ($excludeId) { $sql .= " AND id != :eid"; $params['eid'] = $excludeId; }
        return (int)($this->rawQuery($sql, $params)[0]['c'] ?? 0) > 0;
    }

    // ── Active units for dropdowns ────────────────────────────────────────────
    public function getActiveUnits(?string $unitType = null): array
    {
        $sql    = "SELECT id AS unit_id, name AS unit_name, name AS unit_code, abbreviation, unit_type
                   FROM units_of_measure WHERE is_active = 1 AND deleted_at IS NULL";
        $params = [];
        if ($unitType) { $sql .= " AND unit_type = :unit_type"; $params['unit_type'] = $unitType; }
        $sql .= " ORDER BY unit_type ASC, name ASC";
        return $this->rawQuery($sql, $params);
    }

    public function getGroupedByType(): array
    {
        $units   = $this->getActiveUnits();
        $grouped = [];
        foreach ($units as $unit) {
            $grouped[$unit['unit_type']][] = $unit;
        }
        return $grouped;
    }

    // ── Toggle / delete / restore ─────────────────────────────────────────────
    public function toggleStatus(int $id): bool
    {
        return $this->rawExecute("UPDATE units_of_measure SET is_active = NOT is_active WHERE id = :id", ['id' => $id]);
    }

    public function softDelete(int $id): bool
    {
        return $this->rawExecute("UPDATE units_of_measure SET deleted_at = NOW(), is_active = 0 WHERE id = :id", ['id' => $id]);
    }

    public function restore(int $id): int
    {
        return (int)$this->rawExecute("UPDATE units_of_measure SET deleted_at = NULL, is_active = 1 WHERE id = :id", ['id' => $id]);
    }

    public function canDelete(int $id): bool
    {
        $result = $this->rawQuery(
            "SELECT COUNT(*) AS c FROM products WHERE unit_id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
        return (int)($result[0]['c'] ?? 0) === 0;
    }

    public function getStatistics(): array
    {
        $result = $this->rawQuery(
            "SELECT COUNT(*) AS total_units,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_units,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_units
             FROM units_of_measure WHERE deleted_at IS NULL"
        );
        return $result[0] ?? [];
    }

    public function getUnitTypes(): array
    {
        return [
            'weight' => 'Weight',
            'volume' => 'Volume',
            'length' => 'Length',
            'piece'  => 'Piece/Count',
            'area'   => 'Area',
            'other'  => 'Other',
        ];
    }
}
