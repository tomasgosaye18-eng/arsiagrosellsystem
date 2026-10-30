<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMFarmController.php
 * Purpose : GM farm monitoring + dispatch approval.
 * Routes  : GET  /gm/farms
 *           GET  /gm/farms/harvests
 *           GET  /gm/farms/dispatches
 *           GET  /gm/farms/dispatches/pending   ← approval queue
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\Farm\Models\FarmDispatchModel;
use AAM\GeneralManager\Services\GeneralManagerService;

class GMFarmController extends BaseController
{
    private GeneralManagerService $gms;

    public function __construct()
    {
        parent::__construct();
        $this->gms = new GeneralManagerService();
    }

    /** GET /gm/farms */
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $dispatchModel = new FarmDispatchModel();

        $this->render('general_manager.farms.index', [
            'pageTitle'            => 'All Farms',
            'farms'                => $this->gms->getAllFarmsDetail(),
            'farmPerformance'      => $this->gms->getFarmPerformance(),
            'pendingApprovalCount' => $dispatchModel->countPendingApproval(),
        ]);
    }

    /** GET /gm/farms/harvests */
    public function harvests(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $this->render('general_manager.farms.harvests', [
            'pageTitle' => 'Harvest Activity',
            'harvests'  => $this->gms->getRecentHarvests(50),
        ]);
    }

    /** GET /gm/farms/dispatches — all dispatches (monitoring) */
    public function dispatches(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $dispatchModel = new FarmDispatchModel();

        $this->render('general_manager.farms.dispatches', [
            'pageTitle'            => 'Farm Dispatches',
            'dispatches'           => $this->gms->getRecentDispatches(50),
            'pendingApprovalCount' => $dispatchModel->countPendingApproval(),
        ]);
    }

    /**
     * GET /gm/farms/dispatches/pending
     * Shows dispatch requests awaiting GM approval.
     * GM can approve or reject each one inline.
     */
    public function pendingDispatches(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $dispatchModel = new FarmDispatchModel();
        $pending       = $dispatchModel->getPendingApproval();

        $this->render('general_manager.farms.pending_dispatches', [
            'pageTitle'  => 'Pending Dispatch Approvals',
            'dispatches' => $pending,
        ]);
    }
}
