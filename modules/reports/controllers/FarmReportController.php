<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/reports/controllers/FarmReportController.php
 * Purpose : Farm Manager Reports controller.
 *           Handles Production, Harvest, and Dispatch reports
 *           scoped to the authenticated user's assigned farm.
 *
 * Routes  :
 *   GET /reports/farm                 → index()       — report hub + summary cards
 *   GET /reports/farm/production      → production()  — production report
 *   GET /reports/farm/harvest         → harvest()     — harvest report
 *   GET /reports/farm/dispatch        → dispatch()    — dispatch report
 *
 * Access  : ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER
 *
 * Farm scope:
 *   Farm Manager is hard-scoped to their assigned farm.
 *   Admin / GM receive all-farm (farmId = 0) data.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Reports\Controllers;

use AAM\Core\BaseController;
use AAM\Core\SessionManager;
use AAM\Core\Database;
use AAM\Reports\Models\FarmReportModel;

class FarmReportController extends BaseController
{
    private FarmReportModel $reportModel;

    public function __construct()
    {
        parent::__construct();
        $this->reportModel = new FarmReportModel();
    }

    // ──────────────────────────────────────────────────────
    // GET /reports/farm  — Report hub with summary cards
    // ──────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        [$farmId, $farm] = $this->resolveFarm();
        [$dateFrom, $dateTo] = $this->resolveDefaultDateRange();

        $summary  = $this->reportModel->getSummaryCards($farmId, $dateFrom, $dateTo);
        $products = $this->reportModel->getHarvestedProducts($farmId);

        $this->render('reports.farm.index', [
            'pageTitle'  => 'Farm Reports',
            'farm'       => $farm,
            'farmId'     => $farmId,
            'summary'    => $summary,
            'products'   => $products,
            'dateFrom'   => $dateFrom,
            'dateTo'     => $dateTo,
            'datePreset' => $this->query('preset', 'month'),
            'userRole'   => $this->userRole(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // GET /reports/farm/production  — Production Report
    // ──────────────────────────────────────────────────────

    public function production(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        [$farmId, $farm] = $this->resolveFarm();

        $filters = $this->getFilters();

        // Production aggregated by product
        $rows = $this->reportModel->getProductionByProduct(
            $farmId,
            $filters['date_from'],
            $filters['date_to'],
            (int)($filters['product_id'] ?? 0)
        );

        // Totals
        $grandTotalQty      = array_sum(array_column($rows, 'total_produced'));
        $grandTotalHarvests = array_sum(array_column($rows, 'harvest_count'));

        // Trend chart data
        $trend = $this->reportModel->getProductionTrend(
            $farmId,
            $filters['date_from'],
            $filters['date_to']
        );

        $products = $this->reportModel->getHarvestedProducts($farmId);

        $this->render('reports.farm.production', [
            'pageTitle'          => 'Production Report',
            'farm'               => $farm,
            'farmId'             => $farmId,
            'rows'               => $rows,
            'grandTotalQty'      => $grandTotalQty,
            'grandTotalHarvests' => $grandTotalHarvests,
            'trend'              => $trend,
            'products'           => $products,
            'filters'            => $filters,
            'userRole'           => $this->userRole(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // GET /reports/farm/harvest  — Harvest Report
    // ──────────────────────────────────────────────────────

    public function harvest(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        [$farmId, $farm] = $this->resolveFarm();

        $filters = $this->getFilters();
        $page    = max(1, (int)$this->query('page', 1));

        $result   = $this->reportModel->paginateHarvestReport($farmId, $page, 25, $filters);
        $products = $this->reportModel->getHarvestedProducts($farmId);

        $this->render('reports.farm.harvest', [
            'pageTitle'  => 'Harvest Report',
            'farm'       => $farm,
            'farmId'     => $farmId,
            'harvests'   => $result['data'],
            'pagination' => $result,
            'products'   => $products,
            'filters'    => $filters,
            'userRole'   => $this->userRole(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // GET /reports/farm/dispatch  — Dispatch Report
    // ──────────────────────────────────────────────────────

    public function dispatch(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        [$farmId, $farm] = $this->resolveFarm();

        $filters = $this->getFilters();
        $page    = max(1, (int)$this->query('page', 1));

        $result   = $this->reportModel->paginateDispatchReport($farmId, $page, 25, $filters);
        $products = $this->reportModel->getDispatchedProducts($farmId);

        $this->render('reports.farm.dispatch', [
            'pageTitle'  => 'Dispatch Report',
            'farm'       => $farm,
            'farmId'     => $farmId,
            'dispatches' => $result['data'],
            'pagination' => $result,
            'products'   => $products,
            'filters'    => $filters,
            'userRole'   => $this->userRole(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────

    /**
     * Resolve the farm assignment for the current user.
     * Returns [farmId, farmRow|null].
     *
     * @return array{int, array<string,mixed>|null}
     */
    private function resolveFarm(): array
    {
        $role   = $this->userRole();
        $userId = (int)$this->userId();

        // Admin / GM see all farms — no scoping
        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            return [0, null];
        }

        // 1. Session
        $sessionType = SessionManager::get(SESSION_LOCATION_TYPE);
        $sessionId   = (int)SessionManager::get(SESSION_LOCATION_ID, 0);
        if ($sessionType === 'farm' && $sessionId > 0) {
            $farm = $this->loadFarm($sessionId);
            return [$sessionId, $farm];
        }

        // 2. DB fallback
        $db = Database::getInstance();
        try {
            $row = $db->fetchOne(
                "SELECT location_id FROM user_location_assignments
                 WHERE user_id = :uid AND location_type = 'farm' LIMIT 1",
                ['uid' => $userId]
            ) ?: $db->fetchOne(
                "SELECT location_id FROM organization_assignments
                 WHERE user_id = :uid AND location_type = 'farm' AND is_active = 1
                 ORDER BY assigned_at DESC LIMIT 1",
                ['uid' => $userId]
            );

            if ($row) {
                $fid = (int)$row['location_id'];
                SessionManager::set(SESSION_LOCATION_TYPE, 'farm');
                SessionManager::set(SESSION_LOCATION_ID, $fid);
                return [$fid, $this->loadFarm($fid)];
            }
        } catch (\Throwable) {}

        return [0, null];
    }

    /**
     * Load a farm row by ID.
     */
    private function loadFarm(int $farmId): ?array
    {
        try {
            return Database::getInstance()->fetchOne(
                "SELECT id, name, location, farm_type FROM farms WHERE id = :id AND is_active = 1",
                ['id' => $farmId]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve date range from query string.
     * Supports: preset=today|week|month|custom + date_from + date_to.
     * Default: this month.
     *
     * @return array{string, string} [dateFrom, dateTo]
     */
    private function resolveDefaultDateRange(): array
    {
        return $this->computePresetDates($this->query('preset', 'month'));
    }

    /**
     * Collect all filter params from query string.
     *
     * @return array<string, string|int>
     */
    private function getFilters(): array
    {
        $preset    = $this->query('preset', 'month');
        $dateFrom  = $this->query('date_from', '');
        $dateTo    = $this->query('date_to', '');

        // If a preset is active and no custom range is specified, apply preset dates
        if ($preset !== 'custom' || ($dateFrom === '' && $dateTo === '')) {
            [$dateFrom, $dateTo] = $this->computePresetDates($preset);
        }

        return [
            'preset'     => $preset,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
            'product_id' => (int)$this->query('product_id', 0),
            'status'     => $this->query('status', ''),
            'search'     => $this->query('search', ''),
        ];
    }

    /**
     * Convert a named preset to concrete date strings.
     *
     * @return array{string, string} [from, to]
     */
    private function computePresetDates(string $preset): array
    {
        return match($preset) {
            'today'  => [date('Y-m-d'), date('Y-m-d')],
            'week'   => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
            'custom' => [$this->query('date_from', date('Y-m-01')), $this->query('date_to', date('Y-m-d'))],
            default  => [date('Y-m-01'), date('Y-m-d')],   // 'month'
        };
    }
}
