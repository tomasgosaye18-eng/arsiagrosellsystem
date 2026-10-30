<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * File        : modules/dashboard/services/DashboardService.php
 * Purpose     : Dashboard data aggregation service.
 *               Provides KPI counters, chart datasets, and
 *               activity feeds for all role dashboards.
 * Dependencies: Database, ActivityLogModel.
 * Methods:
 *   — Phase 8A (auth/legacy):
 *     getKpis()                     → legacy user KPI cards
 *     getUserRoleDistribution()     → role pie chart
 *     getUserRegistrationTrend()    → monthly signups
 *     getLoginActivityTrend()       → daily logins bar chart
 *     getActiveVsInactive()         → account status donut
 *     getRecentActivities()         → auth activity feed
 *     getSystemStatus()             → system health snapshot
 *   — Phase 9 (business):
 *     getBusinessKpis()             → full business KPI set
 *     getSalesTrend()               → 14-day revenue chart
 *     getShopPerformance()          → per-shop 30-day revenue
 *     getTopProducts()              → top 8 products by revenue
 *     getRecentBusinessActivities() → operational activity feed
 *     getFarmKpis()                 → farm production counters
 *     getWarehouseKpis()            → warehouse operation counters
 *     getShopKpis()                 → per-shop counters
 *     getRecentShopSales()          → recent shop transactions
 *     getRecentHarvests()           → recent harvest records
 *     getProductionTrend()          → harvest kg / 14 days
 *     getStockMovementTrend()       → warehouse IN/OUT / 14 days
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Dashboard\Services;

use AAM\Core\Database;
use AAM\Admin\Models\ActivityLogModel;

class DashboardService
{
    private Database         $db;
    private ActivityLogModel $activityModel;

    public function __construct()
    {
        $this->db            = Database::getInstance();
        $this->activityModel = new ActivityLogModel();
    }

    // ─── KPI Cards ────────────────────────────────────────────────────────────

    /**
     * Return all counters required for the eight KPI cards.
     *
     * @return array<string, int|string>
     */
    public function getKpis(): array
    {
        // Users
        $totalUsers    = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE account_status != 'deleted'");
        $activeUsers   = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE account_status = 'active' AND is_active = 1");
        $inactiveUsers = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE account_status != 'active' AND account_status != 'deleted'");
        $lockedUsers   = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_locked = 1");

        // Locations — gracefully handle missing tables
        $totalShops      = $this->safeCount("SELECT COUNT(*) FROM shops WHERE is_active = 1");
        $totalWarehouses = $this->safeCount("SELECT COUNT(*) FROM warehouses WHERE is_active = 1");
        $totalFarms      = $this->safeCount("SELECT COUNT(*) FROM farms WHERE is_active = 1");
        $totalProducts   = $this->safeCount("SELECT COUNT(*) FROM products WHERE is_active = 1");

        // Activity in last 24 h
        $activityToday = $this->activityModel->countSince(24);

        // Failed logins last 24 h
        $failedLoginsToday = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM login_history
             WHERE was_successful = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        return [
            'total_users'         => $totalUsers,
            'active_users'        => $activeUsers,
            'inactive_users'      => $inactiveUsers,
            'locked_users'        => $lockedUsers,
            'total_shops'         => $totalShops,
            'total_warehouses'    => $totalWarehouses,
            'total_farms'         => $totalFarms,
            'total_products'      => $totalProducts,
            'activity_today'      => $activityToday,
            'failed_logins_today' => $failedLoginsToday,
        ];
    }

    // ─── Chart Data ───────────────────────────────────────────────────────────

    /**
     * User registrations by month — last 12 months.
     * Returns labels (month names) + data (counts).
     *
     * @return array{labels: string[], data: int[]}
     */
    public function getUserRegistrationTrend(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m') AS month_key,
                DATE_FORMAT(created_at, '%b %Y')  AS month_label,
                COUNT(*) AS total
             FROM users
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
               AND account_status != 'deleted'
             GROUP BY month_key, month_label
             ORDER BY month_key ASC"
        );

        return [
            'labels' => array_column($rows, 'month_label'),
            'data'   => array_map('intval', array_column($rows, 'total')),
        ];
    }

    /**
     * Distribution of users across roles.
     *
     * @return array{labels: string[], data: int[], colors: string[]}
     */
    public function getUserRoleDistribution(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT r.display_name AS role_label, COUNT(u.id) AS total
             FROM roles r
             LEFT JOIN users u ON u.role_id = r.id
                 AND u.account_status != 'deleted'
             WHERE r.is_active = 1
             GROUP BY r.id, r.display_name
             ORDER BY r.id ASC"
        );

        $palette = [
            '#0d6efd', '#198754', '#fd7e14',
            '#dc3545', '#6f42c1', '#0dcaf0',
        ];

        $colors = [];
        foreach ($rows as $i => $_) {
            $colors[] = $palette[$i % count($palette)];
        }

        return [
            'labels' => array_column($rows, 'role_label'),
            'data'   => array_map('intval', array_column($rows, 'total')),
            'colors' => $colors,
        ];
    }

    /**
     * Login activity — successful logins per day for the last 14 days.
     *
     * @return array{labels: string[], success: int[], failed: int[]}
     */
    public function getLoginActivityTrend(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT
                DATE(attempted_at)          AS day,
                DATE_FORMAT(attempted_at, '%d %b') AS day_label,
                SUM(was_successful = 1)     AS success_count,
                SUM(was_successful = 0)     AS failed_count
             FROM login_history
             WHERE attempted_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY day, day_label
             ORDER BY day ASC"
        );

        return [
            'labels'  => array_column($rows, 'day_label'),
            'success' => array_map('intval', array_column($rows, 'success_count')),
            'failed'  => array_map('intval', array_column($rows, 'failed_count')),
        ];
    }

    /**
     * Active vs Inactive users — for donut chart.
     *
     * @return array{labels: string[], data: int[], colors: string[]}
     */
    public function getActiveVsInactive(): array
    {
        $active    = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE account_status = 'active' AND is_active = 1"
        );
        $inactive  = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE account_status = 'inactive'"
        );
        $suspended = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE account_status = 'suspended'"
        );
        $locked    = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE is_locked = 1"
        );
        $pending   = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE account_status = 'pending'"
        );

        return [
            'labels' => ['Active', 'Inactive', 'Suspended', 'Locked', 'Pending'],
            'data'   => [$active, $inactive, $suspended, $locked, $pending],
            'colors' => ['#198754', '#6c757d', '#dc3545', '#ffc107', '#0dcaf0'],
        ];
    }

    // ─── Recent Activities ─────────────────────────────────────────────────

    /**
     * Return recent activity grouped by type for the dashboard panels.
     *
     * @return array{
     *   all:             array,
     *   recent_logins:   array,
     *   user_creations:  array,
     *   password_resets: array,
     *   profile_updates: array,
     * }
     */
    public function getRecentActivities(): array
    {
        return [
            'all' => $this->activityModel->getRecent(30),
            'recent_logins' => $this->activityModel->getRecentByActions(
                [ActivityLogModel::ACTION_LOGIN], 8
            ),
            'user_creations' => $this->activityModel->getRecentByActions(
                [ActivityLogModel::ACTION_USER_CREATED], 8
            ),
            'password_resets' => $this->activityModel->getRecentByActions(
                [ActivityLogModel::ACTION_PASSWORD_RESET, ActivityLogModel::ACTION_PASSWORD_CHANGED], 8
            ),
            'profile_updates' => $this->activityModel->getRecentByActions(
                [ActivityLogModel::ACTION_PROFILE_UPDATED, ActivityLogModel::ACTION_PHOTO_UPLOADED], 8
            ),
        ];
    }

    /**
     * System status snapshot.
     *
     * @return array<string, mixed>
     */
    public function getSystemStatus(): array
    {
        return [
            'php_version'    => PHP_VERSION,
            'server_time'    => date('Y-m-d H:i:s'),
            'app_version'    => \AAM\Config\Application::get('version', '1.0.0'),
            'environment'    => \AAM\Config\Environment::current(),
            'is_production'  => \AAM\Config\Environment::isProduction(),
            'db_status'      => $this->checkDbStatus(),
            'session_status' => 'Active',
            'uptime_hours'   => $this->safeUptime(),
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function safeCount(string $sql): int
    {
        try {
            return (int) $this->db->fetchColumn($sql);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function checkDbStatus(): string
    {
        try {
            $this->db->fetchColumn("SELECT 1");
            return 'Connected';
        } catch (\Throwable) {
            return 'Error';
        }
    }

    private function safeUptime(): int
    {
        try {
            $row = $this->db->fetchOne("SHOW STATUS LIKE 'Uptime'");
            return (int) ($row['Value'] ?? 0) / 3600;
        } catch (\Throwable) {
            return 0;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 9 — BUSINESS KPIs
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Business-wide KPIs for the Manager / Admin dashboard.
     * All queries are safe — return 0 if table not yet populated.
     *
     * @return array<string, int|float|string>
     */
    public function getBusinessKpis(): array
    {
        return [
            'sales_today'            => $this->safeCount(
                "SELECT COUNT(*) FROM sales WHERE sale_date = CURDATE() AND status = 'completed'"
            ),
            'revenue_today'          => $this->safeDecimal(
                "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date = CURDATE() AND status = 'completed'"
            ),
            'sales_this_month'       => $this->safeCount(
                "SELECT COUNT(*) FROM sales WHERE YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE()) AND status = 'completed'"
            ),
            'total_warehouse_stock'  => $this->safeCount(
                "SELECT COALESCE(SUM(current_quantity),0) FROM warehouse_inventory WHERE current_quantity > 0"
            ),
            'total_shop_stock'       => $this->safeCount(
                "SELECT COALESCE(SUM(current_quantity),0) FROM shop_inventory WHERE current_quantity > 0"
            ),
            'low_stock_items'        => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_inventory wi JOIN products p ON p.id = wi.product_id WHERE wi.current_quantity > 0 AND p.min_stock_level > 0 AND wi.current_quantity <= p.min_stock_level"
            ),
            'out_of_stock_items'     => $this->safeCount(
                "SELECT COUNT(*) FROM (
                    SELECT p.id FROM products p
                    LEFT JOIN warehouse_inventory wi ON wi.product_id = p.id
                    WHERE p.is_active = 1
                    GROUP BY p.id
                    HAVING COALESCE(SUM(wi.current_quantity),0) = 0
                ) AS oos"
            ),
            'pending_transfers'      => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_transfers WHERE status = 'pending'"
            ),
            'pending_receiving'      => $this->safeCount(
                "SELECT COUNT(*) FROM farm_dispatches WHERE status IN ('in_transit','pending')"
            ),
            'open_discrepancies'     => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_discrepancies WHERE status = 'open'"
            ),
            'pending_dispatches'     => $this->safeCount(
                "SELECT COUNT(*) FROM farm_dispatches WHERE status IN ('pending','in_transit')"
            ),
            'active_shops'           => $this->safeCount(
                "SELECT COUNT(*) FROM shops WHERE is_active = 1"
            ),
            'active_sellers'         => $this->safeCount(
                "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'salesperson' AND u.account_status = 'active' AND u.is_active = 1"
            ),
            'total_products'         => $this->safeCount(
                "SELECT COUNT(*) FROM products WHERE is_active = 1"
            ),
            'total_users'            => $this->safeCount(
                "SELECT COUNT(*) FROM users WHERE account_status != 'deleted'"
            ),
            'active_users'           => $this->safeCount(
                "SELECT COUNT(*) FROM users WHERE account_status = 'active' AND is_active = 1"
            ),
            'locked_users'           => $this->safeCount(
                "SELECT COUNT(*) FROM users WHERE is_locked = 1"
            ),
            'total_farms'            => $this->safeCount(
                "SELECT COUNT(*) FROM farms WHERE is_active = 1"
            ),
            'total_warehouses'       => $this->safeCount(
                "SELECT COUNT(*) FROM warehouses WHERE is_active = 1"
            ),
        ];
    }

    /**
     * Daily sales trend for the last 14 days.
     *
     * @return array{labels: string[], data: float[], counts: int[]}
     */
    public function getSalesTrend(): array
    {
        $rows = $this->safeQuery(
            "SELECT
                sale_date                          AS day,
                DATE_FORMAT(sale_date, '%d %b')    AS day_label,
                COUNT(*)                           AS tx_count,
                COALESCE(SUM(total_amount), 0)     AS revenue
             FROM sales
             WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
               AND status = 'completed'
             GROUP BY day, day_label
             ORDER BY day ASC"
        );

        return [
            'labels' => array_column($rows, 'day_label'),
            'data'   => array_map('floatval', array_column($rows, 'revenue')),
            'counts' => array_map('intval',   array_column($rows, 'tx_count')),
        ];
    }

    /**
     * Per-shop sales summary — last 30 days.
     *
     * @return array{labels: string[], data: float[], counts: int[], colors: string[]}
     */
    public function getShopPerformance(): array
    {
        $rows = $this->safeQuery(
            "SELECT
                sh.name                        AS shop_name,
                COUNT(s.id)                    AS tx_count,
                COALESCE(SUM(s.total_amount),0) AS revenue
             FROM shops sh
             LEFT JOIN sales s ON s.shop_id = sh.id
                 AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 AND s.status = 'completed'
             WHERE sh.is_active = 1
             GROUP BY sh.id, sh.name
             ORDER BY revenue DESC
             LIMIT 8"
        );

        $palette = ['#0d6efd','#198754','#fd7e14','#dc3545','#6f42c1','#0dcaf0','#20c997','#ffc107'];
        $colors  = [];
        foreach ($rows as $i => $_) {
            $colors[] = $palette[$i % count($palette)];
        }

        return [
            'labels' => array_column($rows, 'shop_name'),
            'data'   => array_map('floatval', array_column($rows, 'revenue')),
            'counts' => array_map('intval',   array_column($rows, 'tx_count')),
            'colors' => $colors,
        ];
    }

    /**
     * Top 8 selling products — last 30 days.
     *
     * @return array{labels: string[], data: float[], quantities: int[]}
     */
    public function getTopProducts(): array
    {
        $rows = $this->safeQuery(
            "SELECT
                p.name                         AS product_name,
                COALESCE(SUM(si.quantity), 0)  AS qty_sold,
                COALESCE(SUM(si.subtotal), 0)  AS revenue
             FROM products p
             JOIN sale_items si ON si.product_id = p.id
             JOIN sales s       ON s.id = si.sale_id
                 AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 AND s.status = 'completed'
             GROUP BY p.id, p.name
             ORDER BY revenue DESC
             LIMIT 8"
        );

        return [
            'labels'     => array_column($rows, 'product_name'),
            'data'       => array_map('floatval', array_column($rows, 'revenue')),
            'quantities' => array_map('intval',   array_column($rows, 'qty_sold')),
        ];
    }

    /**
     * Recent business (operational) activity feed — not auth/login events.
     *
     * @return array
     */
    public function getRecentBusinessActivities(): array
    {
        return $this->safeQuery(
            "SELECT al.*, u.first_name, u.last_name, u.username
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.module NOT IN ('auth','login','session','password')
                OR al.module IS NULL
             ORDER BY al.created_at DESC
             LIMIT 20"
        );
    }

    /**
     * Farm KPIs (optionally scoped to a farm_id).
     *
     * @param  int|null $farmId
     * @return array<string, int>
     */
    public function getFarmKpis(?int $farmId = null): array
    {
        $where = $farmId ? "AND h.farm_id = :farm_id" : "";
        $bind  = $farmId ? ['farm_id' => $farmId] : [];

        return [
            'today_harvests'        => $this->safeCountBound(
                "SELECT COUNT(*) FROM harvest_batches h WHERE DATE(h.harvest_date) = CURDATE() {$where}", $bind
            ),
            'monthly_harvests'      => $this->safeCountBound(
                "SELECT COUNT(*) FROM harvest_batches h WHERE YEAR(h.harvest_date) = YEAR(NOW()) AND MONTH(h.harvest_date) = MONTH(NOW()) {$where}", $bind
            ),
            'pending_dispatch'      => $this->safeCountBound(
                "SELECT COUNT(*) FROM harvest_batches h WHERE h.status IN ('active','partially_dispatched') {$where}", $bind
            ),
            'dispatched_this_month' => $this->safeCountBound(
                "SELECT COUNT(*) FROM farm_dispatches d WHERE YEAR(d.dispatch_date) = YEAR(NOW()) AND MONTH(d.dispatch_date) = MONTH(NOW())" . ($farmId ? " AND d.farm_id = :farm_id" : ""), $bind
            ),
        ];
    }

    /**
     * Warehouse KPIs — Phase 10: uses real warehouse tables.
     *
     * @param  int $warehouseId  0 = warehouse 1 (default)
     * @return array<string, mixed>
     */
    public function getWarehouseKpis(int $warehouseId = 1): array
    {
        $wid = ['wid' => $warehouseId];

        return [
            'total_products'     => $this->safeCountBound(
                "SELECT COUNT(*) FROM warehouse_inventory WHERE warehouse_id = :wid", $wid
            ),
            'total_stock'        => (float)($this->db->fetchOne(
                "SELECT COALESCE(SUM(current_quantity),0) AS v FROM warehouse_inventory WHERE warehouse_id = :wid", $wid
            )['v'] ?? 0),
            'available_stock'    => (float)($this->db->fetchOne(
                "SELECT COALESCE(SUM(available_quantity),0) AS v FROM warehouse_inventory WHERE warehouse_id = :wid", $wid
            )['v'] ?? 0),
            'pending_receipts'   => $this->safeCountBound(
                "SELECT COUNT(*) FROM farm_dispatches fd
                 WHERE fd.warehouse_id = :wid
                   AND fd.status IN ('in_transit','pending')
                   AND NOT EXISTS (
                       SELECT 1 FROM warehouse_receiving wr
                       WHERE wr.dispatch_id = fd.id AND wr.status != 'cancelled'
                   )", $wid
            ),
            'pending_transfers'  => $this->safeCountBound(
                "SELECT COUNT(*) FROM warehouse_transfers WHERE warehouse_id = :wid AND status = 'pending'", $wid
            ),
            'low_stock'          => $this->safeCountBound(
                "SELECT COUNT(*) FROM warehouse_inventory WHERE warehouse_id = :wid AND min_stock_level > 0 AND available_quantity <= min_stock_level AND available_quantity > 0", $wid
            ),
            'out_of_stock'       => $this->safeCountBound(
                "SELECT COUNT(*) FROM warehouse_inventory WHERE warehouse_id = :wid AND available_quantity <= 0", $wid
            ),
            'open_discrepancies' => $this->safeCountBound(
                "SELECT COUNT(*) FROM warehouse_discrepancies WHERE warehouse_id = :wid AND status IN ('open','under_review')", $wid
            ),
            'received_today'     => (float)($this->db->fetchOne(
                "SELECT COALESCE(SUM(total_received),0) AS v FROM warehouse_receiving WHERE warehouse_id = :wid AND received_date = CURDATE() AND status NOT IN ('cancelled','rejected','final_rejected')", $wid
            )['v'] ?? 0),
            'dispatched_today'   => (float)($this->db->fetchOne(
                "SELECT COALESCE(SUM(total_quantity),0) AS v FROM warehouse_transfers WHERE warehouse_id = :wid AND transfer_date = CURDATE() AND status NOT IN ('cancelled','draft','rejected','final_rejected')", $wid
            )['v'] ?? 0),
            'expiring_soon'      => $this->safeCountBound(
                "SELECT COUNT(*) FROM warehouse_inventory WHERE warehouse_id = :wid AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND current_quantity > 0", $wid
            ),
        ];
    }

    /** Transfers currently in-transit to this shop (pending receipt). */
    public function getShopPendingReceipts(int $shopId): array
    {
        return $this->safeQuery(
            "SELECT wt.*, w.name AS warehouse_name,
                    CONCAT(u.first_name,' ',u.last_name) AS initiated_by_name
             FROM warehouse_transfers wt
             JOIN warehouses w ON w.id=wt.warehouse_id
             LEFT JOIN users u ON u.id=wt.initiated_by
             WHERE wt.shop_id=:sid AND wt.status='in_transit'
             ORDER BY wt.transfer_date DESC LIMIT 10",
            ['sid' => $shopId]
        );
    }

    /** Transfers recently received by this shop. */
    public function getShopRecentReceipts(int $shopId): array
    {
        return $this->safeQuery(
            "SELECT wt.*, w.name AS warehouse_name,
                    CONCAT(u.first_name,' ',u.last_name) AS received_by_name
             FROM warehouse_transfers wt
             JOIN warehouses w ON w.id=wt.warehouse_id
             LEFT JOIN users u ON u.id=wt.received_by
             WHERE wt.shop_id=:sid AND wt.status IN ('received','discrepancy','partially_received')
             ORDER BY wt.received_date DESC, wt.updated_at DESC LIMIT 8",
            ['sid' => $shopId]
        );
    }

    /** Low-stock products for a shop. */
    public function getShopLowStock(int $shopId): array
    {
        return $this->safeQuery(
            "SELECT si.*, p.name AS product_name, p.sku,
                    p.min_stock_level AS product_min_stock, u.abbreviation AS unit_abbr
             FROM shop_inventory si
             JOIN products p ON p.id=si.product_id
             LEFT JOIN units_of_measure u ON u.id=si.unit_id
             WHERE si.shop_id=:sid AND si.available_quantity>=0
             AND p.min_stock_level>0 AND si.available_quantity<=p.min_stock_level
             ORDER BY (si.available_quantity/p.min_stock_level) ASC LIMIT 10",
            ['sid' => $shopId]
        );
    }

    /**
     * Shop KPIs for a specific shop.
     *
     * @param  int $shopId
     * @return array<string, int|float>
     */
    public function getShopKpis(int $shopId): array
    {
        $b = ['shop_id' => $shopId];

        return [
            // Use sale_date (indexed date column) — consistent with POSController/SalesModel
            'sales_today'         => $this->safeCountBound(
                "SELECT COUNT(*) FROM sales WHERE shop_id = :shop_id AND sale_date = CURDATE() AND status = 'completed'", $b
            ),
            'transactions_today'  => $this->safeCountBound(
                "SELECT COUNT(*) FROM sales WHERE shop_id = :shop_id AND sale_date = CURDATE() AND status = 'completed'", $b
            ),
            'revenue_today'       => $this->safeDecimalBound(
                "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE shop_id = :shop_id AND sale_date = CURDATE() AND status = 'completed'", $b
            ),
            // SUM returns a decimal, not a count — use safeDecimalBound cast to int
            'total_stock'       => (int) $this->safeDecimalBound(
                "SELECT COALESCE(SUM(available_quantity),0) FROM shop_inventory WHERE shop_id = :shop_id", $b
            ),
            'low_stock'         => $this->safeCountBound(
                "SELECT COUNT(*) FROM shop_inventory si
                 WHERE si.shop_id = :shop_id
                   AND si.min_stock_level > 0
                   AND si.available_quantity > 0
                   AND si.available_quantity <= si.min_stock_level", $b
            ),
            // Wrap GROUP BY in a subquery so fetchColumn gets a single scalar
            'out_of_stock'      => $this->safeCountBound(
                "SELECT COUNT(*) FROM (
                    SELECT si.product_id
                    FROM shop_inventory si
                    WHERE si.shop_id = :shop_id AND si.available_quantity <= 0
                 ) AS oos", $b
            ),
            'pending_transfers' => $this->safeCountBound(
                "SELECT COUNT(*) FROM warehouse_transfers WHERE shop_id = :shop_id AND status IN ('approved','in_transit')", $b
            ),
        ];
    }

    /**
     * Recent sales for a specific shop.
     *
     * @param  int $shopId
     * @return array
     */
    public function getRecentShopSales(int $shopId): array
    {
        return $this->safeQuery(
            "SELECT s.*,
                    u.first_name, u.last_name,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS items_count
             FROM sales s
             LEFT JOIN users u ON u.id = s.seller_id
             WHERE s.shop_id = :shop_id
             ORDER BY s.completed_at DESC
             LIMIT 10",
            ['shop_id' => $shopId]
        );
    }

    /**
     * Recent harvests.
     *
     * @param  int|null $farmId
     * @return array
     */
    public function getRecentHarvests(?int $farmId = null): array
    {
        $where = $farmId ? "WHERE hb.farm_id = :farm_id" : "";
        $bind  = $farmId ? ['farm_id' => $farmId] : [];

        return $this->safeQuery(
            "SELECT hb.*, p.name AS product_name, f.name AS farm_name
             FROM harvest_batches hb
             LEFT JOIN products p ON p.id = hb.product_id
             LEFT JOIN farms f    ON f.id = hb.farm_id
             {$where} ORDER BY hb.harvest_date DESC LIMIT 8",
            $bind
        );
    }

    /**
     * Production trend — harvested kg per day, last 14 days.
     *
     * @param  int|null $farmId
     * @return array{labels: string[], data: float[]}
     */
    public function getProductionTrend(?int $farmId = null): array
    {
        $where = $farmId ? "AND hb.farm_id = :farm_id" : "";
        $bind  = $farmId ? ['farm_id' => $farmId] : [];

        $rows = $this->safeQuery(
            "SELECT DATE_FORMAT(hb.harvest_date,'%d %b') AS day_label,
                    DATE(hb.harvest_date) AS day,
                    COALESCE(SUM(hb.initial_quantity),0) AS total_kg
             FROM harvest_batches hb
             WHERE hb.harvest_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) {$where}
             GROUP BY day, day_label ORDER BY day ASC",
            $bind
        );

        return [
            'labels' => array_column($rows, 'day_label'),
            'data'   => array_map('floatval', array_column($rows, 'total_kg')),
        ];
    }

    /**
     * Warehouse stock movement IN/OUT — last 14 days.
     *
     * @return array{labels: string[], in: float[], out: float[]}
     */
    public function getStockMovementTrend(): array
    {
        // Phase 10: uses stock_movement_log table
        $rows = $this->safeQuery(
            "SELECT DATE(moved_at) AS day,
                    DATE_FORMAT(moved_at,'%d %b') AS day_label,
                    SUM(quantity_in)  AS qty_in,
                    SUM(quantity_out) AS qty_out
             FROM stock_movement_log
             WHERE moved_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
               AND location_type = 'warehouse'
             GROUP BY day, day_label ORDER BY day ASC"
        );

        return [
            'labels' => array_column($rows, 'day_label'),
            'in'     => array_map('floatval', array_column($rows, 'qty_in')),
            'out'    => array_map('floatval', array_column($rows, 'qty_out')),
        ];
    }

    // ─── Additional safe helpers ──────────────────────────────────────────

    private function safeDecimal(string $sql): float
    {
        try {
            return (float) ($this->db->fetchColumn($sql) ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function safeDecimalBound(string $sql, array $bind = []): float
    {
        try {
            return (float) ($this->db->fetchColumn($sql, $bind) ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function safeCountBound(string $sql, array $bind = []): int
    {
        try {
            return (int) ($this->db->fetchColumn($sql, $bind) ?? 0);
        } catch (\Throwable) {
            return 0;
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

    // ═══════════════════════════════════════════════════════════════════════
    // GM EXECUTIVE DASHBOARD — Aggregated management-level data
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * GM Farm Performance summary — all farms combined.
     *
     * @return array<string, int|float>
     */
    public function getGmFarmPerformance(): array
    {
        return [
            'total_farms'        => $this->safeCount("SELECT COUNT(*) FROM farms WHERE is_active = 1"),
            'harvests_today'     => $this->safeCount(
                "SELECT COUNT(*) FROM harvest_batches WHERE DATE(harvest_date) = CURDATE()"
            ),
            'harvests_this_month'=> $this->safeCount(
                "SELECT COUNT(*) FROM harvest_batches
                 WHERE YEAR(harvest_date) = YEAR(CURDATE()) AND MONTH(harvest_date) = MONTH(CURDATE())"
            ),
            'kg_this_month'      => $this->safeDecimal(
                "SELECT COALESCE(SUM(initial_quantity),0) FROM harvest_batches
                 WHERE YEAR(harvest_date) = YEAR(CURDATE()) AND MONTH(harvest_date) = MONTH(CURDATE())"
            ),
            'pending_dispatch'   => $this->safeCount(
                "SELECT COUNT(*) FROM harvest_batches WHERE status IN ('active','partially_dispatched')"
            ),
            'dispatches_this_month' => $this->safeCount(
                "SELECT COUNT(*) FROM farm_dispatches
                 WHERE YEAR(dispatch_date) = YEAR(CURDATE()) AND MONTH(dispatch_date) = MONTH(CURDATE())"
            ),
            'dispatched_kg_this_month' => $this->safeDecimal(
                "SELECT COALESCE(SUM(fdi.quantity_dispatched),0) FROM farm_dispatch_items fdi
                 JOIN farm_dispatches fd ON fd.id=fdi.dispatch_id
                 WHERE YEAR(fd.dispatch_date) = YEAR(CURDATE()) AND MONTH(fd.dispatch_date) = MONTH(CURDATE())"
            ),
        ];
    }

    /**
     * Recent farm activity (harvests + dispatches).
     *
     * @return array
     */
    public function getGmRecentFarmActivity(): array
    {
        return $this->safeQuery(
            "SELECT 'harvest' AS activity_type,
                    hb.id, hb.harvest_date AS activity_date,
                    p.name AS product_name, f.name AS farm_name,
                    hb.initial_quantity AS quantity,
                    NULL AS destination,
                    hb.status
             FROM harvest_batches hb
             LEFT JOIN products p ON p.id = hb.product_id
             LEFT JOIN farms f    ON f.id = hb.farm_id
             WHERE hb.harvest_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             UNION ALL
             SELECT 'dispatch' AS activity_type,
                    fd.id, fd.dispatch_date AS activity_date,
                    GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS product_name,
                    f.name AS farm_name,
                    COALESCE(SUM(fdi.quantity_dispatched),0) AS quantity,
                    w.name AS destination,
                    fd.status
             FROM farm_dispatches fd
             LEFT JOIN farm_dispatch_items fdi ON fdi.dispatch_id=fd.id
             LEFT JOIN products p    ON p.id = fdi.product_id
             LEFT JOIN farms f       ON f.id = fd.farm_id
             LEFT JOIN warehouses w  ON w.id = fd.warehouse_id
             WHERE fd.dispatch_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             GROUP BY fd.id, fd.dispatch_date, f.name, w.name, fd.status
             ORDER BY activity_date DESC LIMIT 12"
        );
    }

    /**
     * GM Warehouse performance summary — all warehouses combined.
     *
     * @return array<string, int|float>
     */
    public function getGmWarehousePerformance(): array
    {
        return [
            'total_warehouses'   => $this->safeCount("SELECT COUNT(*) FROM warehouses WHERE is_active = 1"),
            'total_stock'        => $this->safeDecimal(
                "SELECT COALESCE(SUM(current_quantity),0) FROM warehouse_inventory"
            ),
            'low_stock_lines'    => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_inventory wi
                 JOIN products p ON p.id = wi.product_id
                 WHERE wi.current_quantity > 0
                   AND p.min_stock_level > 0
                   AND wi.current_quantity <= p.min_stock_level"
            ),
            'out_of_stock_lines' => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_inventory WHERE current_quantity <= 0"
            ),
            'pending_receipts'   => $this->safeCount(
                "SELECT COUNT(*) FROM farm_dispatches
                 WHERE status IN ('in_transit','pending')
                 AND NOT EXISTS (
                     SELECT 1 FROM warehouse_receiving wr
                     WHERE wr.dispatch_id = farm_dispatches.id
                       AND wr.status NOT IN ('cancelled','final_rejected')
                 )"
            ),
            'open_discrepancies' => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_discrepancies WHERE status IN ('open','under_review')"
            ),
            'received_today'     => $this->safeDecimal(
                "SELECT COALESCE(SUM(total_received),0) FROM warehouse_receiving
                 WHERE received_date = CURDATE() AND status NOT IN ('cancelled','rejected','final_rejected')"
            ),
            'expiring_7d'        => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_inventory
                 WHERE expiry_date IS NOT NULL
                   AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                   AND current_quantity > 0"
            ),
        ];
    }

    /**
     * GM Transfer pipeline — full view across all statuses.
     *
     * @return array<string, int>
     */
    public function getGmTransferPipeline(): array
    {
        return [
            'pending_approval' => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_transfers WHERE status = 'pending'"
            ),
            'approved'         => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_transfers WHERE status = 'approved'"
            ),
            'in_transit'       => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_transfers WHERE status = 'in_transit'"
            ),
            'received_today'   => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_transfers
                 WHERE status IN ('received','received_with_discrepancy')
                   AND DATE(received_date) = CURDATE()"
            ),
            'received_this_month' => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_transfers
                 WHERE status IN ('received','received_with_discrepancy')
                   AND YEAR(received_date) = YEAR(CURDATE())
                   AND MONTH(received_date) = MONTH(CURDATE())"
            ),
            'with_discrepancy' => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_transfers WHERE status = 'received_with_discrepancy'"
            ),
            'cancelled_this_month' => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_transfers
                 WHERE status = 'cancelled'
                   AND YEAR(updated_at) = YEAR(CURDATE())
                   AND MONTH(updated_at) = MONTH(CURDATE())"
            ),
        ];
    }

    /**
     * Pending transfers awaiting GM approval — with full detail.
     *
     * @return array
     */
    public function getGmPendingApprovals(): array
    {
        return $this->safeQuery(
            "SELECT wt.*,
                    w.name AS warehouse_name,
                    s.name AS shop_name,
                    CONCAT(u.first_name,' ',u.last_name) AS initiated_by_name,
                    DATEDIFF(CURDATE(), DATE(wt.created_at)) AS days_waiting
             FROM warehouse_transfers wt
             JOIN warehouses w ON w.id = wt.warehouse_id
             JOIN shops s      ON s.id = wt.shop_id
             LEFT JOIN users u ON u.id = wt.initiated_by
             WHERE wt.status = 'pending'
             ORDER BY wt.created_at ASC
             LIMIT 20"
        );
    }

    /**
     * GM Shop performance summary — all shops.
     *
     * @return array<string, int|float>
     */
    public function getGmShopPerformance(): array
    {
        return [
            'total_shops'          => $this->safeCount("SELECT COUNT(*) FROM shops WHERE is_active = 1"),
            'total_shop_stock'     => $this->safeDecimal(
                "SELECT COALESCE(SUM(current_quantity),0) FROM shop_inventory"
            ),
            'shop_low_stock'       => $this->safeCount(
                "SELECT COUNT(*) FROM shop_inventory si
                 JOIN products p ON p.id = si.product_id
                 WHERE si.current_quantity > 0
                   AND p.min_stock_level > 0
                   AND si.current_quantity <= p.min_stock_level"
            ),
            'transfers_incoming'   => $this->safeCount(
                "SELECT COUNT(*) FROM warehouse_transfers WHERE status = 'in_transit'"
            ),
            'sales_today'          => $this->safeCount(
                "SELECT COUNT(*) FROM sales WHERE sale_date = CURDATE() AND status = 'completed'"
            ),
            'revenue_today'        => $this->safeDecimal(
                "SELECT COALESCE(SUM(total_amount),0) FROM sales
                 WHERE sale_date = CURDATE() AND status = 'completed'"
            ),
            'sales_this_week'      => $this->safeCount(
                "SELECT COUNT(*) FROM sales
                 WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status = 'completed'"
            ),
            'revenue_this_week'    => $this->safeDecimal(
                "SELECT COALESCE(SUM(total_amount),0) FROM sales
                 WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status = 'completed'"
            ),
            'sales_this_month'     => $this->safeCount(
                "SELECT COUNT(*) FROM sales
                 WHERE YEAR(sale_date) = YEAR(CURDATE())
                   AND MONTH(sale_date) = MONTH(CURDATE())
                   AND status = 'completed'"
            ),
            'revenue_this_month'   => $this->safeDecimal(
                "SELECT COALESCE(SUM(total_amount),0) FROM sales
                 WHERE YEAR(sale_date) = YEAR(CURDATE())
                   AND MONTH(sale_date) = MONTH(CURDATE())
                   AND status = 'completed'"
            ),
        ];
    }

    /**
     * Per-shop sales breakdown for the current month.
     *
     * @return array
     */
    public function getGmShopSalesBreakdown(): array
    {
        return $this->safeQuery(
            "SELECT
                sh.id, sh.name AS shop_name,
                COUNT(s.id)                    AS tx_count,
                COALESCE(SUM(s.total_amount),0) AS revenue
             FROM shops sh
             LEFT JOIN sales s ON s.shop_id = sh.id
                 AND YEAR(s.sale_date) = YEAR(CURDATE())
                 AND MONTH(s.sale_date) = MONTH(CURDATE())
                 AND s.status = 'completed'
             WHERE sh.is_active = 1
             GROUP BY sh.id, sh.name
             ORDER BY revenue DESC"
        );
    }

    /**
     * Top selling products this month.
     *
     * @return array
     */
    public function getGmTopProducts(): array
    {
        return $this->safeQuery(
            "SELECT
                p.name AS product_name, p.sku,
                COALESCE(SUM(si.quantity),0)  AS qty_sold,
                COALESCE(SUM(si.subtotal),0)  AS revenue
             FROM products p
             JOIN sale_items si ON si.product_id = p.id
             JOIN sales s       ON s.id = si.sale_id
                 AND YEAR(s.sale_date) = YEAR(CURDATE())
                 AND MONTH(s.sale_date) = MONTH(CURDATE())
                 AND s.status = 'completed'
             GROUP BY p.id, p.name, p.sku
             ORDER BY revenue DESC
             LIMIT 8"
        );
    }

    /**
     * Products requiring attention (low stock, out of stock, expiring).
     *
     * @return array
     */
    public function getGmStockAlerts(): array
    {
        return $this->safeQuery(
            "SELECT
                p.id, p.name AS product_name, p.sku,
                COALESCE(SUM(wi.current_quantity),0) AS warehouse_qty,
                COALESCE(SUM(si.current_quantity),0) AS shop_qty,
                p.min_stock_level,
                CASE
                    WHEN COALESCE(SUM(wi.current_quantity),0) + COALESCE(SUM(si.current_quantity),0) = 0 THEN 'out_of_stock'
                    WHEN p.min_stock_level > 0
                         AND COALESCE(SUM(wi.current_quantity),0) <= p.min_stock_level THEN 'low_stock'
                    ELSE 'ok'
                END AS stock_status
             FROM products p
             LEFT JOIN warehouse_inventory wi ON wi.product_id = p.id
             LEFT JOIN shop_inventory si      ON si.product_id = p.id
             WHERE p.is_active = 1
             GROUP BY p.id, p.name, p.sku, p.min_stock_level
             HAVING stock_status IN ('out_of_stock','low_stock')
             ORDER BY stock_status ASC, warehouse_qty ASC
             LIMIT 10"
        );
    }

    /**
     * Meaningful recent business activity feed for the GM.
     * Shows operational events across all modules.
     *
     * @return array
     */
    public function getGmRecentActivity(): array
    {
        $events = [];

        // Recent completed sales
        $sales = $this->safeQuery(
            "SELECT 'sale' AS event_type,
                    s.id, s.sale_number AS ref_number,
                    CONCAT(u.first_name,' ',u.last_name) AS actor,
                    sh.name AS location_name,
                    s.total_amount AS amount,
                    s.completed_at AS event_at
             FROM sales s
             LEFT JOIN users u  ON u.id = s.seller_id
             LEFT JOIN shops sh ON sh.id = s.shop_id
             WHERE s.status = 'completed'
             ORDER BY s.completed_at DESC LIMIT 5"
        );

        // Recent transfer status changes
        $transfers = $this->safeQuery(
            "SELECT 'transfer' AS event_type,
                    wt.id, wt.transfer_number AS ref_number,
                    CONCAT(u.first_name,' ',u.last_name) AS actor,
                    CONCAT(w.name,' → ',sh.name) AS location_name,
                    wt.total_quantity AS amount,
                    wt.updated_at AS event_at,
                    wt.status
             FROM warehouse_transfers wt
             JOIN warehouses w ON w.id = wt.warehouse_id
             JOIN shops sh     ON sh.id = wt.shop_id
             LEFT JOIN users u ON u.id = wt.approved_by
             WHERE wt.status IN ('approved','in_transit','received','received_with_discrepancy')
             ORDER BY wt.updated_at DESC LIMIT 5"
        );

        // Recent warehouse receiving
        $receivings = $this->safeQuery(
            "SELECT 'receiving' AS event_type,
                    wr.id, fd.dispatch_number AS ref_number,
                    CONCAT(u.first_name,' ',u.last_name) AS actor,
                    w.name AS location_name,
                    wr.total_received AS amount,
                    wr.received_date AS event_at,
                    wr.status
             FROM warehouse_receiving wr
             JOIN farm_dispatches fd ON fd.id = wr.dispatch_id
             JOIN warehouses w       ON w.id  = wr.warehouse_id
             LEFT JOIN users u       ON u.id  = wr.received_by
             WHERE wr.received_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             ORDER BY wr.received_date DESC LIMIT 4"
        );

        // Recent harvests
        $harvests = $this->safeQuery(
            "SELECT 'harvest' AS event_type,
                    hb.id, hb.batch_number AS ref_number,
                    CONCAT(u.first_name,' ',u.last_name) AS actor,
                    f.name AS location_name,
                    hb.initial_quantity AS amount,
                    hb.harvest_date AS event_at
             FROM harvest_batches hb
             LEFT JOIN farms f    ON f.id  = hb.farm_id
             LEFT JOIN users u    ON u.id  = hb.recorded_by
             WHERE hb.harvest_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             ORDER BY hb.harvest_date DESC LIMIT 4"
        );

        $events = array_merge($sales, $transfers, $receivings, $harvests);

        // Sort by event_at descending
        usort($events, function($a, $b) {
            return strtotime((string)($b['event_at'] ?? '0')) <=> strtotime((string)($a['event_at'] ?? '0'));
        });

        return array_slice($events, 0, 15);
    }

    /**
     * Revenue trend for GM — last 30 days daily.
     *
     * @return array{labels: string[], revenue: float[], transactions: int[]}
     */
    public function getGmRevenueTrend(): array
    {
        $rows = $this->safeQuery(
            "SELECT
                sale_date                         AS day,
                DATE_FORMAT(sale_date, '%d %b')   AS day_label,
                COUNT(*)                          AS tx_count,
                COALESCE(SUM(total_amount), 0)    AS revenue
             FROM sales
             WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
               AND status = 'completed'
             GROUP BY day, day_label
             ORDER BY day ASC"
        );

        return [
            'labels'       => array_column($rows, 'day_label'),
            'revenue'      => array_map('floatval', array_column($rows, 'revenue')),
            'transactions' => array_map('intval', array_column($rows, 'tx_count')),
        ];
    }
}
