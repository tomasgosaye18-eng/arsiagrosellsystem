<?php
/**
 * =========================================================
 * ORGANIZATION CONTROLLER
 * =========================================================
 * File    : modules/organization/controllers/OrganizationController.php
 * Purpose : Organization dashboard and hierarchy views.
 *           Aggregates stats from farms, warehouses, shops,
 *           and assignments for executive view.
 * Deps    : BaseController, FarmModel, WarehouseModel,
 *           ShopModel, AssignmentModel
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Organization\Controllers;

use AAM\Core\BaseController;
use AAM\Organization\Models\FarmModel;
use AAM\Organization\Models\WarehouseModel;
use AAM\Organization\Models\ShopModel;
use AAM\Organization\Models\AssignmentModel;

class OrganizationController extends BaseController
{
    private FarmModel       $farmModel;
    private WarehouseModel  $warehouseModel;
    private ShopModel       $shopModel;
    private AssignmentModel $assignmentModel;

    public function __construct()
    {
        parent::__construct();
        $this->farmModel       = new FarmModel();
        $this->warehouseModel  = new WarehouseModel();
        $this->shopModel       = new ShopModel();
        $this->assignmentModel = new AssignmentModel();
    }

    // ── Organization Dashboard ────────────────────────────────────────────────

    public function dashboard(): void
    {
        $farmStats       = $this->farmModel->getStatistics();
        $warehouseStats  = $this->warehouseModel->getStatistics();
        $shopStats       = $this->shopModel->getStatistics();
        $assignmentStats = $this->assignmentModel->getStatistics();

        $this->render('organization/dashboard', [
            'title'           => 'Organization Dashboard',
            'farmStats'       => $farmStats,
            'warehouseStats'  => $warehouseStats,
            'shopStats'       => $shopStats,
            'assignmentStats' => $assignmentStats,
            'recentFarms'     => $this->farmModel->getAllWithRelations([], 5, 0),
            'recentShops'     => $this->shopModel->getAllWithRelations([], 5, 0),
        ]);
    }

    // ── Dashboard chart data (AJAX) ───────────────────────────────────────────

    public function chartData(): void
    {
        $this->json([
            'success' => true,
            'data' => [
                'farms'      => $this->farmModel->getStatistics(),
                'warehouses' => $this->warehouseModel->getStatistics(),
                'shops'      => $this->shopModel->getStatistics(),
                'assignments'=> $this->assignmentModel->getStatistics(),
            ],
        ]);
    }
}
