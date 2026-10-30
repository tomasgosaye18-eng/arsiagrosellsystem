<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/services/GMSalesReceiptsService.php
 * Purpose : Unified read-only receipt data layer for the GM.
 *           Covers both POS/Salesperson sales (sales table)
 *           and Customer E-Commerce orders (customer_orders).
 *           No writes — GM visibility only.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Services;

use AAM\Core\Database;

class GMSalesReceiptsService
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

    private function safeOne(string $sql, array $bind = []): ?array
    {
        try {
            $row = $this->db->fetchOne($sql, $bind);
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── KPI summary ───────────────────────────────────────

    /**
     * Combined KPIs across all channels for GM dashboard strip.
     */
    public function getKpis(): array
    {
        return [
            // POS / Salesperson channel
            'pos_today'           => $this->safeCount("SELECT COUNT(*) FROM sales WHERE sale_date=CURDATE() AND status='completed'"),
            'pos_revenue_today'   => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date=CURDATE() AND status='completed'"),
            'pos_month'           => $this->safeCount("SELECT COUNT(*) FROM sales WHERE YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE()) AND status='completed'"),
            'pos_revenue_month'   => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE()) AND status='completed'"),
            // E-commerce / Customer channel
            'eco_today'           => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE DATE(completed_at)=CURDATE() AND order_status='completed'"),
            'eco_revenue_today'   => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM customer_orders WHERE DATE(completed_at)=CURDATE() AND order_status='completed'"),
            'eco_month'           => $this->safeCount("SELECT COUNT(*) FROM customer_orders WHERE order_status='completed' AND YEAR(completed_at)=YEAR(CURDATE()) AND MONTH(completed_at)=MONTH(CURDATE())"),
            'eco_revenue_month'   => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM customer_orders WHERE order_status='completed' AND YEAR(completed_at)=YEAR(CURDATE()) AND MONTH(completed_at)=MONTH(CURDATE())"),
            // Seed Distribution channel
            'seed_today'          => $this->safeCount("SELECT COUNT(*) FROM seed_distributions WHERE DATE(released_at)=CURDATE() AND status IN ('released','completed')"),
            'seed_revenue_today'  => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM seed_distributions WHERE DATE(released_at)=CURDATE() AND status IN ('released','completed')"),
            'seed_month'          => $this->safeCount("SELECT COUNT(*) FROM seed_distributions WHERE status IN ('released','completed') AND YEAR(COALESCE(released_at,created_at))=YEAR(CURDATE()) AND MONTH(COALESCE(released_at,created_at))=MONTH(CURDATE())"),
            'seed_revenue_month'  => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM seed_distributions WHERE status IN ('released','completed') AND YEAR(COALESCE(released_at,created_at))=YEAR(CURDATE()) AND MONTH(COALESCE(released_at,created_at))=MONTH(CURDATE())"),
        ];
    }

    // ── Unified paginated receipts list ───────────────────

    /**
     * Paginate receipts from both channels combined.
     *
     * $filters:
     *   channel     : 'pos' | 'ecommerce' | '' (all)
     *   shop_id     : int
     *   status      : string (pos: completed|voided; eco: completed|cancelled|…)
     *   payment_method : string
     *   date_from   : Y-m-d
     *   date_to     : Y-m-d
     *   search      : free text (receipt/order ref, seller name, customer name)
     *
     * Returns rows with a synthetic 'channel' column ('pos' | 'ecommerce').
     *
     * @return array{data:array,total:int,page:int,perPage:int,totalPages:int,from:int,to:int}
     */
    public function paginateReceipts(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $channel = $filters['channel'] ?? '';
        $shopId  = (int)($filters['shop_id'] ?? 0);

        // ── Build POS CTE ──────────────────────────────
        $posWhere  = ["s.status IN ('completed','voided','returned','partially_returned')"];
        $posBind   = [];

        if ($shopId > 0) {
            $posWhere[]             = 's.shop_id = :pos_shop_id';
            $posBind['pos_shop_id'] = $shopId;
        }
        if (!empty($filters['status'])) {
            $posWhere[]           = 's.status = :pos_status';
            $posBind['pos_status'] = $filters['status'];
        }
        if (!empty($filters['payment_method'])) {
            $posWhere[]               = 's.payment_method = :pos_pm';
            $posBind['pos_pm']        = $filters['payment_method'];
        }
        if (!empty($filters['date_from'])) {
            $posWhere[]             = 'DATE(s.completed_at) >= :pos_dfrom';
            $posBind['pos_dfrom']   = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $posWhere[]             = 'DATE(s.completed_at) <= :pos_dto';
            $posBind['pos_dto']     = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $sq = '%' . $filters['search'] . '%';
            $posWhere[] = "(s.receipt_number LIKE :pos_q1
                            OR s.sale_number  LIKE :pos_q2
                            OR CONCAT(u.first_name,' ',u.last_name) LIKE :pos_q3
                            OR u.username LIKE :pos_q4)";
            $posBind['pos_q1'] = $sq;
            $posBind['pos_q2'] = $sq;
            $posBind['pos_q3'] = $sq;
            $posBind['pos_q4'] = $sq;
        }

        $posWhereStr = implode(' AND ', $posWhere);

        $posSql = "SELECT
                    s.id                                              AS id,
                    'pos'                                             AS channel,
                    s.receipt_number                                  AS receipt_ref,
                    s.sale_number                                     AS order_ref,
                    s.shop_id,
                    sh.name                                           AS shop_name,
                    s.total_amount,
                    s.subtotal,
                    s.discount_amount,
                    s.total_items                                     AS item_count,
                    s.status,
                    s.payment_method,
                    s.payment_provider,
                    s.payment_status,
                    s.payment_reference,
                    s.cash_received,
                    s.change_given,
                    s.completed_at                                    AS transaction_at,
                    s.sale_date                                       AS transaction_date,
                    CONCAT(u.first_name,' ',u.last_name)             AS seller_customer_name,
                    u.username                                        AS seller_customer_username,
                    NULL                                              AS customer_phone,
                    s.notes,
                    s.voided_at,
                    s.void_reason
                  FROM sales s
                  JOIN shops sh ON sh.id = s.shop_id
                  JOIN users  u  ON u.id  = s.seller_id
                  WHERE {$posWhereStr}";

        // ── Build E-commerce CTE ───────────────────────
        $ecoWhere = ["co.order_status NOT IN ('pending_payment','payment_failed')"];
        $ecoBind  = [];

        if ($shopId > 0) {
            $ecoWhere[]             = 'co.shop_id = :eco_shop_id';
            $ecoBind['eco_shop_id'] = $shopId;
        }
        if (!empty($filters['status'])) {
            $ecoWhere[]             = 'co.order_status = :eco_status';
            $ecoBind['eco_status']  = $filters['status'];
        }
        if (!empty($filters['payment_method'])) {
            $ecoWhere[]             = 'pm.type = :eco_pm';
            $ecoBind['eco_pm']      = $filters['payment_method'];
        }
        if (!empty($filters['date_from'])) {
            $ecoWhere[]             = 'DATE(co.created_at) >= :eco_dfrom';
            $ecoBind['eco_dfrom']   = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $ecoWhere[]             = 'DATE(co.created_at) <= :eco_dto';
            $ecoBind['eco_dto']     = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $sq = '%' . $filters['search'] . '%';
            $ecoWhere[] = "(co.order_reference LIKE :eco_q1
                            OR co.customer_name  LIKE :eco_q2
                            OR co.customer_phone LIKE :eco_q3
                            OR co.payment_reference LIKE :eco_q4)";
            $ecoBind['eco_q1'] = $sq;
            $ecoBind['eco_q2'] = $sq;
            $ecoBind['eco_q3'] = $sq;
            $ecoBind['eco_q4'] = $sq;
        }

        $ecoWhereStr = implode(' AND ', $ecoWhere);

        $ecoSql = "SELECT
                    co.id                                             AS id,
                    'ecommerce'                                       AS channel,
                    co.order_reference                                AS receipt_ref,
                    co.order_reference                                AS order_ref,
                    co.shop_id,
                    sh.name                                           AS shop_name,
                    co.total_amount,
                    co.subtotal,
                    NULL                                              AS discount_amount,
                    COALESCE(co.item_count,0)                         AS item_count,
                    co.order_status                                   AS status,
                    COALESCE(pm.type,'unknown')                       AS payment_method,
                    pm.name                                           AS payment_provider,
                    co.payment_status,
                    co.payment_reference,
                    NULL                                              AS cash_received,
                    NULL                                              AS change_given,
                    COALESCE(co.completed_at, co.paid_at, co.created_at) AS transaction_at,
                    DATE(COALESCE(co.completed_at, co.paid_at, co.created_at)) AS transaction_date,
                    co.customer_name                                  AS seller_customer_name,
                    NULL                                              AS seller_customer_username,
                    co.customer_phone                                 AS customer_phone,
                    co.notes,
                    NULL                                              AS voided_at,
                    NULL                                              AS void_reason
                  FROM customer_orders co
                  JOIN shops sh ON sh.id = co.shop_id
                  LEFT JOIN payment_methods pm ON pm.id = co.payment_method_id
                  WHERE {$ecoWhereStr}";

        // ── Build Seed Distribution CTE ───────────────
        $seedWhere = ["sd.status IN ('released','completed','pending','approved','cancelled','rejected')"];
        $seedBind  = [];

        if ($shopId > 0) {
            // Seed distributions are warehouse-based — shop filter not applicable, skip
        }
        if (!empty($filters['status'])) {
            $seedWhere[]              = 'sd.status = :seed_status';
            $seedBind['seed_status']  = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $seedWhere[]              = 'sd.distribution_date >= :seed_dfrom';
            $seedBind['seed_dfrom']   = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $seedWhere[]              = 'sd.distribution_date <= :seed_dto';
            $seedBind['seed_dto']     = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $sq = '%' . $filters['search'] . '%';
            $seedWhere[] = "(sd.distribution_number LIKE :seed_q1
                             OR sd.recipient_name   LIKE :seed_q2
                             OR p.name              LIKE :seed_q3)";
            $seedBind['seed_q1'] = $sq;
            $seedBind['seed_q2'] = $sq;
            $seedBind['seed_q3'] = $sq;
        }

        $seedWhereStr = implode(' AND ', $seedWhere);

        $seedSql = "SELECT
                    sd.id                                                    AS id,
                    'seed'                                                   AS channel,
                    sd.distribution_number                                   AS receipt_ref,
                    sd.distribution_number                                   AS order_ref,
                    NULL                                                     AS shop_id,
                    CONCAT(w.name, COALESCE(CONCAT(' (',w.warehouse_code,')'),'')) AS shop_name,
                    COALESCE(sd.total_amount, 0)                            AS total_amount,
                    COALESCE(sd.total_amount, 0)                            AS subtotal,
                    NULL                                                     AS discount_amount,
                    1                                                        AS item_count,
                    sd.status,
                    'seed_distribution'                                      AS payment_method,
                    ''                                                       AS payment_provider,
                    sd.status                                                AS payment_status,
                    sd.distribution_number                                   AS payment_reference,
                    NULL                                                     AS cash_received,
                    NULL                                                     AS change_given,
                    COALESCE(sd.released_at, sd.created_at)                 AS transaction_at,
                    sd.distribution_date                                     AS transaction_date,
                    sd.recipient_name                                        AS seller_customer_name,
                    NULL                                                     AS seller_customer_username,
                    sd.recipient_phone                                       AS customer_phone,
                    sd.notes,
                    NULL                                                     AS voided_at,
                    NULL                                                     AS void_reason
                  FROM seed_distributions sd
                  JOIN warehouses w ON w.id = sd.warehouse_id
                  JOIN products p   ON p.id = sd.product_id
                  WHERE {$seedWhereStr}";

        // ── Combine ────────────────────────────────────
        $allBind = array_merge($posBind, $ecoBind, $seedBind);

        if ($channel === 'pos') {
            $unionSql = $posSql;
            $allBind  = $posBind;
        } elseif ($channel === 'ecommerce') {
            $unionSql = $ecoSql;
            $allBind  = $ecoBind;
        } elseif ($channel === 'seed') {
            $unionSql = $seedSql;
            $allBind  = $seedBind;
        } else {
            $unionSql = "({$posSql}) UNION ALL ({$ecoSql}) UNION ALL ({$seedSql})";
        }

        $total = $this->safeCount(
            "SELECT COUNT(*) FROM ({$unionSql}) AS _all",
            $allBind
        );

        $allBind['_limit']  = $perPage;
        $allBind['_offset'] = $offset;

        $data = $this->safeQuery(
            "SELECT * FROM ({$unionSql}) AS _all
             ORDER BY transaction_at DESC
             LIMIT :_limit OFFSET :_offset",
            $allBind
        );

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / max(1, $perPage)),
            'from'       => $total > 0 ? $offset + 1 : 0,
            'to'         => min($offset + $perPage, $total),
        ];
    }

    // ── POS sale detail (for GM receipt view) ─────────────

    /**
     * Full POS sale record with items — no shop restriction for GM.
     */
    public function getPOSSaleWithItems(int $saleId): ?array
    {
        $sale = $this->safeOne(
            "SELECT s.*,
                    sh.name         AS shop_name,
                    sh.location     AS shop_location,
                    u.first_name    AS seller_first_name,
                    u.last_name     AS seller_last_name,
                    u.username      AS seller_username,
                    vu.username     AS voided_by_username,
                    vu.first_name   AS voided_by_first_name,
                    vu.last_name    AS voided_by_last_name
             FROM sales s
             JOIN shops sh ON sh.id = s.shop_id
             JOIN users  u  ON u.id  = s.seller_id
             LEFT JOIN users vu ON vu.id = s.voided_by
             WHERE s.id = :id",
            ['id' => $saleId]
        );

        if (!$sale) return null;

        $sale['items'] = $this->safeQuery(
            "SELECT si.*,
                    u.abbreviation AS unit_abbr,
                    u.name         AS unit_name
             FROM sale_items si
             LEFT JOIN units_of_measure u ON u.id = si.unit_id
             WHERE si.sale_id = :sid
             ORDER BY si.id ASC",
            ['sid' => $saleId]
        );

        return $sale;
    }

    // ── Customer order detail (for GM receipt view) ────────

    /**
     * Full customer order record with items — no session restriction for GM.
     */
    public function getCustomerOrderWithItems(int $orderId): ?array
    {
        $order = $this->safeOne(
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

        if (!$order) return null;

        $order['items'] = $this->safeQuery(
            "SELECT coi.*,
                    u.abbreviation AS unit_abbr,
                    u.name         AS unit_name,
                    p.primary_image AS product_image,
                    p.sku           AS product_sku
             FROM customer_order_items coi
             LEFT JOIN units_of_measure u ON u.id = coi.unit_id
             LEFT JOIN products          p ON p.id = coi.product_id
             WHERE coi.order_id = :oid
             ORDER BY coi.id ASC",
            ['oid' => $orderId]
        );

        return $order;
    }

    // ── Shops list for filter dropdown ────────────────────

    public function getShopsList(): array
    {
        return $this->safeQuery(
            "SELECT id, COALESCE(shop_name, name) AS shop_name
             FROM shops WHERE is_active = 1 ORDER BY shop_name ASC"
        );
    }

    // ── Status label / badge helpers ─────────────────────

    public static function posStatusLabel(string $status): string
    {
        return match($status) {
            'completed'          => 'Completed',
            'voided'             => 'Voided',
            'returned'           => 'Returned',
            'partially_returned' => 'Partial Return',
            default              => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function posStatusBadge(string $status): string
    {
        return match($status) {
            'completed'          => 'success',
            'voided'             => 'danger',
            'returned'           => 'warning',
            'partially_returned' => 'info',
            default              => 'secondary',
        };
    }

    public static function ecoStatusLabel(string $status): string
    {
        return match($status) {
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

    public static function ecoStatusBadge(string $status): string
    {
        return match($status) {
            'completed'        => 'success',
            'paid'             => 'info',
            'preparing'        => 'primary',
            'ready_for_pickup' => 'success',
            'cancelled'        => 'secondary',
            'payment_failed'   => 'danger',
            'pending_payment'  => 'warning',
            default            => 'secondary',
        };
    }

    public static function seedStatusLabel(string $status): string
    {
        return match($status) {
            'pending'   => 'Pending Approval',
            'approved'  => 'Approved',
            'released'  => 'Released',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'rejected'  => 'Rejected',
            default     => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function seedStatusBadge(string $status): string
    {
        return match($status) {
            'pending'   => 'warning',
            'approved'  => 'info',
            'released'  => 'primary',
            'completed' => 'success',
            'cancelled' => 'secondary',
            'rejected'  => 'danger',
            default     => 'secondary',
        };
    }

    public static function paymentMethodLabel(string $method, string $provider = ''): string
    {
        if ($method === 'mobile_money' && $provider !== '') {
            return match($provider) {
                'cbe_birr'     => 'CBE Birr',
                'telebirr'     => 'Telebirr',
                'other_mobile' => 'Mobile Money',
                default        => strtoupper($provider),
            };
        }
        return match($method) {
            'cash'         => 'Cash',
            'mobile_money' => 'Mobile Money',
            'credit'       => 'Credit',
            'card'         => 'Card',
            default        => ucfirst(str_replace('_', ' ', $method)),
        };
    }
}
