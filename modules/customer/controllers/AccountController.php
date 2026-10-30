<?php
/**
 * =========================================================
 * AU-AgroSale — Customer AccountController
 * =========================================================
 * File    : modules/customer/controllers/AccountController.php
 *
 * Routes:
 *   GET /customer/account           → index()    — account overview / profile
 *   GET /customer/account/receipts  → receipts() — receipts list
 *   GET /customer/account/pickup    → pickup()   — pickup preferences
 *   GET /customer/account/security  → security() — security settings
 *   GET /customer/account/help      → help()     — help & support
 *   GET /customer/account/signout   → signout()  — clear session + redirect
 *
 * SECURITY: Customer identity is derived entirely from the server-side
 * PHP session. No customer ID is trusted from the URL or POST body.
 * The system uses session-scoped anonymous customer sessions — there is
 * no persistent customer login, only session-carried identity.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\Logger;
use AAM\Core\SessionManager;
use AAM\Auth\Models\UserModel;
use AAM\Customer\Services\CustomerOrderService;
use AAM\Customer\Services\FavoritesService;

class AccountController extends BaseController
{
    private CustomerOrderService $orderService;
    private FavoritesService     $favService;

    public function __construct()
    {
        parent::__construct();
        $this->orderService = new CustomerOrderService();
        $this->favService   = new FavoritesService();
    }

    // ──────────────────────────────────────────────────────
    // HELPER: get favorites count for the sidebar badge
    // ──────────────────────────────────────────────────────
    private function getFavoritesCount(): int
    {
        $userId = SessionManager::userId();
        return $userId ? $this->favService->getCount((int)$userId) : 0;
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/account — Account overview / Profile
    // ──────────────────────────────────────────────────────
    public function index(): void
    {
        $userId        = SessionManager::userId();
        $userData      = SessionManager::get(SESSION_USER_DATA, []);
        $sessionId     = session_id();

        // Prefer authenticated user data from session, fall back to ca_* keys
        $customerName  = SessionManager::get(SESSION_USER_NAME)
                         ?: (string)($_SESSION['ca_customer_name'] ?? '');
        $customerPhone = (string)($userData['phone'] ?? $_SESSION['ca_customer_phone'] ?? '');
        $customerEmail = (string)($userData['email'] ?? $_SESSION['ca_customer_email'] ?? '');

        // Fetch the single most-recent order for the overview card.
        $recentOrderResult = $this->orderService->getOrdersForSessionPaginated(
            $sessionId, 'all', 'newest', '', 1, 1, $userId
        );
        $recentOrder = $recentOrderResult['orders'][0] ?? null;

        $this->render('customer.account.index', [
            'pageTitle'      => 'My Account | AU-AgroSale',
            'section'        => 'overview',
            'customerName'   => $customerName,
            'customerPhone'  => $customerPhone,
            'customerEmail'  => $customerEmail,
            'recentOrder'    => $recentOrder,
            'favoritesCount' => $this->getFavoritesCount(),
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/account/profile — Update profile info
    // ──────────────────────────────────────────────────────
    public function updateProfile(): void
    {
        $this->verifyCsrf();

        $userId = SessionManager::userId();

        // Sanitise inputs
        $firstName = trim((string)$this->input('first_name', ''));
        $lastName  = trim((string)$this->input('last_name', ''));
        $phone     = trim((string)$this->input('phone', ''));

        // Basic validation
        if ($firstName === '' && $lastName === '') {
            $this->redirectWith('customer/account', FLASH_ERROR, 'Name cannot be empty.');
            return;
        }

        if ($phone !== '' && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
            $this->redirectWith('customer/account', FLASH_ERROR, 'Invalid phone number format.');
            return;
        }

        $fullName = trim($firstName . ' ' . $lastName);

        // Persist to DB if this is an authenticated (registered) user
        if ($userId) {
            try {
                $userModel = new UserModel();
                $updateData = [];
                if ($firstName !== '') $updateData['first_name'] = $firstName;
                if ($lastName !== '')  $updateData['last_name']  = $lastName;
                if ($phone !== '')     $updateData['phone']       = $phone;

                if (!empty($updateData)) {
                    $userModel->updateProfile($userId, $updateData);
                }

                // Refresh the session user data from DB so UI is up to date
                $freshUser = $userModel->findWithRole($userId);
                if ($freshUser) {
                    SessionManager::set(SESSION_USER_DATA, $freshUser);
                    SessionManager::set(SESSION_USER_NAME, trim(($freshUser['first_name'] ?? '') . ' ' . ($freshUser['last_name'] ?? '')));
                }
            } catch (\Throwable $e) {
                Logger::error('Customer profile update failed', ['error' => $e->getMessage(), 'user_id' => $userId]);
                $this->redirectWith('customer/account', FLASH_ERROR, 'Could not save changes. Please try again.');
                return;
            }
        }

        // Always update session keys so the change is immediately visible
        if ($fullName !== '') {
            $_SESSION['ca_customer_name'] = $fullName;
        }
        if ($phone !== '') {
            $_SESSION['ca_customer_phone'] = $phone;
        }

        $this->redirectWith('customer/account', FLASH_SUCCESS, 'Profile updated successfully.');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/account/orders/{id} — Order detail (with sidebar)
    // ──────────────────────────────────────────────────────
    public function orderShow(int $id): void
    {
        $sessionId    = session_id();
        $userId       = SessionManager::userId();
        $customerName = SessionManager::get(SESSION_USER_NAME)
                        ?: (string)($_SESSION['ca_customer_name'] ?? '');

        $order = $this->orderService->getOrderForSession($id, $sessionId, $userId);

        if (!$order) {
            $this->redirectWith('customer/account/orders', FLASH_ERROR, 'Order not found.');
            return;
        }

        $this->render('customer.account.index', [
            'pageTitle'    => 'Order ' . $order['order_reference'] . ' | AU-AgroSale',
            'section'      => 'order_detail',
            'customerName' => $customerName,
            'order'        => $order,
            'favoritesCount' => $this->getFavoritesCount(),
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/account/orders/{id}/receipt — Receipt (with sidebar)
    // ──────────────────────────────────────────────────────
    public function orderReceipt(int $id): void
    {
        $sessionId    = session_id();
        $userId       = SessionManager::userId();
        $customerName = SessionManager::get(SESSION_USER_NAME)
                        ?: (string)($_SESSION['ca_customer_name'] ?? '');

        $order = $this->orderService->getOrderForSession($id, $sessionId, $userId);

        if (!$order) {
            $this->redirectWith('customer/account/orders', FLASH_ERROR, 'Receipt not found.');
            return;
        }

        $pmType        = $order['payment_method_type'] ?? '';
        $isPaidStatus  = in_array($order['order_status'], ['paid', 'preparing', 'ready_for_pickup', 'completed'], true);
        $isCashPending = ($order['order_status'] === 'pending_payment' && $pmType === 'cash');

        if (!$isPaidStatus && !$isCashPending) {
            $this->redirectWith(
                'customer/account/orders/' . $id,
                FLASH_WARNING,
                'Receipt is only available after payment is confirmed.'
            );
            return;
        }

        $this->render('customer.account.index', [
            'pageTitle'    => 'Receipt — ' . $order['order_reference'] . ' | AU-AgroSale',
            'section'      => 'order_receipt',
            'customerName' => $customerName,
            'order'        => $order,
            'favoritesCount' => $this->getFavoritesCount(),
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/account/orders — My Orders (in-account view)
    // ──────────────────────────────────────────────────────
    public function orders(): void
    {
        $sessionId    = session_id();
        $userId       = SessionManager::userId();
        $customerName = SessionManager::get(SESSION_USER_NAME)
                        ?: (string)($_SESSION['ca_customer_name'] ?? '');

        $this->orderService->expireOldOrders();

        $filter  = trim((string)($_GET['filter']  ?? 'all'));
        $sort    = trim((string)($_GET['sort']    ?? 'newest'));
        $search  = trim((string)($_GET['search']  ?? ''));
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $result = $this->orderService->getOrdersForSessionPaginated(
            $sessionId, $filter, $sort, $search, $page, $perPage, $userId
        );

        $this->render('customer.account.index', [
            'pageTitle'     => 'My Orders | AU-AgroSale',
            'section'       => 'orders',
            'customerName'  => $customerName,
            'orders'        => $result['orders'],
            'ordersTotal'   => $result['total'],
            'ordersPage'    => $page,
            'ordersPerPage' => $perPage,
            'ordersFilter'  => $filter,
            'ordersSort'    => $sort,
            'ordersSearch'  => $search,
            'favoritesCount' => $this->getFavoritesCount(),
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/account/receipts — Receipts list
    //
    // Shows orders that have a receipt available:
    //   - Status: paid, preparing, ready_for_pickup, completed
    //   - OR: pending_payment with cash payment method
    //
    // SECURITY: Session-scoped — only this customer's orders.
    // ──────────────────────────────────────────────────────
    public function receipts(): void
    {
        $sessionId    = session_id();
        $userId       = SessionManager::userId();
        $customerName = SessionManager::get(SESSION_USER_NAME)
                        ?: (string)($_SESSION['ca_customer_name'] ?? '');

        $this->orderService->expireOldOrders();

        $result    = $this->orderService->getOrdersForSessionPaginated(
            $sessionId, 'all', 'newest', '', 1, 50, $userId
        );
        $allOrders = $result['orders'] ?? [];

        // Filter: receipt is available for paid+ orders and cash-pending orders
        $receiptOrders = array_values(array_filter($allOrders, static function (array $order): bool {
            $status = $order['order_status']      ?? '';
            $pmType = $order['payment_method_type'] ?? '';
            $isPaidOrHigher = in_array($status, ['paid', 'preparing', 'ready_for_pickup', 'completed'], true);
            $isCashPending  = ($status === 'pending_payment' && $pmType === 'cash');
            return $isPaidOrHigher || $isCashPending;
        }));

        $receiptTotal  = count($receiptOrders);
        $displayOrders = array_slice($receiptOrders, 0, 20);

        $this->render('customer.account.index', [
            'pageTitle'     => 'Receipts | AU-AgroSale',
            'section'       => 'receipts',
            'customerName'  => $customerName,
            'receiptOrders' => $displayOrders,
            'receiptTotal'  => $receiptTotal,
            'favoritesCount' => $this->getFavoritesCount(),
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/account/pickup — Pickup preferences
    // ──────────────────────────────────────────────────────
    public function pickup(): void
    {
        $customerName = SessionManager::get(SESSION_USER_NAME)
                        ?: (string)($_SESSION['ca_customer_name'] ?? '');

        // Preferred shop stored in session (same session key used by shop selector)
        $preferredShopId   = (int)($_SESSION['ca_selected_shop_id']   ?? 0);
        $preferredShopName = (string)($_SESSION['ca_selected_shop_name'] ?? '');

        // Fetch all active shops for the preference selector
        $shops = [];
        try {
            $db    = Database::getInstance();
            $shops = $db->fetchAll(
                "SELECT id, COALESCE(shop_name, name) AS shop_name, location
                 FROM shops
                 WHERE is_active = 1 AND deleted_at IS NULL
                 ORDER BY name ASC"
            );
        } catch (\Throwable) {
            $shops = [];
        }

        $this->render('customer.account.index', [
            'pageTitle'         => 'Pickup Preferences | AU-AgroSale',
            'section'           => 'pickup',
            'customerName'      => $customerName,
            'preferredShopId'   => $preferredShopId,
            'preferredShopName' => $preferredShopName,
            'shops'             => $shops,
            'favoritesCount'    => $this->getFavoritesCount(),
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/account/security — Security settings
    // ──────────────────────────────────────────────────────
    public function security(): void
    {
        $customerName = SessionManager::get(SESSION_USER_NAME)
                        ?: (string)($_SESSION['ca_customer_name'] ?? '');

        $this->render('customer.account.index', [
            'pageTitle'      => 'Security | AU-AgroSale',
            'section'        => 'security',
            'customerName'   => $customerName,
            'favoritesCount' => $this->getFavoritesCount(),
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/account/help — Help & Support
    // ──────────────────────────────────────────────────────
    public function help(): void
    {
        $customerName = SessionManager::get(SESSION_USER_NAME)
                        ?: (string)($_SESSION['ca_customer_name'] ?? '');

        $this->render('customer.account.index', [
            'pageTitle'      => 'Help & Support | AU-AgroSale',
            'section'        => 'help',
            'customerName'   => $customerName,
            'favoritesCount' => $this->getFavoritesCount(),
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/account/favorites — Favorites / Wishlist
    //
    // Renders the customer's saved products.
    // Always fetches live product data — never uses cached copies.
    // Products that are deleted/inactive are shown as unavailable.
    // ──────────────────────────────────────────────────────
    public function favorites(): void
    {
        $userId       = SessionManager::userId();
        $customerName = SessionManager::get(SESSION_USER_NAME)
                        ?: (string)($_SESSION['ca_customer_name'] ?? '');

        $shopId   = (int)($_SESSION['ca_selected_shop_id']   ?? 0);
        $shopName = (string)($_SESSION['ca_selected_shop_name'] ?? '');

        $favorites      = [];
        $favoritesCount = 0;

        if ($userId) {
            $favorites      = $this->favService->getFavoritesWithProducts((int)$userId, $shopId ?: null);
            $favoritesCount = count($favorites);
        }

        $this->render('customer.account.index', [
            'pageTitle'      => 'My Favorites | AU-AgroSale',
            'section'        => 'favorites',
            'customerName'   => $customerName,
            'favorites'      => $favorites,
            'favoritesCount' => $favoritesCount,
            'shopId'         => $shopId,
            'shopName'       => $shopName,
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/account/signout — Customer sign-out
    //
    // Properly destroys the authenticated session using the
    // same SessionManager used by internal management logout.
    // This clears SESSION_USER_ID and all auth keys, not just
    // the ca_* customer keys.
    // ──────────────────────────────────────────────────────
    public function signout(): void
    {
        // Clear customer-specific session keys first
        $keysToRemove = [
            'ca_customer_name',
            'ca_customer_phone',
            'ca_customer_email',
            'ca_selected_shop_id',
            'ca_selected_shop_name',
            'ca_cart',
            'ca_pending_payment',
        ];
        foreach ($keysToRemove as $key) {
            unset($_SESSION[$key]);
        }

        // Properly destroy the authenticated session
        // (clears SESSION_USER_ID, SESSION_USER_ROLE, etc.)
        SessionManager::logoutUser();
        SessionManager::destroy();

        // Redirect to Customer Home storefront
        $appUrl = rtrim((string)\AAM\Config\Application::get('url', ''), '/');
        header('Location: ' . $appUrl . '/customer');
        exit;
    }
}
