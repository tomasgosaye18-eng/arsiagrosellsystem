<?php
/**
 * =========================================================
 * CATEGORY MODEL
 * =========================================================
 * File: app/modules/admin/models/CategoryModel.php
 * Purpose: Product Category data access layer
 *          Mapped to actual aam_db `categories` table schema
 * =========================================================
 */

namespace AAM\Admin\Models;

use AAM\Core\BaseModel;

class CategoryModel extends BaseModel
{
    protected string $table      = 'categories';   // actual table name
    protected string $primaryKey = 'id';           // actual PK
    protected bool   $softDelete = true;

    protected array $fillable = [
        'name', 'slug', 'description', 'parent_id', 'sort_order', 'is_active',
    ];

    // ── List with creator ─────────────────────────────────────────────────────
    public function getAllWithCreator(array $filters = [], int $limit = 15, int $offset = 0): array
    {
        $sql = "SELECT
                    c.id            AS category_id,
                    c.id,
                    c.name          AS category_name,
                    c.name          AS category_code,
                    c.description,
                    c.sort_order    AS display_order,
                    NULL            AS icon,
                    c.is_active,
                    c.created_at,
                    c.updated_at,
                    c.deleted_at,
                    NULL            AS creator_name,
                    NULL            AS creator_full_name,
                    parent.name     AS parent_category_name,
                    (SELECT COUNT(*) FROM products p
                     WHERE p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL) AS product_count
                FROM categories c
                LEFT JOIN categories parent ON c.parent_id = parent.id
                WHERE c.deleted_at IS NULL";

        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (c.name LIKE :search OR c.description LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND c.is_active = :status";
            $params['status'] = (int)$filters['status'];
        }

        $sql .= " ORDER BY c.sort_order ASC, c.name ASC LIMIT :limit OFFSET :offset";
        $params['limit']  = $limit;
        $params['offset'] = $offset;

        return $this->rawQuery($sql, $params);
    }

    public function countWithFilters(array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) AS total FROM categories WHERE deleted_at IS NULL";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE :search OR description LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND is_active = :status";
            $params['status'] = (int)$filters['status'];
        }

        $result = $this->rawQuery($sql, $params);
        return (int)($result[0]['total'] ?? 0);
    }

    public function getByIdWithRelations(int $id): ?array
    {
        $result = $this->rawQuery(
            "SELECT c.id AS category_id, c.id, c.name AS category_name, c.name AS category_code,
                    c.description, c.sort_order AS display_order, NULL AS icon,
                    c.is_active, c.created_at, c.updated_at, c.deleted_at,
                    NULL AS creator_name, NULL AS creator_full_name,
                    parent.name AS parent_category_name,
                    (SELECT COUNT(*) FROM products p
                     WHERE p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL) AS product_count
             FROM categories c
             LEFT JOIN categories parent ON c.parent_id = parent.id
             WHERE c.id = :id AND c.deleted_at IS NULL",
            ['id' => $id]
        );
        return $result[0] ?? null;
    }

    // ── Existence checks ──────────────────────────────────────────────────────
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) AS c FROM categories WHERE name = :name AND deleted_at IS NULL";
        $params = ['name' => $code];
        if ($excludeId) { $sql .= " AND id != :eid"; $params['eid'] = $excludeId; }
        return (int)($this->rawQuery($sql, $params)[0]['c'] ?? 0) > 0;
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) AS c FROM categories WHERE name = :name AND deleted_at IS NULL";
        $params = ['name' => $name];
        if ($excludeId) { $sql .= " AND id != :eid"; $params['eid'] = $excludeId; }
        return (int)($this->rawQuery($sql, $params)[0]['c'] ?? 0) > 0;
    }

    // ── Active categories for dropdowns ──────────────────────────────────────
    public function getActiveCategories(): array
    {
        return $this->rawQuery(
            "SELECT id AS category_id, name AS category_name, name AS category_code, NULL AS icon
             FROM categories WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC"
        );
    }

    public function getNextCode(): string
    {
        $result = $this->rawQuery(
            "SELECT COUNT(*) AS c FROM categories WHERE deleted_at IS NULL"
        );
        $num = (int)($result[0]['c'] ?? 0) + 1;
        return 'CAT' . str_pad($num, 3, '0', STR_PAD_LEFT);
    }

    // ── Toggle / delete / restore ─────────────────────────────────────────────
    public function toggleStatus(int $id): bool
    {
        return $this->rawExecute("UPDATE categories SET is_active = NOT is_active WHERE id = :id", ['id' => $id]);
    }

    public function softDelete(int $id): bool
    {
        return $this->rawExecute("UPDATE categories SET deleted_at = NOW(), is_active = 0 WHERE id = :id", ['id' => $id]);
    }

    public function restore(int $id): int
    {
        return (int)$this->rawExecute("UPDATE categories SET deleted_at = NULL, is_active = 1 WHERE id = :id", ['id' => $id]);
    }

    public function canDelete(int $id): bool
    {
        $result = $this->rawQuery(
            "SELECT COUNT(*) AS c FROM products WHERE category_id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
        return (int)($result[0]['c'] ?? 0) === 0;
    }

    public function getStatistics(): array
    {
        $result = $this->rawQuery(
            "SELECT COUNT(*) AS total_categories,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_categories,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_categories
             FROM categories WHERE deleted_at IS NULL"
        );
        return $result[0] ?? [];
    }
}
