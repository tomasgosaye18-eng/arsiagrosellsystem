<?php
/**
 * =========================================================
 * PRODUCT MODEL
 * =========================================================
 * File: app/modules/admin/models/ProductModel.php
 * Purpose: Product master data access layer
 *          Mapped to the actual aam_db `products` schema:
 *          PK=id, name, sku, unit_price, min_stock_level,
 *          reorder_point, is_perishable …
 *          Views receive aliased column names for compatibility.
 * =========================================================
 */

namespace AAM\Admin\Models;

use AAM\Core\BaseModel;

class ProductModel extends BaseModel
{
    protected string $table      = 'products';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = true;

    protected array $fillable = [
        'category_id', 'unit_id', 'name', 'slug', 'sku', 'barcode',
        'description', 'unit_price', 'cost_price', 'min_stock_level',
        'reorder_point', 'is_perishable', 'shelf_life_days',
        'is_featured', 'product_status', 'is_active',
        'primary_image', 'has_multiple_images',
        'created_by', 'updated_by',
        // markup_percentage is computed but stored for display performance
        'markup_percentage',
    ];

    // ── Column list: real DB names aliased to view-friendly names ──────────────
    private string $selectCols = "
        p.id            AS product_id,
        p.id,
        p.sku           AS product_code,
        p.barcode,
        p.name          AS product_name,
        p.description,
        p.unit_price    AS selling_price,
        p.cost_price,
        p.min_stock_level AS minimum_stock,
        NULL            AS maximum_stock,
        p.reorder_point AS reorder_level,
        NULL            AS reorder_quantity,
        p.is_perishable AS requires_expiry,
        p.shelf_life_days,
        NULL            AS storage_conditions,
        NULL            AS weight,
        NULL            AS weight_unit,
        NULL            AS dimensions,
        NULL            AS color,
        NULL            AS size,
        NULL            AS scientific_name,
        NULL            AS brand,
        NULL            AS specifications,
        NULL            AS markup_percentage,
        p.primary_image,
        p.has_multiple_images,
        p.product_status,
        p.is_featured,
        p.is_active,
        p.category_id,
        p.unit_id,
        p.created_by,
        p.updated_by,
        p.created_at,
        p.updated_at,
        p.deleted_at,
        c.name          AS category_name,
        c.name          AS category_code,
        NULL            AS category_icon,
        u.name          AS unit_name,
        u.abbreviation  AS unit_abbreviation,
        u.name          AS unit_code,
        creator.username AS creator_name,
        CONCAT(creator.first_name, ' ', creator.last_name) AS creator_full_name,
        updater.username AS updater_name,
        CONCAT(updater.first_name, ' ', updater.last_name) AS updater_full_name
    ";

    // ── List with relations ────────────────────────────────────────────────────
    public function getAllWithRelations(array $filters = [], int $limit = 15, int $offset = 0): array
    {
        $sql = "SELECT {$this->selectCols}
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN units_of_measure u ON p.unit_id = u.id
                LEFT JOIN users creator ON p.created_by = creator.id
                LEFT JOIN users updater ON p.updated_by = updater.id
                WHERE p.deleted_at IS NULL";

        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE :search_like OR p.sku LIKE :search_like OR p.barcode LIKE :search_like)";
            $params['search_like'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = :category_id";
            $params['category_id'] = (int)$filters['category_id'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND p.is_active = :is_active";
            $params['is_active'] = (int)$filters['is_active'];
        }
        if (!empty($filters['product_status'])) {
            $sql .= " AND p.product_status = :product_status";
            $params['product_status'] = $filters['product_status'];
        }
        if (!empty($filters['price_min'])) {
            $sql .= " AND p.unit_price >= :price_min";
            $params['price_min'] = (float)$filters['price_min'];
        }
        if (!empty($filters['price_max'])) {
            $sql .= " AND p.unit_price <= :price_max";
            $params['price_max'] = (float)$filters['price_max'];
        }
        if (isset($filters['is_featured']) && $filters['is_featured'] !== '') {
            $sql .= " AND p.is_featured = :is_featured";
            $params['is_featured'] = (int)$filters['is_featured'];
        }

        $sql .= " ORDER BY p.name ASC LIMIT :limit OFFSET :offset";
        $params['limit']  = $limit;
        $params['offset'] = $offset;

        return $this->rawQuery($sql, $params);
    }

    // ── Count with filters ─────────────────────────────────────────────────────
    public function countWithFilters(array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) AS total FROM products p WHERE p.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE :search_like OR p.sku LIKE :search_like OR p.barcode LIKE :search_like)";
            $params['search_like'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = :category_id";
            $params['category_id'] = (int)$filters['category_id'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND p.is_active = :is_active";
            $params['is_active'] = (int)$filters['is_active'];
        }
        if (!empty($filters['product_status'])) {
            $sql .= " AND p.product_status = :product_status";
            $params['product_status'] = $filters['product_status'];
        }
        if (!empty($filters['price_min'])) {
            $sql .= " AND p.unit_price >= :price_min";
            $params['price_min'] = (float)$filters['price_min'];
        }
        if (!empty($filters['price_max'])) {
            $sql .= " AND p.unit_price <= :price_max";
            $params['price_max'] = (float)$filters['price_max'];
        }
        if (isset($filters['is_featured']) && $filters['is_featured'] !== '') {
            $sql .= " AND p.is_featured = :is_featured";
            $params['is_featured'] = (int)$filters['is_featured'];
        }

        $result = $this->rawQuery($sql, $params);
        return (int)($result[0]['total'] ?? 0);
    }

    // ── Single product with relations ──────────────────────────────────────────
    public function getByIdWithRelations(int $id): ?array
    {
        $sql    = "SELECT {$this->selectCols}
                   FROM products p
                   LEFT JOIN categories c ON p.category_id = c.id
                   LEFT JOIN units_of_measure u ON p.unit_id = u.id
                   LEFT JOIN users creator ON p.created_by = creator.id
                   LEFT JOIN users updater ON p.updated_by = updater.id
                   WHERE p.id = :id AND p.deleted_at IS NULL";
        $result = $this->rawQuery($sql, ['id' => $id]);
        return $result[0] ?? null;
    }

    // ── Existence checks ───────────────────────────────────────────────────────
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) AS c FROM products WHERE sku = :code AND deleted_at IS NULL";
        $params = ['code' => $code];
        if ($excludeId) { $sql .= " AND id != :eid"; $params['eid'] = $excludeId; }
        return (int)($this->rawQuery($sql, $params)[0]['c'] ?? 0) > 0;
    }

    public function barcodeExists(string $barcode, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) AS c FROM products WHERE barcode = :barcode AND deleted_at IS NULL";
        $params = ['barcode' => $barcode];
        if ($excludeId) { $sql .= " AND id != :eid"; $params['eid'] = $excludeId; }
        return (int)($this->rawQuery($sql, $params)[0]['c'] ?? 0) > 0;
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) AS c FROM products WHERE name = :name AND deleted_at IS NULL";
        $params = ['name' => $name];
        if ($excludeId) { $sql .= " AND id != :eid"; $params['eid'] = $excludeId; }
        return (int)($this->rawQuery($sql, $params)[0]['c'] ?? 0) > 0;
    }

    // ── Next SKU code ──────────────────────────────────────────────────────────
    public function getNextCode(): string
    {
        $result = $this->rawQuery(
            "SELECT sku FROM products WHERE sku REGEXP '^PRD[0-9]+$' AND deleted_at IS NULL ORDER BY sku DESC LIMIT 1"
        );
        if (empty($result)) return 'PRD001';
        $number = (int)substr($result[0]['sku'], 3);
        return 'PRD' . str_pad($number + 1, 3, '0', STR_PAD_LEFT);
    }

    // ── Barcode search (POS / AJAX) ────────────────────────────────────────────
    public function findByBarcode(string $barcode): ?array
    {
        $result = $this->rawQuery(
            "SELECT p.id AS product_id, p.name AS product_name, p.sku AS product_code,
                    p.barcode, p.unit_price AS selling_price, p.cost_price,
                    c.name AS category_name, u.name AS unit_name, u.abbreviation AS unit_abbreviation
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN units_of_measure u ON p.unit_id = u.id
             WHERE p.barcode = :barcode AND p.is_active = 1 AND p.deleted_at IS NULL",
            ['barcode' => $barcode]
        );
        return $result[0] ?? null;
    }

    // ── Toggle helpers ─────────────────────────────────────────────────────────
    public function toggleStatus(int $id): bool
    {
        return (bool)$this->rawExecute(
            "UPDATE products SET is_active = NOT is_active WHERE id = :id",
            ['id' => $id]
        );
    }

    public function toggleFeatured(int $id): bool
    {
        return (bool)$this->rawExecute(
            "UPDATE products SET is_featured = NOT is_featured WHERE id = :id",
            ['id' => $id]
        );
    }

    public function updateStatus(int $id, string $status): bool
    {
        return (bool)$this->rawExecute(
            "UPDATE products SET product_status = :status WHERE id = :id",
            ['id' => $id, 'status' => $status]
        );
    }

    // ── Soft delete / restore ──────────────────────────────────────────────────
    public function softDelete(int $id): bool
    {
        return (bool)$this->rawExecute(
            "UPDATE products SET deleted_at = NOW(), is_active = 0 WHERE id = :id",
            ['id' => $id]
        );
    }

    public function restore(int $id): int
    {
        return $this->rawExecute(
            "UPDATE products SET deleted_at = NULL, is_active = 1 WHERE id = :id",
            ['id' => $id]
        );
    }

    // ── Statistics ─────────────────────────────────────────────────────────────
    public function getStatistics(): array
    {
        $result = $this->rawQuery(
            "SELECT
                COUNT(*)                                                          AS total_products,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)                   AS active_products,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END)                   AS inactive_products,
                SUM(CASE WHEN product_status = 'active'       THEN 1 ELSE 0 END) AS status_active,
                SUM(CASE WHEN product_status = 'inactive'     THEN 1 ELSE 0 END) AS status_inactive,
                SUM(CASE WHEN product_status = 'discontinued' THEN 1 ELSE 0 END) AS discontinued,
                SUM(CASE WHEN product_status = 'out_of_stock' THEN 1 ELSE 0 END) AS out_of_stock,
                SUM(CASE WHEN is_featured = 1                 THEN 1 ELSE 0 END) AS featured_products,
                SUM(CASE WHEN is_perishable = 1               THEN 1 ELSE 0 END) AS expiry_tracked,
                COUNT(DISTINCT category_id)                                       AS categories_used,
                AVG(unit_price)                                                   AS avg_selling_price,
                MIN(unit_price)                                                   AS min_price,
                MAX(unit_price)                                                   AS max_price
             FROM products WHERE deleted_at IS NULL"
        );
        return $result[0] ?? [];
    }

    // ── Products by category ───────────────────────────────────────────────────
    public function getProductsByCategory(): array
    {
        return $this->rawQuery(
            "SELECT c.name AS category_name, c.id AS category_code, NULL AS icon,
                    COUNT(p.id) AS product_count,
                    SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) AS active_count
             FROM categories c
             LEFT JOIN products p ON c.id = p.category_id AND p.deleted_at IS NULL
             WHERE c.deleted_at IS NULL
             GROUP BY c.id, c.name
             ORDER BY product_count DESC, c.name ASC"
        );
    }

    // ── Recent products ────────────────────────────────────────────────────────
    public function getRecentProducts(int $limit = 10): array
    {
        return $this->rawQuery(
            "SELECT p.id AS product_id, p.sku AS product_code, p.name AS product_name,
                    p.barcode, p.unit_price AS selling_price, p.primary_image,
                    c.name AS category_name, u.abbreviation AS unit_abbreviation, p.created_at
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN units_of_measure u ON p.unit_id = u.id
             WHERE p.deleted_at IS NULL AND p.is_active = 1
             ORDER BY p.created_at DESC LIMIT :limit",
            ['limit' => $limit]
        );
    }

    // ── Status enum — matches actual DB ENUM values (lowercase) ───────────────
    public function getProductStatuses(): array
    {
        return [
            'active'       => 'Active',
            'inactive'     => 'Inactive',
            'discontinued' => 'Discontinued',
            'out_of_stock' => 'Out of Stock',
        ];
    }

    // ── Active categories for dropdowns ───────────────────────────────────────
    public function getActiveCategories(): array
    {
        return $this->rawQuery(
            "SELECT id AS category_id, name AS category_name
             FROM categories
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY name ASC"
        );
    }
}
