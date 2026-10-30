<?php
/**
 * =========================================================
 * AU-AgroSale — Customer OrderController
 * =========================================================
 * File    : modules/customer/controllers/OrderController.php
 * Step 9  : My Orders — full list, detail, receipt.
 *
 * SECURITY: Every query is scoped to the authenticated
 * customer session via customer_session_id.
 * No order ID from the URL is trusted without server-side
 * ownership verification.
 *
 * Routes:
 *   GET /customer/orders              → index()
 *   GET /customer/orders/{id}         → show()
 *   GET /customer/orders/{id}/receipt → receipt()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Controllers;

use AAM\Core\BaseController;
use AAM\Core\SessionManager;
use AAM\Customer\Services\CustomerOrderService;
use AAM\Customer\Services\ShopAvailabilityService;

class OrderController extends BaseController
{
    private CustomerOrderService    $orderService;
    private ShopAvailabilityService $shopService;

    public function __construct()
    {
        parent::__construct();
        $this->orderService = new CustomerOrderService();
        $this->shopService  = new ShopAvailabilityService();
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/orders — My Orders list
    // Supports: ?filter=, ?sort=, ?q=, ?page=
    // ──────────────────────────────────────────────────────
    public function index(): void
    {
        // SECURITY: Always derive customer identity from the server-side session.
        // Never trust a customer_id supplied by the frontend.
        $sessionId = session_id();
        $userId    = SessionManager::userId();   // null if not authenticated
        $shopId    = (int)($_SESSION['ca_selected_shop_id']   ?? 0);
        $shopName  = (string)($_SESSION['ca_selected_shop_name'] ?? '');

        // Expire stale unpaid orders silently before listing
        $this->orderService->expireOldOrders();

        // Input: filter, sort, search, page
        $filter  = $_GET['filter'] ?? 'all';
        $sort    = $_GET['sort']   ?? 'newest';
        $search  = trim((string)($_GET['q'] ?? ''));
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        // Validate filter against allowed values
        $allowedFilters = ['all', 'pending_payment', 'paid', 'preparing', 'ready_for_pickup', 'completed', 'cancelled', 'payment_failed'];
        if (!in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        // Validate sort
        $allowedSorts = ['newest', 'oldest', 'total_high', 'total_low'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'newest';
        }

        // Fetch orders for this user/session ONLY — ownership enforced server-side
        $result = $this->orderService->getOrdersForSessionPaginated(
            $sessionId, $filter, $sort, $search, $page, $perPage, $userId
        );

        $this->render('customer.orders.index', [
            'pageTitle'  => 'My Orders | AU-AgroSale',
            'orders'     => $result['orders'],
            'total'      => $result['total'],
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($result['total'] / $perPage),
            'filter'     => $filter,
            'sort'       => $sort,
            'search'     => $search,
            'shopId'     => $shopId,
            'shopName'   => $shopName,
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/orders/{id} — Order detail
    //
    // SECURITY: Order is only returned when customer_session_id
    // matches the server session. If the order belongs to another
    // customer, getOrderForSession() returns null and we show a
    // generic "Order not found" — no information is leaked.
    // ──────────────────────────────────────────────────────
    public function show(int $id): void
    {
        // SECURITY: Ownership check uses user_id (authenticated) OR session_id
        $sessionId = session_id();
        $userId    = SessionManager::userId();

        $order = $this->orderService->getOrderForSession($id, $sessionId, $userId);

        if (!$order) {
            $this->redirectWith('customer/orders', FLASH_ERROR, 'Order not found.');
            return;
        }

        $this->render('customer.orders.show', [
            'pageTitle' => 'Order ' . $order['order_reference'] . ' | AU-AgroSale',
            'order'     => $order,
        ], 'customer');
    }

    public function receipt(int $id): void
    {
        // SECURITY: Ownership check uses user_id (authenticated) OR session_id
        $sessionId = session_id();
        $userId    = SessionManager::userId();

        $order = $this->orderService->getOrderForSession($id, $sessionId, $userId);

        if (!$order) {
            $this->redirectWith('customer/orders', FLASH_ERROR, 'Receipt not found.');
            return;
        }

        $pmType        = $order['payment_method_type'] ?? '';
        $isPaidStatus  = in_array($order['order_status'], ['paid', 'preparing', 'ready_for_pickup', 'completed'], true);
        $isCashPending = ($order['order_status'] === 'pending_payment' && $pmType === 'cash');

        if (!$isPaidStatus && !$isCashPending) {
            $this->redirectWith(
                'customer/orders/' . $id,
                FLASH_WARNING,
                'Receipt is only available after payment is confirmed.'
            );
            return;
        }

        $this->render('customer.orders.receipt', [
            'pageTitle' => 'Receipt — ' . $order['order_reference'] . ' | AU-AgroSale',
            'order'     => $order,
        ], 'customer');
    }
}
