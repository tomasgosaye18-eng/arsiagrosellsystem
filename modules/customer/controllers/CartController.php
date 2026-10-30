<?php
/**
 * =========================================================
 * AU-AgroSale — Customer CartController
 * =========================================================
 * File    : modules/customer/controllers/CartController.php
 * Phase 4 : Full session-based cart with server-side stock
 *           validation, stock recheck on every page load,
 *           change-shop workflow, and smooth AJAX updates.
 *
 * Routes:
 *   GET  /customer/cart                  → index()
 *   POST /customer/cart/add              → add()
 *   POST /customer/cart/update           → update()
 *   POST /customer/cart/remove           → remove()
 *   POST /customer/cart/clear            → clear()
 *   POST /customer/cart/recheck          → recheck()
 *   POST /customer/cart/check-shop       → checkShop()
 *   POST /customer/cart/change-shop      → changeShop()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Controllers;

use AAM\Core\BaseController;
use AAM\Customer\Services\CartService;
use AAM\Customer\Services\CustomerProductService;
use AAM\Customer\Services\ShopAvailabilityService;

class CartController extends BaseController
{
    private CartService            $cartService;
    private CustomerProductService $productService;
    private ShopAvailabilityService $shopService;

    public function __construct()
    {
        parent::__construct();
        $this->cartService    = new CartService();
        $this->productService = new CustomerProductService();
        $this->shopService    = new ShopAvailabilityService();
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/cart — full page render with server-side
    // recheck of all items before display
    // ──────────────────────────────────────────────────────
    public function index(): void
    {
        $shopId   = (int)($_SESSION['ca_selected_shop_id']   ?? 0);
        $shopName = (string)($_SESSION['ca_selected_shop_name'] ?? '');
        $shops    = $this->shopService->getActiveShops();
        $shop     = $shopId ? $this->shopService->getShop($shopId) : null;

        // Server-side recheck on every cart page load
        $cartData = $shopId
            ? $this->cartService->recheckCart($shopId)
            : [
                'cart'           => $this->cartService->getSessionCart(),
                'warnings'       => [],
                'subtotal'       => 0.0,
                'item_count'     => $this->cartService->getItemCount(),
                'distinct_count' => $this->cartService->getDistinctCount(),
                'has_issues'     => false,
                'shop_id'        => 0,
                'shop_name'      => '',
            ];

        // Recommended products — top 8 products available at selected shop
        // excluding products already in the cart
        $recommended = [];
        if ($shopId) {
            $cartProductIds = array_map(
                fn($item) => (int)$item['product_id'],
                $cartData['cart']
            );
            $recommended = $this->productService->getRecommendedProducts($shopId, $cartProductIds, 8);
        }

        $this->render('customer.cart.index', [
            'pageTitle'      => 'Your Cart | AU-AgroSale',
            'shopId'         => $shopId,
            'shopName'       => $shopName,
            'shop'           => $shop,
            'shops'          => $shops,
            'cart'           => $cartData['cart'],
            'warnings'       => $cartData['warnings'],
            'subtotal'       => $cartData['subtotal'],
            'itemCount'      => $cartData['item_count'],
            'distinctCount'  => $cartData['distinct_count'],
            'hasIssues'      => $cartData['has_issues'],
            'recommended'    => $recommended,
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/cart/add (AJAX)
    // Validates server-side before confirming add.
    // ──────────────────────────────────────────────────────
    public function add(): void
    {
        $productId = (int)$this->input('product_id', 0);
        $shopId    = (int)$this->input('shop_id', 0);
        $qty       = max(0.001, (float)$this->input('quantity', 1));

        if (!$productId || !$shopId) {
            $this->jsonError('Product and shop are required.', 400);
            return;
        }

        // Enforce single-shop rule
        $cart = $this->cartService->getSessionCart();
        if (!empty($cart)) {
            $firstShopId = (int)($cart[0]['shop_id'] ?? 0);
            if ($firstShopId && $firstShopId !== $shopId) {
                $this->jsonError(
                    'Your cart contains items from a different shop. Clear your cart or stay with the current shop.',
                    422
                );
                return;
            }
        }

        $result = $this->cartService->addItem($shopId, $productId, $qty);

        if ($result['success']) {
            // Persist the selected shop to the PHP session so the cart page
            // always renders with the correct shop — even if the separate
            // /customer/shops/select AJAX call was never made (e.g. the user
            // clicked "Add to cart" directly without going through the shop
            // selector page).
            if (empty($_SESSION['ca_selected_shop_id']) || (int)$_SESSION['ca_selected_shop_id'] !== $shopId) {
                $shopInfo = $this->shopService->getShop($shopId);
                if ($shopInfo) {
                    $_SESSION['ca_selected_shop_id']   = $shopId;
                    $_SESSION['ca_selected_shop_name'] = $shopInfo['shop_name'] ?? $shopInfo['name'] ?? '';
                }
            }

            $this->jsonSuccess($result, $result['message']);
        } else {
            $this->jsonError($result['message'], 422, array_filter([
                'available' => $result['available'] ?? null,
            ]));
        }
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/cart/update (AJAX — qty change)
    // ──────────────────────────────────────────────────────
    public function update(): void
    {
        $productId = (int)$this->input('product_id', 0);
        $shopId    = (int)$this->input('shop_id', 0);
        $qty       = max(0, (float)$this->input('quantity', 0));

        if (!$productId || !$shopId) {
            $this->jsonError('Invalid request.', 400);
            return;
        }

        $result = $this->cartService->updateItem($shopId, $productId, $qty);

        if ($result['success'] || ($result['adjusted'] ?? false)) {
            $this->jsonSuccess($result, $result['message']);
        } else {
            $this->jsonError($result['message'], 422, array_filter([
                'available' => $result['available'] ?? null,
                'removed'   => $result['removed'] ?? null,
            ]));
        }
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/cart/remove (AJAX)
    // ──────────────────────────────────────────────────────
    public function remove(): void
    {
        $productId = (int)$this->input('product_id', 0);
        $shopId    = (int)$this->input('shop_id', $_SESSION['ca_selected_shop_id'] ?? 0);

        if (!$productId) {
            $this->jsonError('Invalid product.', 400);
            return;
        }

        $result = $this->cartService->removeItem($productId, $shopId);
        $this->jsonSuccess($result, $result['message']);
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/cart/clear (AJAX)
    // ──────────────────────────────────────────────────────
    public function clear(): void
    {
        $this->cartService->clearCart();
        $this->jsonSuccess(['cart_count' => 0], 'Cart cleared.');
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/cart/recheck (AJAX)
    // Called on cart page load — re-validates all items.
    // ──────────────────────────────────────────────────────
    public function recheck(): void
    {
        $shopId = (int)($_SESSION['ca_selected_shop_id'] ?? 0);

        if (!$shopId) {
            $this->jsonSuccess([
                'cart'           => [],
                'warnings'       => [],
                'subtotal'       => 0,
                'item_count'     => 0,
                'distinct_count' => 0,
                'has_issues'     => false,
            ], 'No shop selected.');
            return;
        }

        $result = $this->cartService->recheckCart($shopId);
        $this->jsonSuccess($result, $result['has_issues'] ? 'Cart has issues.' : 'Cart is up to date.');
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/cart/check-shop (AJAX)
    // Preview what would happen if shop is changed.
    // ──────────────────────────────────────────────────────
    public function checkShop(): void
    {
        $newShopId = (int)$this->input('shop_id', 0);
        if (!$newShopId) {
            $this->jsonError('Invalid shop.', 400);
            return;
        }

        $shop = $this->shopService->getShop($newShopId);
        if (!$shop) {
            $this->jsonError('Shop not found or inactive.', 404);
            return;
        }

        $result = $this->cartService->checkShopChange($newShopId);

        $this->jsonSuccess([
            'shop_id'          => $newShopId,
            'shop_name'        => $shop['shop_name'] ?? $shop['name'] ?? '',
            'unavailable'      => $result['unavailable'],
            'available'        => $result['available'],
            'unavailable_count'=> count($result['unavailable']),
            'available_count'  => count($result['available']),
            'has_conflict'     => !empty($result['unavailable']),
        ], 'Shop conflict check complete.');
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/cart/change-shop (AJAX)
    // Applies shop change — removes unavailable items.
    // ──────────────────────────────────────────────────────
    public function changeShop(): void
    {
        $newShopId = (int)$this->input('shop_id', 0);
        if (!$newShopId) {
            $this->jsonError('Invalid shop.', 400);
            return;
        }

        $shop = $this->shopService->getShop($newShopId);
        if (!$shop) {
            $this->jsonError('Shop not found or inactive.', 404);
            return;
        }

        $newShopName = $shop['shop_name'] ?? $shop['name'] ?? '';
        $result      = $this->cartService->applyShopChange($newShopId, $newShopName, true);

        $this->jsonSuccess([
            'shop_id'    => $newShopId,
            'shop_name'  => $newShopName,
            'cart_count' => $result['cart_count'],
        ], 'Shop changed. Unavailable items removed.');
    }
}
