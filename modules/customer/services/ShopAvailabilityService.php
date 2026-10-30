<?php
/**
 * =========================================================
 * AU-AgroSale — ShopAvailabilityService
 * =========================================================
 * File    : modules/customer/services/ShopAvailabilityService.php
 * Purpose : Shop listing and per-shop product availability
 *           for the customer storefront.
 *           Reads from existing shops and shop_inventory tables.
 *           NO new tables created.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Services;

use AAM\Core\Database;

class ShopAvailabilityService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ──────────────────────────────────────────────────────
    // SHOP LIST
    // ──────────────────────────────────────────────────────

    /**
     * Get all active shops with their product availability counts.
     * Used for the shop selector dropdown and shop discovery page.
     */
    public function getActiveShops(): array
    {
        return $this->db->fetchAll(
            "SELECT
                s.id,
                COALESCE(s.shop_name, s.name) AS shop_name,
                s.name,
                s.location,
                s.address,
                s.description,
                s.opening_time,
                s.closing_time,
                s.is_active,
                s.shop_image,
                (
                    SELECT COUNT(DISTINCT si.product_id)
                    FROM shop_inventory si
                    JOIN products p ON p.id = si.product_id
                    WHERE si.shop_id        = s.id
                      AND si.available_quantity > 0
                      AND p.is_active       = 1
                      AND p.deleted_at      IS NULL
                      AND p.product_status  = 'active'
                ) AS product_count,
                (
                    SELECT COUNT(DISTINCT si2.product_id)
                    FROM shop_inventory si2
                    WHERE si2.shop_id = s.id
                ) AS total_stocked_products
             FROM shops s
             WHERE s.is_active = 1
               AND s.deleted_at IS NULL
             ORDER BY s.name ASC"
        );
    }

    /**
     * Get a single shop by ID (active only).
     */
    public function getShop(int $shopId): ?array
    {
        $shop = $this->db->fetchOne(
            "SELECT
                s.id,
                COALESCE(s.shop_name, s.name) AS shop_name,
                s.name,
                s.location,
                s.address,
                s.description,
                s.opening_time,
                s.closing_time,
                s.is_active,
                s.shop_image
             FROM shops s
             WHERE s.id = :id
               AND s.is_active = 1
               AND s.deleted_at IS NULL
             LIMIT 1",
            ['id' => $shopId]
        );

        if (!$shop) {
            return null;
        }

        // Attach product counts
        $counts = $this->db->fetchOne(
            "SELECT
                COUNT(DISTINCT si.product_id) AS available_product_count,
                COALESCE(SUM(si.available_quantity), 0) AS total_available_qty
             FROM shop_inventory si
             JOIN products p ON p.id = si.product_id
             WHERE si.shop_id        = :sid
               AND si.available_quantity > 0
               AND p.is_active       = 1
               AND p.deleted_at      IS NULL
               AND p.product_status  = 'active'",
            ['sid' => $shopId]
        );

        $shop['product_count']         = (int)($counts['available_product_count'] ?? 0);
        $shop['total_available_qty']   = (float)($counts['total_available_qty']   ?? 0);

        return $shop;
    }

    // ──────────────────────────────────────────────────────
    // CART SHOP CHANGE VALIDATION
    // ──────────────────────────────────────────────────────

    /**
     * Given a list of cart items and a new shop ID, identify which
     * products are unavailable at the new shop.
     *
     * @param array $cartItems  Array of ['product_id' => int, 'qty' => float, ...]
     * @param int   $newShopId
     * @return array  Array of product_ids that are NOT available at the new shop
     */
    public function getUnavailableProductsAtShop(array $cartItems, int $newShopId): array
    {
        if (empty($cartItems)) {
            return [];
        }

        $productIds = array_map(fn($i) => (int)$i['product_id'], $cartItems);
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        $available = $this->db->fetchAll(
            "SELECT si.product_id
             FROM shop_inventory si
             JOIN products p ON p.id = si.product_id
             WHERE si.shop_id             = ?
               AND si.product_id          IN ({$placeholders})
               AND si.available_quantity  > 0
               AND p.is_active            = 1
               AND p.deleted_at           IS NULL
               AND p.product_status       = 'active'",
            array_merge([$newShopId], $productIds)
        );

        $availableIds = array_map(fn($r) => (int)$r['product_id'], $available);
        return array_values(array_diff($productIds, $availableIds));
    }

    // ──────────────────────────────────────────────────────
    // SHOP DISCOVERY PAGE DATA
    // ──────────────────────────────────────────────────────

    /**
     * Get all shops with availability summary for the discovery page.
     * Includes breakdown by category.
     */
    public function getShopsWithAvailability(): array
    {
        $shops = $this->getActiveShops();

        foreach ($shops as &$shop) {
            // Top categories at this shop
            $shop['top_categories'] = $this->db->fetchAll(
                "SELECT c.name, COUNT(DISTINCT p.id) AS product_count
                 FROM shop_inventory si
                 JOIN products p ON p.id = si.product_id
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE si.shop_id              = :sid
                   AND si.available_quantity   > 0
                   AND p.is_active             = 1
                   AND p.deleted_at            IS NULL
                   AND p.product_status        = 'active'
                 GROUP BY c.id, c.name
                 ORDER BY product_count DESC
                 LIMIT 4",
                ['sid' => $shop['id']]
            );
        }
        unset($shop);

        return $shops;
    }
}
