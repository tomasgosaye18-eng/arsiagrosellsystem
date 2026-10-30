<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMShopController.php
 * Purpose : GM shop monitoring view. Read-only.
 * Routes  : GET /gm/shops
 *           GET /gm/shops/sales
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\GeneralManager\Services\GeneralManagerService;
use AAM\GeneralManager\Services\GMCustomerOrderService;

class GMShopController extends BaseController
{
    private GeneralManagerService  $gms;
    private GMCustomerOrderService $coSvc;

    public function __construct()
    {
        parent::__construct();
        $this->gms   = new GeneralManagerService();
        $this->coSvc = new GMCustomerOrderService();
    }

    /** GET /gm/shops */
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $this->render('general_manager.shops.index', [
            'pageTitle'          => 'All Shops',
            'shops'              => $this->gms->getAllShopsDetail(),
            'shopPerformance'    => $this->gms->getShopPerformance(),
            'topProducts'        => $this->gms->getTopProducts(10),
            'shopOrderSummary'   => $this->coSvc->getShopOrderSummary(),
        ]);
    }

    /** GET /gm/shops/sales */
    public function sales(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $this->render('general_manager.shops.sales', [
            'pageTitle'           => 'Sales Overview',
            'shopBreakdown'       => $this->gms->getShopSalesBreakdown(),
            'recentSales'         => $this->gms->getRecentSales(30),
            'topProducts'         => $this->gms->getTopProducts(10),
            'shopPerf'            => $this->gms->getShopPerformance(),
            // Customer order integration
            'customerOrdersByShop'=> $this->coSvc->getOrdersByShop(),
            'customerOrderKpis'   => $this->coSvc->getKpis(),
            'recentCustomerOrders'=> $this->coSvc->getRecentOrders(15),
        ]);
    }
}
