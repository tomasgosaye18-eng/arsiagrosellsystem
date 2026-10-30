<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/services/GeneralManagerService.php
 * Purpose : Data aggregation service for the General Manager
 *           module. Queries real operational data across all
 *           business domains. GM monitors â€” does not operate.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Services;

use AAM\Core\Database;

class GeneralManagerService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // â”€â”€â”€ Safe query helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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

    private function safeOne(string $sql, array $bind = []): array
    {
        try {
            return $this->db->fetchOne($sql, $bind) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    // â”€â”€â”€ DASHBOARD KPIs â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getDashboardKpis(): array
    {
        return [
            'total_farms'             => $this->safeCount("SELECT COUNT(*) FROM farms WHERE is_active=1"),
            'active_farms'            => $this->safeCount("SELECT COUNT(*) FROM farms WHERE is_active=1"),
            'total_warehouses'        => $this->safeCount("SELECT COUNT(*) FROM warehouses WHERE is_active=1"),
            'total_shops'             => $this->safeCount("SELECT COUNT(*) FROM shops WHERE is_active=1"),
            'total_products'          => $this->safeCount("SELECT COUNT(*) FROM products WHERE is_active=1"),
            'warehouse_stock'         => $this->safeDecimal("SELECT COALESCE(SUM(wi.current_quantity),0) FROM warehouse_inventory wi JOIN warehouses w ON w.id=wi.warehouse_id WHERE w.is_active=1 AND w.deleted_at IS NULL"),
            'shop_stock'              => $this->safeDecimal("SELECT COALESCE(SUM(current_quantity),0) FROM shop_inventory"),
            'pending_approvals'                    => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status='pending'"),
            'pending_farm_dispatches'              => $this->safeCount("SELECT COUNT(*) FROM farm_dispatches WHERE status='pending_approval'"),
            'pending_discrepancy_reviews'          => $this->safeCount("SELECT COUNT(*) FROM warehouse_receiving WHERE status='discrepancy_pending'"),
            'pending_transfer_discrepancy_reviews' => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status='discrepancy_pending'"),
            'pending_adjustments'                  => $this->safeCount("SELECT COUNT(*) FROM stock_adjustments WHERE status='pending'"),
            'in_transit'                           => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status='in_transit'"),
            'in_transit'                  => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status='in_transit'"),
            'sales_today'             => $this->safeCount("SELECT COUNT(*) FROM sales WHERE sale_date=CURDATE() AND status='completed'"),
            'revenue_today'           => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date=CURDATE() AND status='completed'"),
            'sales_this_month'        => $this->safeCount("SELECT COUNT(*) FROM sales WHERE YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE()) AND status='completed'"),
            'revenue_this_month'      => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE()) AND status='completed'"),
            'low_stock_items'         => $this->safeCount("SELECT COUNT(*) FROM warehouse_inventory wi JOIN products p ON p.id=wi.product_id WHERE wi.current_quantity>0 AND p.min_stock_level>0 AND wi.current_quantity<=p.min_stock_level"),
            'out_of_stock_items'      => $this->safeCount("SELECT COUNT(*) FROM warehouse_inventory WHERE current_quantity<=0"),
            'open_discrepancies'      => $this->safeCount("SELECT COUNT(*) FROM warehouse_discrepancies WHERE status IN ('open','under_review')"),
            'pending_farm_dispatch'   => $this->safeCount("SELECT COUNT(*) FROM harvest_batches WHERE status IN ('active','partially_dispatched')"),
            'pending_receipts'        => $this->safeCount("SELECT COUNT(*) FROM farm_dispatches WHERE status IN ('in_transit','pending') AND NOT EXISTS (SELECT 1 FROM warehouse_receiving wr WHERE wr.dispatch_id=farm_dispatches.id AND wr.status NOT IN ('cancelled','final_rejected'))"),
            'harvests_this_month'     => $this->safeCount("SELECT COUNT(*) FROM harvest_batches WHERE YEAR(harvest_date)=YEAR(CURDATE()) AND MONTH(harvest_date)=MONTH(CURDATE())"),
        ];
    }

    // â”€â”€â”€ ALL FARMS DETAIL â€” GM centralized view â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Full per-farm detail for GM â†’ All Farms page.
     * Includes every farm regardless of is_active (so deactivated farms
     * still appear with an "Inactive" badge, reflecting live DB state).
     * Operational data (harvests, dispatches, available qty) is pulled
     * from the same tables used by the Farm Manager module.
     */
    public function getAllFarmsDetail(): array
    {
        return $this->safeQuery(
            "SELECT
                f.id,
                COALESCE(f.farm_name, f.name)                           AS farm_name,
                COALESCE(f.farm_code, CONCAT('FARM-', LPAD(f.id,3,'0'))) AS farm_code,
                f.farm_type,
                f.location,
                f.is_active,
                f.created_at,
                CONCAT(m.first_name, ' ', m.last_name)                  AS manager_name,
                m.username                                               AS manager_username,

                /* harvest stats */
                COUNT(DISTINCT hb.id)                                   AS total_harvests,
                COALESCE(SUM(
                    CASE WHEN hb.status IN ('active','partially_dispatched')
                         THEN hb.remaining_quantity ELSE 0 END), 0)     AS available_qty,

                /* dispatch stats */
                COUNT(DISTINCT CASE
                    WHEN fd.status IN ('pending','in_transit')
                    THEN fd.id END)                                      AS pending_dispatches,
                COUNT(DISTINCT CASE
                    WHEN fd.status = 'dispatched'
                     AND YEAR(fd.dispatch_date)  = YEAR(CURDATE())
                     AND MONTH(fd.dispatch_date) = MONTH(CURDATE())
                    THEN fd.id END)                                      AS dispatches_this_month,

                /* harvests in last 30 days */
                COUNT(DISTINCT CASE
                    WHEN hb.harvest_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    THEN hb.id END)                                      AS recent_harvests,

                /* kg harvested this month */
                COALESCE(SUM(
                    CASE WHEN YEAR(hb.harvest_date)  = YEAR(CURDATE())
                          AND MONTH(hb.harvest_date) = MONTH(CURDATE())
                         THEN hb.initial_quantity ELSE 0 END), 0)       AS kg_this_month

             FROM farms f
             LEFT JOIN users m            ON m.id  = f.manager_id
             LEFT JOIN harvest_batches hb ON hb.farm_id = f.id
             LEFT JOIN farm_dispatches fd ON fd.farm_id = f.id
             WHERE f.deleted_at IS NULL
             GROUP BY
                f.id, f.farm_name, f.name, f.farm_code, f.farm_type,
                f.location, f.is_active, f.created_at,
                m.first_name, m.last_name, m.username
             ORDER BY f.is_active DESC, f.name ASC"
        );
    }

    // â”€â”€â”€ ALL WAREHOUSES DETAIL â€” GM centralized view â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Full per-warehouse detail for GM â†’ All Warehouses page.
     * Includes every warehouse (active + inactive) to reflect live DB.
     */
    public function getAllWarehousesDetail(): array
    {
        return $this->safeQuery(
            "SELECT
                w.id,
                COALESCE(w.warehouse_name, w.name)                            AS warehouse_name,
                COALESCE(w.warehouse_code, CONCAT('WH-', LPAD(w.id,3,'0')))   AS warehouse_code,
                COALESCE(w.warehouse_type, 'CENTRAL')                          AS warehouse_type,
                w.location,
                w.is_active,
                w.created_at,
                CONCAT(m.first_name, ' ', m.last_name)                        AS manager_name,
                m.username                                                     AS manager_username,

                /* current stock */
                COALESCE(SUM(wi.current_quantity),   0)                       AS total_stock,
                COALESCE(SUM(wi.available_quantity), 0)                       AS available_stock,
                COUNT(DISTINCT wi.product_id)                                 AS product_lines,

                /* open discrepancies */
                COUNT(DISTINCT CASE
                    WHEN wd.status IN ('open','under_review')
                    THEN wd.id END)                                            AS open_discrepancies,

                /* pending receiving: dispatches in-transit with no completed receiving */
                (SELECT COUNT(*)
                 FROM farm_dispatches fd2
                 WHERE fd2.warehouse_id = w.id
                   AND fd2.status IN ('in_transit','pending')
                   AND NOT EXISTS (
                       SELECT 1 FROM warehouse_receiving wr2
                       WHERE wr2.dispatch_id = fd2.id
                         AND wr2.status NOT IN ('cancelled','rejected','final_rejected')
                   )
                )                                                              AS pending_receiving,

                /* incoming warehouse-to-shop transfers in transit */
                COUNT(DISTINCT CASE
                    WHEN wt.status = 'in_transit'
                    THEN wt.id END)                                            AS incoming_transfers

             FROM warehouses w
             LEFT JOIN users m                    ON m.id            = w.manager_id
             LEFT JOIN warehouse_inventory wi     ON wi.warehouse_id = w.id
             LEFT JOIN warehouse_discrepancies wd ON wd.warehouse_id = w.id
             LEFT JOIN warehouse_transfers wt     ON wt.warehouse_id = w.id
             WHERE w.deleted_at IS NULL
             GROUP BY
                w.id, w.warehouse_name, w.name, w.warehouse_code, w.warehouse_type,
                w.location, w.is_active, w.created_at,
                m.first_name, m.last_name, m.username
             ORDER BY w.is_active DESC, w.name ASC"
        );
    }

    // â”€â”€â”€ ALL SHOPS DETAIL â€” GM centralized view â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Full per-shop detail for GM â†’ All Shops page.
     * Includes every shop (active + inactive) to reflect live DB.
     */
    public function getAllShopsDetail(): array
    {
        return $this->safeQuery(
            "SELECT
                s.id,
                COALESCE(s.shop_name, s.name)                             AS shop_name,
                COALESCE(s.shop_code, CONCAT('SHOP-', LPAD(s.id,3,'0'))) AS shop_code,
                COALESCE(s.shop_type, 'RETAIL')                           AS shop_type,
                s.location,
                s.is_active,
                s.created_at,
                s.phone,
                s.shop_image,
                CONCAT(m.first_name, ' ', m.last_name)                   AS manager_name,
                m.username                                                AS manager_username,

                /* current stock */
                COALESCE(SUM(si.current_quantity),   0)                  AS total_stock,
                COALESCE(SUM(si.available_quantity), 0)                  AS available_stock,
                COUNT(DISTINCT si.product_id)                            AS product_lines,

                /* low-stock lines (current_qty > 0 but <= min_stock_level) */
                COUNT(DISTINCT CASE
                    WHEN si.current_quantity > 0
                     AND p.min_stock_level    > 0
                     AND si.current_quantity <= p.min_stock_level
                    THEN si.id END)                                       AS low_stock_lines,

                /* today's sales */
                COUNT(DISTINCT CASE
                    WHEN sl.status = 'completed'
                     AND sl.sale_date = CURDATE()
                    THEN sl.id END)                                       AS sales_today,
                COALESCE(SUM(
                    CASE WHEN sl.status = 'completed'
                          AND sl.sale_date = CURDATE()
                         THEN sl.total_amount ELSE 0 END), 0)            AS revenue_today,

                /* this month's sales */
                COUNT(DISTINCT CASE
                    WHEN sl.status = 'completed'
                     AND YEAR(sl.sale_date)  = YEAR(CURDATE())
                     AND MONTH(sl.sale_date) = MONTH(CURDATE())
                    THEN sl.id END)                                       AS sales_this_month,
                COALESCE(SUM(
                    CASE WHEN sl.status = 'completed'
                          AND YEAR(sl.sale_date)  = YEAR(CURDATE())
                          AND MONTH(sl.sale_date) = MONTH(CURDATE())
                         THEN sl.total_amount ELSE 0 END), 0)            AS revenue_this_month,

                /* incoming transfers */
                COUNT(DISTINCT CASE
                    WHEN wt.status = 'in_transit'
                    THEN wt.id END)                                       AS incoming_transfers

             FROM shops s
             LEFT JOIN users m                ON m.id      = s.manager_id
             LEFT JOIN shop_inventory si      ON si.shop_id = s.id
             LEFT JOIN products p             ON p.id       = si.product_id
             LEFT JOIN sales sl               ON sl.shop_id = s.id
             LEFT JOIN warehouse_transfers wt ON wt.shop_id = s.id
             WHERE s.deleted_at IS NULL
             GROUP BY
                s.id, s.shop_name, s.name, s.shop_code, s.shop_type,
                s.location, s.is_active, s.created_at, s.phone, s.shop_image,
                m.first_name, m.last_name, m.username
             ORDER BY s.is_active DESC, s.name ASC"
        );
    }

    // â”€â”€â”€ FARM DATA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getFarmSummary(): array
    {
        return $this->safeQuery(
            "SELECT f.id, f.name, f.farm_type, f.location, f.is_active,
                    COUNT(DISTINCT hb.id) AS total_batches,
                    COALESCE(SUM(CASE WHEN hb.status IN ('active','partially_dispatched') THEN hb.remaining_quantity ELSE 0 END),0) AS available_qty,
                    COUNT(DISTINCT CASE WHEN fd.status IN ('pending','in_transit') THEN fd.id END) AS pending_dispatches,
                    COUNT(DISTINCT CASE WHEN hb.harvest_date >= DATE_SUB(CURDATE(),INTERVAL 30 DAY) THEN hb.id END) AS recent_harvests
             FROM farms f
             LEFT JOIN harvest_batches hb ON hb.farm_id=f.id
             LEFT JOIN farm_dispatches fd ON fd.farm_id=f.id
             WHERE f.is_active=1
             GROUP BY f.id, f.name, f.farm_type, f.location, f.is_active
             ORDER BY f.name"
        );
    }

    public function getFarmPerformance(): array
    {
        return [
            'total_farms'              => $this->safeCount("SELECT COUNT(*) FROM farms WHERE is_active=1"),
            'harvests_this_month'      => $this->safeCount("SELECT COUNT(*) FROM harvest_batches WHERE YEAR(harvest_date)=YEAR(CURDATE()) AND MONTH(harvest_date)=MONTH(CURDATE())"),
            'kg_this_month'            => $this->safeDecimal("SELECT COALESCE(SUM(initial_quantity),0) FROM harvest_batches WHERE YEAR(harvest_date)=YEAR(CURDATE()) AND MONTH(harvest_date)=MONTH(CURDATE())"),
            'pending_dispatch'         => $this->safeCount("SELECT COUNT(*) FROM harvest_batches WHERE status IN ('active','partially_dispatched')"),
            'dispatches_this_month'    => $this->safeCount("SELECT COUNT(*) FROM farm_dispatches WHERE YEAR(dispatch_date)=YEAR(CURDATE()) AND MONTH(dispatch_date)=MONTH(CURDATE())"),
            'dispatched_kg_this_month' => $this->safeDecimal("SELECT COALESCE(SUM(fdi.quantity_dispatched),0) FROM farm_dispatch_items fdi JOIN farm_dispatches fd ON fd.id=fdi.dispatch_id WHERE YEAR(fd.dispatch_date)=YEAR(CURDATE()) AND MONTH(fd.dispatch_date)=MONTH(CURDATE())"),
        ];
    }

    public function getRecentHarvests(int $limit = 15): array
    {
        return $this->safeQuery(
            "SELECT hb.*, p.name AS product_name, f.name AS farm_name,
                    CONCAT(u.first_name,' ',u.last_name) AS recorded_by_name
             FROM harvest_batches hb
             LEFT JOIN products p ON p.id=hb.product_id
             LEFT JOIN farms f    ON f.id=hb.farm_id
             LEFT JOIN users u    ON u.id=hb.recorded_by
             ORDER BY hb.harvest_date DESC, hb.created_at DESC LIMIT :lim",
            ['lim' => $limit]
        );
    }

    public function getRecentDispatches(int $limit = 15): array
    {
        return $this->safeQuery(
            "SELECT fd.*,
                    GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS product_name,
                    COALESCE(SUM(fdi.quantity_dispatched),0) AS dispatched_quantity,
                    f.name AS farm_name,
                    w.name AS warehouse_name,
                    CONCAT(u.first_name,' ',u.last_name) AS dispatched_by_name
             FROM farm_dispatches fd
             LEFT JOIN farm_dispatch_items fdi ON fdi.dispatch_id=fd.id
             LEFT JOIN products p   ON p.id=fdi.product_id
             LEFT JOIN farms f      ON f.id=fd.farm_id
             LEFT JOIN warehouses w ON w.id=fd.warehouse_id
             LEFT JOIN users u      ON u.id=fd.initiated_by
             GROUP BY fd.id
             ORDER BY fd.dispatch_date DESC, fd.created_at DESC LIMIT :lim",
            ['lim' => $limit]
        );
    }

    // â”€â”€â”€ WAREHOUSE DATA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getWarehouseSummary(): array
    {
        return $this->safeQuery(
            "SELECT w.id, w.name, w.location, w.is_active,
                    COALESCE(SUM(wi.current_quantity),0)   AS total_stock,
                    COALESCE(SUM(wi.available_quantity),0) AS available_stock,
                    COUNT(DISTINCT wi.product_id)          AS product_lines,
                    COUNT(DISTINCT CASE WHEN wi.current_quantity<=0 THEN wi.id END) AS out_of_stock_lines,
                    COUNT(DISTINCT d.id) AS open_discrepancies
             FROM warehouses w
             LEFT JOIN warehouse_inventory wi ON wi.warehouse_id=w.id
             LEFT JOIN warehouse_discrepancies d ON d.warehouse_id=w.id AND d.status IN ('open','under_review')
             WHERE w.is_active=1
             GROUP BY w.id, w.name, w.location, w.is_active
             ORDER BY w.name"
        );
    }

    public function getWarehousePerformance(): array
    {
        return [
            'total_warehouses'   => $this->safeCount("SELECT COUNT(*) FROM warehouses WHERE is_active=1"),
            'total_stock'        => $this->safeDecimal("SELECT COALESCE(SUM(wi.current_quantity),0) FROM warehouse_inventory wi JOIN warehouses w ON w.id=wi.warehouse_id WHERE w.is_active=1 AND w.deleted_at IS NULL"),
            'low_stock_lines'    => $this->safeCount("SELECT COUNT(*) FROM warehouse_inventory wi JOIN products p ON p.id=wi.product_id WHERE wi.current_quantity>0 AND p.min_stock_level>0 AND wi.current_quantity<=p.min_stock_level"),
            'out_of_stock_lines' => $this->safeCount("SELECT COUNT(*) FROM warehouse_inventory WHERE current_quantity<=0"),
            'pending_receipts'   => $this->safeCount("SELECT COUNT(*) FROM farm_dispatches WHERE status IN ('in_transit','pending') AND NOT EXISTS (SELECT 1 FROM warehouse_receiving wr WHERE wr.dispatch_id=farm_dispatches.id AND wr.status NOT IN ('cancelled','final_rejected'))"),
            'open_discrepancies' => $this->safeCount("SELECT COUNT(*) FROM warehouse_discrepancies WHERE status IN ('open','under_review')"),
            'expiring_7d'        => $this->safeCount("SELECT COUNT(*) FROM warehouse_inventory WHERE expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND current_quantity>0"),
            'received_today'     => $this->safeDecimal("SELECT COALESCE(SUM(total_received),0) FROM warehouse_receiving WHERE received_date=CURDATE() AND status NOT IN ('cancelled','rejected','final_rejected')"),
        ];
    }

    public function getRecentReceiving(int $limit = 10): array
    {
        return $this->safeQuery(
            "SELECT wr.*, fd.dispatch_number, f.name AS farm_name,
                    w.name AS warehouse_name,
                    CONCAT(u.first_name,' ',u.last_name) AS received_by_name
             FROM warehouse_receiving wr
             JOIN farm_dispatches fd ON fd.id=wr.dispatch_id
             JOIN warehouses w       ON w.id=wr.warehouse_id
             JOIN farms f            ON f.id=fd.farm_id
             LEFT JOIN users u       ON u.id=wr.received_by
             ORDER BY wr.received_date DESC, wr.created_at DESC LIMIT :lim",
            ['lim' => $limit]
        );
    }

    public function getLowStockItems(int $limit = 20): array
    {
        return $this->safeQuery(
            "SELECT p.id, p.name AS product_name, p.sku, p.min_stock_level,
                    wi.warehouse_id, w.name AS warehouse_name,
                    wi.current_quantity, wi.available_quantity, wi.expiry_date,
                    u.abbreviation AS unit_abbr
             FROM warehouse_inventory wi
             JOIN products p    ON p.id=wi.product_id
             JOIN warehouses w  ON w.id=wi.warehouse_id
             LEFT JOIN units_of_measure u ON u.id=wi.unit_id
             WHERE p.min_stock_level>0 AND wi.current_quantity<=p.min_stock_level
             ORDER BY (wi.current_quantity/NULLIF(p.min_stock_level,0)) ASC LIMIT :lim",
            ['lim' => $limit]
        );
    }

    public function getOpenDiscrepancies(int $limit = 20): array
    {
        return $this->safeQuery(
            "SELECT wd.*, w.name AS warehouse_name, wt.transfer_number,
                    CONCAT(u.first_name,' ',u.last_name) AS reported_by_name
             FROM warehouse_discrepancies wd
             JOIN warehouses w         ON w.id=wd.warehouse_id
             LEFT JOIN warehouse_transfers wt ON wt.id=wd.transfer_id
             LEFT JOIN users u         ON u.id=wd.reported_by
             WHERE wd.status IN ('open','under_review')
             ORDER BY wd.created_at ASC LIMIT :lim",
            ['lim' => $limit]
        );
    }

    // â”€â”€â”€ TRANSFER DATA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getTransferPipeline(): array
    {
        return [
            'pending_approval'     => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status='pending'"),
            'approved'             => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status='approved'"),
            'in_transit'           => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status='in_transit'"),
            'received_today'       => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status IN ('received','received_with_discrepancy') AND DATE(received_date)=CURDATE()"),
            'received_this_month'  => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status IN ('received','received_with_discrepancy') AND YEAR(received_date)=YEAR(CURDATE()) AND MONTH(received_date)=MONTH(CURDATE())"),
            'with_discrepancy'     => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status='received_with_discrepancy'"),
            'cancelled_this_month' => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status='cancelled' AND YEAR(updated_at)=YEAR(CURDATE()) AND MONTH(updated_at)=MONTH(CURDATE())"),
            // Final rejections: transfers permanently closed after 2 GM rejections
            'final_rejected_transfers' => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status='final_rejected'"),
            'final_rejected_receivings' => $this->safeCount("SELECT COUNT(*) FROM warehouse_receiving WHERE status='final_rejected'"),
        ];
    }

    public function getPendingApprovals(): array
    {
        return $this->safeQuery(
            "SELECT wt.*,
                    w.name AS warehouse_name, s.name AS shop_name,
                    CONCAT(u.first_name,' ',u.last_name) AS initiated_by_name,
                    DATEDIFF(CURDATE(),DATE(wt.created_at)) AS days_waiting,
                    COUNT(wti.id) AS total_items,
                    COALESCE(SUM(wti.quantity_requested),0) AS total_quantity
             FROM warehouse_transfers wt
             JOIN warehouses w    ON w.id=wt.warehouse_id
             JOIN shops s         ON s.id=wt.shop_id
             LEFT JOIN users u    ON u.id=wt.initiated_by
             LEFT JOIN warehouse_transfer_items wti ON wti.transfer_id=wt.id
             WHERE wt.status='pending'
             GROUP BY wt.id, w.name, s.name, u.first_name, u.last_name
             ORDER BY wt.created_at ASC"
        );
    }

    /**
     * Approved transfers that have NOT yet been dispatched.
     * GM can still reject these â€” the WM has not physically sent the goods yet.
     */
    public function getApprovedNotDispatched(): array
    {
        return $this->safeQuery(
            "SELECT wt.*,
                    w.name AS warehouse_name, s.name AS shop_name,
                    CONCAT(ui.first_name,' ',ui.last_name) AS initiated_by_name,
                    CONCAT(ua.first_name,' ',ua.last_name) AS approved_by_name,
                    DATEDIFF(CURDATE(),DATE(wt.updated_at)) AS days_since_approval,
                    COUNT(wti.id) AS total_items,
                    COALESCE(SUM(wti.quantity_requested),0) AS total_quantity
             FROM warehouse_transfers wt
             JOIN warehouses w     ON w.id  = wt.warehouse_id
             JOIN shops s          ON s.id  = wt.shop_id
             LEFT JOIN users ui    ON ui.id = wt.initiated_by
             LEFT JOIN users ua    ON ua.id = wt.approved_by
             LEFT JOIN warehouse_transfer_items wti ON wti.transfer_id = wt.id
             WHERE wt.status = 'approved'
             GROUP BY wt.id, w.name, s.name, ui.first_name, ui.last_name,
                      ua.first_name, ua.last_name
             ORDER BY wt.updated_at ASC"
        );
    }

    public function getTransferList(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['1=1'];
        $bind   = [];

        if (!empty($filters['status'])) {
            $where[] = 'wt.status = :status';
            $bind['status'] = $filters['status'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where[] = 'wt.warehouse_id = :warehouse_id';
            $bind['warehouse_id'] = (int)$filters['warehouse_id'];
        }
        if (!empty($filters['shop_id'])) {
            $where[] = 'wt.shop_id = :shop_id';
            $bind['shop_id'] = (int)$filters['shop_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(wt.transfer_date) >= :date_from';
            $bind['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(wt.transfer_date) <= :date_to';
            $bind['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(wt.transfer_number LIKE :search OR w.name LIKE :search OR s.name LIKE :search)';
            $bind['search'] = '%' . $filters['search'] . '%';
        }

        $whereStr = implode(' AND ', $where);
        $total    = $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers wt JOIN warehouses w ON w.id=wt.warehouse_id JOIN shops s ON s.id=wt.shop_id WHERE {$whereStr}", $bind);
        $offset   = ($page - 1) * $perPage;

        $bind['limit']  = $perPage;
        $bind['offset'] = $offset;

        $rows = $this->safeQuery(
            "SELECT wt.*, w.name AS warehouse_name, s.name AS shop_name,
                    CONCAT(u.first_name,' ',u.last_name) AS initiated_by_name,
                    CONCAT(a.first_name,' ',a.last_name) AS approved_by_name
             FROM warehouse_transfers wt
             JOIN warehouses w    ON w.id=wt.warehouse_id
             JOIN shops s         ON s.id=wt.shop_id
             LEFT JOIN users u    ON u.id=wt.initiated_by
             LEFT JOIN users a    ON a.id=wt.approved_by
             WHERE {$whereStr}
             ORDER BY wt.created_at DESC LIMIT :limit OFFSET :offset",
            $bind
        );

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int)ceil($total / $perPage),
        ];
    }

    // â”€â”€â”€ SHOP DATA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getShopSummary(): array
    {
        return $this->safeQuery(
            "SELECT s.id, s.name, s.location, s.is_active,
                    COALESCE(SUM(si.current_quantity),0)   AS total_stock,
                    COALESCE(SUM(si.available_quantity),0) AS available_stock,
                    COUNT(DISTINCT si.product_id)          AS product_lines,
                    COUNT(DISTINCT CASE WHEN sl.status='completed' AND sl.sale_date=CURDATE() THEN sl.id END) AS sales_today,
                    COALESCE(SUM(CASE WHEN sl.status='completed' AND sl.sale_date=CURDATE() THEN sl.total_amount ELSE 0 END),0) AS revenue_today,
                    COUNT(DISTINCT wt.id) AS pending_transfers
             FROM shops s
             LEFT JOIN shop_inventory si ON si.shop_id=s.id
             LEFT JOIN sales sl          ON sl.shop_id=s.id
             LEFT JOIN warehouse_transfers wt ON wt.shop_id=s.id AND wt.status='in_transit'
             WHERE s.is_active=1
             GROUP BY s.id, s.name, s.location, s.is_active
             ORDER BY s.name"
        );
    }

    public function getShopPerformance(): array
    {
        return [
            'total_shops'        => $this->safeCount("SELECT COUNT(*) FROM shops WHERE is_active=1"),
            'sales_today'        => $this->safeCount("SELECT COUNT(*) FROM sales WHERE sale_date=CURDATE() AND status='completed'"),
            'revenue_today'      => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date=CURDATE() AND status='completed'"),
            'sales_this_week'    => $this->safeCount("SELECT COUNT(*) FROM sales WHERE sale_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) AND status='completed'"),
            'revenue_this_week'  => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) AND status='completed'"),
            'sales_this_month'   => $this->safeCount("SELECT COUNT(*) FROM sales WHERE YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE()) AND status='completed'"),
            'revenue_this_month' => $this->safeDecimal("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE()) AND status='completed'"),
            'shop_low_stock'     => $this->safeCount("SELECT COUNT(*) FROM shop_inventory si JOIN products p ON p.id=si.product_id WHERE si.current_quantity>0 AND p.min_stock_level>0 AND si.current_quantity<=p.min_stock_level"),
            'transfers_incoming' => $this->safeCount("SELECT COUNT(*) FROM warehouse_transfers WHERE status='in_transit'"),
        ];
    }

    public function getShopSalesBreakdown(): array
    {
        return $this->safeQuery(
            "SELECT sh.id, sh.name AS shop_name,
                    COUNT(s.id)                     AS tx_count,
                    COALESCE(SUM(s.total_amount),0) AS revenue
             FROM shops sh
             LEFT JOIN sales s ON s.shop_id=sh.id
                 AND YEAR(s.sale_date)=YEAR(CURDATE())
                 AND MONTH(s.sale_date)=MONTH(CURDATE())
                 AND s.status='completed'
             WHERE sh.is_active=1
             GROUP BY sh.id, sh.name
             ORDER BY revenue DESC"
        );
    }

    public function getTopProducts(int $limit = 10): array
    {
        return $this->safeQuery(
            "SELECT p.name AS product_name, p.sku,
                    COALESCE(SUM(si.quantity),0) AS qty_sold,
                    COALESCE(SUM(si.subtotal),0) AS revenue
             FROM products p
             JOIN sale_items si ON si.product_id=p.id
             JOIN sales s       ON s.id=si.sale_id
                 AND YEAR(s.sale_date)=YEAR(CURDATE())
                 AND MONTH(s.sale_date)=MONTH(CURDATE())
                 AND s.status='completed'
             GROUP BY p.id, p.name, p.sku
             ORDER BY revenue DESC LIMIT :lim",
            ['lim' => $limit]
        );
    }

    // â”€â”€â”€ INVENTORY OVERVIEW â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getInventoryOverview(): array
    {
        return $this->safeQuery(
            "SELECT p.id, p.name AS product_name, p.sku, p.min_stock_level,
                    COALESCE(SUM(wi.current_quantity),0) AS warehouse_qty,
                    COALESCE(SUM(si.current_quantity),0) AS shop_qty,
                    COALESCE(SUM(wi.current_quantity),0)+COALESCE(SUM(si.current_quantity),0) AS total_qty,
                    c.name AS category_name
             FROM products p
             LEFT JOIN warehouse_inventory wi ON wi.product_id=p.id
             LEFT JOIN shop_inventory si      ON si.product_id=p.id
             LEFT JOIN categories c           ON c.id=p.category_id
             WHERE p.is_active=1
             GROUP BY p.id, p.name, p.sku, p.min_stock_level, c.name
             ORDER BY total_qty ASC"
        );
    }

    public function getStockAlerts(): array
    {
        return $this->safeQuery(
            "SELECT p.id, p.name AS product_name, p.sku, p.min_stock_level,
                    COALESCE(SUM(wi.current_quantity),0) AS warehouse_qty,
                    COALESCE(SUM(si.current_quantity),0) AS shop_qty,
                    CASE
                        WHEN COALESCE(SUM(wi.current_quantity),0)+COALESCE(SUM(si.current_quantity),0)=0 THEN 'out_of_stock'
                        WHEN p.min_stock_level>0 AND COALESCE(SUM(wi.current_quantity),0)<=p.min_stock_level THEN 'low_stock'
                        ELSE 'ok'
                    END AS stock_status
             FROM products p
             LEFT JOIN warehouse_inventory wi ON wi.product_id=p.id
             LEFT JOIN shop_inventory si      ON si.product_id=p.id
             WHERE p.is_active=1
             GROUP BY p.id, p.name, p.sku, p.min_stock_level
             HAVING stock_status IN ('out_of_stock','low_stock')
             ORDER BY stock_status ASC, warehouse_qty ASC
             LIMIT 20"
        );
    }

    // â”€â”€â”€ SALES OVERVIEW â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getRevenueTrend(int $days = 30): array
    {
        $rows = $this->safeQuery(
            "SELECT sale_date AS day,
                    DATE_FORMAT(sale_date,'%d %b') AS day_label,
                    COUNT(*) AS tx_count,
                    COALESCE(SUM(total_amount),0) AS revenue
             FROM sales
             WHERE sale_date>=DATE_SUB(CURDATE(),INTERVAL :days DAY) AND status='completed'
             GROUP BY day, day_label ORDER BY day ASC",
            ['days' => $days - 1]
        );
        return [
            'labels'       => array_column($rows, 'day_label'),
            'revenue'      => array_map('floatval', array_column($rows, 'revenue')),
            'transactions' => array_map('intval',   array_column($rows, 'tx_count')),
        ];
    }

    public function getRecentSales(int $limit = 20): array
    {
        return $this->safeQuery(
            "SELECT s.*, sh.name AS shop_name,
                    CONCAT(u.first_name,' ',u.last_name) AS seller_name
             FROM sales s
             JOIN shops sh    ON sh.id=s.shop_id
             LEFT JOIN users u ON u.id=s.seller_id
             WHERE s.status='completed'
             ORDER BY s.completed_at DESC LIMIT :lim",
            ['lim' => $limit]
        );
    }

    // â”€â”€â”€ REPORTS DATA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getRecentActivity(int $limit = 15): array
    {
        $sales = $this->safeQuery("SELECT 'sale' AS event_type, s.id, s.sale_number AS ref_number, CONCAT(u.first_name,' ',u.last_name) AS actor, sh.name AS location_name, s.total_amount AS amount, s.completed_at AS event_at FROM sales s LEFT JOIN users u ON u.id=s.seller_id LEFT JOIN shops sh ON sh.id=s.shop_id WHERE s.status='completed' ORDER BY s.completed_at DESC LIMIT 5");
        $transfers = $this->safeQuery("SELECT 'transfer' AS event_type, wt.id, wt.transfer_number AS ref_number, CONCAT(u.first_name,' ',u.last_name) AS actor, CONCAT(w.name,' â†’ ',sh.name) AS location_name, wt.total_quantity AS amount, wt.updated_at AS event_at, wt.status FROM warehouse_transfers wt JOIN warehouses w ON w.id=wt.warehouse_id JOIN shops sh ON sh.id=wt.shop_id LEFT JOIN users u ON u.id=wt.approved_by WHERE wt.status IN ('approved','in_transit','received','received_with_discrepancy') ORDER BY wt.updated_at DESC LIMIT 5");
        $harvests  = $this->safeQuery("SELECT 'harvest' AS event_type, hb.id, hb.batch_number AS ref_number, CONCAT(u.first_name,' ',u.last_name) AS actor, f.name AS location_name, hb.initial_quantity AS amount, hb.harvest_date AS event_at FROM harvest_batches hb LEFT JOIN farms f ON f.id=hb.farm_id LEFT JOIN users u ON u.id=hb.recorded_by WHERE hb.harvest_date>=DATE_SUB(CURDATE(),INTERVAL 14 DAY) ORDER BY hb.harvest_date DESC LIMIT 5");
        $events    = array_merge($sales, $transfers, $harvests);
        usort($events, fn($a, $b) => strtotime((string)($b['event_at'] ?? '0')) <=> strtotime((string)($a['event_at'] ?? '0')));
        return array_slice($events, 0, $limit);
    }

    /**
     * Count receiving records with discrepancies awaiting GM review.
     * Used in dashboard KPI badges.
     */
    /**
     * Count receiving records with discrepancies awaiting GM review.
     * Used in dashboard KPI badges.
     */
    public function getPendingDiscrepancyReviewCount(): int
    {
        return $this->safeCount(
            "SELECT COUNT(*) FROM warehouse_receiving WHERE status = 'discrepancy_pending'"
        );
    }

    /**
     * Full list of receiving discrepancies pending GM review.
     * Used on the GM dashboard action card and the pending-review list page.
     */
    public function getPendingGMDiscrepancyReviews(): array
    {
        return $this->safeQuery(
            "SELECT
                wr.id AS receiving_id,
                wr.receiving_number,
                wr.received_date,
                wr.total_expected,
                wr.total_received,
                (wr.total_expected - wr.total_received) AS total_missing,
                wr.status AS receiving_status,
                f.name   AS farm_name,
                w.name   AS warehouse_name,
                CONCAT(u.first_name,' ',u.last_name) AS received_by_name,
                fd.dispatch_number,
                fd.dispatch_date,
                COUNT(wd.id)              AS discrepancy_count,
                GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS product_names
             FROM warehouse_receiving wr
             JOIN warehouse_discrepancies wd
               ON wd.reference_type = 'receiving' AND wd.reference_id = wr.id
               AND wd.gm_action IS NULL
             LEFT JOIN farm_dispatches fd ON fd.id = wr.dispatch_id
             LEFT JOIN farms           f  ON f.id  = wr.farm_id
             LEFT JOIN warehouses      w  ON w.id  = wr.warehouse_id
             LEFT JOIN users           u  ON u.id  = wr.received_by
             LEFT JOIN products        p  ON p.id  = wd.product_id
             WHERE wr.status = 'discrepancy_pending'
             GROUP BY wr.id, wr.receiving_number, wr.received_date,
                      wr.total_expected, wr.total_received, wr.status,
                      f.name, w.name, u.first_name, u.last_name,
                      fd.dispatch_number, fd.dispatch_date
             ORDER BY wr.received_date ASC"
        );
    }

    /**
     * Warehouseâ†’Shop transfer receiving discrepancies pending GM review.
     * Used on the GM dashboard alert card and the pending-review list page.
     */
    public function getPendingTransferDiscrepancyReviews(): array
    {
        return $this->safeQuery(
            "SELECT
                wt.id              AS transfer_id,
                wt.transfer_number,
                wt.transfer_date,
                wt.total_quantity  AS total_sent,
                w.name             AS warehouse_name,
                s.name             AS shop_name,
                s.location         AS shop_location,
                CONCAT(ur.first_name,' ',ur.last_name) AS received_by_name,
                COUNT(wd.id)              AS discrepancy_count,
                SUM(wd.variance_quantity) AS total_missing,
                SUM(wd.actual_quantity)   AS total_received,
                GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS product_names
             FROM warehouse_transfers wt
             JOIN warehouse_discrepancies wd
               ON wd.reference_type = 'transfer' AND wd.reference_id = wt.id
               AND wd.gm_action IS NULL
             LEFT JOIN warehouses w  ON w.id  = wt.warehouse_id
             LEFT JOIN shops      s  ON s.id  = wt.shop_id
             LEFT JOIN users      ur ON ur.id = wt.received_by
             LEFT JOIN products   p  ON p.id  = wd.product_id
             WHERE wt.status = 'discrepancy_pending'
             GROUP BY wt.id, wt.transfer_number, wt.transfer_date,
                      wt.total_quantity, w.name, s.name, s.location,
                      ur.first_name, ur.last_name
             ORDER BY wt.updated_at ASC"
        );
    }
}
