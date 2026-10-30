<?php
/**
 * =========================================================
 * AU-AgroSale — CustomerCheckoutService
 * =========================================================
 * File    : modules/customer/services/CheckoutService.php
 * Purpose : Orchestrates the full customer checkout flow.
 *
 * RESPONSIBILITIES
 *   1. Validate cart (server-side re-validation)
 *   2. Re-fetch authoritative prices from DB
 *   3. Create customer_orders + customer_order_items
 *   4. Initiate mobile payment (via POS MobilePaymentService)
 *   5. After payment verified: deduct inventory + create sale
 *   6. Send notifications to shop + customer
 *   7. Clear customer cart
 *
 * CRITICAL RULES
 *   - Prices are NEVER trusted from the client or cart session.
 *   - Inventory is ONLY deducted after payment is VERIFIED.
 *   - All operations are atomic (DB transactions).
 *   - Duplicate payment handling is idempotent.
 *   - Stock race conditions are prevented via SELECT FOR UPDATE.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Services;

use AAM\Core\Database;
use AAM\Core\Logger;
use AAM\POS\Services\MobilePaymentService;
use AAM\POS\Models\SalesModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Notifications\NotificationService;
use AAM\Notifications\Models\NotificationModel;
use AAM\Settings\Services\PaymentMethodService;

class CheckoutService
{
    private Database             $db;
    private CustomerOrderService $orderService;
    private CartService          $cartService;
    private NotificationService  $notifService;

    /** Seller account used for customer-initiated orders (system user) */
    private const CUSTOMER_ORDER_SELLER_ID = 0; // Set to a system-user ID in production

    public function __construct()
    {
        $this->db           = Database::getInstance();
        $this->orderService = new CustomerOrderService();
        $this->cartService  = new CartService();
        $this->notifService = new NotificationService();
    }

    // ──────────────────────────────────────────────────────
    // STEP 1 — VALIDATE CHECKOUT
    // ──────────────────────────────────────────────────────

    /**
     * Validate everything before creating an order.
     * Returns validated items array on success.
     * Throws RuntimeException on any validation failure.
     *
     * @return array{items: array, subtotal: float, total: float}
     */
    public function validateCheckout(int $shopId, int $paymentMethodId, string $sessionId): array
    {
        // 1. Validate shop
        $shop = $this->db->fetchOne(
            "SELECT id, name, is_active, deleted_at FROM shops WHERE id = :id LIMIT 1",
            ['id' => $shopId]
        );
        if (!$shop || !$shop['is_active'] || $shop['deleted_at']) {
            throw new \RuntimeException('The selected shop is not available.');
        }

        // 2. Validate payment method
        $pmService = new PaymentMethodService();
        $pm        = $pmService->getById($paymentMethodId);
        if (!$pm || !$pm['is_active']) {
            throw new \RuntimeException('The selected payment method is not available.');
        }
        if (!in_array($pm['type'], ['cash', 'mobile_banking'], true)) {
            throw new \RuntimeException('The selected payment method type is not supported.');
        }

        // 3. Get and recheck cart
        $cartData = $this->cartService->recheckCart($shopId);
        if (empty($cartData['cart'])) {
            throw new \RuntimeException('Your cart is empty. Please add products before checking out.');
        }

        // 4. Check for blocking cart issues
        foreach ($cartData['cart'] as $item) {
            $status = $item['status'] ?? 'ok';
            if (in_array($status, ['unavailable', 'out_of_stock'], true)) {
                throw new \RuntimeException(
                    'Your cart contains unavailable items. Please remove them before checking out.'
                );
            }
        }

        // 5. Re-validate every item against DB (price + stock)
        $validatedItems = [];
        $subtotal       = 0.0;

        foreach ($cartData['cart'] as $item) {
            $pid = (int)$item['product_id'];
            $qty = (float)$item['quantity'];

            $row = $this->db->fetchOne(
                "SELECT
                    p.id, p.name, p.sku, p.unit_id, p.unit_price,
                    p.is_active, p.product_status, p.deleted_at,
                    u.abbreviation AS unit_abbr,
                    COALESCE(si.available_quantity, 0) AS available_qty
                 FROM products p
                 LEFT JOIN units_of_measure u ON u.id = p.unit_id
                 LEFT JOIN shop_inventory si ON si.product_id = p.id AND si.shop_id = :sid
                 WHERE p.id = :pid
                 LIMIT 1",
                ['sid' => $shopId, 'pid' => $pid]
            );

            if (!$row || !$row['is_active'] || $row['product_status'] !== 'active' || $row['deleted_at']) {
                throw new \RuntimeException(
                    "Product \"{$item['product_name']}\" is no longer available. Please update your cart."
                );
            }

            $officialPrice = $row['unit_price'] !== null ? (float)$row['unit_price'] : null;
            if ($officialPrice === null || $officialPrice <= 0) {
                throw new \RuntimeException(
                    "Product \"{$row['name']}\" does not have an approved selling price."
                );
            }

            $available = (float)$row['available_qty'];
            if ($qty > $available) {
                throw new \RuntimeException(
                    "Only {$available} {$row['unit_abbr']} of \"{$row['name']}\" is available. " .
                    "Your cart has been updated."
                );
            }

            $itemSubtotal     = round($qty * $officialPrice, 2);
            $subtotal        += $itemSubtotal;
            $validatedItems[] = [
                'product_id'   => $pid,
                'unit_id'      => $row['unit_id'],
                'product_name' => $row['name'],
                'product_sku'  => $row['sku'],
                'quantity'     => $qty,
                'unit_price'   => $officialPrice,
                'subtotal'     => $itemSubtotal,
                'available'    => $available,
                'unit_abbr'    => $row['unit_abbr'] ?? '',
            ];
        }

        if (empty($validatedItems)) {
            throw new \RuntimeException('No valid items in cart.');
        }

        $total = round($subtotal, 2);

        return [
            'items'    => $validatedItems,
            'subtotal' => $subtotal,
            'total'    => $total,
            'shop'     => $shop,
            'pm'       => $pm,
        ];
    }

    // ──────────────────────────────────────────────────────
    // STEP 2 — PLACE ORDER + INITIATE PAYMENT
    // ──────────────────────────────────────────────────────

    /**
     * Full checkout flow:
     *   Cash:
     *     1. Validate cart
     *     2. Create customer_order (status: pending_payment)
     *     3. Return order data — no mobile payment initiated
     *        Customer pays at pickup; shop marks as paid on completion.
     *
     *   Mobile Banking:
     *     1. Validate cart
     *     2. Create customer_order
     *     3. Initiate mobile_payment (QR)
     *     4. Link payment to order
     *     5. Send notification
     *
     * @throws \RuntimeException on any failure
     */
    public function placeOrder(
        int    $shopId,
        int    $paymentMethodId,
        string $sessionId,
        string $customerName,
        ?string $customerPhone = null,
        ?string $customerEmail = null,
        string $notes          = '',
        ?int   $userId         = null    // server-side authenticated user ID only
    ): array {
        // 1. Validate
        $validated = $this->validateCheckout($shopId, $paymentMethodId, $sessionId);
        $items     = $validated['items'];
        $total     = $validated['total'];
        $pm        = $validated['pm'];
        $shop      = $validated['shop'];

        // 2. Generate payment reference
        $payRef = $this->orderService->generatePaymentReference();

        // 3. Create order in a transaction
        // SECURITY: user_id comes EXCLUSIVELY from the server-side session parameter.
        // Never from POST body or JavaScript.
        $orderId = $this->orderService->createOrder([
            'session_id'        => $sessionId,
            'user_id'           => $userId,        // server-side only
            'customer_name'     => $customerName,
            'customer_phone'    => $customerPhone,
            'customer_email'    => $customerEmail,
            'shop_id'           => $shopId,
            'payment_method_id' => $paymentMethodId,
            'payment_reference' => $payRef,
            'subtotal'          => $validated['subtotal'],
            'total_amount'      => $total,
            'notes'             => $notes,
            'items'             => $items,
        ]);

        // 4. Get order reference
        $orderRow = $this->db->fetchOne(
            "SELECT order_reference FROM customer_orders WHERE id = :id",
            ['id' => $orderId]
        );
        $orderRef = $orderRow['order_reference'] ?? '';

        // 5a. CASH PAYMENT — no QR, customer pays at pickup
        if ($pm['type'] === 'cash') {
            $_SESSION['ca_last_order_id']  = $orderId;
            $_SESSION['ca_last_order_ref'] = $orderRef;

            Logger::info('Customer cash order placed', [
                'order_id'        => $orderId,
                'order_reference' => $orderRef,
                'shop_id'         => $shopId,
                'total'           => $total,
            ]);

            return [
                'order_id'          => $orderId,
                'order_reference'   => $orderRef,
                'payment_reference' => $payRef,
                'payment_type'      => 'cash',
                'provider_code'     => 'cash',
                'provider_name'     => $pm['name'],
                'amount'            => $total,
                'payment_id'        => null,
                'qr_payload'        => null,
                'display_hint'      => 'Pay with cash when you collect your order at ' . ($shop['name'] ?? 'the shop') . '.',
                'is_live'           => true,
                'expires_at'        => date('Y-m-d H:i:s', time() + 15 * 60),
                'customer_name'     => $customerName,
                'shop_name'         => $shop['name'],
                'items'             => $items,
            ];
        }

        // 5b. MOBILE BANKING — initiate QR payment
        $mobilePaySvc = new MobilePaymentService();
        $providerCode = $pm['provider_code'];

        $posItems = array_map(fn($i) => [
            'product_id' => $i['product_id'],
            'quantity'   => $i['quantity'],
        ], $items);

        $sysUserId = $this->getSystemUserId();

        try {
            $paymentData = $mobilePaySvc->initiatePayment(
                shopId:       $shopId,
                sellerId:     $sysUserId,
                shiftId:      null,
                providerCode: $providerCode,
                items:        $posItems,
                notes:        "Customer order {$orderRef}"
            );
        } catch (\Throwable $e) {
            $this->orderService->cancelOrder($orderId, 'Payment initiation failed: ' . $e->getMessage());
            throw new \RuntimeException('Payment initiation failed. Please try again.', 0, $e);
        }

        $mobilePaymentId = $paymentData['payment_id'];

        // 6. Link the mobile_payment back to our customer_order
        $this->orderService->linkMobilePayment($orderId, $mobilePaymentId);
        $this->db->execute(
            "UPDATE mobile_payments SET customer_order_id = :oid WHERE id = :pid",
            ['oid' => $orderId, 'pid' => $mobilePaymentId]
        );

        // 7. Store in session
        $_SESSION['ca_last_order_id']  = $orderId;
        $_SESSION['ca_last_order_ref'] = $orderRef;

        Logger::info('Customer mobile order placed', [
            'order_id'         => $orderId,
            'order_reference'  => $orderRef,
            'mobile_payment_id'=> $mobilePaymentId,
            'shop_id'          => $shopId,
            'total'            => $total,
        ]);

        return array_merge($paymentData, [
            'order_id'          => $orderId,
            'order_reference'   => $orderRef,
            'payment_reference' => $payRef,
            'payment_type'      => 'mobile_banking',
            'customer_name'     => $customerName,
            'shop_name'         => $shop['name'],
            'items'             => $items,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // STEP 3 — VERIFY PAYMENT (polling endpoint)
    // ──────────────────────────────────────────────────────

    /**
     * Check mobile payment status for a customer order.
     * If payment is now PAID:
     *   1. Re-validate stock atomically (prevent race conditions)
     *   2. Deduct inventory
     *   3. Create sale record
     *   4. Update order status to PAID
     *   5. Notify shop
     *   6. Clear customer cart
     *
     * Returns current status and order reference.
     * This is IDEMPOTENT — safe to call multiple times.
     *
     * @throws \RuntimeException on critical failures
     */
    public function verifyPayment(int $orderId, string $sessionId): array
    {
        // Security: verify this order belongs to this session OR this authenticated user
        $userId = \AAM\Core\SessionManager::userId();
        if ($userId !== null && $userId > 0) {
            $order = $this->db->fetchOne(
                "SELECT * FROM customer_orders WHERE id = :id AND (user_id = :uid OR customer_session_id = :sid) LIMIT 1",
                ['id' => $orderId, 'uid' => $userId, 'sid' => $sessionId]
            );
        } else {
            $order = $this->db->fetchOne(
                "SELECT * FROM customer_orders WHERE id = :id AND customer_session_id = :sid LIMIT 1",
                ['id' => $orderId, 'sid' => $sessionId]
            );
        }

        if (!$order) {
            return ['status' => 'error', 'message' => 'Order not found.'];
        }

        // Already paid/completed — return success (idempotent)
        if (in_array($order['order_status'], ['paid', 'preparing', 'ready_for_pickup', 'completed'], true)) {
            return [
                'status'          => 'paid',
                'order_status'    => $order['order_status'],
                'order_reference' => $order['order_reference'],
                'order_id'        => $orderId,
                'message'         => 'Payment confirmed.',
            ];
        }

        // Check if order has expired
        if ($order['order_status'] === 'cancelled' || $order['order_status'] === 'payment_failed') {
            return [
                'status'  => $order['payment_status'],
                'message' => 'This order is no longer active.',
            ];
        }

        // Get the mobile payment ID
        $mobilePaymentId = (int)($order['mobile_payment_id'] ?? 0);
        if (!$mobilePaymentId) {
            return ['status' => 'pending', 'message' => 'Payment not initiated yet.'];
        }

        // Use existing MobilePaymentService to verify
        $mobilePaySvc = new MobilePaymentService();
        $sysUserId    = $this->getSystemUserId();

        try {
            $result = $mobilePaySvc->verifyPayment($mobilePaymentId, $sysUserId);
        } catch (\Throwable $e) {
            Logger::error('Customer payment verification failed', [
                'order_id'    => $orderId,
                'mp_id'       => $mobilePaymentId,
                'error'       => $e->getMessage(),
            ]);
            return ['status' => 'pending', 'message' => 'Verification in progress. Please wait.'];
        }

        $payStatus = $result['status'] ?? 'pending';

        if ($payStatus === 'paid') {
            // Payment confirmed — update order, deduct stock, create sale
            $saleId = (int)($result['sale_id'] ?? 0);

            // Update order to PAID
            $this->orderService->markPaid($orderId);

            // Clear the customer's cart
            $this->cartService->clearCart();
            unset($_SESSION['ca_last_order_id'], $_SESSION['ca_last_order_ref']);

            // Enrich order with shop name for notification
            if (empty($order['shop_name'])) {
                $shopRow = $this->db->fetchOne(
                    "SELECT COALESCE(shop_name, name) AS shop_name FROM shops WHERE id = :id LIMIT 1",
                    ['id' => $order['shop_id']]
                );
                $order['shop_name'] = $shopRow['shop_name'] ?? '';
            }

            // Notify shop staff
            $this->notifyShop($order, $saleId);

            return [
                'status'          => 'paid',
                'order_status'    => 'paid',
                'order_reference' => $order['order_reference'],
                'order_id'        => $orderId,
                'sale_id'         => $saleId,
                'message'         => 'Payment confirmed. Your order is being prepared.',
            ];
        }

        if (in_array($payStatus, ['failed', 'cancelled', 'expired'], true)) {
            if ($payStatus === 'expired') {
                $this->orderService->cancelOrder($orderId, 'Payment expired');
            } else {
                $this->orderService->markPaymentFailed($orderId, "Payment {$payStatus}");
            }

            return [
                'status'  => $payStatus,
                'message' => 'Payment was not completed.',
            ];
        }

        return [
            'status'  => 'pending',
            'message' => 'Waiting for payment confirmation.',
        ];
    }

    // ──────────────────────────────────────────────────────
    // SHOP-SIDE — COMPLETE PICKUP
    // ──────────────────────────────────────────────────────

    /**
     * Called by shop salesperson when handing products to customer.
     * Creates the final sale record (if not already created via mobile payment)
     * and marks order COMPLETED.
     *
     * This handles the case where the sale was already created by
     * MobilePaymentService (at payment verification time).
     */
    public function completePickup(int $orderId, int $shopId, int $staffId): array
    {
        $order = $this->orderService->getOrderForShop($orderId, $shopId);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found.'];
        }

        if ($order['order_status'] !== 'ready_for_pickup') {
            return ['success' => false, 'message' => 'Order is not ready for pickup yet.'];
        }

        $saleId = (int)($order['sale_id'] ?? 0);

        if (!$saleId) {
            // Sale was not yet created (edge case) — create it now
            try {
                $saleId = $this->createSaleFromOrder($order, $staffId);
            } catch (\Throwable $e) {
                Logger::error('Failed to create sale from customer order', [
                    'order_id' => $orderId,
                    'error'    => $e->getMessage(),
                ]);
                return ['success' => false, 'message' => 'Failed to complete order: ' . $e->getMessage()];
            }
        }

        // Mark completed — markCompleted uses SELECT FOR UPDATE internally,
        // preventing double-completion under concurrent requests.
        try {
            $this->orderService->markCompleted($orderId, $saleId, $staffId);
        } catch (\RuntimeException $e) {
            // Could happen if a concurrent request already completed the order
            Logger::warning('completePickup: markCompleted rejected', [
                'order_id' => $orderId,
                'reason'   => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }

        // Notify customer
        $this->notifyCustomerCompleted($order);

        return [
            'success'   => true,
            'message'   => 'Order completed. Customer has received their products.',
            'sale_id'   => $saleId,
            'order_ref' => $order['order_reference'],
        ];
    }

    // ──────────────────────────────────────────────────────
    // ACTIVE PAYMENT METHODS (for checkout page)
    // ──────────────────────────────────────────────────────

    /**
     * Get ALL active payment methods for the checkout form.
     * Returns both cash and mobile_banking types.
     * Cash appears first (sort_order), then mobile banking methods.
     * Includes display_name for customer-facing labels (falls back to name
     * if the display_name column has not been added yet via migration 042).
     */
    public function getActiveMobileMethods(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT id, code, name,
                        COALESCE(display_name, name) AS display_name,
                        type, provider_code, description, sort_order
                 FROM payment_methods
                 WHERE is_active = 1
                 ORDER BY sort_order ASC, id ASC"
            );
        } catch (\Throwable $e) {
            // display_name column may not exist yet (migration 042 pending)
            // Fall back to query without it — safe on all installs
            return $this->db->fetchAll(
                "SELECT id, code, name,
                        name AS display_name,
                        type, provider_code, description, sort_order
                 FROM payment_methods
                 WHERE is_active = 1
                 ORDER BY sort_order ASC, id ASC"
            );
        }
    }

    // ──────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────

    /**
     * Create a sale record from a completed customer order.
     * Uses the same pattern as POSService::processSale() / MobilePaymentService::completeSaleFromPayment().
     * Called when the sale was not already created at payment time.
     */
    private function createSaleFromOrder(array $order, int $staffId): int
    {
        $saleId  = 0;
        $shopId  = (int)$order['shop_id'];
        $items   = $this->orderService->getOrderItems((int)$order['id']);

        $this->db->transaction(function() use ($order, $items, $shopId, $staffId, &$saleId) {

            // Re-validate stock one final time (race condition protection)
            foreach ($items as &$item) {
                $row = $this->db->fetchOne(
                    "SELECT p.unit_price, si.available_quantity
                     FROM products p
                     LEFT JOIN shop_inventory si ON si.product_id=p.id AND si.shop_id=:sid
                     WHERE p.id=:pid AND p.is_active=1 FOR UPDATE",
                    ['sid' => $shopId, 'pid' => $item['product_id']]
                );

                if (!$row) {
                    throw new \RuntimeException("Product ID {$item['product_id']} not found.");
                }
                if ((float)$row['available_quantity'] < (float)$item['quantity']) {
                    throw new \RuntimeException(
                        "Insufficient stock for \"{$item['product_name']}\" at completion time."
                    );
                }

                // Always use DB price
                $item['unit_price'] = (float)$row['unit_price'];
                $item['subtotal']   = round((float)$item['quantity'] * (float)$row['unit_price'], 2);
                $item['available']  = (float)$row['available_quantity'];
            }
            unset($item);

            $totalAmount = round(array_sum(array_column($items, 'subtotal')), 2);

            $salesModel    = new SalesModel();
            $saleNumber    = $salesModel->nextSaleNumber();
            $receiptNumber = $salesModel->nextReceiptNumber();
            $now           = date('Y-m-d H:i:s');
            $today         = date('Y-m-d');

            $pmCode = 'mobile_money';
            // Determine mobile payment provider from the order's mobile_payment record
            $mpRow = $order['mobile_payment_id']
                ? $this->db->fetchOne(
                    "SELECT provider, payment_reference FROM mobile_payments WHERE id=:id",
                    ['id' => $order['mobile_payment_id']]
                  )
                : null;
            $providerCode = $mpRow['provider'] ?? null;

            $saleId = $salesModel->create([
                'sale_number'      => $saleNumber,
                'receipt_number'   => $receiptNumber,
                'shop_id'          => $shopId,
                'seller_id'        => $staffId,
                'shift_id'         => null,
                'status'           => 'completed',
                'payment_method'   => $pmCode,
                'payment_provider' => $providerCode,
                'payment_reference'=> $order['payment_reference'],
                'payment_status'   => 'paid',
                'verified_at'      => $order['paid_at'] ?? $now,
                'subtotal'         => $totalAmount,
                'discount_amount'  => 0.00,
                'total_amount'     => $totalAmount,
                'cash_received'    => 0.00,
                'change_given'     => 0.00,
                'total_items'      => count($items),
                'notes'            => "Customer pickup order: {$order['order_reference']}",
                'sale_date'        => $today,
                'completed_at'     => $now,
            ]);

            foreach ($items as $item) {
                $this->db->execute(
                    "INSERT INTO sale_items
                        (sale_id, product_id, unit_id, product_name, product_sku, quantity, unit_price, subtotal)
                     VALUES (:sid,:pid,:uid,:pname,:psku,:qty,:price,:sub)",
                    [
                        'sid'   => $saleId,
                        'pid'   => (int)$item['product_id'],
                        'uid'   => $item['unit_id'] ?? null,
                        'pname' => $item['product_name'],
                        'psku'  => $item['product_sku'] ?? null,
                        'qty'   => (float)$item['quantity'],
                        'price' => (float)$item['unit_price'],
                        'sub'   => (float)$item['subtotal'],
                    ]
                );

                // Deduct shop inventory
                $this->db->execute(
                    "UPDATE shop_inventory
                     SET current_quantity   = current_quantity   - :qty,
                         available_quantity = available_quantity - :qty2,
                         last_movement_at   = NOW(),
                         last_movement_type = 'sale'
                     WHERE shop_id=:sid AND product_id=:pid",
                    [
                        'qty'  => (float)$item['quantity'],
                        'qty2' => (float)$item['quantity'],
                        'sid'  => $shopId,
                        'pid'  => (int)$item['product_id'],
                    ]
                );

                // Stock movement log
                $before = (float)$item['available'];
                $after  = $before - (float)$item['quantity'];

                $this->db->execute(
                    "INSERT INTO stock_movement_log
                        (movement_type, location_type, location_id, product_id,
                         quantity_before, quantity_in, quantity_out, quantity_after,
                         reference_type, reference_id, reference_number,
                         performed_by, notes, moved_at)
                     VALUES
                        ('sale','shop',:loc,:pid,
                         :before,0,:out,:after,
                         'sales',:ref_id,:ref_num,
                         :user,:notes,NOW())",
                    [
                        'loc'     => $shopId,
                        'pid'     => (int)$item['product_id'],
                        'before'  => $before,
                        'out'     => (float)$item['quantity'],
                        'after'   => $after,
                        'ref_id'  => $saleId,
                        'ref_num' => $saleNumber,
                        'user'    => $staffId,
                        'notes'   => "Customer pickup: {$item['product_name']} — Order {$order['order_reference']}",
                    ]
                );
            }

            // Update customer_order with sale_id
            $this->db->execute(
                "UPDATE customer_orders SET sale_id = :sid WHERE id = :oid",
                ['sid' => $saleId, 'oid' => (int)$order['id']]
            );
        });

        return $saleId;
    }

    /**
     * Notify shop salesperson(s) of a new paid customer order.
     */
    private function notifyShop(array $order, int $saleId = 0): void
    {
        try {
            $shopId    = (int)$order['shop_id'];
            $shopName  = $order['shop_name'] ?? 'Shop';
            $orderRef  = $order['order_reference'] ?? '';
            $total     = number_format((float)$order['total_amount'], 2);

            // Find salesperson(s) assigned to this shop
            // user_location_assignments links users to shops (no is_active column)
            $salespersons = $this->db->fetchAll(
                "SELECT DISTINCT ula.user_id
                 FROM user_location_assignments ula
                 JOIN users u ON u.id = ula.user_id
                 JOIN roles r ON r.id = u.role_id
                 WHERE ula.location_type = 'shop'
                   AND ula.location_id   = :sid
                   AND r.name            = 'salesperson'
                   AND u.is_active       = 1",
                ['sid' => $shopId]
            );

            if (empty($salespersons)) {
                // Fallback: notify all salespersons with role
                $this->notifService->sendToRole(
                    'salesperson',
                    NOTIF_SHOP_NEW_ORDER,
                    "New Customer Order — {$orderRef}",
                    "New paid customer order {$orderRef} for ETB {$total} is waiting at {$shopName}.",
                    'high',
                    null,
                    'customer_order',
                    (int)$order['id'],
                    "/customer/shop-orders/{$order['id']}"
                );
            } else {
                foreach ($salespersons as $sp) {
                    $this->notifService->sendToUser(
                        (int)$sp['user_id'],
                        NOTIF_SHOP_NEW_ORDER,
                        "New Customer Order — {$orderRef}",
                        "New paid customer order {$orderRef} for ETB {$total} needs preparation.",
                        'high',
                        null,
                        'customer_order',
                        (int)$order['id'],
                        "/customer/shop-orders/{$order['id']}"
                    );
                }
            }
        } catch (\Throwable $e) {
            // Notification failure must NEVER break the payment flow
            Logger::warning('Failed to send shop notification for customer order', [
                'order_id' => $order['id'],
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify customer that their order is completed.
     */
    private function notifyCustomerCompleted(array $order): void
    {
        // For session-based customers, notifications are stored in session flash
        // Future: send SMS/email when customer accounts are implemented
        try {
            $_SESSION['ca_order_completed_' . $order['id']] = true;
        } catch (\Throwable) {}
    }

    /**
     * Get system user ID for customer-initiated payment operations.
     * Falls back to admin user (ID=1) if no dedicated system user exists.
     */
    private function getSystemUserId(): int
    {
        $row = $this->db->fetchOne(
            "SELECT id FROM users WHERE role_id = 1 AND is_active = 1 ORDER BY id ASC LIMIT 1"
        );
        return $row ? (int)$row['id'] : 1;
    }
}
