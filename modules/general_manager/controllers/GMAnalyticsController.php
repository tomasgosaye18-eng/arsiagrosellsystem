<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMAnalyticsController.php
 * Purpose : GM analytics — business insights.
 * Routes  : GET /gm/analytics
 *           GET /gm/analytics/products
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\GeneralManager\Services\GeneralManagerService;
use AAM\GeneralManager\Services\GMCustomerOrderService;

class GMAnalyticsController extends BaseController
{
    private GeneralManagerService  $gms;
    private GMCustomerOrderService $coSvc;

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

        $this->render('general_manager.analytics.index', [
            'pageTitle'           => 'Business Analytics',
            'revenueTrend'        => $this->gms->getRevenueTrend(30),
            'shopBreakdown'       => $this->gms->getShopSalesBreakdown(),
            'shopPerf'            => $this->gms->getShopPerformance(),
            'topProducts'         => $this->gms->getTopProducts(10),
            // Customer order integration
            'customerOrdersByShop'=> $this->coSvc->getOrdersByShop(),
            'customerOrderKpis'   => $this->coSvc->getKpis(),
        ]);
    }

    public function products(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $this->render('general_manager.analytics.products', [
            'pageTitle'   => 'Product Analytics',
            'topProducts' => $this->gms->getTopProducts(20),
            'stockAlerts' => $this->gms->getStockAlerts(),
        ]);
    }
}