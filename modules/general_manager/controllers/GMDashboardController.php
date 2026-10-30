<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMDashboardController.php
 * Purpose : General Manager executive dashboard.
 * Route   : GET /gm/dashboard
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\Core\SessionManager;
use AAM\GeneralManager\Services\GeneralManagerService;
use AAM\GeneralManager\Services\GMCustomerOrderService;

class GMDashboardController extends BaseController
{
    private GeneralManagerService    $gms;
    private GMCustomerOrderService   $coSvc;

    public function __construct()
    {
        parent::__construct();
        $this->gms   = new GeneralManagerService();
        $this->coSvc = new GMCustomerOrderService();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $userName = SessionManager::get(SESSION_USER_NAME, 'Manager');

        $this->render('general_manager.dashboard.index', [
            'pageTitle'                => 'Executive Dashboard',
            'userName'                 => $userName,
            'kpis'                     => $this->gms->getDashboardKpis(),
            'farmPerformance'          => $this->gms->getFarmPerformance(),
            'warehousePerf'            => $this->gms->getWarehousePerformance(),
            'transferPipeline'         => $this->gms->getTransferPipeline(),
            'pendingApprovals'         => $this->gms->getPendingApprovals(),
            'pendingFarmDispatches'    => (new \AAM\Farm\Models\FarmDispatchModel())->getPendingApproval(),
            'pendingDiscrepancyReviews'        => $this->gms->getPendingGMDiscrepancyReviews(),
            'pendingTransferDiscrepancyReviews'=> $this->gms->getPendingTransferDiscrepancyReviews(),
            'shopPerformance'          => $this->gms->getShopPerformance(),
            'shopSalesBreakdown'       => $this->gms->getShopSalesBreakdown(),
            'topProducts'              => $this->gms->getTopProducts(8),
            'stockAlerts'              => $this->gms->getStockAlerts(),
            'recentActivity'           => $this->gms->getRecentActivity(),
            // Customer order integration (Phase 2 integration)
            'customerOrderKpis'        => $this->coSvc->getKpis(),
            'customerOrdersByShop'     => $this->coSvc->getOrdersByShop(),
            'recentCustomerOrders'     => $this->coSvc->getRecentOrders(8),
        ]);
    }

    /** GET /api/gm/revenue-trend */
    public function revenueTrend(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->jsonSuccess($this->gms->getRevenueTrend(30), 'Revenue trend loaded.');
    }
}
