<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMProductController.php
 * Purpose : General Manager read-only product catalog view.
 *           Shows management-relevant product information only.
 *           NO Create, Edit, Delete, Price change, Categories,
 *           Units management, or any product administration.
 * Route   : GET /gm/products
 * Access  : ROLE_GENERAL_MANAGER only
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;

class GMProductController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $db = Database::getInstance();

        $search       = trim($this->query('search', ''));
        $categoryId   = (int)$this->query('category_id', 0);
        $statusFilter = trim($this->query('status', ''));
        $priceFilter  = trim($this->query('price_filter', '')); // 'no_price' to show only unpriced
        $page         = max(1, (int)$this->query('page', 1));
        $perPage      = 20;
        $offset       = ($page - 1) * $perPage;

        $where  = ['p.is_active = 1'];
        $bind   = [];

        if ($search !== '') {
            $where[]         = '(p.name LIKE :search OR p.sku LIKE :search OR p.barcode LIKE :search)';
            $bind['search']  = '%' . $search . '%';
        }
        if ($categoryId > 0) {
            $where[]         = 'p.category_id = :cat';
            $bind['cat']     = $categoryId;
        }
        if ($statusFilter !== '') {
            $where[]         = 'p.product_status = :pstatus';
            $bind['pstatus'] = $statusFilter;
        }
        // Filter for products that have no selling price set (blocked from POS)
        if ($priceFilter === 'no_price') {
            $where[] = '(p.unit_price IS NULL OR p.unit_price <= 0)';
        }

        $whereStr = 'WHERE ' . implode(' AND ', $where);

        try {
            $total = (int)$db->fetchColumn(
                "SELECT COUNT(*) FROM products p {$whereStr}", $bind
            );
        } catch (\Throwable) { $total = 0; }

        $bind['limit']  = $perPage;
        $bind['offset'] = $offset;

        $products = [];
        try {
            $products = $db->fetchAll(
                "SELECT
                    p.id, p.name AS product_name, p.sku, p.barcode,
                    p.product_status, p.is_active, p.is_featured,
                    p.unit_price AS selling_price, p.min_stock_level,
                    c.name AS category_name,
                    u.name AS unit_name, u.abbreviation AS unit_abbr,
                    COALESCE(SUM(wi.current_quantity),0) AS warehouse_stock,
                    COALESCE(SUM(si.current_quantity),0) AS shop_stock
                 FROM products p
                 LEFT JOIN categories c          ON c.id = p.category_id
                 LEFT JOIN units_of_measure u    ON u.id = p.unit_id
                 LEFT JOIN warehouse_inventory wi ON wi.product_id = p.id
                 LEFT JOIN shop_inventory si      ON si.product_id = p.id
                 {$whereStr}
                 GROUP BY p.id, p.name, p.sku, p.barcode, p.product_status,
                          p.is_active, p.is_featured, p.unit_price, p.min_stock_level,
                          c.name, u.name, u.abbreviation
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

        // Count products with no selling price (POS-blocked) — used by the view
        // to show the warning badge and "No Price" quick-filter button.
        $noPriceCount = 0;
        try {
            $noPriceCount = (int)$db->fetchColumn(
                "SELECT COUNT(*) FROM products p
                 WHERE p.is_active = 1
                   AND p.deleted_at IS NULL
                   AND (p.unit_price IS NULL OR p.unit_price <= 0)"
            );
        } catch (\Throwable) {}

        $this->render('general_manager.products.index', [
            'pageTitle'    => 'Product Catalog',
            'products'     => $products,
            'categories'   => $categories,
            'noPriceCount' => $noPriceCount,
            'filters'      => [
                'search'       => $search,
                'category_id'  => $categoryId,
                'status'       => $statusFilter,
                'price_filter' => $priceFilter,
            ],
            'pagination'   => [
                'current_page' => $page,
                'total'        => $total,
                'per_page'     => $perPage,
                'last_page'    => (int)ceil(max(1, $total) / $perPage),
            ],
        ]);
    }
}
