<?php
/**
 * =========================================================
 * AU-AgroSale — CartService
 * =========================================================
 * File    : modules/customer/services/CartService.php
 * Purpose : Server-side session cart management for the
 *           customer storefront.
 *
 *           Responsibilities:
 *             - Validate every item against DB stock + price
 *               before adding or updating.
 *             - Recheck the full cart on page load (stock may
 *               have changed since the customer last visited).
 *             - Enforce single-shop rule (all items must belong
 *               to the same selected shop).
 *             - Never trust browser-submitted prices.
 *
 *           Architecture:
 *             - Cart lives in PHP session: $_SESSION['ca_cart']
 *             - localStorage mirrors this for badge/UX.
 *             - On each cart page load the server rechecks and
 *               returns the authoritative cart + any warnings.
 *
 *           Phase 4 scope — NO order creation yet.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Services;

use AAM\Core\Database;

class CartService
{
    private Database $db;

    /** Session key for cart data */
    private const CART_SESSION_KEY = 'ca_cart';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ──────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────

    /**
     * Get raw cart items from session (without DB recheck).
     * Returns array of cart item rows.
     */
    public function getSessionCart(): array
    {
        return $_SESSION[self::CART_SESSION_KEY] ?? [];
    }

    /**
     * Get cart item count (sum of all quantities).
     */
    public function getItemCount(): int
    {
        $cart = $this->getSessionCart();
        return (int) array_sum(array_column($cart, 'quantity'));
    }

    /**
     * Get distinct item count (number of product lines).
     */
    public function getDistinctCount(): int
    {
        return count($this->getSessionCart());
    }

    // ──────────────────────────────────────────────────────
    // ADD ITEM
    // ──────────────────────────────────────────────────────

    /**
     * Add a product to the cart — with full server-side validation.
     *
     * Rules enforced:
     *   - product must be active and belong to selected shop
     *   - price fetched from DB (never trusted from browser)
     *   - quantity must not exceed available stock
     *   - all items must be from the same shop (ca_selected_shop_id)
     *
     * @return array{success: bool, message: string, cart_count: int,
     *               item?: array, available?: float}
     */
    public function addItem(int $shopId, int $productId, float $qty): array
    {
        if ($qty <= 0) {
            return ['success' => false, 'message' => 'Quantity must be greater than zero.', 'cart_count' => $this->getItemCount()];
        }

        // Fetch authoritative data from DB
        $row = $this->fetchProductInventory($shopId, $productId);
        if (!$row) {
            return ['success' => false, 'message' => 'This product is not available at the selected shop.', 'cart_count' => $this->getItemCount()];
        }

        if (!$row['is_active'] || $row['product_status'] !== 'active' || $row['deleted_at']) {
            return ['success' => false, 'message' => 'This product is currently unavailable.', 'cart_count' => $this->getItemCount()];
        }

        $available = (float)$row['available_quantity'];

        // Check existing qty in cart for this product
        $cart    = $this->getSessionCart();
        $existing = $this->findItem($cart, $productId, $shopId);
        $alreadyInCart = $existing ? (float)$existing['quantity'] : 0;
        $totalRequested = $alreadyInCart + $qty;

        if ($totalRequested > $available) {
            $unit = $row['unit_abbr'] ?? '';
            return [
                'success'   => false,
                'message'   => "Only {$available}" . ($unit ? " {$unit}" : '') . " available at this shop.",
                'available' => $available,
                'cart_count'=> $this->getItemCount(),
            ];
        }

        // Build/update cart item
        $price = (float)$row['unit_price'];

        if ($existing) {
            // Update existing item
            $cart = $this->updateItemInCart($cart, $productId, $shopId, $totalRequested, $price, $available);
        } else {
            // Add new item
            $cart[] = $this->buildCartItem($row, $shopId, $productId, $qty, $price, $available);
        }

        $this->saveCart($cart);

        return [
            'success'     => true,
            'message'     => $row['product_name'] . ' added to cart.',
            'cart_count'  => $this->getItemCount(),
            'item'        => [
                'product_id'   => $productId,
                'product_name' => $row['product_name'],
                'price'        => $price,
                'unit'         => $row['unit_abbr'] ?? '',
                'quantity'     => $totalRequested,
                'available'    => $available,
                'shop_id'      => $shopId,
                'shop_name'    => $row['shop_name'] ?? '',
                'subtotal'     => round($totalRequested * $price, 2),
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    // UPDATE ITEM QUANTITY
    // ──────────────────────────────────────────────────────

    /**
     * Update the quantity of a cart item — server-side validated.
     * Pass qty=0 to remove the item.
     *
     * @return array{success: bool, message: string, cart_count: int,
     *               item?: array, available?: float}
     */
    public function updateItem(int $shopId, int $productId, float $qty): array
    {
        if ($qty < 0) {
            return ['success' => false, 'message' => 'Invalid quantity.', 'cart_count' => $this->getItemCount()];
        }

        $cart = $this->getSessionCart();

        if ($qty === 0.0) {
            $cart = $this->removeFromCart($cart, $productId, $shopId);
            $this->saveCart($cart);
            return ['success' => true, 'message' => 'Item removed.', 'cart_count' => $this->getItemCount()];
        }

        // Re-validate stock against DB
        $row = $this->fetchProductInventory($shopId, $productId);
        if (!$row) {
            // Product no longer available — remove it
            $cart = $this->removeFromCart($cart, $productId, $shopId);
            $this->saveCart($cart);
            return ['success' => false, 'message' => 'Product is no longer available at this shop. It has been removed from your cart.', 'cart_count' => $this->getItemCount(), 'removed' => true];
        }

        $available = (float)$row['available_quantity'];
        $price     = (float)$row['unit_price'];

        if ($qty > $available) {
            $unit = $row['unit_abbr'] ?? '';
            // Cap at available
            $qty = $available;
            $cart = $this->updateItemInCart($cart, $productId, $shopId, $qty, $price, $available);
            $this->saveCart($cart);
            return [
                'success'    => false,
                'message'    => "Only {$available}" . ($unit ? " {$unit}" : '') . " currently available. Quantity adjusted.",
                'available'  => $available,
                'quantity'   => $qty,
                'cart_count' => $this->getItemCount(),
                'adjusted'   => true,
            ];
        }

        $cart = $this->updateItemInCart($cart, $productId, $shopId, $qty, $price, $available);
        $this->saveCart($cart);

        return [
            'success'    => true,
            'message'    => 'Cart updated.',
            'cart_count' => $this->getItemCount(),
            'item'       => [
                'product_id' => $productId,
                'quantity'   => $qty,
                'price'      => $price,
                'subtotal'   => round($qty * $price, 2),
                'available'  => $available,
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    // REMOVE ITEM
    // ──────────────────────────────────────────────────────

    /**
     * Remove an item from the cart.
     */
    public function removeItem(int $productId, int $shopId): array
    {
        $cart = $this->getSessionCart();
        $cart = $this->removeFromCart($cart, $productId, $shopId);
        $this->saveCart($cart);
        return ['success' => true, 'message' => 'Item removed from cart.', 'cart_count' => $this->getItemCount()];
    }

    // ──────────────────────────────────────────────────────
    // CLEAR CART
    // ──────────────────────────────────────────────────────

    public function clearCart(): void
    {
        $this->saveCart([]);
    }

    // ──────────────────────────────────────────────────────
    // RECHECK (full cart stock + price revalidation)
    // ──────────────────────────────────────────────────────

    /**
     * Recheck all cart items against current DB stock and prices.
     * Called on every cart page load.
     *
     * Returns the validated cart with per-item warnings.
     * Automatically adjusts quantities that exceed new stock levels.
     * Marks items that are no longer available.
     *
     * @return array{
     *   cart: array,
     *   warnings: array,
     *   subtotal: float,
     *   item_count: int,
     *   distinct_count: int,
     *   has_issues: bool,
     *   shop_id: int,
     *   shop_name: string,
     * }
     */
    public function recheckCart(int $shopId): array
    {
        $cart     = $this->getSessionCart();
        $warnings = [];
        $updated  = false;

        foreach ($cart as $idx => &$item) {
            $pid = (int)$item['product_id'];
            $row = $this->fetchProductInventory($shopId, $pid);

            if (!$row || !$row['is_active'] || $row['product_status'] !== 'active' || $row['deleted_at']) {
                $warnings[] = [
                    'product_id'   => $pid,
                    'product_name' => $item['product_name'],
                    'type'         => 'unavailable',
                    'message'      => $item['product_name'] . ' is no longer available at this shop.',
                ];
                $item['status']  = 'unavailable';
                $item['warning'] = 'No longer available at this shop.';
                $updated = true;
                continue;
            }

            $available = (float)$row['available_quantity'];
            $newPrice  = (float)$row['unit_price'];

            // Update authoritative price (never trust what was stored from a previous session)
            $oldPrice = (float)$item['price'];
            if (abs($oldPrice - $newPrice) > 0.001) {
                $warnings[] = [
                    'product_id'   => $pid,
                    'product_name' => $item['product_name'],
                    'type'         => 'price_changed',
                    'message'      => $item['product_name'] . ' price changed: ETB ' . number_format($oldPrice, 2) . ' → ETB ' . number_format($newPrice, 2) . '.',
                    'old_price'    => $oldPrice,
                    'new_price'    => $newPrice,
                ];
                $item['price'] = $newPrice;
                $updated = true;
            }

            // Update available qty
            $item['available'] = $available;

            if ($available <= 0) {
                $item['status']  = 'out_of_stock';
                $item['warning'] = 'Out of stock.';
                $warnings[] = [
                    'product_id'   => $pid,
                    'product_name' => $item['product_name'],
                    'type'         => 'out_of_stock',
                    'message'      => $item['product_name'] . ' is out of stock.',
                ];
                $updated = true;
            } elseif ((float)$item['quantity'] > $available) {
                $oldQty = $item['quantity'];
                $item['quantity'] = $available;
                $item['status']   = 'adjusted';
                $unit = $item['unit'] ?? '';
                $item['warning']  = "Only {$available}" . ($unit ? " {$unit}" : '') . " available. Quantity adjusted from {$oldQty}.";
                $warnings[] = [
                    'product_id'   => $pid,
                    'product_name' => $item['product_name'],
                    'type'         => 'qty_adjusted',
                    'message'      => "Only {$available}" . ($unit ? " {$unit}" : '') . " available for " . $item['product_name'] . ". Quantity adjusted.",
                    'old_qty'      => $oldQty,
                    'new_qty'      => $available,
                ];
                $updated = true;
            } else {
                $item['status']  = 'ok';
                $item['warning'] = null;
            }

            // Recalculate subtotal with authoritative price
            $item['subtotal'] = round((float)$item['quantity'] * (float)$item['price'], 2);
        }
        unset($item);

        if ($updated) {
            $this->saveCart($cart);
        }

        $subtotal = 0.0;
        $itemCount = 0;
        foreach ($cart as $item) {
            if (in_array($item['status'] ?? 'ok', ['unavailable', 'out_of_stock'], true)) {
                continue; // don't include in totals
            }
            $subtotal  += (float)$item['subtotal'];
            $itemCount += (int)$item['quantity'];
        }

        $shopName = $_SESSION['ca_selected_shop_name'] ?? '';

        return [
            'cart'           => $cart,
            'warnings'       => $warnings,
            'subtotal'       => round($subtotal, 2),
            'item_count'     => $itemCount,
            'distinct_count' => count($cart),
            'has_issues'     => !empty($warnings),
            'shop_id'        => $shopId,
            'shop_name'      => $shopName,
        ];
    }

    // ──────────────────────────────────────────────────────
    // CHANGE SHOP
    // ──────────────────────────────────────────────────────

    /**
     * Check which current cart items are unavailable at a new shop.
     * Does NOT change the cart — just reports conflicts.
     *
     * @return array{unavailable: array, available: array}
     */
    public function checkShopChange(int $newShopId): array
    {
        $cart        = $this->getSessionCart();
        $unavailable = [];
        $available   = [];

        foreach ($cart as $item) {
            $pid = (int)$item['product_id'];
            $row = $this->fetchProductInventory($newShopId, $pid);
            if (!$row || (float)$row['available_quantity'] <= 0) {
                $unavailable[] = [
                    'product_id'   => $pid,
                    'product_name' => $item['product_name'],
                    'quantity'     => $item['quantity'],
                    'unit'         => $item['unit'] ?? '',
                ];
            } else {
                $available[] = $item;
            }
        }

        return ['unavailable' => $unavailable, 'available' => $available];
    }

    /**
     * Apply shop change — remove unavailable items, update session shop.
     * Called after user confirms the shop change.
     */
    public function applyShopChange(int $newShopId, string $newShopName, bool $removeUnavailable = true): array
    {
        if ($removeUnavailable) {
            $cart    = $this->getSessionCart();
            $newCart = [];

            foreach ($cart as $item) {
                $pid = (int)$item['product_id'];
                $row = $this->fetchProductInventory($newShopId, $pid);
                if ($row && (float)$row['available_quantity'] > 0) {
                    // Re-validate price for new shop
                    $item['price']     = (float)$row['unit_price'];
                    $item['available'] = (float)$row['available_quantity'];
                    $item['shop_id']   = $newShopId;
                    $item['shop_name'] = $newShopName;
                    $item['subtotal']  = round((float)$item['quantity'] * (float)$item['price'], 2);
                    $item['status']    = 'ok';
                    $item['warning']   = null;
                    $newCart[]         = $item;
                }
            }

            $this->saveCart($newCart);
        } else {
            // Just update shop references in cart items
            $cart = $this->getSessionCart();
            foreach ($cart as &$item) {
                $item['shop_id']   = $newShopId;
                $item['shop_name'] = $newShopName;
            }
            unset($item);
            $this->saveCart($cart);
        }

        $_SESSION['ca_selected_shop_id']   = $newShopId;
        $_SESSION['ca_selected_shop_name'] = $newShopName;

        return ['success' => true, 'cart_count' => $this->getItemCount()];
    }

    // ──────────────────────────────────────────────────────
    // CART SUMMARY (for checkout preparation)
    // ──────────────────────────────────────────────────────

    /**
     * Get a validated cart summary (for checkout use in Phase 5).
     * Returns null if cart is empty or has blocking issues.
     *
     * @return array|null
     */
    public function getValidatedSummary(int $shopId): ?array
    {
        $recheck = $this->recheckCart($shopId);
        if (empty($recheck['cart'])) {
            return null;
        }
        // Check for blocking issues
        foreach ($recheck['cart'] as $item) {
            if (in_array($item['status'] ?? 'ok', ['unavailable', 'out_of_stock'], true)) {
                return null; // has blocking items
            }
        }
        return $recheck;
    }

    // ──────────────────────────────────────────────────────
    // HELPERS — DB Queries
    // ──────────────────────────────────────────────────────

    /**
     * Fetch product + shop inventory + shop name in one query.
     * Returns null if product not found or not in this shop.
     */
    private function fetchProductInventory(int $shopId, int $productId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT
                p.id,
                p.name              AS product_name,
                p.unit_price,
                p.is_active,
                p.product_status,
                p.deleted_at,
                p.primary_image,
                p.description,
                p.is_perishable,
                c.name              AS category_name,
                u.abbreviation      AS unit_abbr,
                u.name              AS unit_name,
                si.available_quantity,
                si.current_quantity,
                si.expiry_date,
                si.min_stock_level  AS shop_min_stock_level,
                s.id                AS shop_id,
                COALESCE(s.shop_name, s.name) AS shop_name
             FROM products p
             LEFT JOIN categories c       ON c.id = p.category_id
             LEFT JOIN units_of_measure u ON u.id = p.unit_id
             LEFT JOIN shop_inventory si  ON si.product_id = p.id AND si.shop_id = :sid
             LEFT JOIN shops s            ON s.id = :sid2
             WHERE p.id = :pid
             LIMIT 1",
            ['sid' => $shopId, 'sid2' => $shopId, 'pid' => $productId]
        );

        return $row ?: null;
    }

    // ──────────────────────────────────────────────────────
    // HELPERS — Cart manipulation
    // ──────────────────────────────────────────────────────

    private function buildCartItem(array $row, int $shopId, int $productId, float $qty, float $price, float $available): array
    {
        return [
            'product_id'   => $productId,
            'product_name' => $row['product_name'],
            'price'        => $price,
            'unit'         => $row['unit_abbr'] ?? '',
            'unit_name'    => $row['unit_name'] ?? '',
            'quantity'     => $qty,
            'available'    => $available,
            'subtotal'     => round($qty * $price, 2),
            'shop_id'      => $shopId,
            'shop_name'    => $row['shop_name'] ?? '',
            'primary_image'=> $row['primary_image'] ?? null,
            'category_name'=> $row['category_name'] ?? '',
            'status'       => 'ok',
            'warning'      => null,
            'added_at'     => time(),
        ];
    }

    private function findItem(array $cart, int $productId, int $shopId): ?array
    {
        foreach ($cart as $item) {
            if ((int)$item['product_id'] === $productId && (int)$item['shop_id'] === $shopId) {
                return $item;
            }
        }
        return null;
    }

    private function updateItemInCart(array $cart, int $productId, int $shopId, float $qty, float $price, float $available): array
    {
        foreach ($cart as &$item) {
            if ((int)$item['product_id'] === $productId && (int)$item['shop_id'] === $shopId) {
                $item['quantity']  = $qty;
                $item['price']     = $price;
                $item['available'] = $available;
                $item['subtotal']  = round($qty * $price, 2);
                $item['status']    = 'ok';
                $item['warning']   = null;
                return $cart;
            }
        }
        unset($item);
        return $cart;
    }

    private function removeFromCart(array $cart, int $productId, int $shopId): array
    {
        return array_values(array_filter($cart, function ($item) use ($productId, $shopId) {
            return !((int)$item['product_id'] === $productId && (int)$item['shop_id'] === $shopId);
        }));
    }

    private function saveCart(array $cart): void
    {
        $_SESSION[self::CART_SESSION_KEY] = array_values($cart);
    }
}
