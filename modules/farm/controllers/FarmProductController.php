<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/farm/controllers/FarmProductController.php
 * Purpose : Farm Manager read-only product catalog.
 *           Reuses the existing product database/model.
 *           Shows catalog info only — no prices, no CRUD,
 *           no admin controls of any kind.
 * Routes  : GET /farm/products
 *           GET /farm/products/{id}
 * Access  : ROLE_FARM_MANAGER only
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Farm\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;

class FarmProductController extends BaseController
{
    // ── GET /farm/products ────────────────────────────────────────────────────
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER);

        $db = Database::getInstance();

        $search     = trim($this->query('search', ''));
        $categoryId = (int)$this->query('category_id', 0);
        $status     = trim($this->query('status', ''));
        $page       = max(1, (int)$this->query('page', 1));
        $perPage    = 20;
        $offset     = ($page - 1) * $perPage;

        $where = ["p.is_active = 1"];
        $bind  = [];

        if ($search !== '') {
            $where[]        = "(p.name LIKE :search OR p.sku LIKE :search OR p.barcode LIKE :search OR p.description LIKE :search)";
            $bind['search'] = '%' . $search . '%';
        }
        if ($categoryId > 0) {
            $where[]    = "p.category_id = :cat";
            $bind['cat']= $categoryId;
        }
        if ($status !== '') {
            $where[]        = "p.product_status = :status";
            $bind['status'] = $status;
        }

        $whereStr = 'WHERE ' . implode(' AND ', $where);

        try {
            $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM products p $whereStr", $bind);
        } catch (\Throwable) { $total = 0; }

        $bind['limit']  = $perPage;
        $bind['offset'] = $offset;

        $products = [];
        try {
            $products = $db->fetchAll(
                "SELECT
                    p.id, p.name AS product_name, p.sku AS product_code, p.barcode,
                    p.description, p.product_status, p.is_active, p.is_featured,
                    p.min_stock_level,
                    p.primary_image,
                    c.name  AS category_name,
                    u.name  AS unit_name,
                    u.abbreviation AS unit_abbr,
                    u.unit_type
                 FROM products p
                 LEFT JOIN categories     c ON c.id = p.category_id
                 LEFT JOIN units_of_measure u ON u.id = p.unit_id
                 $whereStr
                 ORDER BY p.name ASC
                 LIMIT :limit OFFSET :offset",
                $bind
            );
        } catch (\Throwable) { $products = []; }

        $categories = [];
        try {
            $categories = $db->fetchAll(
                "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name"
            );
        } catch (\Throwable) {}

        $this->render('farm.products.index', [
            'pageTitle'  => 'Product Catalog',
            'products'   => $products,
            'categories' => $categories,
            'filters'    => compact('search', 'categoryId', 'status'),
            'pagination' => [
                'current_page' => $page,
                'total'        => $total,
                'per_page'     => $perPage,
                'last_page'    => (int)ceil(max(1, $total) / $perPage),
            ],
        ]);
    }

    // ── GET /farm/products/{id} ───────────────────────────────────────────────
    public function show(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER);

        $db = Database::getInstance();

        $product = null;
        try {
            $product = $db->fetchOne(
                "SELECT
                    p.id, p.name AS product_name, p.sku AS product_code, p.barcode,
                    p.description, p.product_status, p.is_active, p.is_featured,
                    p.min_stock_level, p.primary_image,
                    p.is_perishable AS requires_expiry, p.shelf_life_days,
                    NULL AS storage_conditions,
                    NULL AS weight, NULL AS weight_unit,
                    NULL AS dimensions, NULL AS color, NULL AS size, NULL AS brand,
                    c.name  AS category_name,
                    u.name  AS unit_name,
                    u.abbreviation AS unit_abbr,
                    u.unit_type
                 FROM products p
                 LEFT JOIN categories     c ON c.id = p.category_id
                 LEFT JOIN units_of_measure u ON u.id = p.unit_id
                 WHERE p.id = :id AND p.is_active = 1",
                ['id' => (int)$id]
            );
        } catch (\Throwable $e) {
            \AAM\Core\Logger::error('FarmProductController::show query failed', ['error' => $e->getMessage(), 'id' => $id]);
        }

        if (!$product) {
            $this->flash(FLASH_ERROR, 'Product not found.');
            $this->redirect('/farm/products');
            return;
        }

        $this->render('farm.products.show', [
            'pageTitle' => e($product['product_name']),
            'product'   => $product,
        ]);
    }
}
