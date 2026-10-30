<?php
/**
 * =========================================================
 * AU-AgroSale — Customer CheckoutController
 * =========================================================
 * File    : modules/customer/controllers/CheckoutController.php
 * Phase 5 : Full checkout → payment → order flow.
 *
 * Routes:
 *   GET  /customer/checkout              → index()
 *   POST /customer/checkout/place        → place()
 *   GET  /customer/checkout/payment/{id} → payment()
 *   POST /customer/checkout/verify       → verify()    (AJAX poll)
 *   GET  /customer/checkout/success/{ref}→ success()
 *   POST /customer/checkout/cancel       → cancel()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Logger;
use AAM\Core\SessionManager;
use AAM\Customer\Services\CartService;
use AAM\Customer\Services\CheckoutService;
use AAM\Customer\Services\CustomerOrderService;
use AAM\Customer\Services\ShopAvailabilityService;

class CheckoutController extends BaseController
{
    private CartService          $cartService;
    private CheckoutService      $checkoutService;
    private CustomerOrderService $orderService;
    private ShopAvailabilityService $shopService;

    public function __construct()
    {
        parent::__construct();
        $this->cartService     = new CartService();
        $this->checkoutService = new CheckoutService();
        $this->orderService    = new CustomerOrderService();
        $this->shopService     = new ShopAvailabilityService();
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/checkout — Show checkout page
    // Protected by CustomerAuthMiddleware (see routes.php).
    // ──────────────────────────────────────────────────────
    public function index(): void
    {
        $shopId   = (int)($_SESSION['ca_selected_shop_id']   ?? 0);
        $shopName = (string)($_SESSION['ca_selected_shop_name'] ?? '');

        if (!$shopId) {
            $this->redirectWith('customer/shops', FLASH_WARNING, 'Please select a pickup shop first.');
            return;
        }

        // Expire stale orders silently
        $this->orderService->expireOldOrders();

        // Recheck cart
        $cartData = $this->cartService->recheckCart($shopId);

        if (empty($cartData['cart'])) {
            $this->redirectWith('customer/cart', FLASH_WARNING, 'Your cart is empty.');
            return;
        }

        // Check for blocking items
        $hasBlockingItems = false;
        foreach ($cartData['cart'] as $item) {
            if (in_array($item['status'] ?? 'ok', ['unavailable', 'out_of_stock'], true)) {
                $hasBlockingItems = true;
                break;
            }
        }
        if ($hasBlockingItems) {
            $this->redirectWith('customer/cart', FLASH_WARNING,
                'Please remove unavailable items from your cart before checking out.');
            return;
        }

        // Get shop details
        $shop = $this->shopService->getShop($shopId);

        // Get active mobile payment methods
        $paymentMethods = $this->checkoutService->getActiveMobileMethods();

        // Pre-fill customer info: prefer authenticated user data, fall back to session
        $userData      = SessionManager::get(SESSION_USER_DATA, []);
        $sessionName   = SessionManager::get(SESSION_USER_NAME);
        $customerName  = $sessionName ?: (string)($_SESSION['ca_customer_name']  ?? '');
        $customerPhone = (string)($userData['phone'] ?? $_SESSION['ca_customer_phone'] ?? '');
        $customerEmail = (string)($userData['email'] ?? $_SESSION['ca_customer_email'] ?? '');

        $this->render('customer.checkout.index', [
            'pageTitle'      => 'Checkout | AU-AgroSale',
            'shopId'         => $shopId,
            'shopName'       => $shopName,
            'shop'           => $shop,
            'cart'           => $cartData['cart'],
            'subtotal'       => $cartData['subtotal'],
            'itemCount'      => $cartData['item_count'],
            'distinctCount'  => $cartData['distinct_count'],
            'warnings'       => $cartData['warnings'],
            'hasIssues'      => $cartData['has_issues'],
            'paymentMethods' => $paymentMethods,
            'customerName'   => $customerName,
            'customerPhone'  => $customerPhone,
            'customerEmail'  => $customerEmail,
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/checkout/place — Submit checkout
    // Protected by CustomerAuthMiddleware (see routes.php).
    //
    // SECURITY: customer_id is derived EXCLUSIVELY from the
    // server-side authenticated session (SessionManager::userId()).
    // It is NEVER accepted from POST body, hidden inputs, or JS.
    // ──────────────────────────────────────────────────────
    public function place(): void
    {
        $shopId          = (int)$this->input('shop_id', 0);
        $paymentMethodId = (int)$this->input('payment_method_id', 0);
        $notes           = trim((string)$this->input('notes', ''));

        // SECURITY: customer identity from server-side session only
        $authenticatedUserId = SessionManager::userId();
        if (!$authenticatedUserId) {
            // Should not reach here (CustomerAuthMiddleware guards this route),
            // but defensive check to prevent null customer_id in orders.
            $this->redirectWith('customer/auth/login', FLASH_WARNING, 'Please sign in to continue.');
            return;
        }

        $sessionShopId = (int)($_SESSION['ca_selected_shop_id'] ?? 0);
        $sessionId     = session_id();

        // Security: shop must match session shop
        if (!$shopId || $shopId !== $sessionShopId) {
            $this->redirectWith('customer/checkout', FLASH_ERROR, 'Invalid shop selection.');
            return;
        }

        if (!$paymentMethodId) {
            $this->redirectWith('customer/checkout', FLASH_ERROR, 'Please select a payment method.');
            return;
        }

        // Customer info: prefer authenticated session data over POST input
        $userData      = SessionManager::get(SESSION_USER_DATA, []);
        $sessionName   = SessionManager::get(SESSION_USER_NAME);
        $customerName  = $sessionName ?: trim((string)$this->input('customer_name', ''));
        $customerPhone = (string)($userData['phone'] ?? trim((string)$this->input('customer_phone', '')));
        $customerEmail = (string)($userData['email'] ?? trim((string)$this->input('customer_email', '')));

        if (empty($customerName)) {
            $this->redirectWith('customer/checkout', FLASH_ERROR, 'Please enter your name.');
            return;
        }

        // Save customer info to session for pre-fill on next visit
        $_SESSION['ca_customer_name']  = $customerName;
        if ($customerPhone) $_SESSION['ca_customer_phone'] = $customerPhone;
        if ($customerEmail) $_SESSION['ca_customer_email'] = $customerEmail;

        try {
            $result = $this->checkoutService->placeOrder(
                shopId:          $shopId,
                paymentMethodId: $paymentMethodId,
                sessionId:       $sessionId,
                customerName:    $customerName,
                customerPhone:   $customerPhone ?: null,
                customerEmail:   $customerEmail ?: null,
                notes:           $notes,
                userId:          $authenticatedUserId   // server-side identity only
            );

            // Cash payment — go straight to confirmation
            if (($result['payment_type'] ?? '') === 'cash') {
                $this->redirect('customer/checkout/success/' . urlencode($result['order_reference']));
                return;
            }

            // Mobile banking — store payment data in session for the payment page
            $_SESSION['ca_pending_payment'] = [
                'order_id'          => $result['order_id'],
                'order_reference'   => $result['order_reference'],
                'payment_id'        => $result['payment_id'],
                'payment_reference' => $result['payment_reference'],
                'provider_code'     => $result['provider_code'],
                'provider_name'     => $result['provider_name'],
                'amount'            => $result['amount'],
                'qr_payload'        => $result['qr_payload'],
                'display_hint'      => $result['display_hint'],
                'is_live'           => $result['is_live'],
                'expires_at'        => $result['expires_at'],
                'ttl_minutes'       => $result['ttl_minutes'] ?? 15,
            ];

            $this->redirect('customer/checkout/payment/' . $result['order_id']);
        } catch (\RuntimeException $e) {
            $this->redirectWith('customer/checkout', FLASH_ERROR, $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error('Checkout place order failed', ['error' => $e->getMessage()]);
            $this->redirectWith('customer/checkout', FLASH_ERROR, 'Something went wrong. Please try again.');
        }
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/checkout/payment/{id} — Payment screen
    // ──────────────────────────────────────────────────────
    public function payment(string $id): void
    {
        $orderId   = (int)$id;
        $sessionId = session_id();
        $userId    = SessionManager::userId();

        $this->orderService->expireOldOrders();

        $order = $this->orderService->getOrderForSession($orderId, $sessionId, $userId);

        if (!$order) {
            $this->redirectWith('customer/orders', FLASH_ERROR, 'Order not found.');
            return;
        }

        if (!in_array($order['order_status'], ['pending_payment', 'paid'], true)) {
            // For cancelled / payment_failed orders — show the orders page with context
            if (in_array($order['order_status'], ['cancelled', 'payment_failed'], true)) {
                $this->redirectWith(
                    'customer/orders',
                    FLASH_WARNING,
                    'Order ' . $order['order_reference'] . ' was ' .
                    ($order['order_status'] === 'payment_failed' ? 'not completed due to a payment failure' : 'cancelled') .
                    '. You can place a new order from your cart.'
                );
                return;
            }
            // Already completed — show confirmation
            $this->redirect('customer/checkout/success/' . urlencode($order['order_reference']));
            return;
        }

        // Get payment data from session or rebuild from order
        $pendingPayment = $_SESSION['ca_pending_payment'] ?? null;

        if (!$pendingPayment || (int)($pendingPayment['order_id'] ?? 0) !== $orderId) {
            // Rebuild from DB
            $mpRow = null;
            if ($order['mobile_payment_id']) {
                $mpRow = \AAM\Core\Database::getInstance()->fetchOne(
                    "SELECT * FROM mobile_payments WHERE id = :id",
                    ['id' => $order['mobile_payment_id']]
                );
            }
            $pendingPayment = [
                'order_id'          => $orderId,
                'order_reference'   => $order['order_reference'],
                'payment_id'        => $order['mobile_payment_id'] ?? 0,
                'payment_reference' => $order['payment_reference'],
                'provider_code'     => $mpRow['provider']        ?? '',
                'provider_name'     => $this->resolveProviderName(
                                           $mpRow['provider'] ?? '',
                                           $order['payment_method_name'] ?? 'Mobile Banking'
                                       ),
                'amount'            => (float)$order['total_amount'],
                'qr_payload'        => $mpRow['qr_payload']      ?? '',
                'display_hint'      => null,
                'is_live'           => false,
                'expires_at'        => $order['expires_at'],
                'ttl_minutes'       => 15,
            ];
        }

        $this->render('customer.checkout.payment', [
            'pageTitle'      => 'Complete Payment | AU-AgroSale',
            'order'          => $order,
            'orderId'        => $orderId,
            'paymentData'    => $pendingPayment,
            'shopName'       => $order['shop_name'] ?? '',
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/checkout/verify — AJAX payment status poll
    // ──────────────────────────────────────────────────────
    public function verify(): void
    {
        $orderId   = (int)$this->input('order_id', 0);
        $sessionId = session_id();

        if (!$orderId) {
            $this->jsonError('Invalid request.', 400);
            return;
        }

        try {
            $result = $this->checkoutService->verifyPayment($orderId, $sessionId);
            $this->jsonSuccess($result, $result['message'] ?? 'Status checked.');
        } catch (\Throwable $e) {
            Logger::error('Payment verify controller error', ['error' => $e->getMessage()]);
            $this->jsonError('Verification failed. Please try again.', 500);
        }
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/checkout/success/{ref} — Success page
    // ──────────────────────────────────────────────────────
    public function success(string $ref): void
    {
        $orderRef  = $ref;
        $sessionId = session_id();
        $userId    = SessionManager::userId();

        $order = $this->orderService->getOrderByReferenceForSession($orderRef, $sessionId, $userId);

        if (!$order) {
            $this->redirectWith('customer/orders', FLASH_ERROR, 'Order not found.');
            return;
        }

        // For cash orders, pending_payment is valid to show on success page
        // For mobile banking, redirect to payment page if still pending
        $pmType = $order['payment_method_type'] ?? '';
        if ($order['order_status'] === 'pending_payment' && $pmType !== 'cash') {
            $this->redirect('customer/checkout/payment/' . $order['id']);
            return;
        }

        // Clear pending payment session data
        unset($_SESSION['ca_pending_payment']);

        // Clear cart for cash orders (mobile banking cart is cleared after payment verification)
        // Use session flag to prevent double-clearing on page refresh
        if ($pmType === 'cash' && empty($_SESSION['ca_cart_cleared_' . $order['id']])) {
            $this->cartService->clearCart();
            $_SESSION['ca_cart_cleared_' . $order['id']] = true;
        }

        $this->render('customer.checkout.success', [
            'pageTitle' => 'Order Confirmed | AU-AgroSale',
            'order'     => $order,
            'shopName'  => $order['shop_name'] ?? '',
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/checkout/cancel — Cancel order
    // ──────────────────────────────────────────────────────
    public function cancel(): void
    {
        $orderId   = (int)$this->input('order_id', 0);
        $sessionId = session_id();
        $userId    = SessionManager::userId();

        if (!$orderId) {
            $this->jsonError('Invalid request.', 400);
            return;
        }

        $order = $this->orderService->getOrderForSession($orderId, $sessionId, $userId);
        if (!$order) {
            $this->jsonError('Order not found.', 404);
            return;
        }

        if (!in_array($order['order_status'], ['pending_payment', 'payment_failed'], true)) {
            $this->jsonError('This order cannot be cancelled.', 422);
            return;
        }

        $this->orderService->cancelOrder($orderId, 'Cancelled by customer');
        unset($_SESSION['ca_pending_payment']);

        $this->jsonSuccess([
            'redirect' => $this->buildUrl('customer/cart'),
        ], 'Order cancelled. You can modify your cart and try again.');
    }

    // ──────────────────────────────────────────────────────
    // HELPER
    // ──────────────────────────────────────────────────────
    private function buildUrl(string $path): string
    {
        $appUrl = rtrim((string)\AAM\Config\Application::get('url', ''), '/');
        return $appUrl . '/' . ltrim($path, '/');
    }

    /**
     * Resolve a human-readable provider name from a provider code.
     * Falls back to the order's payment_method_name if code is unrecognised.
     */
    private function resolveProviderName(string $providerCode, string $fallback = 'Mobile Banking'): string
    {
        $map = [
            'cbe_birr'     => 'CBE Birr',
            'telebirr'     => 'Telebirr',
            'other_mobile' => 'Mobile Banking',
        ];
        return $map[$providerCode] ?? ($fallback ?: 'Mobile Banking');
    }
}
