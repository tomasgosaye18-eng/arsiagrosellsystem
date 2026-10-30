<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMOrderController.php
 * Purpose : GM customer order monitoring.
 *           Read-only view of all customer pickup orders
 *           across all shops. GM does NOT process orders.
 *
 * Routes  : GET /gm/orders          → index()
 *           GET /gm/orders/{id}     → show()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\GeneralManager\Services\GMCustomerOrderService;
use AAM\Customer\Services\CustomerOrderService;

class GMOrderController extends BaseController
{
    private GMCustomerOrderService $coSvc;
    private Database               $db;

    public function __construct()
    {
        parent::__construct();
        $this->coSvc = new GMCustomerOrderService();
        $this->db    = Database::getInstance();
    }

    // ── GET /gm/orders ────────────────────────────────────
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        // ── Filters from query string ─────────────────────
        $filters = [
            'status'         => trim($this->query('status',         '')),
            'payment_status' => trim($this->query('payment_status', '')),
            'shop_id'        => (int)$this->query('shop_id',        0),
            'date_from'      => trim($this->query('date_from',      '')),
            'date_to'        => trim($this->query('date_to',        '')),
            'search'         => trim($this->query('search',         '')),
        ];

        // Normalise empty shop_id
        if ($filters['shop_id'] <= 0) {
            unset($filters['shop_id']);
        }

        $page    = max(1, (int)$this->query('page', 1));
        $perPage = 25;

        $result  = $this->coSvc->getOrders($filters, $page, $perPage);
        $kpis    = $this->coSvc->getKpis();

        // Shops list for the filter dropdown
        $shops = [];
        try {
            $shops = $this->db->fetchAll(
                "SELECT id, COALESCE(shop_name, name) AS shop_name
                 FROM shops WHERE is_active = 1 ORDER BY shop_name ASC"
            ) ?: [];
        } catch (\Throwable) {
            // Non-fatal — filter dropdown just won't have options
        }

        $this->render('general_manager.orders.index', [
            'pageTitle'  => 'Customer Orders',
            'kpis'       => $kpis,
            'orders'     => $result['data'],
            'pagination' => [
                'total'      => $result['total'],
                'page'       => $result['current_page'],
                'perPage'    => $result['per_page'],
                'totalPages' => $result['last_page'],
                'from'       => $result['from'],
                'to'         => $result['to'],
            ],
            'filters'    => $filters,
            'shops'      => $shops,
        ]);
    }

    // ── GET /gm/orders/{id} ───────────────────────────────
    public function show(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $orderId = (int)$id;

        // Full order with items — no shop restriction for GM
        $order = null;
        try {
            $order = $this->db->fetchOne(
                "SELECT co.*,
                        s.name                               AS shop_name,
                        s.location                           AS shop_location,
                        pm.name                              AS payment_method_name,
                        pm.type                              AS payment_method_type,
                        CONCAT(u.first_name,' ',u.last_name) AS processed_by_name,
                        u.username                           AS processed_by_username
                 FROM customer_orders co
                 LEFT JOIN shops           s  ON s.id  = co.shop_id
                 LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
                 LEFT JOIN users           u  ON u.id  = co.processed_by
                 WHERE co.id = :id LIMIT 1",
                ['id' => $orderId]
            );
        } catch (\Throwable) {}

        if (!$order) {
            $this->redirectWith('gm/orders', FLASH_ERROR, 'Order not found.');
            return;
        }

        // Items
        $items = [];
        try {
            $items = $this->db->fetchAll(
                "SELECT coi.*,
                        u.abbreviation AS unit_abbr,
                        u.name         AS unit_name,
                        p.primary_image AS product_image
                 FROM customer_order_items coi
                 LEFT JOIN units_of_measure u ON u.id = coi.unit_id
                 LEFT JOIN products          p ON p.id = coi.product_id
                 WHERE coi.order_id = :oid
                 ORDER BY coi.id ASC",
                ['oid' => $orderId]
            ) ?: [];
        } catch (\Throwable) {}

        // Audit trail — status changes
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
                ['id' => $orderId]
            ) ?: [];
        } catch (\Throwable) {
            // Fallback to audit_logs
            try {
                $raw = $this->db->fetchAll(
                    "SELECT al.*,
                            CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                            u.username
                     FROM audit_logs al
                     LEFT JOIN users u ON u.id = al.user_id
                     WHERE al.table_affected = 'customer_orders' AND al.record_id = :id
                     ORDER BY al.action_at ASC",
                    ['id' => $orderId]
                ) ?: [];
                foreach ($raw as $entry) {
                    $oldVals = json_decode((string)($entry['old_values'] ?? ''), true) ?? [];
                    $newVals = json_decode((string)($entry['new_values'] ?? ''), true) ?? [];
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
            } catch (\Throwable) {}
        }

        $order['items'] = $items;

        $this->render('general_manager.orders.show', [
            'pageTitle'  => 'Order ' . ($order['order_reference'] ?? '#' . $orderId),
            'order'      => $order,
            'auditTrail' => $auditTrail,
        ]);
    }
}
