<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — AU-AgroSale POS
 * =========================================================
 * File    : modules/pos/services/MobilePaymentService.php
 * Purpose : Mobile payment orchestration service.
 *
 * RESPONSIBILITIES
 *   - Create mobile_payments records (pending)
 *   - Dispatch to the correct payment provider
 *   - Verify payment status
 *   - After verified payment → atomically:
 *       • Create the sale record
 *       • Deduct inventory
 *       • Log stock movement
 *       • Update shift totals
 *   - Handle cancellation and expiry
 *
 * CRITICAL BUSINESS RULES (enforced here — not in the controller)
 *   1. A unique payment reference is generated server-side.
 *   2. Amount is NEVER trusted from the client — always from DB prices.
 *   3. Inventory is deducted ONLY ONCE, ONLY after verified payment.
 *   4. Duplicate payment confirmation is prevented by DB transaction
 *      + status check inside a SELECT FOR UPDATE lock.
 *   5. Cash workflow is completely unchanged — this service is only
 *      invoked for mobile payments.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\POS\Services;

use AAM\Core\Database;
use AAM\Core\Logger;
use AAM\POS\Services\Payment\CbeBirrProvider;
use AAM\POS\Services\Payment\MobilePaymentInterface;
use AAM\POS\Services\Payment\PaymentProviderException;
use AAM\POS\Services\Payment\TelebirrProvider;
use AAM\POS\Models\SalesModel;
use AAM\POS\Models\ShiftModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Settings\Services\PaymentMethodService;

class MobilePaymentService
{
    /** QR / payment request expires after this many minutes */
    private const PAYMENT_TTL_MINUTES = 15;

    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================================
    // getProvider()
    // Returns the correct provider implementation for a code.
    // =========================================================
    public function getProvider(string $providerCode): MobilePaymentInterface
    {
        return match ($providerCode) {
            'cbe_birr' => new CbeBirrProvider(),
            'telebirr' => new TelebirrProvider(),
            default    => throw new \InvalidArgumentException(
                "Unsupported payment provider: {$providerCode}. Supported: cbe_birr, telebirr."
            ),
        };
    }

    // =========================================================
    // initiatePayment()
    //
    // Called when the salesperson clicks "Generate QR" in the POS.
    //
    // 1. Validate all cart items against DB (server-side prices).
    // 2. Calculate the authoritative total.
    // 3. Generate a unique payment reference.
    // 4. Insert mobile_payments row (status = pending).
    // 5. Call provider to initiate the payment.
    // 6. Update mobile_payments with provider response.
    // 7. Return data for the QR modal.
    //
    // Returns: array with payment_id, reference, qr_payload, etc.
    // =========================================================
    public function initiatePayment(
        int    $shopId,
        int    $sellerId,
        ?int   $shiftId,
        string $providerCode,
        array  $items,
        string $notes = ''
    ): array {
        if (empty($items)) {
            throw new \RuntimeException('Cart is empty. Please add products before initiating payment.');
        }

        $provider = $this->getProvider($providerCode);

        // ── Step 0: Confirm the provider is ACTIVE in system configuration ─
        // Payment methods are centrally managed by System Administrator.
        // A salesperson cannot bypass an inactive method.
        // Wrapped in try/catch so a missing payment_methods table
        // (pre-migration) does not block the existing mobile payment flow.
        try {
            $pmService = new PaymentMethodService();
            if (!$pmService->isProviderActive($providerCode)) {
                throw new \InvalidArgumentException(
                    "Payment provider \"{$providerCode}\" is not currently active. " .
                    'Please contact your System Administrator.'
                );
            }
        } catch (\InvalidArgumentException $e) {
            // Re-throw — this is a business rule violation, not a technical error
            throw $e;
        } catch (\Throwable $e) {
            // Table may not exist yet (pre-migration) — allow through as warning
            Logger::warning('MobilePaymentService: could not check provider active status', [
                'provider' => $providerCode,
                'error'    => $e->getMessage(),
            ]);
        }

        // ── Step 1: Validate items & fetch authoritative prices ────────────
        $validatedItems = $this->validateCartItems($shopId, $items);

        // ── Step 2: Calculate authoritative total ─────────────────────────
        $totalAmount = round(
            array_sum(array_column($validatedItems, 'subtotal')),
            2
        );

        if ($totalAmount <= 0) {
            throw new \RuntimeException('Sale total must be greater than zero.');
        }

        // ── Step 3: Generate unique reference ─────────────────────────────
        $reference = $this->generateReference();

        // ── Step 4: Insert mobile_payments row ────────────────────────────
        $cartSnapshot = json_encode($validatedItems, JSON_THROW_ON_ERROR);
        $expiresAt    = date('Y-m-d H:i:s', time() + self::PAYMENT_TTL_MINUTES * 60);

        // Resolve payment_method_id from the registry
        $pmMethodId = null;
        try {
            $pmModel = new \AAM\Settings\Models\PaymentMethodModel();
            $pmRow   = $pmModel->getByCode($providerCode);
            if ($pmRow) {
                $pmMethodId = (int)$pmRow['id'];
            }
        } catch (\Throwable) {}

        $this->db->execute(
            "INSERT INTO mobile_payments
                (payment_reference, provider, payment_method_id, amount, currency, status,
                 shop_id, seller_id, shift_id, cart_snapshot, notes, expires_at)
             VALUES
                (:ref, :prov, :pm_id, :amt, 'ETB', 'pending',
                 :shop, :seller, :shift, :cart, :notes, :expires)",
            [
                'ref'     => $reference,
                'prov'    => $providerCode,
                'pm_id'   => $pmMethodId,
                'amt'     => $totalAmount,
                'shop'    => $shopId,
                'seller'  => $sellerId,
                'shift'   => $shiftId,
                'cart'    => $cartSnapshot,
                'notes'   => substr($notes, 0, 500),
                'expires' => $expiresAt,
            ]
        );

        $paymentId = (int)$this->db->lastInsertId();

        // ── Step 5: Call provider ─────────────────────────────────────────
        $shop = $this->db->fetchOne("SELECT name FROM shops WHERE id=:id", ['id' => $shopId]);

        $paymentRequest = $provider->initiate($reference, $totalAmount, [
            'shop_name'    => $shop['name'] ?? 'AU AgroSale',
            'callback_url' => '', // set when webhook endpoint is ready
        ]);

        // ── Step 6: Update mobile_payments with provider response ─────────
        $this->db->execute(
            "UPDATE mobile_payments
             SET provider_txn_id  = :txn,
                 qr_payload       = :qr,
                 provider_response = :resp
             WHERE id = :id",
            [
                'txn'  => $paymentRequest->providerTxnId,
                'qr'   => $paymentRequest->qrPayload,
                'resp' => json_encode($paymentRequest->rawResponse),
                'id'   => $paymentId,
            ]
        );

        Logger::info('Mobile payment initiated', [
            'payment_id' => $paymentId,
            'reference'  => $reference,
            'provider'   => $providerCode,
            'amount'     => $totalAmount,
            'is_live'    => $paymentRequest->isLive,
        ]);

        // ── Step 7: Return data for QR modal ─────────────────────────────
        return [
            'payment_id'     => $paymentId,
            'reference'      => $reference,
            'provider_code'  => $providerCode,
            'provider_name'  => $provider->getProviderName(),
            'amount'         => $totalAmount,
            'status'         => 'pending',
            'qr_payload'     => $paymentRequest->qrPayload,
            'display_hint'   => $paymentRequest->displayHint,
            'is_live'        => $paymentRequest->isLive,
            'expires_at'     => $expiresAt,
            'ttl_minutes'    => self::PAYMENT_TTL_MINUTES,
            'items'          => $validatedItems,
        ];
    }

    // =========================================================
    // verifyPayment()
    //
    // Called by the browser polling endpoint or manually.
    //
    // 1. Load and lock the mobile_payments row.
    // 2. Check expiry.
    // 3. Call provider to verify.
    // 4. If PAID → atomically create the sale + deduct inventory.
    // 5. Return status + sale_id (if newly completed).
    //
    // DUPLICATE PROTECTION:
    //   The SELECT FOR UPDATE lock inside the transaction prevents
    //   two concurrent verify requests from both creating a sale.
    // =========================================================
    public function verifyPayment(int $paymentId, int $requestingUserId): array
    {
        // Load payment outside transaction first (read-only check)
        $payment = $this->db->fetchOne(
            "SELECT * FROM mobile_payments WHERE id = :id",
            ['id' => $paymentId]
        );

        if (!$payment) {
            throw new \RuntimeException('Payment record not found.');
        }

        // Security: only the seller who initiated (or GM/Admin) can verify
        // This is enforced in the controller via role check + seller_id match.

        // Check if already finalised — no re-processing
        if (in_array($payment['status'], ['paid', 'failed', 'cancelled', 'expired'], true)) {
            return [
                'status'    => $payment['status'],
                'sale_id'   => $payment['sale_id'],
                'reference' => $payment['payment_reference'],
                'message'   => 'Payment already in final state: ' . $payment['status'],
            ];
        }

        // Check expiry before calling the provider
        if (strtotime($payment['expires_at']) < time()) {
            $this->db->execute(
                "UPDATE mobile_payments SET status='expired', updated_at=NOW() WHERE id=:id AND status='pending'",
                ['id' => $paymentId]
            );
            return [
                'status'    => 'expired',
                'sale_id'   => null,
                'reference' => $payment['payment_reference'],
                'message'   => 'Payment has expired. Please start a new payment.',
            ];
        }

        // ── Call provider to verify ────────────────────────────────────────
        $provider = $this->getProvider($payment['provider']);
        $verification = $provider->verify(
            $payment['payment_reference'],
            (string)($payment['provider_txn_id'] ?? '')
        );

        Logger::info('Mobile payment verification', [
            'payment_id' => $paymentId,
            'reference'  => $payment['payment_reference'],
            'status'     => $verification->status,
            'amount'     => $verification->verifiedAmount,
        ]);

        // ── Handle non-paid states ────────────────────────────────────────
        if (!$verification->isPaid()) {
            if ($verification->isFailed()) {
                // Update to failed/expired/cancelled
                $this->db->execute(
                    "UPDATE mobile_payments
                     SET status           = :st,
                         failure_reason   = :reason,
                         provider_response= :resp,
                         updated_at       = NOW()
                     WHERE id = :id",
                    [
                        'st'     => $verification->status,
                        'reason' => $verification->message,
                        'resp'   => json_encode($verification->rawResponse),
                        'id'     => $paymentId,
                    ]
                );
            }
            // PENDING: no state change yet, just return current status
            return [
                'status'    => $verification->status,
                'sale_id'   => null,
                'reference' => $payment['payment_reference'],
                'message'   => $verification->message,
            ];
        }

        // ── PAID: Amount validation ───────────────────────────────────────
        // Compare verified amount with our authoritative record.
        // Reject mismatches — never allow partial payments to complete a sale.
        $authoritative = (float)$payment['amount'];
        $verified      = $verification->verifiedAmount;

        // Allow a tiny tolerance (rounding at provider) — max ETB 0.01
        if ($verified > 0 && abs($verified - $authoritative) > 0.01) {
            Logger::warning('Mobile payment amount mismatch', [
                'payment_id'   => $paymentId,
                'reference'    => $payment['payment_reference'],
                'expected'     => $authoritative,
                'received'     => $verified,
            ]);
            $this->db->execute(
                "UPDATE mobile_payments SET status='failed', failure_reason=:r, updated_at=NOW() WHERE id=:id",
                [
                    'r'  => "Amount mismatch: expected ETB {$authoritative}, received ETB {$verified}",
                    'id' => $paymentId,
                ]
            );
            throw new \RuntimeException(
                'Payment amount mismatch. Expected ETB ' . number_format($authoritative, 2) .
                ', received ETB ' . number_format($verified, 2) . '. Payment rejected.'
            );
        }

        // ── Atomically create sale + deduct inventory ─────────────────────
        $saleId = $this->completeSaleFromPayment($payment, $verification);

        return [
            'status'    => 'paid',
            'sale_id'   => $saleId,
            'reference' => $payment['payment_reference'],
            'message'   => 'Payment verified. Sale completed.',
        ];
    }

    // =========================================================
    // cancelPayment()
    // Called when the seller clicks Cancel in the QR modal.
    // Only works on PENDING payments.
    // =========================================================
    public function cancelPayment(int $paymentId, int $sellerId): void
    {
        $payment = $this->db->fetchOne(
            "SELECT id, status, seller_id, payment_reference FROM mobile_payments WHERE id = :id",
            ['id' => $paymentId]
        );

        if (!$payment) {
            throw new \RuntimeException('Payment not found.');
        }
        if ((int)$payment['seller_id'] !== $sellerId) {
            throw new \RuntimeException('You can only cancel your own payments.');
        }
        if ($payment['status'] !== 'pending') {
            throw new \RuntimeException('Only pending payments can be cancelled.');
        }

        $this->db->execute(
            "UPDATE mobile_payments
             SET status='cancelled', cancelled_at=NOW(), failure_reason='Cancelled by seller', updated_at=NOW()
             WHERE id=:id AND status='pending'",
            ['id' => $paymentId]
        );

        Logger::info('Mobile payment cancelled', [
            'payment_id' => $paymentId,
            'reference'  => $payment['payment_reference'],
            'seller_id'  => $sellerId,
        ]);
    }

    // =========================================================
    // getPaymentStatus()
    // Lightweight status check — used by the polling endpoint.
    // Returns minimal data — never includes credentials or raw API responses.
    // =========================================================
    public function getPaymentStatus(int $paymentId): array
    {
        $payment = $this->db->fetchOne(
            "SELECT id, payment_reference, provider, amount, status,
                    sale_id, expires_at, created_at
             FROM mobile_payments
             WHERE id = :id",
            ['id' => $paymentId]
        );

        if (!$payment) {
            throw new \RuntimeException('Payment not found.');
        }

        return [
            'payment_id' => (int)$payment['id'],
            'reference'  => $payment['payment_reference'],
            'provider'   => $payment['provider'],
            'amount'     => (float)$payment['amount'],
            'status'     => $payment['status'],
            'sale_id'    => $payment['sale_id'] ? (int)$payment['sale_id'] : null,
            'expires_at' => $payment['expires_at'],
            'is_expired' => strtotime($payment['expires_at']) < time(),
        ];
    }

    // =========================================================
    // completeSaleFromPayment()
    // PRIVATE — only called after payment.status = 'paid'.
    // Mirrors POSService::processSale() but for mobile payments.
    // All within a single DB transaction + SELECT FOR UPDATE lock.
    // =========================================================
    private function completeSaleFromPayment(array $payment, $verification): int
    {
        $saleId = 0;

        $this->db->transaction(function() use ($payment, $verification, &$saleId) {

            // ── Re-lock the payment row ── prevents double-processing
            $locked = $this->db->fetchOne(
                "SELECT id, status FROM mobile_payments WHERE id = :id FOR UPDATE",
                ['id' => $payment['id']]
            );

            if (!$locked || $locked['status'] !== 'pending') {
                // Another process already handled this payment — abort
                if ($locked && $locked['status'] === 'paid' && !empty($payment['sale_id'])) {
                    $saleId = (int)$payment['sale_id'];
                }
                return; // Nothing to do — idempotent
            }

            // ── Re-validate cart from the snapshot (never from client) ───
            $cartSnapshot = json_decode($payment['cart_snapshot'], true);
            if (!is_array($cartSnapshot) || empty($cartSnapshot)) {
                throw new \RuntimeException('Invalid cart snapshot. Cannot complete sale.');
            }

            $shopId     = (int)$payment['shop_id'];
            $sellerId   = (int)$payment['seller_id'];
            $shiftId    = $payment['shift_id'] ? (int)$payment['shift_id'] : null;

            // Re-check stock availability before final deduction
            foreach ($cartSnapshot as &$item) {
                $row = $this->db->fetchOne(
                    "SELECT p.unit_price, si.available_quantity
                     FROM products p
                     LEFT JOIN shop_inventory si ON si.product_id=p.id AND si.shop_id=:sid
                     WHERE p.id=:pid AND p.is_active=1",
                    ['sid' => $shopId, 'pid' => $item['product_id']]
                );

                if (!$row) {
                    throw new \RuntimeException("Product ID {$item['product_id']} not found.");
                }
                if ((float)$row['available_quantity'] < (float)$item['quantity']) {
                    throw new \RuntimeException(
                        "Insufficient stock for product ID {$item['product_id']} at time of sale completion."
                    );
                }
                // Use DB price — never the snapshot price (belt-and-suspenders)
                $item['unit_price'] = (float)$row['unit_price'];
                $item['subtotal']   = round((float)$item['quantity'] * (float)$row['unit_price'], 2);
            }
            unset($item);

            // Recalculate total
            $totalAmount = round(array_sum(array_column($cartSnapshot, 'subtotal')), 2);

            // Create sale record
            $salesModel    = new SalesModel();
            $shiftModel    = new ShiftModel();
            $saleNumber    = $salesModel->nextSaleNumber();
            $receiptNumber = $salesModel->nextReceiptNumber();
            $today         = date('Y-m-d');
            $now           = date('Y-m-d H:i:s');

            $saleId = $salesModel->create([
                'sale_number'      => $saleNumber,
                'receipt_number'   => $receiptNumber,
                'shop_id'          => $shopId,
                'seller_id'        => $sellerId,
                'shift_id'         => $shiftId,
                'status'           => 'completed',
                'payment_method'   => 'mobile_money',
                'payment_provider' => $payment['provider'],
                'payment_reference'=> $payment['payment_reference'],
                'payment_status'   => 'paid',
                'verified_at'      => $now,
                'subtotal'         => $totalAmount,
                'discount_amount'  => 0.00,
                'total_amount'     => $totalAmount,
                'cash_received'    => 0.00,
                'change_given'     => 0.00,
                'total_items'      => count($cartSnapshot),
                'notes'            => $payment['notes'],
                'sale_date'        => $today,
                'completed_at'     => $now,
            ]);

            // Insert sale items + deduct inventory
            foreach ($cartSnapshot as $item) {
                // Sale line item
                $this->db->execute(
                    "INSERT INTO sale_items
                        (sale_id, product_id, unit_id, product_name, product_sku, quantity, unit_price, subtotal)
                     VALUES
                        (:sale_id,:pid,:uid,:pname,:psku,:qty,:price,:sub)",
                    [
                        'sale_id' => $saleId,
                        'pid'     => $item['product_id'],
                        'uid'     => $item['unit_id'] ?? null,
                        'pname'   => $item['product_name'],
                        'psku'    => $item['product_sku'] ?? null,
                        'qty'     => $item['quantity'],
                        'price'   => $item['unit_price'],
                        'sub'     => $item['subtotal'],
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
                        'qty'  => $item['quantity'],
                        'qty2' => $item['quantity'],
                        'sid'  => $shopId,
                        'pid'  => $item['product_id'],
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
                         :before, 0, :out, :after,
                         'sales',:ref_id,:ref_num,
                         :user, :notes, NOW())",
                    [
                        'loc'     => $shopId,
                        'pid'     => $item['product_id'],
                        'before'  => $before,
                        'out'     => $item['quantity'],
                        'after'   => $after,
                        'ref_id'  => $saleId,
                        'ref_num' => $saleNumber,
                        'user'    => $sellerId,
                        'notes'   => "Mobile sale: {$item['product_name']}",
                    ]
                );
            }

            // Update shift totals
            if ($shiftId) {
                $shiftModel->incrementTotals($shiftId, $totalAmount);
            }

            // Update mobile_payments row — link to sale, mark paid
            $this->db->execute(
                "UPDATE mobile_payments
                 SET status          = 'paid',
                     sale_id         = :sid,
                     provider_txn_id = :txn,
                     verified_at     = NOW(),
                     updated_at      = NOW()
                 WHERE id = :id",
                [
                    'sid' => $saleId,
                    'txn' => $verification->providerTxnId,
                    'id'  => $payment['id'],
                ]
            );

            // Audit trail
            try {
                $auditLog = new ActivityLogModel();
                $auditLog->logBusiness([
                    'action'       => ActivityLogModel::ACTION_SALE_CREATED,
                    'module'       => 'shop',
                    'entity_type'  => 'sale',
                    'entity_label' => "Receipt #{$receiptNumber} — ETB " . number_format($totalAmount, 2),
                    'record_id'    => $saleId,
                    'description'  => "Mobile payment sale ({$payment['provider']}): " .
                                      count($cartSnapshot) . " item(s), " .
                                      "Total ETB " . number_format($totalAmount, 2) . ", " .
                                      "Ref: {$payment['payment_reference']}",
                    'shop_id'      => $shopId,
                    'new_values'   => [
                        'sale_number'       => $saleNumber,
                        'receipt_number'    => $receiptNumber,
                        'total_amount'      => $totalAmount,
                        'payment_method'    => 'mobile_money',
                        'payment_provider'  => $payment['provider'],
                        'payment_reference' => $payment['payment_reference'],
                    ],
                ]);
            } catch (\Throwable) {
                // Non-critical — never break a sale over an audit failure
            }

            Logger::info('Mobile payment sale completed', [
                'payment_id' => $payment['id'],
                'sale_id'    => $saleId,
                'reference'  => $payment['payment_reference'],
            ]);
        });

        return $saleId;
    }

    // =========================================================
    // validateCartItems()
    // Loads authoritative prices from DB for each cart item.
    // NEVER trusts prices from the client.
    // Returns validated items with DB prices + available stock.
    // =========================================================
    private function validateCartItems(int $shopId, array $items): array
    {
        $validated = [];

        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $quantity  = (float)($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                throw new \RuntimeException('Invalid item data. Please refresh and try again.');
            }

            $row = $this->db->fetchOne(
                "SELECT
                    p.id, p.name, p.sku, p.unit_id,
                    p.unit_price                               AS official_price,
                    COALESCE(si.available_quantity, 0)         AS available_qty
                 FROM products p
                 LEFT JOIN shop_inventory si
                   ON si.product_id = p.id AND si.shop_id = :sid
                 WHERE p.id = :pid AND p.is_active = 1",
                ['sid' => $shopId, 'pid' => $productId]
            );

            if (!$row) {
                throw new \RuntimeException("Product ID {$productId} not found or inactive.");
            }

            $officialPrice = $row['official_price'] !== null ? (float)$row['official_price'] : null;
            if ($officialPrice === null || $officialPrice <= 0) {
                throw new \RuntimeException(
                    "Sale blocked: \"{$row['name']}\" does not have an approved selling price."
                );
            }

            $available = (float)$row['available_qty'];
            if ($available < $quantity) {
                throw new \RuntimeException(
                    "Insufficient stock for \"{$row['name']}\". " .
                    "Available: {$available}, Requested: {$quantity}."
                );
            }

            $validated[] = [
                'product_id'   => $productId,
                'unit_id'      => $row['unit_id'],
                'product_name' => $row['name'],
                'product_sku'  => $row['sku'],
                'quantity'     => $quantity,
                'unit_price'   => $officialPrice,
                'subtotal'     => round($quantity * $officialPrice, 2),
                'available'    => $available,
            ];
        }

        return $validated;
    }

    // =========================================================
    // devSimulatePayment()
    // DEVELOPMENT ONLY — simulates a successful provider callback.
    // Must be called only when APP_ENV !== 'production'.
    // Injects a fake PaymentVerification object and calls
    // completeSaleFromPayment() directly, bypassing the provider.
    // =========================================================
    public function devSimulatePayment(int $paymentId, int $requestingUserId): array
    {
        if (defined('APP_ENV') && APP_ENV === 'production') {
            throw new \RuntimeException('devSimulatePayment is not available in production.');
        }

        $payment = $this->db->fetchOne(
            "SELECT * FROM mobile_payments WHERE id = :id",
            ['id' => $paymentId]
        );

        if (!$payment) {
            throw new \RuntimeException('Payment record not found.');
        }

        // Already finalised — return idempotent result
        if (in_array($payment['status'], ['paid', 'failed', 'cancelled', 'expired'], true)) {
            return [
                'status'    => $payment['status'],
                'sale_id'   => $payment['sale_id'],
                'reference' => $payment['payment_reference'],
                'message'   => 'Payment already in final state: ' . $payment['status'],
            ];
        }

        // Build a fake successful PaymentVerification
        $fakeVerification = new PaymentVerification(
            status:         'paid',
            verifiedAmount: (float)$payment['amount'],
            providerTxnId:  'DEV-SIM-' . strtoupper(bin2hex(random_bytes(4))),
            message:        'Development simulation — payment confirmed',
            rawResponse:    ['dev_mode' => true, 'simulated_at' => date('Y-m-d H:i:s')],
        );

        Logger::info('DEV: Simulating payment confirmation', [
            'payment_id'  => $paymentId,
            'reference'   => $payment['payment_reference'],
            'amount'      => $payment['amount'],
            'requested_by'=> $requestingUserId,
        ]);

        $saleId = $this->completeSaleFromPayment($payment, $fakeVerification);

        return [
            'status'    => 'paid',
            'sale_id'   => $saleId,
            'reference' => $payment['payment_reference'],
            'message'   => 'Development simulation: payment confirmed and sale created.',
        ];
    }

    // =========================================================
    // generateReference()
    // Generates a unique payment reference: AUPOS-YYYYMMDD-NNNNNN
    // Collision-safe: uses a DB count + date + retry on duplicate.
    // =========================================================
    private function generateReference(): string
    {
        $date = date('Ymd');
        $attempt = 0;

        do {
            $count = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM mobile_payments WHERE payment_reference LIKE :pattern",
                ['pattern' => "AUPOS-{$date}-%"]
            );
            $reference = 'AUPOS-' . $date . '-' . str_pad((string)($count + 1 + $attempt), 6, '0', STR_PAD_LEFT);

            // Verify it doesn't already exist (belt-and-suspenders)
            $exists = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM mobile_payments WHERE payment_reference = :ref",
                ['ref' => $reference]
            );
            $attempt++;
        } while ($exists > 0 && $attempt < 10);

        if ($exists > 0) {
            // Fallback: add microseconds to guarantee uniqueness
            $reference = 'AUPOS-' . $date . '-' . substr((string)microtime(false), 2, 6);
        }

        return $reference;
    }
}
