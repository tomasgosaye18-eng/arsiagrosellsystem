<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMTransferController.php
 * Purpose : GM transfer pipeline monitor + approval actions.
 *           GM approves/rejects transfer creation.
 *           GM reviews and approves/rejects receiving discrepancies.
 *
 * Routes:
 *   GET  /gm/transfers
 *   GET  /gm/transfers/pending
 *   POST /gm/transfers/{id}/approve
 *   POST /gm/transfers/{id}/reject
 *   GET  /gm/transfers/discrepancies/pending-review
 *   GET  /gm/transfers/{id}/discrepancy-review
 *   POST /gm/transfers/{id}/discrepancy-approve
 *   POST /gm/transfers/{id}/discrepancy-reject
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\GeneralManager\Services\GeneralManagerService;
use AAM\Warehouse\Models\DiscrepancyModel;
use AAM\Warehouse\Models\TransferModel;
use AAM\Warehouse\Services\WarehouseService;

class GMTransferController extends BaseController
{
    private GeneralManagerService $gms;

    public function __construct()
    {
        parent::__construct();
        $this->gms = new GeneralManagerService();
    }

    // =========================================================
    // TRANSFER PIPELINE MONITORING
    // =========================================================

    /** GET /gm/transfers */
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $filters = [
            'status'       => $this->query('status', ''),
            'warehouse_id' => $this->query('warehouse_id', ''),
            'shop_id'      => $this->query('shop_id', ''),
            'date_from'    => $this->query('date_from', ''),
            'date_to'      => $this->query('date_to', ''),
            'search'       => $this->query('search', ''),
        ];
        $page   = max(1, (int)$this->query('page', 1));
        $result = $this->gms->getTransferList($filters, $page, 20);

        $db = \AAM\Core\Database::getInstance();

        // Count pending transfer discrepancies for the badge
        $trnModel                    = new TransferModel();
        $pendingDiscrepancyCount     = $trnModel->countPendingDiscrepancyReview();

        $this->render('general_manager.transfers.index', [
            'pageTitle'               => 'Transfer Pipeline',
            'transfers'               => $result['data'],
            'pagination'              => $result,
            'filters'                 => $filters,
            'pipeline'                => $this->gms->getTransferPipeline(),
            'pendingApprovals'        => $this->gms->getPendingApprovals(),
            'pendingDiscrepancyCount' => $pendingDiscrepancyCount,
            'warehouses'              => $db->fetchAll("SELECT id, name FROM warehouses WHERE is_active=1 ORDER BY name"),
            'shops'                   => $db->fetchAll("SELECT id, name FROM shops WHERE is_active=1 ORDER BY name"),
        ]);
    }

    /** GET /gm/transfers/pending */
    public function pending(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $dispatchModel           = new \AAM\Farm\Models\FarmDispatchModel();
        $trnModel                = new TransferModel();
        $pendingDiscrepancyCount = $trnModel->countPendingDiscrepancyReview();

        $this->render('general_manager.transfers.pending', [
            'pageTitle'                => 'Pending Approvals',
            'pendingApprovals'         => $this->gms->getPendingApprovals(),
            'approvedNotDispatched'    => $this->gms->getApprovedNotDispatched(),
            'pendingFarmDispatches'    => $dispatchModel->getPendingApproval(),
            'pipeline'                 => $this->gms->getTransferPipeline(),
            'pendingDiscrepancyCount'  => $pendingDiscrepancyCount,
        ]);
    }

    // =========================================================
    // TRANSFER CREATION — APPROVE / REJECT
    // =========================================================

    /** POST /gm/transfers/{id}/approve */
    public function approve(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        try {
            (new WarehouseService())->approveTransfer((int)$id);
            $this->flash(FLASH_SUCCESS, 'Transfer approved. The Warehouse Manager can now dispatch it.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }
        $this->redirect('/gm/transfers/pending');
    }

    /** POST /gm/transfers/{id}/reject */
    public function reject(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $reason = trim($this->input('reason', ''));
        try {
            (new WarehouseService())->rejectTransfer((int)$id, $reason);
            $this->flash(FLASH_SUCCESS, 'Transfer rejected.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }
        $this->redirect('/gm/transfers/pending');
    }

    // =========================================================
    // RECEIVING DISCREPANCY WORKFLOW — GM REVIEW
    // =========================================================

    /**
     * GET /gm/transfers/discrepancies/pending-review
     * Show all Warehouse→Shop transfer receiving discrepancies awaiting GM review.
     */
    public function discrepanciesPendingReview(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $disModel = new DiscrepancyModel();
        $pending  = $disModel->getPendingTransferGMReview();

        $this->render('general_manager.transfers.discrepancy_pending_review', [
            'pageTitle' => 'Transfer Receiving Discrepancies — Pending Your Review',
            'pending'   => $pending,
        ]);
    }

    /**
     * GET /gm/transfers/{id}/discrepancy-review
     * Show details of a specific transfer's receiving discrepancy for GM review.
     */
    public function discrepancyReview(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $transferId = (int)$id;
        $trnModel   = new TransferModel();
        $transfer   = $trnModel->getWithItems($transferId);

        if (!$transfer) {
            $this->flash(FLASH_ERROR, 'Transfer not found.');
            $this->redirect('/gm/transfers/discrepancies/pending-review');
            return;
        }

        // Must be in a discrepancy-related status
        $reviewableStatuses = [
            TransferModel::STATUS_DISCREPANCY_PENDING,
            TransferModel::STATUS_GM_APPROVED,
            TransferModel::STATUS_GM_REJECTED,
            TransferModel::STATUS_RECEIVED_WITH_DISCREPANCY,
            TransferModel::STATUS_FINAL_REJECTED,
        ];
        if (!in_array($transfer['status'], $reviewableStatuses, true)) {
            $this->flash(FLASH_WARNING, 'This transfer does not have a discrepancy pending review.');
            $this->redirect('/gm/transfers/discrepancies/pending-review');
            return;
        }

        $disModel      = new DiscrepancyModel();
        $discrepancies = $disModel->getForTransfer($transferId);
        $workflowLogs  = [];
        foreach ($discrepancies as $dis) {
            $workflowLogs[$dis['id']] = $disModel->getWorkflowLogForTransfer((int)$dis['id']);
        }

        $db        = \AAM\Core\Database::getInstance();
        $warehouse = $db->fetchOne(
            "SELECT id, name FROM warehouses WHERE id = :id",
            ['id' => $transfer['warehouse_id']]
        );

        $this->render('general_manager.transfers.discrepancy_review', [
            'pageTitle'     => 'Discrepancy Review — ' . ($transfer['transfer_number'] ?? ''),
            'transfer'      => $transfer,
            'warehouse'     => $warehouse,
            'discrepancies' => $discrepancies,
            'workflowLogs'  => $workflowLogs,
        ]);
    }

    /**
     * POST /gm/transfers/{id}/discrepancy-approve
     * GM approves the receiving discrepancy — shop inventory is updated with actual qty.
     */
    public function discrepancyApprove(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $transferId = (int)$id;
        $gmNotes    = trim($this->input('gm_notes', ''));

        try {
            (new WarehouseService())->gmApproveTransferDiscrepancy($transferId, $gmNotes);
            $this->flash(FLASH_SUCCESS,
                'Discrepancy approved. Shop inventory has been updated with the actual received quantity. '
                . 'The Salesperson and Warehouse Manager have been notified.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }
        $this->redirect("/gm/transfers/{$transferId}/discrepancy-review");
    }

    /**
     * POST /gm/transfers/{id}/discrepancy-reject
     * GM rejects the discrepancy — Salesperson must review and resubmit.
     */
    public function discrepancyReject(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $transferId = (int)$id;
        $reason     = trim($this->input('rejection_reason', ''));

        if ($reason === '') {
            $this->flash(FLASH_ERROR, 'A rejection reason is required.');
            $this->redirect("/gm/transfers/{$transferId}/discrepancy-review");
            return;
        }

        try {
            (new WarehouseService())->gmRejectTransferDiscrepancy($transferId, $reason);
            $this->flash(FLASH_WARNING,
                'Discrepancy rejected. The Salesperson has been notified and must review and re-submit.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }
        $this->redirect("/gm/transfers/{$transferId}/discrepancy-review");
    }
}
