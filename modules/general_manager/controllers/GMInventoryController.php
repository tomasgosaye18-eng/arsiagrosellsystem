<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMInventoryController.php
 * Purpose : GM cross-system inventory overview + approval of
 *           stock adjustments submitted by Warehouse Manager.
 *
 * Stock Count module has been removed. All inventory
 * discrepancies are handled through the Adjustments workflow.
 *
 * SELF-APPROVAL PREVENTION:
 *   The GM cannot approve an adjustment they submitted.
 *   Enforced at both controller and service level.
 *
 * Routes:
 *   GET  /gm/inventory                   → index()
 *   GET  /gm/adjustments                 → adjustments()
 *   GET  /gm/adjustments/pending         → adjustmentsPending()
 *   GET  /gm/adjustments/{id}            → adjustmentShow()
 *   POST /gm/adjustments/{id}/approve    → adjustmentApprove()
 *   POST /gm/adjustments/{id}/reject     → adjustmentReject()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\GeneralManager\Services\GeneralManagerService;
use AAM\Inventory\Models\StockAdjustmentModel;
use AAM\Inventory\Services\InventoryService;

class GMInventoryController extends BaseController
{
    private GeneralManagerService $gms;

    public function __construct()
    {
        parent::__construct();
        $this->gms = new GeneralManagerService();
    }

    // =========================================================
    // GET /gm/inventory — Cross-system inventory overview
    // =========================================================
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $adjModel        = new StockAdjustmentModel();
        $pendingAdjCount = $adjModel->countByStatus('pending');

        $this->render('general_manager.inventory.index', [
            'pageTitle'       => 'Inventory Overview',
            'inventory'       => $this->gms->getInventoryOverview(),
            'stockAlerts'     => $this->gms->getStockAlerts(),
            'warehousePerf'   => $this->gms->getWarehousePerformance(),
            'pendingAdjCount' => $pendingAdjCount,
        ]);
    }

    // =========================================================
    // STOCK ADJUSTMENTS — GM approval queue
    // =========================================================

    /** GET /gm/adjustments */
    public function adjustments(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $filters = [
            'status'    => $this->query('status',    ''),
            'search'    => $this->query('search',    ''),
            'date_from' => $this->query('date_from', ''),
            'date_to'   => $this->query('date_to',   ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $adjModel = new StockAdjustmentModel();
        $result   = $adjModel->paginateAll($page, 25, $filters);

        $this->render('general_manager.inventory.adjustments', [
            'pageTitle'   => 'Stock Adjustments',
            'adjustments' => $result['data'],
            'pagination'  => $result,
            'filters'     => $filters,
        ]);
    }

    /** GET /gm/adjustments/pending */
    public function adjustmentsPending(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $adjModel = new StockAdjustmentModel();
        $result   = $adjModel->paginateAll(1, 50, ['status' => 'pending']);

        $this->render('general_manager.inventory.adjustments_pending', [
            'pageTitle'   => 'Pending Adjustments — Awaiting Approval',
            'adjustments' => $result['data'],
            'pagination'  => $result,
        ]);
    }

    /** GET /gm/adjustments/{id} */
    public function adjustmentShow(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $adjModel = new StockAdjustmentModel();
        $adj      = $adjModel->getWithDetails((int)$id);

        if (!$adj) {
            $this->flash(FLASH_ERROR, 'Adjustment not found.');
            $this->redirect('/gm/adjustments');
            return;
        }

        // GM can approve only when pending and did NOT submit it themselves
        $canApprove = $adj['status'] === 'pending'
                      && (int)($adj['requested_by'] ?? 0) !== (int)$this->userId();

        $this->render('general_manager.inventory.adjustment_show', [
            'pageTitle'  => 'Adjustment — ' . $adj['adjustment_number'],
            'adj'        => $adj,
            'canApprove' => $canApprove,
        ]);
    }

    /** POST /gm/adjustments/{id}/approve */
    public function adjustmentApprove(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $adjModel = new StockAdjustmentModel();
        $adj      = $adjModel->find((int)$id);

        if (!$adj) {
            $this->flash(FLASH_ERROR, 'Adjustment not found.');
            $this->redirect('/gm/adjustments');
            return;
        }

        if ((int)($adj['requested_by'] ?? 0) === (int)$this->userId()) {
            $this->flash(FLASH_ERROR, 'You cannot approve an adjustment you submitted.');
            $this->redirect("/gm/adjustments/{$id}");
            return;
        }

        $notes = trim($this->input('notes', ''));

        try {
            $service = new InventoryService();
            $service->approveAdjustment((int)$id, $notes);
            $this->flash(FLASH_SUCCESS, 'Adjustment approved and applied to inventory.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }

        $this->redirect("/gm/adjustments/{$id}");
    }

    /** POST /gm/adjustments/{id}/reject */
    public function adjustmentReject(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $notes = trim($this->input('notes', ''));

        try {
            $service = new InventoryService();
            $service->rejectAdjustment((int)$id, $notes);
            $this->flash(FLASH_SUCCESS, 'Adjustment rejected.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }

        $this->redirect("/gm/adjustments/{$id}");
    }
}
