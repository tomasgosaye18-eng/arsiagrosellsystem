<?php
/**
 * =========================================================
 * AU-AgroSale — FavoritesService
 * =========================================================
 * File    : modules/customer/services/FavoritesService.php
 * Purpose : All database operations for customer favorites.
 *
 * Design decisions:
 *   - Favorites are scoped to authenticated users (user_id).
 *   - Product data is always fetched live — never duplicated
 *     in the favorites table.
 *   - Soft-deleted or inactive products are shown as
 *     "unavailable" rather than silently dropped.
 *   - Toggle pattern: add if not exists, remove if exists.
 *   - Returns current live product data with availability
 *     computed from shop_inventory for the customer's
 *     selected shop (if any).
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Services;

use AAM\Core\Database;
use AAM\Core\Logger;

class FavoritesService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ──────────────────────────────────────────────────────
    // TOGGLE (add if not favorited, remove if favorited)
    //
    // Returns:
    //   ['success' => true, 'favorited' => true,  'message' => '...']  → added
    //   ['success' => true, 'favorited' => false, 'message' => '...']  → removed
    //   ['success' => false, 'message' => '...']                       → error
    //
    // SECURITY: user_id comes from the session — never trusted
    //           from the request body.
    // ──────────────────────────────────────────────────────
    public function toggle(int $userId, int $productId): array
    {
        if ($userId <= 0 || $productId <= 0) {
            return ['success' => false, 'message' => 'Invalid request.'];
        }

        // Verify the product exists (active or not — we allow favoriting
        // even soft-deleted products, but we check the product exists at all)
        $productExists = $this->db->fetchOne(
            "SELECT id FROM products WHERE id = :pid LIMIT 1",
            ['pid' => $productId]
        );

        if (!$productExists) {
            return ['success' => false, 'message' => 'Product not found.'];
        }

        try {
            // Check if already favorited
            $existing = $this->db->fetchOne(
                "SELECT id FROM customer_favorites
                 WHERE user_id = :uid AND product_id = :pid
                 LIMIT 1",
                ['uid' => $userId, 'pid' => $productId]
            );

            if ($existing) {
                // Already favorited — remove it
                $this->db->execute(
                    "DELETE FROM customer_favorites
                     WHERE user_id = :uid AND product_id = :pid",
                    ['uid' => $userId, 'pid' => $productId]
                );

                return [
                    'success'   => true,
                    'favorited' => false,
                    'message'   => 'Removed from favorites.',
                    'count'     => $this->getCount($userId),
                ];
            } else {
                // Not favorited — add it
                $this->db->execute(
                    "INSERT IGNORE INTO customer_favorites (user_id, product_id)
                     VALUES (:uid, :pid)",
                    ['uid' => $userId, 'pid' => $productId]
                );

                return [
                    'success'   => true,
                    'favorited' => true,
                    'message'   => 'Added to favorites.',
                    'count'     => $this->getCount($userId),
                ];
            }
        } catch (\Throwable $e) {
            Logger::error('FavoritesService::toggle failed', [
                'user_id'    => $userId,
                'product_id' => $productId,
                'error'      => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Could not update favorites. Please try again.'];
        }
    }

    // ──────────────────────────────────────────────────────
    // CHECK if a product is favorited by the user
    // ──────────────────────────────────────────────────────
    public function isFavorited(int $userId, int $productId): bool
    {
        if ($userId <= 0 || $productId <= 0) {
            return false;
        }
        return (bool)$this->db->fetchOne(
            "SELECT 1 FROM customer_favorites
             WHERE user_id = :uid AND product_id = :pid
             LIMIT 1",
            ['uid' => $userId, 'pid' => $productId]
        );
    }

    // ──────────────────────────────────────────────────────
    // GET SET of favorited product IDs for a user
    // Used to mark favorites on product listing pages.
    // Returns an array of product_id integers.
    // ──────────────────────────────────────────────────────
    public function getFavoritedIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT product_id FROM customer_favorites
             WHERE user_id = :uid
             ORDER BY created_at DESC",
            ['uid' => $userId]
        );
        return array_column($rows, 'product_id');
    }

    // ──────────────────────────────────────────────────────
    // GET TOTAL COUNT of favorites for a user
    // ──────────────────────────────────────────────────────
    public function getCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM customer_favorites WHERE user_id = :uid",
            ['uid' => $userId]
        );
        return (int)($row['c'] ?? 0);
    }

    // ──────────────────────────────────────────────────────
    // GET FAVORITES WITH LIVE PRODUCT DATA
    //
    // Fetches all favorited products for a user with current
    // product information. Products that have been soft-deleted
    // or made inactive are still returned but marked
    // unavailable so the customer can see and remove them.
    //
    // $shopId — if provided, includes shop-specific availability.
    //
    // Returns array of product rows (same structure as
    // CustomerProductService queries for consistency).
    // ──────────────────────────────────────────────────────
    public function getFavoritesWithProducts(int $userId, ?int $shopId = null): array
    {
        if ($userId <= 0) {
            return [];
        }

        // Base query: join favorites → products → categories → units
        // LEFT JOINs everywhere so favorites survive even if related data is gone.
        // Products that are deleted/inactive are returned with product_active=0
        // so the view can show an "unavailable" state.
        $sql = "
            SELECT
                cf.id               AS favorite_id,
                cf.created_at       AS favorited_at,
                p.id                AS id,
                p.name              AS product_name,
                p.sku               AS product_code,
                p.description,
                p.unit_price        AS selling_price,
                p.is_featured,
                p.is_perishable,
                p.primary_image,
                p.min_stock_level,
                p.is_active         AS product_active,
                p.product_status,
                p.deleted_at        AS product_deleted_at,
                c.id                AS category_id,
                c.name              AS category_name,
                u.id                AS unit_id,
                u.name              AS unit_name,
                u.abbreviation      AS unit_abbreviation
            FROM customer_favorites cf
            LEFT JOIN products p ON p.id = cf.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN units_of_measure u ON u.id = p.unit_id
            WHERE cf.user_id = :uid
            ORDER BY cf.created_at DESC
        ";

        $rows = $this->db->fetchAll($sql, ['uid' => $userId]);

        if (empty($rows)) {
            return [];
        }

        // Collect product IDs for batch shop_inventory lookup
        $productIds = array_filter(array_column($rows, 'id'));

        // Batch-fetch shop_inventory for selected shop to avoid N+1
        $inventoryMap = [];
        if ($shopId > 0 && !empty($productIds)) {
            $inList = implode(',', array_map('intval', $productIds));
            $invRows = $this->db->fetchAll(
                "SELECT product_id, available_quantity, current_quantity,
                        min_stock_level, expiry_date
                 FROM shop_inventory
                 WHERE shop_id = :sid AND product_id IN ({$inList})",
                ['sid' => $shopId]
            );
            foreach ($invRows as $inv) {
                $inventoryMap[(int)$inv['product_id']] = $inv;
            }
        }

        // Resolve availability for each product
        foreach ($rows as &$row) {
            $pid = (int)($row['id'] ?? 0);

            // Product was hard-deleted or is null (FK broken)
            if (!$pid) {
                $row['availability'] = ['status' => 'unavailable', 'label' => 'Unavailable', 'qty_label' => ''];
                $row['available_quantity'] = 0;
                $row['product_unavailable'] = true;
                continue;
            }

            // Product is soft-deleted or inactive
            $productIsLive = $row['product_active']
                && $row['product_status'] === 'active'
                && !$row['product_deleted_at'];

            if (!$productIsLive) {
                $row['availability'] = ['status' => 'unavailable', 'label' => 'No longer available', 'qty_label' => ''];
                $row['available_quantity'] = 0;
                $row['product_unavailable'] = true;
                continue;
            }

            $row['product_unavailable'] = false;

            if ($shopId > 0) {
                $inv = $inventoryMap[$pid] ?? null;
                $availQty = (float)($inv['available_quantity'] ?? 0);
                $minStock  = (float)($inv['min_stock_level'] ?? $row['min_stock_level'] ?? 0);
                $expiry    = $inv['expiry_date'] ?? null;

                $row['available_quantity']   = $availQty;
                $row['current_quantity']     = (float)($inv['current_quantity'] ?? 0);
                $row['shop_min_stock_level'] = $minStock;
                $row['expiry_date']          = $expiry;
                $row['in_this_shop']         = ($inv !== null);
            } else {
                // No shop selected: aggregate across all shops
                $totals = $this->db->fetchOne(
                    "SELECT COALESCE(SUM(available_quantity), 0) AS total_qty
                     FROM shop_inventory WHERE product_id = :pid",
                    ['pid' => $pid]
                );
                $availQty = (float)($totals['total_qty'] ?? 0);
                $minStock  = 0;
                $expiry    = null;

                $row['available_quantity']   = $availQty;
                $row['current_quantity']     = $availQty;
                $row['shop_min_stock_level'] = 0;
                $row['expiry_date']          = null;
                $row['in_this_shop']         = false;
            }

            $row['availability'] = $this->resolveAvailability($availQty, $minStock, $expiry ?? null);
        }
        unset($row);

        return $rows;
    }

    // ──────────────────────────────────────────────────────
    // AVAILABILITY HELPER
    // Mirrors the logic from CustomerProductService
    // to ensure consistent status labels.
    // ──────────────────────────────────────────────────────
    private function resolveAvailability(
        float   $qty,
        float   $minStock = 0,
        ?string $expiry   = null
    ): array {
        // Check expiry first
        if ($expiry && strtotime($expiry) < time()) {
            return ['status' => 'unavailable', 'label' => 'Expired', 'qty_label' => ''];
        }

        $low = max(5, $minStock);

        if ($qty <= 0) {
            return ['status' => 'unavailable', 'label' => 'Out of stock', 'qty_label' => ''];
        }

        if ($qty <= $low) {
            $label = $qty < 1
                ? 'Almost gone'
                : 'Low stock — ' . number_format($qty, 0) . ' left';
            return [
                'status'    => 'low',
                'label'     => $label,
                'qty_label' => number_format($qty, 0),
            ];
        }

        return [
            'status'    => 'available',
            'label'     => 'In stock',
            'qty_label' => number_format($qty, 0),
        ];
    }
}
