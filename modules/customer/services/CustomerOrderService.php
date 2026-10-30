<?php
/**
 * =========================================================
 * AU-AgroSale — CustomerOrderService
 * =========================================================
 * File    : modules/customer/services/CustomerOrderService.php
 * Purpose : Customer pickup order data access.
 *           Creates, retrieves, and manages customer_orders
 *           and customer_order_items.
 *
 * DOES NOT handle payment logic — that is PaymentService.
 * DOES NOT handle inventory — that is CustomerCheckoutService.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Services;

use AAM\Core\Database;

class CustomerOrderService
{
    private Database $db;

    /** Order reference prefix: ORD-YYYY-NNNNNN */
    private const ORDER_PREFIX = 'ORD';

    /**
     * Generate a short pickup verification code: 6 uppercase alphanumeric chars.
     * Example: X8K3M7
     * Displayed on the customer's order confirmation page.
     * Salesperson can search by this code at the pickup desk.
     */
    public function generatePickupCode(): string
    {
        $chars   = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // 34 chars (no I, O)
        $attempt = 0;
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $exists = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM customer_orders WHERE pickup_code = :c",
                ['c' => $code]
            );
            $attempt++;
        } while ($exists > 0 && $attempt < 20);
        return $code;
    }

    /**
     * Payment reference prefix: AUS-PAY-YYYYMMDD-XXXXX
     * Format matches branding spec (AU-AgroSale Payment).
     * Example: AUS-PAY-20260823-8F42K7
     */
    private const PAY_PREFIX = 'AUS-PAY';

    /** Minutes until an unpaid order expires */
    private const ORDER_TTL_MINUTES = 15;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ──────────────────────────────────────────────────────
    // REFERENCE GENERATORS
    // ──────────────────────────────────────────────────────

    /**
     * Generate a unique order reference: ORD-YYYY-NNNNNN
     * Called inside a transaction for row-count stability.
     */
    public function generateOrderReference(): string
    {
        $year  = date('Y');
        $count = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM customer_orders WHERE YEAR(created_at) = :y",
            ['y' => $year]
        );
        $seq = str_pad((string)($count + 1), 6, '0', STR_PAD_LEFT);
        $ref = self::ORDER_PREFIX . '-' . $year . '-' . $seq;

        // Collision guard (rare but possible under concurrent load)
        $exists = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM customer_orders WHERE order_reference = :r",
            ['r' => $ref]
        );
        if ($exists > 0) {
            // Append microseconds to disambiguate
            $ref .= '-' . substr((string)round(microtime(true) * 1000), -4);
        }

        return $ref;
    }

    /**
     * Generate a unique payment reference: AUS-PAY-YYYYMMDD-XXXXXX
     * Example: AUS-PAY-20260823-8F42K7
     *
     * Uses a random alphanumeric suffix (not a sequential counter alone)
     * so the reference is not guessable/predictable.
     * Collision-checked against DB before returning.
     */
    public function generatePaymentReference(): string
    {
        $date    = date('Ymd');
        $attempt = 0;

        do {
            // 6 random uppercase alphanumeric characters
            $chars  = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // 34 chars (no I, O to avoid confusion)
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $ref = self::PAY_PREFIX . '-' . $date . '-' . $suffix;

            $exists = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM customer_orders WHERE payment_reference = :r",
                ['r' => $ref]
            );
            $attempt++;
        } while ($exists > 0 && $attempt < 10);

        if ($exists > 0) {
            // Ultra-rare collision after 10 attempts — append microsecond fragment
            $ref .= substr(str_replace('.', '', (string)microtime(true)), -4);
        }

        return $ref;
    }

    // ──────────────────────────────────────────────────────
    // CREATE ORDER
    // ──────────────────────────────────────────────────────

    /**
     * Create a new customer order (status: pending_payment).
     * Items must be pre-validated by CheckoutService.
     *
     * Returns the new order ID.
     *
     * @param array $data {
     *   session_id:       string,
     *   customer_name:    string,
     *   customer_phone:   string|null,
     *   customer_email:   string|null,
     *   shop_id:          int,
     *   payment_method_id:int,
     *   payment_reference:string,
     *   total_amount:     float,
     *   subtotal:         float,
     *   items: array of {
     *     product_id, unit_id, product_name, product_sku,
     *     quantity, unit_price, subtotal
     *   }
     * }
     */
    public function createOrder(array $data): int
    {
        $orderId    = 0;
        $orderRef   = $this->generateOrderReference();
        $pickupCode = $this->generatePickupCode();
        $itemCount  = count($data['items'] ?? []);
        $expiresAt  = date('Y-m-d H:i:s', time() + self::ORDER_TTL_MINUTES * 60);
        $userId     = isset($data['user_id']) ? (int)$data['user_id'] : null;

        $this->db->transaction(function() use ($data, $orderRef, $pickupCode, $itemCount, $expiresAt, $userId, &$orderId) {
            // Try the full insert with user_id and pickup_code columns first.
            // Falls back gracefully if columns don't exist yet (migration pending).
            try {
                $this->db->execute(
                    "INSERT INTO customer_orders
                        (customer_session_id, user_id, customer_name, customer_phone, customer_email,
                         order_reference, pickup_code, shop_id, order_status, payment_status,
                         payment_method_id, payment_reference,
                         subtotal, total_amount, item_count, notes, expires_at)
                     VALUES
                        (:sid, :uid, :cname, :cphone, :cemail,
                         :oref, :pcode, :shop, 'pending_payment', 'pending',
                         :pm_id, :pref,
                         :sub, :total, :icount, :notes, :exp)",
                    [
                        'sid'    => $data['session_id']        ?? '',
                        'uid'    => $userId,
                        'cname'  => $data['customer_name']     ?? 'Customer',
                        'cphone' => $data['customer_phone']    ?? null,
                        'cemail' => $data['customer_email']    ?? null,
                        'oref'   => $orderRef,
                        'pcode'  => $pickupCode,
                        'shop'   => (int)$data['shop_id'],
                        'pm_id'  => (int)$data['payment_method_id'],
                        'pref'   => $data['payment_reference'],
                        'sub'    => (float)$data['subtotal'],
                        'total'  => (float)$data['total_amount'],
                        'icount' => $itemCount,
                        'notes'  => $data['notes'] ?? null,
                        'exp'    => $expiresAt,
                    ]
                );
            } catch (\Throwable $e) {
                // Fallback: user_id / pickup_code / item_count columns not yet added
                $this->db->execute(
                    "INSERT INTO customer_orders
                        (customer_session_id, customer_name, customer_phone, customer_email,
                         order_reference, shop_id, order_status, payment_status,
                         payment_method_id, payment_reference,
                         subtotal, total_amount, notes, expires_at)
                     VALUES
                        (:sid, :cname, :cphone, :cemail,
                         :oref, :shop, 'pending_payment', 'pending',
                         :pm_id, :pref,
                         :sub, :total, :notes, :exp)",
                    [
                        'sid'    => $data['session_id']        ?? '',
                        'cname'  => $data['customer_name']     ?? 'Customer',
                        'cphone' => $data['customer_phone']    ?? null,
                        'cemail' => $data['customer_email']    ?? null,
                        'oref'   => $orderRef,
                        'shop'   => (int)$data['shop_id'],
                        'pm_id'  => (int)$data['payment_method_id'],
                        'pref'   => $data['payment_reference'],
                        'sub'    => (float)$data['subtotal'],
                        'total'  => (float)$data['total_amount'],
                        'notes'  => $data['notes'] ?? null,
                        'exp'    => $expiresAt,
                    ]
                );
            }

            $orderId = (int)$this->db->lastInsertId();

            // Insert order items
            foreach ($data['items'] ?? [] as $item) {
                $this->db->execute(
                    "INSERT INTO customer_order_items
                        (order_id, product_id, unit_id, product_name, product_sku,
                         quantity, unit_price, subtotal)
                     VALUES
                        (:oid, :pid, :uid, :pname, :psku, :qty, :price, :sub)",
                    [
                        'oid'   => $orderId,
                        'pid'   => (int)$item['product_id'],
                        'uid'   => $item['unit_id'] ?? null,
                        'pname' => $item['product_name'],
                        'psku'  => $item['product_sku'] ?? null,
                        'qty'   => (float)$item['quantity'],
                        'price' => (float)$item['unit_price'],
                        'sub'   => (float)$item['subtotal'],
                    ]
                );
            }
        });

        return $orderId;
    }

    // ──────────────────────────────────────────────────────
    // LINK PAYMENT
    // ──────────────────────────────────────────────────────

    /**
     * Link a mobile_payments row to a customer order.
     */
    public function linkMobilePayment(int $orderId, int $mobilePaymentId): void
    {
        $this->db->execute(
            "UPDATE customer_orders SET mobile_payment_id = :mpid WHERE id = :oid",
            ['mpid' => $mobilePaymentId, 'oid' => $orderId]
        );
    }

    // ──────────────────────────────────────────────────────
    // STATUS TRANSITIONS
    // ──────────────────────────────────────────────────────

    /**
     * Mark order as paid (after payment verification).
     */
    public function markPaid(int $orderId): void
    {
        $this->db->execute(
            "UPDATE customer_orders
             SET order_status   = 'paid',
                 payment_status = 'paid',
                 paid_at        = NOW()
             WHERE id = :id",
            ['id' => $orderId]
        );
    }

    /**
     * Mark order as preparing (shop acknowledged).
     */
    public function markPreparing(int $orderId, int $staffId): void
    {
        $this->db->transaction(function() use ($orderId, $staffId) {
            // Validate current status before updating (strict transition)
            $order = $this->db->fetchOne(
                "SELECT id, order_status, order_reference, shop_id
                 FROM customer_orders WHERE id = :id FOR UPDATE",
                ['id' => $orderId]
            );
            if (!$order || $order['order_status'] !== 'paid') {
                throw new \RuntimeException(
                    'Cannot move to preparing: order is not in PAID status.'
                );
            }
            $this->db->execute(
                "UPDATE customer_orders
                 SET order_status = 'preparing',
                     processed_by = :staff
                 WHERE id = :id AND order_status = 'paid'",
                ['staff' => $staffId, 'id' => $orderId]
            );
            // Append-only status change log
            $this->logStatusChange(
                $orderId, $order['order_reference'],
                (int)$order['shop_id'], $staffId,
                'paid', 'preparing'
            );
        });
    }

    /**
     * Mark order ready for pickup (shop completed preparation).
     */
    public function markReady(int $orderId, int $staffId, string $instructions = ''): void
    {
        $this->db->transaction(function() use ($orderId, $staffId, $instructions) {
            $order = $this->db->fetchOne(
                "SELECT id, order_status, order_reference, shop_id
                 FROM customer_orders WHERE id = :id FOR UPDATE",
                ['id' => $orderId]
            );
            if (!$order || !in_array($order['order_status'], ['paid', 'preparing'], true)) {
                throw new \RuntimeException(
                    'Cannot mark ready: order must be in PAID or PREPARING status.'
                );
            }
            $oldStatus = $order['order_status'];
            $this->db->execute(
                "UPDATE customer_orders
                 SET order_status        = 'ready_for_pickup',
                     processed_by        = :staff,
                     ready_at            = NOW(),
                     pickup_instructions = :instr
                 WHERE id = :id AND order_status IN ('paid','preparing')",
                ['staff' => $staffId, 'id' => $orderId, 'instr' => $instructions]
            );
            $this->logStatusChange(
                $orderId, $order['order_reference'],
                (int)$order['shop_id'], $staffId,
                $oldStatus, 'ready_for_pickup',
                $instructions ?: null
            );
        });
    }

    /**
     * Mark order completed and link the resulting sale.
     */
    public function markCompleted(int $orderId, int $saleId, int $staffId): void
    {
        $this->db->transaction(function() use ($orderId, $saleId, $staffId) {
            $order = $this->db->fetchOne(
                "SELECT id, order_status, order_reference, shop_id
                 FROM customer_orders WHERE id = :id FOR UPDATE",
                ['id' => $orderId]
            );
            if (!$order || $order['order_status'] !== 'ready_for_pickup') {
                throw new \RuntimeException(
                    'Cannot complete: order must be in READY_FOR_PICKUP status.'
                );
            }
            $this->db->execute(
                "UPDATE customer_orders
                 SET order_status = 'completed',
                     sale_id      = :sid,
                     processed_by = :staff,
                     completed_at = NOW()
                 WHERE id = :id AND order_status = 'ready_for_pickup'",
                ['sid' => $saleId, 'staff' => $staffId, 'id' => $orderId]
            );
            $this->logStatusChange(
                $orderId, $order['order_reference'],
                (int)$order['shop_id'], $staffId,
                'ready_for_pickup', 'completed'
            );
        });
    }

    /**
     * Mark order payment failed.
     */
    public function markPaymentFailed(int $orderId, string $reason = ''): void
    {
        $this->db->execute(
            "UPDATE customer_orders
             SET order_status      = 'payment_failed',
                 payment_status    = 'failed',
                 cancelled_at      = NOW(),
                 cancellation_reason = :reason
             WHERE id = :id AND order_status = 'pending_payment'",
            ['reason' => $reason, 'id' => $orderId]
        );
    }

    /**
     * Cancel an order.
     */
    public function cancelOrder(int $orderId, string $reason = ''): void
    {
        $this->db->execute(
            "UPDATE customer_orders
             SET order_status        = 'cancelled',
                 payment_status      = 'cancelled',
                 cancelled_at        = NOW(),
                 cancellation_reason = :reason
             WHERE id = :id AND order_status IN ('pending_payment','payment_failed')",
            ['reason' => $reason, 'id' => $orderId]
        );
    }

    /**
     * Expire unpaid orders past their expiry time.
     * Called periodically (or on order page load).
     */
    public function expireOldOrders(): int
    {
        $result = $this->db->execute(
            "UPDATE customer_orders
             SET order_status        = 'cancelled',
                 payment_status      = 'expired',
                 cancelled_at        = NOW(),
                 cancellation_reason = 'Payment window expired'
             WHERE order_status   = 'pending_payment'
               AND payment_status = 'pending'
               AND expires_at     < NOW()"
        );
        return $result ?? 0;
    }

    // ──────────────────────────────────────────────────────
    // RETRIEVAL
    // ──────────────────────────────────────────────────────

    /**
     * Get a single order with its items (for the authenticated customer who owns it).
     *
     * SECURITY: Checks user_id (authenticated) first, falls back to customer_session_id.
     * Either match confirms ownership — never trusts the ID from the URL alone.
     * Returns null if neither user_id nor session_id matches.
     *
     * @param int    $orderId   Order ID from URL
     * @param string $sessionId Current PHP session_id()
     * @param int|null $userId  Authenticated user ID from SessionManager::userId()
     */
    public function getOrderForSession(int $orderId, string $sessionId, ?int $userId = null): ?array
    {
        // Build ownership clause:
        // - If authenticated: user_id match OR session_id match (covers session rotations)
        // - If anonymous: session_id match only
        if ($userId !== null && $userId > 0) {
            $ownerClause = '(co.user_id = :uid OR co.customer_session_id = :sid)';
            $params = ['id' => $orderId, 'uid' => $userId, 'sid' => $sessionId];
        } else {
            $ownerClause = 'co.customer_session_id = :sid';
            $params = ['id' => $orderId, 'sid' => $sessionId];
        }

        $order = $this->db->fetchOne(
            "SELECT co.*,
                    s.name       AS shop_name,
                    s.location   AS shop_location,
                    s.address    AS shop_address,
                    pm.name      AS payment_method_name,
                    pm.type      AS payment_method_type,
                    u.first_name AS staff_first_name,
                    u.last_name  AS staff_last_name
             FROM customer_orders co
             LEFT JOIN shops           s  ON s.id  = co.shop_id
             LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
             LEFT JOIN users           u  ON u.id  = co.processed_by
             WHERE co.id = :id
               AND {$ownerClause}
             LIMIT 1",
            $params
        );

        if (!$order) {
            return null;
        }

        $order['items'] = $this->getOrderItems((int)$order['id']);
        return $order;
    }

    /**
     * Get order by reference for authenticated/session-based customer.
     *
     * @param int|null $userId Authenticated user ID from SessionManager::userId()
     */
    public function getOrderByReferenceForSession(string $ref, string $sessionId, ?int $userId = null): ?array
    {
        if ($userId !== null && $userId > 0) {
            $ownerClause = '(co.user_id = :uid OR co.customer_session_id = :sid)';
            $params = ['ref' => $ref, 'uid' => $userId, 'sid' => $sessionId];
        } else {
            $ownerClause = 'co.customer_session_id = :sid';
            $params = ['ref' => $ref, 'sid' => $sessionId];
        }

        $order = $this->db->fetchOne(
            "SELECT co.*,
                    s.name       AS shop_name,
                    s.location   AS shop_location,
                    s.address    AS shop_address,
                    pm.name      AS payment_method_name,
                    pm.type      AS payment_method_type
             FROM customer_orders co
             LEFT JOIN shops           s  ON s.id  = co.shop_id
             LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
             WHERE co.order_reference = :ref
               AND {$ownerClause}
             LIMIT 1",
            $params
        );

        if (!$order) {
            return null;
        }

        $order['items'] = $this->getOrderItems((int)$order['id']);
        return $order;
    }

    /**
     * Get all orders for a session (My Orders page).
     * Simple version — kept for backward compatibility with success/payment pages.
     */
    public function getOrdersForSession(string $sessionId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $this->db->fetchAll(
            "SELECT co.*,
                    s.name  AS shop_name,
                    pm.name AS payment_method_name,
                    pm.type AS payment_method_type
             FROM customer_orders co
             LEFT JOIN shops           s  ON s.id  = co.shop_id
             LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
             WHERE co.customer_session_id = :sid
             ORDER BY co.created_at DESC
             LIMIT {$limit}",
            ['sid' => $sessionId]
        );
    }

    /**
     * Get orders for the authenticated customer with filter, sort, search, and pagination.
     *
     * SECURITY: All queries are scoped to user_id (when authenticated) OR
     * customer_session_id (anonymous). The session/user ID comes from server-side
     * session only — never from user input.
     *
     * @param string   $sessionId  Server-side session ID (never from user)
     * @param string   $filter     Order-status filter ('all' | status value)
     * @param string   $sort       'newest'|'oldest'|'total_high'|'total_low'
     * @param string   $search     Search by order reference or payment reference
     * @param int      $page       1-indexed page number
     * @param int      $perPage    Rows per page (max 50)
     * @param int|null $userId     Authenticated user ID from SessionManager::userId()
     * @return array{orders: array, total: int}
     */
    public function getOrdersForSessionPaginated(
        string $sessionId,
        string $filter   = 'all',
        string $sort     = 'newest',
        string $search   = '',
        int    $page     = 1,
        int    $perPage  = 10,
        ?int   $userId   = null
    ): array {
        $perPage = max(1, min(50, $perPage));
        $offset  = ($page - 1) * $perPage;

        // ── Build ownership clause ────────────────────────────────
        if ($userId !== null && $userId > 0) {
            // Authenticated: match by user_id OR session_id
            $ownerClause = '(co.user_id = :uid OR co.customer_session_id = :sid)';
            $params      = ['uid' => $userId, 'sid' => $sessionId];
        } else {
            // Anonymous: session only
            $ownerClause = 'co.customer_session_id = :sid';
            $params      = ['sid' => $sessionId];
        }

        $whereClauses = [$ownerClause];

        // Status filter
        if ($filter !== 'all') {
            $whereClauses[] = 'co.order_status = :status';
            $params['status'] = $filter;
        }

        // Search
        if ($search !== '') {
            $whereClauses[] = '(co.order_reference LIKE :q OR co.payment_reference LIKE :q2)';
            $params['q']  = '%' . $search . '%';
            $params['q2'] = '%' . $search . '%';
        }

        $whereSQL = implode(' AND ', $whereClauses);

        // ── ORDER BY ──────────────────────────────────────────────
        $orderSQL = match ($sort) {
            'oldest'     => 'co.created_at ASC',
            'total_high' => 'co.total_amount DESC, co.created_at DESC',
            'total_low'  => 'co.total_amount ASC, co.created_at DESC',
            default      => 'co.created_at DESC',
        };

        // ── Count ─────────────────────────────────────────────────
        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM customer_orders co WHERE {$whereSQL}",
            $params
        );

        // ── Data ──────────────────────────────────────────────────
        $orders = $this->db->fetchAll(
            "SELECT co.id,
                    co.order_reference,
                    co.payment_reference,
                    co.order_status,
                    co.payment_status,
                    co.total_amount,
                    co.subtotal,
                    co.created_at,
                    co.paid_at,
                    co.payment_method_id,
                    COALESCE(co.item_count, 0) AS item_count,
                    s.name       AS shop_name,
                    s.location   AS shop_location,
                    pm.name      AS payment_method_name,
                    pm.type      AS payment_method_type
             FROM customer_orders co
             LEFT JOIN shops           s  ON s.id  = co.shop_id
             LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
             WHERE {$whereSQL}
             ORDER BY {$orderSQL}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['orders' => $orders, 'total' => $total];
    }

    /**
     * Get order items (with product image for display on confirmation page).
     */
    public function getOrderItems(int $orderId): array
    {
        return $this->db->fetchAll(
            "SELECT coi.*,
                    u.abbreviation AS unit_abbr,
                    u.name         AS unit_name,
                    p.primary_image AS product_image
             FROM customer_order_items coi
             LEFT JOIN units_of_measure u ON u.id  = coi.unit_id
             LEFT JOIN products          p ON p.id  = coi.product_id
             WHERE coi.order_id = :oid
             ORDER BY coi.id ASC",
            ['oid' => $orderId]
        );
    }

    /**
     * Get orders for a shop (for salesperson order queue).
     * Only returns PAID / PREPARING / READY_FOR_PICKUP orders.
     */
    public function getShopOrderQueue(int $shopId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $this->db->fetchAll(
            "SELECT co.*,
                    pm.name AS payment_method_name
             FROM customer_orders co
             LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
             WHERE co.shop_id      = :shop
               AND co.order_status IN ('paid','preparing','ready_for_pickup')
             ORDER BY co.paid_at ASC, co.created_at ASC
             LIMIT {$limit}",
            ['shop' => $shopId]
        );
    }

    /**
     * Get a single order by ID for shop staff (no session restriction).
     * shop_id filter enforces shop isolation — salesperson cannot see
     * orders from a different shop.
     * If shopId = 0 (GM/Admin), returns order regardless of shop.
     */
    public function getOrderForShop(int $orderId, int $shopId): ?array
    {
        $shopClause = $shopId > 0 ? 'AND co.shop_id = :shop' : '';
        $params     = ['id' => $orderId];
        if ($shopId > 0) {
            $params['shop'] = $shopId;
        }

        $order = $this->db->fetchOne(
            "SELECT co.*,
                    s.name       AS shop_name,
                    s.location   AS shop_location,
                    pm.name      AS payment_method_name,
                    pm.type      AS payment_method_type,
                    CONCAT(u.first_name,' ',u.last_name) AS processed_by_name,
                    u.username   AS processed_by_username
             FROM customer_orders co
             LEFT JOIN shops           s  ON s.id  = co.shop_id
             LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
             LEFT JOIN users           u  ON u.id  = co.processed_by
             WHERE co.id = :id {$shopClause}
             LIMIT 1",
            $params
        );

        if (!$order) {
            return null;
        }

        $order['items'] = $this->getOrderItems($orderId);
        return $order;
    }

    /**
     * Get order by ID (admin/staff access, no shop restriction).
     */
    public function getOrderById(int $orderId): ?array
    {
        $order = $this->db->fetchOne(
            "SELECT co.*,
                    s.name       AS shop_name,
                    s.location   AS shop_location,
                    pm.name      AS payment_method_name,
                    pm.type      AS payment_method_type,
                    CONCAT(u.first_name,' ',u.last_name) AS processed_by_name,
                    u.username   AS processed_by_username
             FROM customer_orders co
             LEFT JOIN shops           s  ON s.id  = co.shop_id
             LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
             LEFT JOIN users           u  ON u.id  = co.processed_by
             WHERE co.id = :id LIMIT 1",
            ['id' => $orderId]
        );

        if (!$order) {
            return null;
        }

        $order['items'] = $this->getOrderItems($orderId);
        return $order;
    }

    // ──────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────

    /**
     * Append an immutable row to customer_order_status_changes.
     * Called inside a transaction in every markX() method.
     * Silently swallows errors to avoid blocking the main update.
     */
    private function logStatusChange(
        int     $orderId,
        string  $orderRef,
        int     $shopId,
        int     $changedBy,
        string  $oldStatus,
        string  $newStatus,
        ?string $notes = null
    ): void {
        try {
            $this->db->execute(
                "INSERT INTO customer_order_status_changes
                    (order_id, order_reference, shop_id, changed_by,
                     old_status, new_status, notes, changed_at)
                 VALUES
                    (:oid, :ref, :shop, :user,
                     :old, :new, :notes, NOW())",
                [
                    'oid'   => $orderId,
                    'ref'   => $orderRef,
                    'shop'  => $shopId,
                    'user'  => $changedBy,
                    'old'   => $oldStatus,
                    'new'   => $newStatus,
                    'notes' => $notes,
                ]
            );
        } catch (\Throwable $e) {
            // Non-fatal — log warning but never break the main operation
            \AAM\Core\Logger::warning('Order status change log failed: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'old'      => $oldStatus,
                'new'      => $newStatus,
            ]);
        }
    }

    public function getTtlMinutes(): int
    {
        return self::ORDER_TTL_MINUTES;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending_payment'  => 'Pending Payment',
            'paid'             => 'Paid',
            'preparing'        => 'Preparing',
            'ready_for_pickup' => 'Ready for Pickup',
            'completed'        => 'Completed',
            'cancelled'        => 'Cancelled',
            'payment_failed'   => 'Payment Failed',
            default            => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            'pending_payment'  => 'warning',
            'paid'             => 'info',
            'preparing'        => 'primary',
            'ready_for_pickup' => 'success',
            'completed'        => 'success',
            'cancelled'        => 'secondary',
            'payment_failed'   => 'danger',
            default            => 'secondary',
        };
    }

    public static function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            'pending'   => 'Pending',
            'paid'      => 'Paid',
            'failed'    => 'Failed',
            'expired'   => 'Expired',
            'cancelled' => 'Cancelled',
            default     => ucfirst($status),
        };
    }
}
