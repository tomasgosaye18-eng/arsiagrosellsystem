<?php
/**
 * =========================================================
 * AU-AgroSale — Customer HomeController
 * =========================================================
 * Serves the customer storefront home page.
 * Reads real data from existing models — NO duplicate tables.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Customer\Services\FavoritesService;

class HomeController extends BaseController
{
    /**
     * GET /customer
     * Customer storefront home page.
     * Internal staff are redirected to their own dashboard immediately.
     */
    public function index(): void
    {
        // Internal staff who navigate to /customer should go back to their dashboard
        if (\AAM\Core\SessionManager::isAuthenticated()) {
            $role = \AAM\Core\SessionManager::userRole();
            if ($role !== null && $role !== \ROLE_CUSTOMER) {
                $this->redirect('/dashboard');
                return;
            }
        }

        $db = Database::getInstance();

        /* ── Active shops ─── */
        $shops = $db->fetchAll(
            "SELECT
                s.id,
                COALESCE(s.shop_name, s.name) AS shop_name,
                s.name,
                s.location,
                s.is_active,
                s.opening_time,
                s.closing_time,
                s.shop_image,
                (
                    SELECT COUNT(DISTINCT si.product_id)
                    FROM shop_inventory si
                    JOIN products p ON p.id = si.product_id
                    WHERE si.shop_id            = s.id
                      AND si.available_quantity > 0
                      AND p.is_active           = 1
                      AND p.deleted_at          IS NULL
                      AND p.product_status      = 'active'
                ) AS product_count
             FROM shops s
             WHERE s.is_active = 1
               AND s.deleted_at IS NULL
             ORDER BY s.name ASC"
        );

        /* ── Active categories (from existing categories table) ─── */
        $categories = $db->fetchAll(
            "SELECT
                c.id,
                c.name,
                COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p
                 ON p.category_id = c.id
                AND p.is_active = 1
                AND p.deleted_at IS NULL
                AND p.product_status = 'active'
             WHERE c.is_active = 1
               AND c.deleted_at IS NULL
             GROUP BY c.id, c.name
             HAVING product_count > 0
             ORDER BY product_count DESC, c.name ASC
             LIMIT 8"
        );

        // Resolve selected shop from session BEFORE building the products query
        // so we can scope inventory to the correct shop.
        $selectedShopId   = (int)($_SESSION['ca_selected_shop_id']   ?? 0);
        $selectedShopName = (string)($_SESSION['ca_selected_shop_name'] ?? '');

        // If we have a selected shop ID but no name cached, resolve it
        if ($selectedShopId && !$selectedShopName) {
            $shopRow = $db->fetchOne(
                "SELECT COALESCE(shop_name, name) AS shop_name, location
                 FROM shops WHERE id = :id AND is_active = 1 AND deleted_at IS NULL LIMIT 1",
                ['id' => $selectedShopId]
            );
            if ($shopRow) {
                $selectedShopName = $shopRow['shop_name'];
                $_SESSION['ca_selected_shop_name'] = $selectedShopName;
            } else {
                // Shop no longer valid — clear session
                $selectedShopId   = 0;
                $selectedShopName = '';
                unset($_SESSION['ca_selected_shop_id'], $_SESSION['ca_selected_shop_name']);
            }
        }

        // Favorited product IDs for the logged-in customer (empty for guests)
        $favoritedIds = [];
        $userId = SessionManager::userId();
        if ($userId && SessionManager::userRole() === \ROLE_CUSTOMER) {
            $favService   = new FavoritesService();
            $favoritedIds = $favService->getFavoritedIds((int)$userId);
        }

        /* ── Featured products ─── */
        /* If a shop is selected: scope inventory to that shop and respect expiry_date.
         * Otherwise: aggregate across all shops, exclude rows where all inventory
         * records are expired (expiry_date < CURDATE()).
         */
        if ($selectedShopId > 0) {
            // Shop-scoped: mirrors the same logic as CustomerProductService::getProductsByShop
            $products = $db->fetchAll(
                "SELECT
                    p.id,
                    p.id AS product_id,
                    p.name AS product_name,
                    p.sku AS product_code,
                    p.unit_price AS selling_price,
                    p.is_featured,
                    p.is_perishable,
                    p.primary_image,
                    c.name AS category_name,
                    u.abbreviation AS unit_abbreviation,
                    u.name AS unit_name,
                    si.available_quantity,
                    si.expiry_date
                 FROM products p
                 INNER JOIN shop_inventory si
                        ON si.product_id = p.id
                       AND si.shop_id    = :shop_id
                 LEFT JOIN categories c ON c.id = p.category_id
                 LEFT JOIN units_of_measure u ON u.id = p.unit_id
                 WHERE p.is_active = 1
                   AND p.deleted_at IS NULL
                   AND p.product_status = 'active'
                   AND si.available_quantity > 0
                   AND (si.expiry_date IS NULL OR si.expiry_date >= CURDATE())
                 ORDER BY
                   p.is_featured DESC,
                   p.created_at DESC
                 LIMIT 8",
                ['shop_id' => $selectedShopId]
            );
        } else {
            // No shop selected: aggregate across all shops, but only count non-expired stock
            $products = $db->fetchAll(
                "SELECT
                    p.id,
                    p.id AS product_id,
                    p.name AS product_name,
                    p.sku AS product_code,
                    p.unit_price AS selling_price,
                    p.is_featured,
                    p.is_perishable,
                    p.primary_image,
                    c.name AS category_name,
                    u.abbreviation AS unit_abbreviation,
                    u.name AS unit_name,
                    COALESCE(
                        (
                            SELECT SUM(si2.available_quantity)
                            FROM shop_inventory si2
                            WHERE si2.product_id = p.id
                              AND (si2.expiry_date IS NULL OR si2.expiry_date >= CURDATE())
                        ), 0
                    ) AS available_quantity,
                    NULL AS expiry_date
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 LEFT JOIN units_of_measure u ON u.id = p.unit_id
                 WHERE p.is_active = 1
                   AND p.deleted_at IS NULL
                   AND p.product_status = 'active'
                   AND (
                        SELECT SUM(si3.available_quantity)
                        FROM shop_inventory si3
                        WHERE si3.product_id = p.id
                          AND (si3.expiry_date IS NULL OR si3.expiry_date >= CURDATE())
                   ) > 0
                 ORDER BY
                   p.is_featured DESC,
                   p.created_at DESC
                 LIMIT 8"
            );
        }

        $this->render('customer.home', [
            'pageTitle'        => 'AU-AgroSale — Fresh Farm Products',
            'shops'            => $shops,
            'categories'       => $categories,
            'products'         => $products,
            'selectedShopId'   => $selectedShopId,
            'selectedShopName' => $selectedShopName,
            'favoritedIds'     => $favoritedIds,
        ], 'customer'); // <-- uses templates/customer.php (NOT master.php)
    }
}
