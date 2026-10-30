<?php
/**
 * =========================================================
 * AU-AgroSale — Customer ProductController
 * =========================================================
 * File    : modules/customer/controllers/ProductController.php
 * Routes  :
 *   GET  /customer/products              → index()
 *   GET  /customer/products/{id}         → show()
 *   GET  /customer/products/search       → search()
 *   POST /customer/cart/validate-stock   → validateStock()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Customer\Services\CustomerProductService;
use AAM\Customer\Services\FavoritesService;
use AAM\Customer\Services\ShopAvailabilityService;

class ProductController extends BaseController
{
    private CustomerProductService  $productService;
    private ShopAvailabilityService $shopService;
    private FavoritesService        $favService;

    public function __construct()
    {
        parent::__construct();
        $this->productService = new CustomerProductService();
        $this->shopService    = new ShopAvailabilityService();
        $this->favService     = new FavoritesService();
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/products
    // ──────────────────────────────────────────────────────

    public function index(): void
    {
        $shopId     = $this->resolveShopId();
        $page       = max(1, (int)$this->query('page', 1));
        $categoryId = (int)$this->query('category_id', 0);
        $sort       = $this->query('sort', '');
        $search     = trim((string)$this->query('q', ''));

        $availability = $this->query('availability', '');

        $filters = [
            'category_id'  => $categoryId ?: null,
            'sort'         => $sort,
            'search'       => $search,
            'availability' => $availability,
        ];

        // Products (shop-scoped if shop selected)
        if ($shopId) {
            $pagination  = $this->productService->getProductsByShop($shopId, $page, 16, $filters);
            $shop        = $this->shopService->getShop($shopId);
            // Compute availability counts from paginated data for shop mode
            // (use total counts via a lightweight aggregate)
            $availCounts = $this->productService->getAvailCountsByShop($shopId, $filters);
        } else {
            // No shop selected — show all products with aggregate availability
            $pagination  = $this->productService->getAllProducts($search, $availability, $page, 16);
            $shop        = null;
            $availCounts = $pagination['availCounts'] ?? ['in_stock' => 0, 'low_stock' => 0, 'out_of_stock' => 0];
        }

        // Categories for filter (shop-scoped)
        $categories = $this->productService->getCategories($shopId ?: null);

        // All shops for selector
        $shops = $this->shopService->getActiveShops();

        // Selected category info
        $selectedCategory = null;
        if ($categoryId) {
            foreach ($categories as $cat) {
                if ((int)$cat['id'] === $categoryId) {
                    $selectedCategory = $cat;
                    break;
                }
            }
        }

        // Favorited product IDs for the logged-in customer (empty for guests)
        $favoritedIds = [];
        $userId = SessionManager::userId();
        if ($userId) {
            $favoritedIds = $this->favService->getFavoritedIds((int)$userId);
        }

        $this->render('customer.products.index', [
            'pageTitle'        => 'Products' . ($shop ? ' — ' . ($shop['shop_name'] ?? $shop['name']) : '') . ' | AU-AgroSale',
            'pagination'       => $pagination,
            'products'         => $pagination['data'],
            'shop'             => $shop,
            'shopId'           => $shopId,
            'shopName'         => $shop ? ($shop['shop_name'] ?? $shop['name'] ?? '') : '',
            'shops'            => $shops,
            'categories'       => $categories,
            'selectedCategory' => $selectedCategory,
            'categoryId'       => $categoryId,
            'sort'             => $sort,
            'searchQuery'      => $search,
            'availability'     => $availability,
            'availCounts'      => $availCounts,
            'favoritedIds'     => $favoritedIds,
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/products/search
    // ──────────────────────────────────────────────────────

    public function search(): void
    {
        $query  = trim((string)$this->query('q', ''));
        $shopId = $this->resolveShopId();
        $page   = max(1, (int)$this->query('page', 1));

        $pagination = $this->productService->searchProducts($query, $shopId ?: null, $page, 16);
        $shop       = $shopId ? $this->shopService->getShop($shopId) : null;
        $shops      = $this->shopService->getActiveShops();
        $categories = $this->productService->getCategories($shopId ?: null);

        $this->render('customer.products.search', [
            'pageTitle'   => 'Search: ' . htmlspecialchars($query) . ' | AU-AgroSale',
            'pagination'  => $pagination,
            'products'    => $pagination['data'],
            'searchQuery' => $query,
            'shop'        => $shop,
            'shopId'      => $shopId,
            'shopName'    => $shop ? ($shop['shop_name'] ?? $shop['name'] ?? '') : '',
            'shops'       => $shops,
            'categories'  => $categories,
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/products/{id}
    // ──────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $productId = (int)$id;
        $shopId    = $this->resolveShopId();

        $product = $this->productService->getProductDetail($productId, $shopId ?: null);

        if (!$product) {
            http_response_code(404);
            $this->render('customer.products.index', [
                'pageTitle'        => 'Product Not Found | AU-AgroSale',
                'pagination'       => ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => 16, 'totalPages' => 0, 'from' => 0, 'to' => 0],
                'products'         => [],
                'shop'             => null,
                'shopId'           => $shopId,
                'shopName'         => '',
                'shops'            => $this->shopService->getActiveShops(),
                'categories'       => $this->productService->getCategories(),
                'selectedCategory' => null,
                'categoryId'       => 0,
                'sort'             => '',
                'searchQuery'      => '',
                'errorMessage'     => 'The product you are looking for does not exist or is no longer available.',
            ], 'customer');
            return;
        }

        $shop       = $shopId ? $this->shopService->getShop($shopId) : null;
        $shops      = $this->shopService->getActiveShops();

        // Related products (same category, same shop, excluding current)
        $related = [];
        if ($shopId && !empty($product['category_id'])) {
            $relPagination = $this->productService->getProductsByShop(
                $shopId, 1, 4,
                ['category_id' => $product['category_id']]
            );
            $related = array_filter(
                $relPagination['data'],
                fn($p) => (int)$p['id'] !== $productId
            );
            $related = array_values(array_slice($related, 0, 4));
        }

        // Is this product favorited by the logged-in customer?
        $isFavorited = false;
        $userId = SessionManager::userId();
        if ($userId) {
            $isFavorited = $this->favService->isFavorited((int)$userId, $productId);
        }

        $this->render('customer.products.show', [
            'pageTitle'    => ($product['product_name'] ?? 'Product') . ' | AU-AgroSale',
            'product'      => $product,
            'shop'         => $shop,
            'shopId'       => $shopId,
            'shopName'     => $shop ? ($shop['shop_name'] ?? $shop['name'] ?? '') : '',
            'shops'        => $shops,
            'related'      => $related,
            'isFavorited'  => $isFavorited,
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /api/customer/search (AJAX suggestions)
    // ──────────────────────────────────────────────────────

    public function apiSearch(): void
    {
        $query  = trim((string)$this->query('q', ''));
        $shopId = (int)$this->query('shop_id', 0);

        // Also check session if no query param
        if (!$shopId) {
            $shopId = (int)($_SESSION['ca_selected_shop_id'] ?? 0);
        }

        if (strlen($query) < 2) {
            $this->jsonSuccess([], 'OK');
            return;
        }

        $results = $this->productService->searchSuggestions($query, $shopId ?: null, 8);
        $this->jsonSuccess($results, 'OK');
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/cart/validate-stock (AJAX)
    // ──────────────────────────────────────────────────────

    public function validateStock(): void
    {
        $shopId    = (int)$this->input('shop_id', 0);
        $productId = (int)$this->input('product_id', 0);
        $qty       = (float)$this->input('quantity', 1);

        if (!$shopId || !$productId || $qty <= 0) {
            $this->jsonError('Invalid request parameters.', 400);
            return;
        }

        $result = $this->productService->validateStock($shopId, $productId, $qty);

        if ($result['valid']) {
            $this->jsonSuccess(['available' => $result['available']], 'Stock available.');
        } else {
            $this->jsonError($result['message'], 422, ['available' => $result['available']]);
        }
    }

    // ──────────────────────────────────────────────────────
    // HELPER — resolve selected shop from query / session
    // ──────────────────────────────────────────────────────

    private function resolveShopId(): int
    {
        // Priority: URL param > session
        $fromQuery   = (int)$this->query('shop_id', 0);
        $fromSession = (int)($_SESSION['ca_selected_shop_id'] ?? 0);

        $shopId = $fromQuery ?: $fromSession;

        // Persist to session if set via URL
        if ($fromQuery > 0) {
            $_SESSION['ca_selected_shop_id']   = $fromQuery;
            $_SESSION['ca_selected_shop_name'] = '';  // will be resolved on next page load
        }

        return $shopId;
    }
}
