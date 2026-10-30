<?php
/**
 * =========================================================
 * AU-AgroSale — Customer AboutController
 * =========================================================
 * File    : modules/customer/controllers/AboutController.php
 * Route   : GET /customer/about
 * Purpose : Serves the customer-facing About page.
 *           Loads active shops dynamically — no hardcoded data.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;

class AboutController extends BaseController
{
    /**
     * GET /customer/about
     */
    public function index(): void
    {
        $db = Database::getInstance();

        /* ── Active shops with product counts ─── */
        $shops = $db->fetchAll(
            "SELECT
                s.id,
                COALESCE(s.shop_name, s.name) AS shop_name,
                s.name,
                s.location,
                s.is_active,
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

        $this->render('customer.about', [
            'pageTitle' => 'About AU-AgroSale — Arsi University Agricultural Marketplace',
            'shops'     => $shops,
        ], 'customer');
    }
}
