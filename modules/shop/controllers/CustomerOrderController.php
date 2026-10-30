<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 6
 * =========================================================
 * File    : modules/shop/controllers/CustomerOrderController.php
 * Purpose : Shop-side customer pickup order management.
 *           Salesperson views paid orders and processes pickup.
 *
 * AUTHORIZATION
 *   Salesperson — restricted to their assigned shop (server-side).
 *   GM/Admin    — can view any shop's orders.
 *   Shop isolation is enforced in EVERY query via shop_id filter.
 *
 * Routes:
 *   GET  /customer/shop-orders               → queue()
 *   GET  /customer/shop-orders/{id}          → show()
 *   POST /customer/shop-orders/{id}/prepare  → markPreparing()
 *   POST /customer/shop-orders/{id}/ready    → markReady()
 *   POST /customer/shop-orders/{id}/complete → complete()
 *   GET  /api/customer/shop-orders/search    → apiSearch()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Shop\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\Logger;
use AAM\Core\SessionManager;
use AAM\Customer\Services\CustomerOrderService;
use AAM\Customer\Services\CheckoutService;
use AAM\Notifications\NotificationService;

class CustomerOrderController extends BaseController
{
    private CustomerOrderService $orderService;
    private NotificationService  $notifService;
    private Database             $db;

    public function __construct()
    {
        parent::__construct();
        $this->orderService = new CustomerOrderService();
        $this->notifService = new NotificationService();
        $this->db           = Database::getInstance();
    }

    // ──────────────────────────────────────────────────────
    // RESOLVE SHOP — salesperson is always scoped to their shop
    // GM/Admin can optionally scope to a specific shop via ?shop_id=
    // ──────────────────────────────────────────────────────
    private function resolveShopId(): int
    {
        $role = $this->userRole();

        // Admin/GM: optional query override (for viewing a specific shop's orders)
        if (in_array($role, [ROLE_GENERAL_MANAGER, ROLE_ADMIN], true)) {
            $override = (int)$this->query('shop_id', 0);
            if ($override > 0) {
                return $override;
            }
            // GM/Admin without filter: return 0 (show all — handled in queue)
            return 0;
        }

        // Salesperson: always their assigned shop — NEVER another shop
        $assigned = (int)SessionManager::get(SESSION_LOCATION_ID, 0);
        $ltype    = SessionManager::get(SESSION_LOCATION_TYPE, '');

        if ($ltype === 'shop' && $assigned > 0) {
            return $assigned;
        }

        // Fallback: look up from DB
        $row = $this->db->fetchOne(
            "SELECT location_id FROM user_location_assignments
             WHERE user_id = :uid AND location_type = 'shop'
             ORDER BY assigned_at DESC LIMIT 1",
            ['uid' => $this->userId()]
        );

        return $row ? (int)$row['location_id'] : 0;
    }

    /**
     * Strict shop enforcement — used for write operations.
     * Salesperson MUST have a shop assignment or action is rejected.
     * Returns shop_id > 0 or null on failure.
     */
    private function requireShopId(): ?int
    {
        $role   = $this->userRole();
        $shopId = $this->resolveShopId();

        if ($shopId <= 0 && !in_array($role, [ROLE_GENERAL_MANAGER, ROLE_ADMIN], true)) {
            return null;
        }

        return $shopId > 0 ? $shopId : null;
    }

    // ──────────────────────────────────────────────────────
    // AUDIT HELPER — record every status change
    // ──────────────────────────────────────────────────────
    private function auditOrderStatus(
        int    $orderId,
        string $orderRef,
        string $oldStatus,
        string $newStatus,
        int    $shopId,
        int    $userId
    ): void {
        try {
            $this->db->execute(
                "INSERT INTO audit_logs
                    (user_id, action_type, module, table_affected, record_id,
                     old_values, new_values, role_name, entity_type, entity_label,
                     description, location_type, shop_id, action_at)
                 VALUES
                    (:uid, 'update', 'customer_orders', 'customer_orders', :rid,
                     :old, :new, :role, 'customer_order', :label,
                     :desc, 'shop', :shop, NOW())",
                [
                    'uid'   => $userId,
                    'rid'   => $orderId,
                    'old'   => json_encode(['order_status' => $oldStatus]),
                    'new'   => json_encode(['order_status' => $newStatus]),
                    'role'  => $this->userRole() ?? '',
                    'label' => $orderRef,
                    'desc'  => "Customer order {$orderRef}: {$oldStatus} → {$newStatus}",
                    'shop'  => $shopId,
                ]
            );
        } catch (\Throwable $e) {
            Logger::warning('CustomerOrder audit failed: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────
    // NOTIFY CUSTOMER — order ready for pickup
    // ──────────────────────────────────────────────────────
    private function notifyCustomerReady(array $order): void
    {
        try {
            $ref      = $order['order_reference'] ?? '';
            $shopName = $order['shop_name'] ?? 'the shop';

            // For session-based customers: store in session for the customer storefront
            // The customer "My Orders" page checks this flag to show an alert
            $sessionKey = 'co_ready_' . $order['id'];
            if (!isset($_SESSION[$sessionKey])) {
                $_SESSION[$sessionKey] = time();
            }

            // If customer has a user account — send internal notification
            if (!empty($order['customer_user_id'])) {
                $this->notifService->sendToUser(
                    (int)$order['customer_user_id'],
                    NOTIF_CUSTOMER_ORDER_READY,
                    "Order {$ref} Ready for Pickup",
                    "Your order {$ref} is ready for pickup at {$shopName}. Please proceed to the shop.",
                    'high',
                    null,
                    'customer_order',
                    (int)$order['id'],
                    "/customer/orders/{$order['id']}"
                );
            }

            Logger::info('Customer notified: order ready for pickup', [
                'order_id'  => $order['id'],
                'order_ref' => $ref,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Failed to notify customer of ready order: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/shop-orders — Order queue for this shop
    // ──────────────────────────────────────────────────────
    public function queue(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER, ROLE_ADMIN);

        $shopId = $this->resolveShopId();

        if (!$shopId && !in_array($this->userRole(), [ROLE_GENERAL_MANAGER, ROLE_ADMIN], true)) {
            $this->redirectWith('dashboard', FLASH_ERROR, 'No shop assigned to your account.');
            return;
        }

        // Get shop info
        $shop = $shopId
            ? $this->db->fetchOne("SELECT id, name, location FROM shops WHERE id = :id", ['id' => $shopId])
            : null;

        // ── Filters ──────────────────────────────────────
        $statusFilter  = trim($this->query('status',    ''));
        $payFilter     = trim($this->query('payment',   ''));
        $search        = trim($this->query('search',    ''));
        $dateFrom      = trim($this->query('date_from', ''));
        $dateTo        = trim($this->query('date_to',   ''));
        $page          = max(1, (int)$this->query('page', 1));
        $perPage       = 20;
        $offset        = ($page - 1) * $perPage;

        // ── Build query ───────────────────────────────────
        $where  = [];
        $params = [];

        // Shop scope — CRITICAL: always filter by shop_id for non-admin
        if ($shopId > 0) {
            $where[]       = 'co.shop_id = :shop_id';
            $params['shop_id'] = $shopId;
        }

        // Status filter
        if ($statusFilter && $statusFilter !== 'all') {
            // 'active' = pseudo-filter for the default queue view
            if ($statusFilter === 'active') {
                $where[] = "co.order_status IN ('paid','preparing','ready_for_pickup')";
            } else {
                $where[]           = 'co.order_status = :ostatus';
                $params['ostatus'] = $statusFilter;
            }
        }

        // Payment filter
        if ($payFilter) {
            $where[]          = 'co.payment_status = :pstatus';
            $params['pstatus'] = $payFilter;
        }

        // Date filters
        if ($dateFrom !== '') {
            $where[]          = 'DATE(co.created_at) >= :dfrom';
            $params['dfrom']  = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[]          = 'DATE(co.created_at) <= :dto';
            $params['dto']    = $dateTo;
        }

        // Search — order reference, pickup code, customer name, customer phone, payment reference
        if ($search !== '') {
            $where[]          = "(co.order_reference LIKE :sq
                                  OR co.customer_name LIKE :sq2
                                  OR co.customer_phone LIKE :sq3
                                  OR co.payment_reference LIKE :sq4
                                  OR co.pickup_code LIKE :sq5)";
            $params['sq']  = '%' . $search . '%';
            $params['sq2'] = '%' . $search . '%';
            $params['sq3'] = '%' . $search . '%';
            $params['sq4'] = '%' . $search . '%';
            $params['sq5'] = '%' . $search . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $total = (int)($this->db->fetchOne(
            "SELECT COUNT(*) AS c
             FROM customer_orders co
             {$whereClause}",
            $params
        )['c'] ?? 0);

        // Fetch page
        $params['lim'] = $perPage;
        $params['off'] = $offset;

        $orders = $this->db->fetchAll(
            "SELECT co.*,
                    pm.name AS payment_method_name,
                    s.name  AS shop_name
             FROM customer_orders co
             LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
             LEFT JOIN shops s ON s.id = co.shop_id
             {$whereClause}
             ORDER BY
               CASE co.order_status
                 WHEN 'ready_for_pickup' THEN 1
                 WHEN 'paid'             THEN 2
                 WHEN 'preparing'        THEN 3
                 ELSE 4
               END,
               co.paid_at ASC, co.created_at ASC
             LIMIT :lim OFFSET :off",
            $params
        );

        $totalPages = (int)ceil($total / max(1, $perPage));

        // ── Queue KPIs ────────────────────────────────────
        $kpiWhere  = $shopId > 0 ? 'WHERE co.shop_id = :kshop' : '';
        $kpiParams = $shopId > 0 ? ['kshop' => $shopId] : [];

        $kpis = $this->db->fetchOne(
            "SELECT
                SUM(CASE WHEN co.order_status = 'paid'             THEN 1 ELSE 0 END) AS new_count,
                SUM(CASE WHEN co.order_status = 'preparing'        THEN 1 ELSE 0 END) AS preparing_count,
                SUM(CASE WHEN co.order_status = 'ready_for_pickup' THEN 1 ELSE 0 END) AS ready_count,
                SUM(CASE WHEN co.order_status IN ('paid','preparing','ready_for_pickup') THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN co.order_status = 'completed' AND DATE(co.completed_at) = CURDATE() THEN 1 ELSE 0 END) AS completed_today
             FROM customer_orders co
             {$kpiWhere}",
            $kpiParams
        ) ?? [];

        $this->render('shop.customer_orders.queue', [
            'pageTitle'    => 'Customer Orders | ' . ($shop['name'] ?? 'Shop'),
            'shop'         => $shop,
            'shopId'       => $shopId,
            'orders'       => $orders,
            'kpis'         => $kpis,
            'filters'      => [
                'status'    => $statusFilter,
                'payment'   => $payFilter,
                'search'    => $search,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
            ],
            'pagination'   => [
                'total'      => $total,
                'page'       => $page,
                'perPage'    => $perPage,
                'totalPages' => $totalPages,
                'from'       => $total > 0 ? $offset + 1 : 0,
                'to'         => min($offset + $perPage, $total),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────
    // GET /customer/shop-orders/{id} — Order detail
    // ──────────────────────────────────────────────────────
    public function show(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER, ROLE_ADMIN);

        $shopId = $this->resolveShopId();

        // For salesperson: strict shop enforcement
        // For GM/Admin: can view any order (shopId may be 0)
        $order = $shopId > 0
            ? $this->orderService->getOrderForShop((int)$id, $shopId)
            : $this->orderService->getOrderById((int)$id);

        if (!$order) {
            $this->redirectWith('customer/shop-orders', FLASH_ERROR, 'Order not found or access denied.');
            return;
        }

        // Fetch the audit trail from the dedicated status changes table
        // Falls back to audit_logs if the table is not yet migrated
        $auditTrail = [];
        try {
            $auditTrail = $this->db->fetchAll(
                "SELECT
                    cosc.id,
                    cosc.old_status,
                    cosc.new_status,
                    cosc.notes       AS description,
                    cosc.changed_at  AS action_at,
                    cosc.changed_by  AS user_id,
                    CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                    u.username
                 FROM customer_order_status_changes cosc
                 LEFT JOIN users u ON u.id = cosc.changed_by
                 WHERE cosc.order_id = :id
                 ORDER BY cosc.changed_at ASC",
                ['id' => $id]
            );
        } catch (\Throwable) {
            // Table may not exist yet on older installs — try audit_logs fallback
            try {
                $rawTrail = $this->db->fetchAll(
                    "SELECT al.*,
                            CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                            u.username
                     FROM audit_logs al
                     LEFT JOIN users u ON u.id = al.user_id
                     WHERE al.table_affected = 'customer_orders'
                       AND al.record_id = :id
                     ORDER BY al.action_at ASC",
                    ['id' => $id]
                );
                // Normalize to the same shape used by the view:
                // old_status / new_status extracted from JSON old_values/new_values
                foreach ($rawTrail as $entry) {
                    $oldVals = [];
                    $newVals = [];
                    if (!empty($entry['old_values'])) {
                        $oldVals = json_decode($entry['old_values'], true) ?? [];
                    }
                    if (!empty($entry['new_values'])) {
                        $newVals = json_decode($entry['new_values'], true) ?? [];
                    }
                    $auditTrail[] = [
                        'old_status'  => $oldVals['order_status'] ?? '',
                        'new_status'  => $newVals['order_status'] ?? '',
                        'description' => $entry['description'] ?? $entry['action_type'] ?? '',
                        'action_at'   => $entry['action_at'],
                        'user_id'     => $entry['user_id'],
                        'user_name'   => $entry['user_name'] ?? 'System',
                        'username'    => $entry['username']  ?? '',
                    ];
                }
            } catch (\Throwable) {
                // Non-fatal — proceed without audit trail
            }
        }

        $this->render('shop.customer_orders.show', [
            'pageTitle'   => 'Order ' . $order['order_reference'],
            'order'       => $order,
            'shopId'      => $shopId,
            'auditTrail'  => $auditTrail,
            'canProcess'  => ($this->userRole() === ROLE_SALESPERSON),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/shop-orders/{id}/confirm-cash
    //   Salesperson physically receives cash from customer
    //   and confirms it. Transitions:
    //     order_status:   pending_payment → paid
    //     payment_status: pending         → paid
    //
    // SECURITY (server-side, never trusts client):
    //   1. User authenticated + ROLE_SALESPERSON
    //   2. User belongs to this shop
    //   3. Order exists and belongs to this shop
    //   4. payment_method type = cash
    //   5. payment_status is still 'pending'
    //   6. order_status is 'pending_payment'
    //   7. Order is not cancelled/completed
    //   8. Amount taken from DB — never from POST body
    //   9. DB transaction with SELECT FOR UPDATE prevents
    //      two salespersons confirming the same order
    // ──────────────────────────────────────────────────────
    public function confirmCash(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON);

        $shopId = $this->requireShopId();
        if (!$shopId) {
            $this->redirectWith('dashboard', FLASH_ERROR, 'No shop assigned to your account.');
            return;
        }

        $orderId = (int)$id;
        $userId  = (int)$this->userId();

        // Load order with shop isolation
        $order = $this->orderService->getOrderForShop($orderId, $shopId);

        if (!$order) {
            $this->redirectWith('customer/shop-orders', FLASH_ERROR, 'Order not found or access denied.');
            return;
        }

        // Must be a cash payment
        $pmType = $order['payment_method_type'] ?? '';
        if ($pmType !== 'cash') {
            $this->redirectWith(
                'customer/shop-orders/' . $orderId,
                FLASH_ERROR,
                'This action is only valid for cash payment orders.'
            );
            return;
        }

        // Must be in pending_payment + payment pending
        if ($order['order_status'] !== 'pending_payment' || $order['payment_status'] !== 'pending') {
            $this->redirectWith(
                'customer/shop-orders/' . $orderId,
                FLASH_ERROR,
                'Payment is already confirmed or this order cannot be confirmed. Current status: ' .
                CustomerOrderService::statusLabel($order['order_status'])
            );
            return;
        }

        try {
            // Atomic transaction with SELECT FOR UPDATE to prevent double-confirmation
            $this->db->transaction(function() use ($orderId, $shopId, $userId, $order) {

                // Re-read with row lock — prevents two salespersons confirming simultaneously
                $locked = $this->db->fetchOne(
                    "SELECT id, order_status, payment_status
                     FROM customer_orders
                     WHERE id = :id AND shop_id = :shop FOR UPDATE",
                    ['id' => $orderId, 'shop' => $shopId]
                );

                if (!$locked) {
                    throw new \RuntimeException('Order not found.');
                }

                // Double-confirmation guard — re-check status inside the lock
                if ($locked['order_status'] !== 'pending_payment' || $locked['payment_status'] !== 'pending') {
                    throw new \RuntimeException(
                        'This payment has already been confirmed. Please refresh the page.'
                    );
                }

                // Mark order PAID — uses authoritative DB amount, never client value
                $this->db->execute(
                    "UPDATE customer_orders
                     SET order_status   = 'paid',
                         payment_status = 'paid',
                         paid_at        = NOW(),
                         processed_by   = :staff
                     WHERE id              = :id
                       AND order_status    = 'pending_payment'
                       AND payment_status  = 'pending'",
                    ['staff' => $userId, 'id' => $orderId]
                );

                // Status change log (customer_order_status_changes table)
                $this->db->execute(
                    "INSERT INTO customer_order_status_changes
                        (order_id, order_reference, shop_id, changed_by,
                         old_status, new_status, notes, changed_at)
                     VALUES
                        (:oid, :ref, :shop, :user,
                         'pending_payment', 'paid',
                         'Cash payment received and confirmed by salesperson', NOW())",
                    [
                        'oid'  => $orderId,
                        'ref'  => $order['order_reference'],
                        'shop' => $shopId,
                        'user' => $userId,
                    ]
                );
            });

        } catch (\RuntimeException $e) {
            $this->redirectWith(
                'customer/shop-orders/' . $orderId,
                FLASH_ERROR,
                $e->getMessage()
            );
            return;
        } catch (\Throwable $e) {
            Logger::error('confirmCash failed', [
                'order_id' => $orderId,
                'user_id'  => $userId,
                'error'    => $e->getMessage(),
            ]);
            $this->redirectWith(
                'customer/shop-orders/' . $orderId,
                FLASH_ERROR,
                'Failed to confirm payment. Please try again.'
            );
            return;
        }

        // Audit log (audit_logs table — consistent with other actions in this controller)
        $this->auditOrderStatus(
            $orderId, $order['order_reference'],
            'pending_payment', 'paid',
            $shopId, $userId
        );

        Logger::info('Cash payment confirmed', [
            'order_id'   => $orderId,
            'order_ref'  => $order['order_reference'],
            'shop_id'    => $shopId,
            'confirmed_by' => $userId,
            'amount'     => $order['total_amount'],
        ]);

        $this->redirectWith(
            'customer/shop-orders/' . $orderId,
            FLASH_SUCCESS,
            'Cash payment confirmed for order ' . $order['order_reference'] . '. You can now start preparing.'
        );
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/shop-orders/{id}/prepare — Mark preparing
    // ──────────────────────────────────────────────────────
    public function markPreparing(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON);

        $shopId = $this->requireShopId();
        if (!$shopId) {
            $this->redirectWith('dashboard', FLASH_ERROR, 'No shop assigned to your account.');
            return;
        }

        $order = $this->orderService->getOrderForShop((int)$id, $shopId);

        if (!$order) {
            $this->redirectWith('customer/shop-orders', FLASH_ERROR, 'Order not found.');
            return;
        }

        if ($order['order_status'] !== 'paid') {
            $this->redirectWith(
                'customer/shop-orders/' . $id,
                FLASH_ERROR,
                'Only PAID orders can be moved to preparing. Current status: ' .
                CustomerOrderService::statusLabel($order['order_status'])
            );
            return;
        }

        $userId = (int)$this->userId();
        $this->orderService->markPreparing((int)$id, $userId);

        // Audit trail
        $this->auditOrderStatus(
            (int)$id, $order['order_reference'],
            'paid', 'preparing',
            $shopId, $userId
        );

        $this->redirectWith(
            'customer/shop-orders/' . $id,
            FLASH_SUCCESS,
            'Order ' . $order['order_reference'] . ' is now being prepared.'
        );
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/shop-orders/{id}/ready — Mark ready for pickup
    // ──────────────────────────────────────────────────────
    public function markReady(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON);

        $shopId = $this->requireShopId();
        if (!$shopId) {
            $this->redirectWith('dashboard', FLASH_ERROR, 'No shop assigned to your account.');
            return;
        }

        $order        = $this->orderService->getOrderForShop((int)$id, $shopId);
        $instructions = trim((string)$this->input('instructions', ''));

        if (!$order) {
            $this->redirectWith('customer/shop-orders', FLASH_ERROR, 'Order not found.');
            return;
        }

        if (!in_array($order['order_status'], ['paid', 'preparing'], true)) {
            $this->redirectWith(
                'customer/shop-orders/' . $id,
                FLASH_ERROR,
                'Order cannot be marked ready from status: ' .
                CustomerOrderService::statusLabel($order['order_status'])
            );
            return;
        }

        $oldStatus = $order['order_status'];
        $userId    = (int)$this->userId();

        $this->orderService->markReady((int)$id, $userId, $instructions);

        // Notify customer
        $this->notifyCustomerReady($order);

        // Audit trail
        $this->auditOrderStatus(
            (int)$id, $order['order_reference'],
            $oldStatus, 'ready_for_pickup',
            $shopId, $userId
        );

        $this->redirectWith(
            'customer/shop-orders/' . $id,
            FLASH_SUCCESS,
            'Order ' . $order['order_reference'] . ' is ready for pickup. Customer has been notified.'
        );
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/shop-orders/{id}/complete — Complete pickup
    // ──────────────────────────────────────────────────────
    public function complete(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON);

        $shopId = $this->requireShopId();
        if (!$shopId) {
            $this->redirectWith('dashboard', FLASH_ERROR, 'No shop assigned to your account.');
            return;
        }

        // Pre-check: load order to capture old status for audit
        $order = $this->orderService->getOrderForShop((int)$id, $shopId);
        if (!$order) {
            $this->redirectWith('customer/shop-orders', FLASH_ERROR, 'Order not found.');
            return;
        }

        if ($order['order_status'] !== 'ready_for_pickup') {
            $this->redirectWith(
                'customer/shop-orders/' . $id,
                FLASH_ERROR,
                'Only orders with status READY_FOR_PICKUP can be completed. Current status: ' .
                CustomerOrderService::statusLabel($order['order_status'])
            );
            return;
        }

        $userId       = (int)$this->userId();
        $checkoutSvc  = new CheckoutService();
        $result       = $checkoutSvc->completePickup((int)$id, $shopId, $userId);

        if ($result['success']) {
            // Audit trail
            $this->auditOrderStatus(
                (int)$id, $order['order_reference'],
                'ready_for_pickup', 'completed',
                $shopId, $userId
            );

            $this->redirectWith(
                'customer/shop-orders',
                FLASH_SUCCESS,
                'Order ' . ($result['order_ref'] ?? '') . ' completed. Products handed to customer.'
            );
        } else {
            $this->redirectWith(
                'customer/shop-orders/' . $id,
                FLASH_ERROR,
                $result['message'] ?? 'Could not complete order. Please try again.'
            );
        }
    }

    // ──────────────────────────────────────────────────────
    // GET /api/customer/shop-orders/search — AJAX order search
    // Allows salesperson to search by order reference for pickup
    // ──────────────────────────────────────────────────────
    public function apiSearch(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER, ROLE_ADMIN);

        $q      = trim((string)$this->query('q', ''));
        $shopId = $this->resolveShopId();

        if (strlen($q) < 3) {
            $this->json(['success' => false, 'message' => 'Query too short.', 'results' => []]);
            return;
        }

        $where  = ['(co.order_reference LIKE :q OR co.customer_name LIKE :q2 OR co.payment_reference LIKE :q3 OR co.pickup_code LIKE :q4)'];
        $params = [
            'q'  => '%' . $q . '%',
            'q2' => '%' . $q . '%',
            'q3' => '%' . $q . '%',
            'q4' => '%' . $q . '%',
        ];

        // Salesperson: always restrict to their shop
        if ($shopId > 0) {
            $where[]        = 'co.shop_id = :shop_id';
            $params['shop_id'] = $shopId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $results = $this->db->fetchAll(
            "SELECT co.id, co.order_reference, co.pickup_code, co.customer_name, co.customer_phone,
                    co.order_status, co.payment_status, co.total_amount,
                    co.paid_at, co.shop_id,
                    s.name AS shop_name,
                    pm.name AS payment_method_name
             FROM customer_orders co
             LEFT JOIN shops s ON s.id = co.shop_id
             LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
             {$whereClause}
             ORDER BY co.created_at DESC
             LIMIT 10",
            $params
        );

        // Add status labels
        foreach ($results as &$r) {
            $r['status_label']  = CustomerOrderService::statusLabel($r['order_status']);
            $r['status_badge']  = CustomerOrderService::statusBadge($r['order_status']);
            $r['total_fmt']     = 'ETB ' . number_format((float)$r['total_amount'], 2);
            $r['pickup_code']   = $r['pickup_code'] ?? '';
        }
        unset($r);

        $this->json(['success' => true, 'results' => $results]);
    }
}
