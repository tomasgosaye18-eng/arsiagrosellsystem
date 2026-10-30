<?php
/**
 * =========================================================
 * AU-AgroSale — Customer ShopController
 * =========================================================
 * File    : modules/customer/controllers/ShopController.php
 * Routes  :
 *   GET  /customer/shops                       → index()
 *   GET  /customer/shops/{id}/availability     → availability()
 *   POST /customer/shops/select                → select()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Controllers;

use AAM\Core\BaseController;
use AAM\Customer\Services\ShopAvailabilityService;
use AAM\Customer\Services\CustomerProductService;

class ShopController extends BaseController
{
    private ShopAvailabilityService $shopService;
    private CustomerProductService  $productService;

    public function __construct()
    {
        parent::__construct();
        $this->shopService    = new ShopAvailabilityService();
        $this->productService = new CustomerProductService();
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/shops
    // ──────────────────────────────────────────────────────

    public function index(): void
    {
        $shops = $this->shopService->getShopsWithAvailability();

        $this->render('customer.shops.availability', [
            'pageTitle' => 'Find a Shop | AU-AgroSale',
            'shops'     => $shops,
            'shopId'    => (int)($_SESSION['ca_selected_shop_id'] ?? 0),
            'viewMode'  => 'all_shops',
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/shops/{id}/availability
    // ──────────────────────────────────────────────────────

    public function availability(string $id): void
    {
        $shopId = (int)$id;
        $shop   = $this->shopService->getShop($shopId);

        if (!$shop) {
            $this->redirectWith('customer/shops', 'warning', 'Shop not found or is not currently active.');
            return;
        }

        // Persist shop selection
        $_SESSION['ca_selected_shop_id']   = $shopId;
        $_SESSION['ca_selected_shop_name'] = $shop['shop_name'] ?? $shop['name'];

        // All shops for comparison
        $allShops = $this->shopService->getActiveShops();

        // First page of products at this shop (for preview)
        $pagination = $this->productService->getProductsByShop($shopId, 1, 12);
        $categories = $this->productService->getCategories($shopId);

        $this->render('customer.shops.availability', [
            'pageTitle'  => ($shop['shop_name'] ?? $shop['name']) . ' — Availability | AU-AgroSale',
            'shop'       => $shop,
            'shopId'     => $shopId,
            'shops'      => $allShops,
            'pagination' => $pagination,
            'products'   => $pagination['data'],
            'categories' => $categories,
            'viewMode'   => 'single_shop',
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/shops/select (AJAX)
    // Persists selected shop to session + validates cart
    // ──────────────────────────────────────────────────────

    public function select(): void
    {
        $newShopId = (int)$this->input('shop_id', 0);

        if (!$newShopId) {
            $this->jsonError('Invalid shop ID.', 400);
            return;
        }

        $shop = $this->shopService->getShop($newShopId);

        if (!$shop) {
            $this->jsonError('Shop not found or inactive.', 404);
            return;
        }

        $oldShopId = (int)($_SESSION['ca_selected_shop_id'] ?? 0);
        $_SESSION['ca_selected_shop_id']   = $newShopId;
        $_SESSION['ca_selected_shop_name'] = $shop['shop_name'] ?? $shop['name'];

        // If cart items exist and shop changed, check for unavailable items
        $cartItemsJson = $this->input('cart_items', '[]');
        $cartItems     = json_decode($cartItemsJson, true) ?: [];

        $unavailableIds = [];
        if ($oldShopId && $oldShopId !== $newShopId && !empty($cartItems)) {
            $unavailableIds = $this->shopService->getUnavailableProductsAtShop($cartItems, $newShopId);
        }

        $this->jsonSuccess([
            'shop_id'          => $newShopId,
            'shop_name'        => $shop['shop_name'] ?? $shop['name'],
            'shop_location'    => $shop['location'] ?? '',
            'product_count'    => $shop['product_count'] ?? 0,
            'unavailable_ids'  => $unavailableIds,
            'had_conflict'     => !empty($unavailableIds),
        ], 'Shop selected.');
    }
}
