<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMWarehouseController.php
 * Purpose : GM warehouse monitoring view. Read-only.
 * Routes  : GET /gm/warehouses
 *           GET /gm/warehouses/receiving
 *           GET /gm/warehouses/discrepancies
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\GeneralManager\Services\GeneralManagerService;
use AAM\Warehouse\Services\WarehouseService;
use AAM\Warehouse\Models\DiscrepancyModel;
use AAM\Warehouse\Models\ReceivingModel;

class GMWarehouseController extends BaseController
{
    private GeneralManagerService $gms;

    public function __construct()
    {
        parent::__construct();
        $this->gms = new GeneralManagerService();
    }

    /** GET /gm/warehouses */
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $this->render('general_manager.warehouses.index', [
            'pageTitle'     => 'All Warehouses',
            'warehouses'    => $this->gms->getAllWarehousesDetail(),
            'warehousePerf' => $this->gms->getWarehousePerformance(),
            'lowStock'      => $this->gms->getLowStockItems(25),
        ]);
    }

    /** GET /gm/warehouses/receiving */
    public function receiving(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $this->render('general_manager.warehouses.receiving', [
            'pageTitle'  => 'Warehouse Receiving',
            'receivings' => $this->gms->getRecentReceiving(30),
        ]);
    }

    /** GET /gm/warehouses/discrepancies */
    public function discrepancies(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $this->render('general_manager.warehouses.discrepancies', [
            'pageTitle'     => 'Stock Discrepancies',
            'discrepancies' => $this->gms->getOpenDiscrepancies(30),
        ]);
    }

    // =========================================================
    // RECEIVING DISCREPANCY WORKFLOW — GM REVIEW
    // =========================================================

    /**
     * GET /gm/warehouses/discrepancies/pending-review
     * Show all receiving discrepancies awaiting GM review.
     */
    public function discrepanciesPendingReview(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $disModel = new DiscrepancyModel();
        $pending  = $disModel->getPendingGMReview();

        $this->render('general_manager.warehouses.discrepancy_pending_review', [
            'pageTitle' => 'Receiving Discrepancies — Pending GM Review',
            'pending'   => $pending,
        ]);
    }

    /**
     * GET /gm/warehouses/receiving/{id}/discrepancy-review
     * Show detail of a specific receiving discrepancy for GM review.
     */
    public function discrepancyReview(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $receivingId = (int)$id;
        $rcvModel    = new ReceivingModel();
        $receiving   = $rcvModel->getWithItems($receivingId);

        if (!$receiving) {
            $this->flash(FLASH_ERROR, 'Receiving record not found.');
            $this->redirect('/gm/warehouses/discrepancies/pending-review');
            return;
        }

        // Must be in a discrepancy-related status to view the review page
        $reviewableStatuses = [
            ReceivingModel::STATUS_DISCREPANCY_PENDING,
            ReceivingModel::STATUS_GM_APPROVED,
            ReceivingModel::STATUS_GM_REJECTED,
            ReceivingModel::STATUS_FINAL_REJECTED,
            'discrepancy',
            'received',           // completed receivings may have discrepancy history
        ];
        if (!in_array($receiving['status'], $reviewableStatuses, true)) {
            $this->flash(FLASH_WARNING, 'This receiving record does not have a discrepancy pending review.');
            $this->redirect('/gm/warehouses/discrepancies/pending-review');
            return;
        }

        $disModel      = new DiscrepancyModel();
        $discrepancies = $disModel->getForReceiving($receivingId);
        $workflowLogs  = [];
        foreach ($discrepancies as &$dis) {
            $workflowLogs[$dis['id']] = $disModel->getWorkflowLog((int)$dis['id']);
        }
        unset($dis);

        $this->render('general_manager.warehouses.discrepancy_review', [
            'pageTitle'     => 'Discrepancy Review — ' . ($receiving['receiving_number'] ?? ''),
            'receiving'     => $receiving,
            'discrepancies' => $discrepancies,
            'workflowLogs'  => $workflowLogs,
        ]);
    }

    /**
     * POST /gm/warehouses/receiving/{id}/discrepancy-approve
     */
    public function discrepancyApprove(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $receivingId = (int)$id;
        $gmNotes     = trim($this->input('gm_notes', ''));

        try {
            (new WarehouseService())->gmApproveReceivingDiscrepancy($receivingId, $gmNotes);
            $this->flash(FLASH_SUCCESS,
                'Discrepancy approved. The Warehouse Manager can now complete the receiving '
                . 'and inventory will be updated with the actual received quantity.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }
        $this->redirect("/gm/warehouses/receiving/{$receivingId}/discrepancy-review");
    }

    /**
     * POST /gm/warehouses/receiving/{id}/discrepancy-reject
     */
    public function discrepancyReject(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $receivingId = (int)$id;
        $reason      = trim($this->input('rejection_reason', ''));

        if ($reason === '') {
            $this->flash(FLASH_ERROR, 'A rejection reason is required.');
            $this->redirect("/gm/warehouses/receiving/{$receivingId}/discrepancy-review");
            return;
        }

        try {
            (new WarehouseService())->gmRejectReceivingDiscrepancy($receivingId, $reason);
            $this->flash(FLASH_WARNING,
                'Discrepancy rejected. The Warehouse Manager has been notified and must review.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }
        $this->redirect("/gm/warehouses/receiving/{$receivingId}/discrepancy-review");
    }
}
