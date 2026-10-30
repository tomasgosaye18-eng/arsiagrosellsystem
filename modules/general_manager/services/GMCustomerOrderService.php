<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/services/GMCustomerOrderService.php
 * Purpose : Customer order data methods for the GM module.
 *           Keeps the main GeneralManagerService lean.
 *           All queries are safe — returns empty/zero on error.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Services;

use AAM\Core\Database;

class GMCustomerOrderService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Safe helpers ─────────────────────────────────────
    private function safeCount(string $sql, array $bind = []): int
    {
        try {
            return (int)($this->db->fetchColumn($sql, $bind) ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeDecimal(string $sql, array $bind = []): float
    {
        try {
            return (float)($this->db->fetchColumn($sql, $bind) ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function safeQuery(string $sql, array $bind = []): array
    {
        try {
            return $this->db->fetchAll($sql, $bind) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Dashboard KPIs ────────────────────────────────────

    /**
     * Customer order KPIs for the GM dashboard — cross-shop.
     */
    public function getKpis(): array
    {
        return [
            // Today
            'orders_today'         => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE DATE(created_at) = CURDATE()"),
            'paid_orders_today'    => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'"),
            'completed_today'      => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE order_status = 'completed' AND DATE(completed_at) = CURDATE()"),
            'revenue_today'        => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM customer_orders WHERE order_status = 'completed' AND DATE(completed_at) = CURDATE()"),
            // Active queue
            'pending_payment'      => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE order_status = 'pending_payment'"),
            'paid_awaiting'        => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE order_status = 'paid'"),
            'preparing'            => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE order_status = 'preparing'"),
            'ready_for_pickup'     => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE order_status = 'ready_for_pickup'"),
            'active_orders'        => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE order_status IN ('paid','preparing','ready_for_pickup')"),
            // This month
            'orders_this_month'    => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())"),
            'completed_this_month' => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE order_status = 'completed' AND YEAR(completed_at)=YEAR(CURDATE()) AND MONTH(completed_at)=MONTH(CURDATE())"),
            'revenue_this_month'   => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM customer_orders WHERE order_status = 'completed' AND YEAR(completed_at)=YEAR(CURDATE()) AND MONTH(completed_at)=MONTH(CURDATE())"),
            'cancelled_this_month' => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE order_status IN ('cancelled','payment_failed') AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())"),
        ];
    }

    // ── Orders by shop ────────────────────────────────────

    /**
     * Per-shop customer order breakdown for the current month.
     */
    public function getOrdersByShop(): array
    {
        return $this->safeQuery(
            "SELECT
                s.id                                                       AS shop_id,
                COALESCE(s.shop_name, s.name)                              AS shop_name,
                COUNT(co.id)                                               AS total_orders,
                SUM(CASE WHEN co.order_status IN ('paid','preparing','ready_for_pickup') THEN 1 ELSE 0 END) AS active_orders,
                SUM(CASE WHEN co.order_status = 'ready_for_pickup'         THEN 1 ELSE 0 END) AS ready_count,
                SUM(CASE WHEN co.order_status = 'completed'                THEN 1 ELSE 0 END) AS completed_orders,
                SUM(CASE WHEN co.order_status IN ('cancelled','payment_failed') THEN 1 ELSE 0 END) AS cancelled_orders,
                COALESCE(SUM(CASE WHEN co.order_status = 'completed' THEN co.total_amount ELSE 0 END), 0) AS completed_revenue
             FROM shops s
             LEFT JOIN customer_orders co
                ON co.shop_id = s.id
               AND YEAR(co.created_at)  = YEAR(CURDATE())
               AND MONTH(co.created_at) = MONTH(CURDATE())
             WHERE s.is_active = 1
             GROUP BY s.id, s.shop_name, s.name
             ORDER BY completed_revenue DESC"
        );
    }

    // ── Paginated orders list ─────────────────────────────

    /**
     * Paginated customer orders with full filters for GM monitoring view.
     */
    public function getOrders(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ['1=1'];
        $bind  = [];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $where[] = "co.order_status IN ('paid','preparing','ready_for_pickup')";
            } else {
                $where[]         = 'co.order_status = :ostatus';
                $bind['ostatus'] = $filters['status'];
            }
        }
        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            $where[]          = 'co.payment_status = :pstatus';
            $bind['pstatus']  = $filters['payment_status'];
        }
        if (!empty($filters['shop_id'])) {
            $where[]          = 'co.shop_id = :shop_id';
            $bind['shop_id']  = (int)$filters['shop_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]          = 'DATE(co.created_at) >= :dfrom';
            $bind['dfrom']    = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]          = 'DATE(co.created_at) <= :dto';
            $bind['dto']      = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $where[]          = "(co.order_reference LIKE :q OR co.customer_name LIKE :q2 OR co.customer_phone LIKE :q3 OR co.payment_reference LIKE :q4)";
            $bind['q']        = '%' . $filters['search'] . '%';
            $bind['q2']       = '%' . $filters['search'] . '%';
            $bind['q3']       = '%' . $filters['search'] . '%';
            $bind['q4']       = '%' . $filters['search'] . '%';
        }

        $whereStr = implode(' AND ', $where);
        $total    = $this->safeCount(
            "SELECT COUNT(*) FROM customer_orders co WHERE {$whereStr}",
            $bind
        );

        $offset      = ($page - 1) * $perPage;
        $bind['lim'] = $perPage;
        $bind['off'] = $offset;

        $rows = $this->safeQuery(
            "SELECT
                co.id,
                co.order_reference,
                co.customer_name,
                co.customer_phone,
                co.order_status,
                co.payment_status,
                co.payment_reference,
                COALESCE(co.item_count, 0)           AS item_count,
                co.subtotal,
                co.total_amount,
                co.created_at,
                co.paid_at,
                co.completed_at,
                co.cancelled_at,
                co.shop_id,
                s.name                               AS shop_name,
                pm.name                              AS payment_method_name,
                pm.type                              AS payment_method_type,
                CONCAT(u.first_name,' ',u.last_name) AS processed_by_name
             FROM customer_orders co
             LEFT JOIN shops           s  ON s.id  = co.shop_id
             LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
             LEFT JOIN users           u  ON u.id  = co.processed_by
             WHERE {$whereStr}
             ORDER BY
               CASE co.order_status
                 WHEN 'ready_for_pickup' THEN 1
                 WHEN 'paid'             THEN 2
                 WHEN 'preparing'        THEN 3
                 ELSE 4
               END,
               co.created_at DESC
             LIMIT :lim OFFSET :off",
            $bind
        );

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int)ceil($total / max(1, $perPage)),
            'from'         => $total > 0 ? $offset + 1 : 0,
            'to'           => min($offset + $perPage, $total),
        ];
    }

    // ── Recent orders ─────────────────────────────────────

    /**
     * Recent customer orders for dashboard activity feed.
     */
    public function getRecentOrders(int $limit = 10): array
    {
        return $this->safeQuery(
            "SELECT
                co.id,
                co.order_reference,
                co.customer_name,
                co.customer_phone,
                co.order_status,
                co.payment_status,
                co.total_amount,
                co.created_at,
                co.paid_at,
                co.completed_at,
                s.name  AS shop_name,
                pm.name AS payment_method_name
             FROM customer_orders co
             LEFT JOIN shops           s  ON s.id  = co.shop_id
             LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
             ORDER BY co.created_at DESC
             LIMIT :lim",
            ['lim' => $limit]
        );
    }

    // ── Shop-scoped summary ───────────────────────────────

    /**
     * Quick lookup of active + completed-today counts per shop.
     * Keyed by shop_id for easy merge with shop data.
     */
    public function getShopOrderSummary(): array
    {
        $rows = $this->safeQuery(
            "SELECT
                co.shop_id,
                SUM(CASE WHEN co.order_status IN ('paid','preparing','ready_for_pickup') THEN 1 ELSE 0 END) AS active_orders,
                SUM(CASE WHEN co.order_status = 'ready_for_pickup'                       THEN 1 ELSE 0 END) AS ready_count,
                SUM(CASE WHEN co.order_status = 'completed' AND DATE(co.completed_at) = CURDATE() THEN 1 ELSE 0 END) AS completed_today
             FROM customer_orders co
             GROUP BY co.shop_id"
        );
        // Index by shop_id for O(1) lookup
        $indexed = [];
        foreach ($rows as $r) {
            $indexed[(int)$r['shop_id']] = $r;
        }
        return $indexed;
    }

    // ── Status helpers (mirrors CustomerOrderService) ────

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

    public static function paymentStatusBadge(string $status): string
    {
        return match ($status) {
            'paid'      => 'success',
            'pending'   => 'warning',
            'failed'    => 'danger',
            'expired'   => 'danger',
            'cancelled' => 'secondary',
            default     => 'secondary',
        };
    }
}
