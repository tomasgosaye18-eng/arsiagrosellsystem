<?php
/**
 * =========================================================
 * AU-AgroSale — Customer PaymentController
 * =========================================================
 * File    : modules/customer/controllers/PaymentController.php
 * Phase 5 : Payment status polling + dev simulation.
 *
 * Routes:
 *   GET  /customer/payment/status/{id}  → status()   (AJAX poll)
 *   POST /customer/payment/dev-confirm  → devConfirm() (DEV ONLY)
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\Logger;
use AAM\Customer\Services\CheckoutService;
use AAM\Customer\Services\CustomerOrderService;
use AAM\POS\Services\MobilePaymentService;

class PaymentController extends BaseController
{
    private CheckoutService      $checkoutService;
    private CustomerOrderService $orderService;
    private Database             $db;

    public function __construct()
    {
        parent::__construct();
        $this->checkoutService = new CheckoutService();
        $this->orderService    = new CustomerOrderService();
        $this->db              = Database::getInstance();
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/payment/status/{id} — AJAX poll
    // Lightweight status check without forcing verification.
    // ──────────────────────────────────────────────────────
    public function status(int $id): void
    {
        $orderId   = $id;
        $sessionId = session_id();

        try {
            // First check order status — if already paid, return immediately
            $order = $this->orderService->getOrderForSession($orderId, $sessionId);
            if (!$order) {
                $this->jsonError('Order not found.', 404);
                return;
            }

            if (in_array($order['order_status'], ['paid','preparing','ready_for_pickup','completed'], true)) {
                $this->jsonSuccess([
                    'status'          => 'paid',
                    'order_status'    => $order['order_status'],
                    'order_reference' => $order['order_reference'],
                    'order_id'        => $orderId,
                    'message'         => 'Payment confirmed.',
                ], 'Payment confirmed.');
                return;
            }

            if (in_array($order['order_status'], ['cancelled','payment_failed'], true)) {
                $this->jsonSuccess([
                    'status'  => $order['payment_status'],
                    'message' => 'Order is no longer active.',
                ], 'Order not active.');
                return;
            }

            // Still pending — trigger verify
            $result = $this->checkoutService->verifyPayment($orderId, $sessionId);
            $this->jsonSuccess($result, $result['message'] ?? 'OK');

        } catch (\Throwable $e) {
            Logger::warning('Payment status check error', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            $this->jsonSuccess(['status' => 'pending', 'message' => 'Checking…'], 'Pending');
        }
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/payment/dev-confirm — DEV ONLY
    // Simulates a successful payment confirmation.
    // Calls MobilePaymentService::devSimulatePayment() which
    // bypasses the real provider and forces the payment to paid,
    // creating the sale record and deducting inventory atomically.
    // ──────────────────────────────────────────────────────
    public function devConfirm(): void
    {
        // Hard guard — never available in production
        if (defined('APP_ENV') && APP_ENV === 'production') {
            $this->jsonError('Not available in production.', 403);
            return;
        }

        $mobilePaymentId = (int)$this->input('payment_id', 0);
        $orderId         = (int)$this->input('order_id', 0);
        $sessionId       = session_id();

        if (!$mobilePaymentId || !$orderId) {
            $this->jsonError('Missing payment_id or order_id.', 400);
            return;
        }

        // Security: order must belong to this session
        $order = $this->orderService->getOrderForSession($orderId, $sessionId);
        if (!$order) {
            $this->jsonError('Order not found.', 404);
            return;
        }

        // Already handled — return success idempotently
        if (in_array($order['order_status'], ['paid','preparing','ready_for_pickup','completed'], true)) {
            $this->jsonSuccess([
                'status'          => 'paid',
                'order_status'    => $order['order_status'],
                'order_reference' => $order['order_reference'],
                'order_id'        => $orderId,
                'redirect'        => $this->buildUrl('customer/checkout/success/' . urlencode($order['order_reference'])),
            ], 'Payment already confirmed.');
            return;
        }

        if ($order['order_status'] !== 'pending_payment') {
            $this->jsonError('Order cannot be confirmed in its current state: ' . $order['order_status'], 422);
            return;
        }

        try {
            $sysUserId    = $this->getSystemUserId();
            $mobilePaySvc = new MobilePaymentService();

            // Simulate the payment through the dev pathway
            $result = $mobilePaySvc->devSimulatePayment($mobilePaymentId, $sysUserId);

            if ($result['status'] === 'paid') {
                // Update customer order to PAID
                $this->orderService->markPaid($orderId);

                // Clear cart
                $cartSvc = new \AAM\Customer\Services\CartService();
                $cartSvc->clearCart();

                $this->jsonSuccess([
                    'status'          => 'paid',
                    'order_status'    => 'paid',
                    'order_reference' => $order['order_reference'],
                    'order_id'        => $orderId,
                    'sale_id'         => $result['sale_id'],
                    'redirect'        => $this->buildUrl('customer/checkout/success/' . urlencode($order['order_reference'])),
                ], 'Development simulation: payment confirmed successfully.');
            } else {
                $this->jsonSuccess([
                    'status'  => $result['status'],
                    'message' => $result['message'],
                ], 'Dev confirm returned: ' . $result['status']);
            }

        } catch (\Throwable $e) {
            Logger::error('Dev confirm failed', [
                'order_id'   => $orderId,
                'payment_id' => $mobilePaymentId,
                'error'      => $e->getMessage(),
            ]);
            $this->jsonError('Simulation failed: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────
    private function getSystemUserId(): int
    {
        $row = $this->db->fetchOne(
            "SELECT id FROM users WHERE role_id = 1 AND is_active = 1 ORDER BY id ASC LIMIT 1"
        );
        return $row ? (int)$row['id'] : 1;
    }

    private function buildUrl(string $path): string
    {
        $appUrl = rtrim((string)\AAM\Config\Application::get('url', ''), '/');
        return $appUrl . '/' . ltrim($path, '/');
    }
}
