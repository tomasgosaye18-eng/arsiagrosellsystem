<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * File        : modules/dashboard/controllers/DashboardController.php
 * Purpose     : Dashboard controller — role-aware dashboards.
 *               Phase 9: business-focused KPIs per role.
 * Routes      :
 *   GET /dashboard                → index()
 *   GET /analytics                → analytics()
 *   GET /analytics/products       → productAnalytics()
 *   GET /api/dashboard/charts     → chartData() (AJAX)
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Dashboard\Controllers;

use AAM\Core\BaseController;
use AAM\Core\SessionManager;
use AAM\Dashboard\Services\DashboardService;

class DashboardController extends BaseController
{
    private DashboardService $dashboardService;

    public function __construct()
    {
        parent::__construct();
        $this->dashboardService = new DashboardService();
    }

    /**
     * GET /dashboard
     * Role-aware dashboard.
     */
    public function index(): void
    {
        $this->requireAuth();

        $role     = $this->userRole();
        $user     = $this->currentUser();
        $userName = SessionManager::get(SESSION_USER_NAME, 'User');

        // ── Customer role → redirect to Customer storefront ──────────────────
        // A customer who somehow reaches /dashboard gets redirected to their
        // own area. They cannot access any internal management dashboard.
        if ($role === ROLE_CUSTOMER) {
            $this->redirect('/customer');
            return;
        }

        // ── System Administrator → dedicated /admin/dashboard ─────────────
        if ($role === ROLE_ADMIN) {
            $this->redirect('/admin/dashboard');
            return;
        }

        // ── Salesperson / Seller → Shop Dashboard (primary action: POS) ──────
        if ($role === ROLE_SALESPERSON) {
            // Resolve assigned shop_id from session (never trust user array directly)
            $shopId = (int) SessionManager::get(SESSION_LOCATION_ID, 0);
            $ltype  = (string) SessionManager::get(SESSION_LOCATION_TYPE, '');

            // Fallback: check user_location_assignments if session not populated
            if ($shopId <= 0 || $ltype !== 'shop') {
                try {
                    $row = \AAM\Core\Database::getInstance()->fetchOne(
                        "SELECT location_id FROM user_location_assignments
                         WHERE user_id=:uid AND location_type='shop'
                         ORDER BY assigned_at DESC LIMIT 1",
                        ['uid' => $this->userId()]
                    );
                    $shopId = $row ? (int)$row['location_id'] : 0;
                } catch (\Throwable) {}
            }

            $shopInfo = null;
            try {
                if ($shopId > 0) {
                    $shopInfo = \AAM\Core\Database::getInstance()->fetchOne(
                        "SELECT id, name, location FROM shops WHERE id = :id AND is_active = 1",
                        ['id' => $shopId]
                    );
                }
            } catch (\Throwable) {}

            $shopKpis    = $shopId > 0 ? $this->dashboardService->getShopKpis($shopId) : [];
            $recentSales = $shopId > 0 ? $this->dashboardService->getRecentShopSales($shopId) : [];

            // Active shift for this seller
            $activeShift = null;
            if ($shopId > 0) {
                try {
                    $activeShift = \AAM\Core\Database::getInstance()->fetchOne(
                        "SELECT id, shift_number, status, opened_at,
                                total_transactions, total_sales_amount
                         FROM seller_shifts
                         WHERE seller_id=:uid AND shop_id=:sid AND status='open'
                         ORDER BY opened_at DESC LIMIT 1",
                        ['uid' => $this->userId(), 'sid' => $shopId]
                    );
                } catch (\Throwable) {}
            }

            // Customer order KPIs for the dashboard widget — Phase 6
            $customerOrderKpis = [];
            if ($shopId > 0) {
                try {
                    $customerOrderKpis = \AAM\Core\Database::getInstance()->fetchOne(
                        "SELECT
                            SUM(CASE WHEN order_status='paid'             THEN 1 ELSE 0 END) AS new_count,
                            SUM(CASE WHEN order_status='preparing'        THEN 1 ELSE 0 END) AS preparing_count,
                            SUM(CASE WHEN order_status='ready_for_pickup' THEN 1 ELSE 0 END) AS ready_count,
                            SUM(CASE WHEN order_status IN ('paid','preparing','ready_for_pickup') THEN 1 ELSE 0 END) AS active_count
                         FROM customer_orders
                         WHERE shop_id = :sid",
                        ['sid' => $shopId]
                    ) ?? [];
                } catch (\Throwable) {}
            }

            $this->render('dashboard.cashier', [
                'pageTitle'          => 'Shop Dashboard',
                'user'               => $user,
                'userName'           => $userName,
                'userRole'           => $role,
                'shopInfo'           => $shopInfo,
                'shopKpis'           => $shopKpis,
                'recentSales'        => $recentSales,
                'activeShift'        => $activeShift,
                'customerOrderKpis'  => $customerOrderKpis,
            ]);
            return;
        }

        // ── General Manager → dedicated /gm/dashboard ─────────────────────
        if ($role === ROLE_GENERAL_MANAGER) {
            $this->redirect('/gm/dashboard');
            return;
        }

        // ── Farm Manager → dedicated farm dashboard ────────────────────────
        if ($role === ROLE_FARM_MANAGER) {
            $this->redirect('/farm/dashboard');
            return;
        }

        // ── Warehouse Manager → Warehouse dashboard ───────────────────────
        if ($role === ROLE_WAREHOUSE_MANAGER) {
            $lid   = (int)SessionManager::get(SESSION_LOCATION_ID, 0);
            $ltype = (string)SessionManager::get(SESSION_LOCATION_TYPE, '');

            // Only use session if it points to a warehouse
            $warehouseId = ($ltype === 'warehouse' && $lid > 0) ? $lid : 0;

            // Fallback to user_location_assignments
            if ($warehouseId <= 0) {
                try {
                    $row = \AAM\Core\Database::getInstance()->fetchOne(
                        "SELECT location_id FROM user_location_assignments
                         WHERE user_id=:uid AND location_type='warehouse'
                         ORDER BY assigned_at DESC LIMIT 1",
                        ['uid' => $this->userId()]
                    );
                    $warehouseId = $row ? (int)$row['location_id'] : 0;
                } catch (\Throwable) {}
            }

            // Fallback to organization_assignments
            if ($warehouseId <= 0) {
                try {
                    $row = \AAM\Core\Database::getInstance()->fetchOne(
                        "SELECT location_id FROM organization_assignments
                         WHERE user_id=:uid AND location_type='warehouse' AND is_active=1
                         ORDER BY assigned_at DESC LIMIT 1",
                        ['uid' => $this->userId()]
                    );
                    $warehouseId = $row ? (int)$row['location_id'] : 0;
                } catch (\Throwable) {}
            }

            // Load REAL warehouse info from DB (system admin created record)
            $warehouseInfo = null;
            if ($warehouseId > 0) {
                try {
                    $warehouseInfo = \AAM\Core\Database::getInstance()->fetchOne(
                        "SELECT id, name, COALESCE(warehouse_name, name) AS warehouse_name,
                                COALESCE(warehouse_code, CONCAT('WH-', LPAD(id,3,'0'))) AS warehouse_code,
                                warehouse_type, location, is_active
                         FROM warehouses WHERE id = :id AND is_active = 1",
                        ['id' => $warehouseId]
                    );
                } catch (\Throwable) {}
            }

            $warehouseKpis = $warehouseId > 0 ? $this->dashboardService->getWarehouseKpis($warehouseId) : [];

            $this->render('dashboard.warehouse_manager', [
                'pageTitle'     => 'Dashboard',
                'user'          => $user,
                'userName'      => $userName,
                'userRole'      => $role,
                'warehouseId'   => $warehouseId,
                'warehouseInfo' => $warehouseInfo,
                'warehouseKpis' => $warehouseKpis,
            ]);
            return;
        }

        // Fallback — unknown role gets a minimal safe dashboard
        $this->render('dashboard._stub', [
            'pageTitle' => 'Dashboard',
            'user'      => $user,
            'userName'  => $userName,
            'userRole'  => $role,
        ]);
    }

    /**
     * GET /admin/dashboard
     * System Administrator exclusive dashboard.
     */
    public function adminDashboard(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_ADMIN);

        $user     = $this->currentUser();
        $userName = SessionManager::get(SESSION_USER_NAME, 'User');
        $kpis     = $this->dashboardService->getBusinessKpis();

        // System-specific KPIs
        $db = \AAM\Core\Database::getInstance();

        // Recent login activity
        $recentLogins = [];
        try {
            $recentLogins = $db->fetchAll(
                "SELECT lh.*, CONCAT(u.first_name,' ',u.last_name) AS user_name,
                        u.username, r.display_name AS role_name
                 FROM login_history lh
                 LEFT JOIN users u ON u.id = lh.user_id
                 LEFT JOIN roles r ON r.id = u.role_id
                 ORDER BY lh.attempted_at DESC
                 LIMIT 10"
            );
        } catch (\Throwable) {}

        // Locked/inactive accounts needing attention
        $lockedUsers = [];
        try {
            $lockedUsers = $db->fetchAll(
                "SELECT u.id, u.username, CONCAT(u.first_name,' ',u.last_name) AS full_name,
                        r.display_name AS role_name, u.failed_login_attempts, u.updated_at AS locked_at
                 FROM users u
                 LEFT JOIN roles r ON r.id = u.role_id
                 WHERE u.is_locked = 1
                 ORDER BY u.updated_at DESC
                 LIMIT 10"
            );
        } catch (\Throwable) {}

        $this->render('dashboard.system_admin', [
            'pageTitle'    => 'System Administration',
            'user'         => $user,
            'userName'     => $userName,
            'userRole'     => ROLE_ADMIN,
            'kpis'         => $kpis,
            'recentLogins' => $recentLogins,
            'lockedUsers'  => $lockedUsers,
        ]);
    }

    /**
     * GET /analytics  — redirects to new GM module
     */
    public function analytics(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->redirect('/gm/analytics');
    }

    /**
     * GET /analytics/products  — redirects to new GM module
     */
    public function productAnalytics(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->redirect('/gm/analytics/products');
    }

    /**
     * GET /api/dashboard/charts
     * Returns chart datasets as JSON for Chart.js.
     * All authenticated roles may call this — the view only
     * renders the charts relevant to the role's dashboard.
     */
    public function chartData(): void
    {
        $this->requireAuth();

        $this->jsonSuccess([
            'sales_trend'       => $this->dashboardService->getSalesTrend(),
            'shop_performance'  => $this->dashboardService->getShopPerformance(),
            'top_products'      => $this->dashboardService->getTopProducts(),
            'stock_movement'    => $this->dashboardService->getStockMovementTrend(),
            'production_trend'  => $this->dashboardService->getProductionTrend(),
            // Keep legacy auth charts for admin system view
            'role_distribution' => $this->dashboardService->getUserRoleDistribution(),
            'login_activity'    => $this->dashboardService->getLoginActivityTrend(),
        ], 'Chart data loaded.');
    }

    /**
     * GET /api/dashboard/farm-charts
     * Farm-specific chart data.
     */
    public function farmChartData(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER, ROLE_FARM_MANAGER);

        $this->jsonSuccess([
            'production_trend' => $this->dashboardService->getProductionTrend(),
        ], 'Farm chart data loaded.');
    }

    /**
     * GET /api/dashboard/warehouse-charts
     * Warehouse-specific chart data.
     */
    public function warehouseChartData(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER, ROLE_WAREHOUSE_MANAGER);

        $this->jsonSuccess([
            'stock_movement' => $this->dashboardService->getStockMovementTrend(),
        ], 'Warehouse chart data loaded.');
    }

    /**
     * GET /api/gm/revenue-trend
     * GM revenue trend — last 30 days. General Manager only.
     */
    public function gmRevenueTrend(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $this->jsonSuccess(
            $this->dashboardService->getGmRevenueTrend(),
            'GM revenue trend loaded.'
        );
    }
}
