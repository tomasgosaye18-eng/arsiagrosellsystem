<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Transfer Workflow (Phase 10+)
 * =========================================================
 * File    : modules/transfer/controllers/TransferController.php
 * Purpose : Warehouse → Shop transfer management.
 *
 * ROLE SEPARATION:
 *   Warehouse Manager : create, dispatch, cancel, view
 *   General Manager   : approve, reject, discrepancy approve/reject, view all
 *   Salesperson/Shop  : view incoming, receive, resubmit discrepancy
 *
 * WORKFLOW:
 *   pending → approved (GM) → in_transit (WM dispatch)
 *     → IF quantities match:   received
 *     → IF quantities differ:  discrepancy_pending (→ GM review)
 *         → GM approves:       received_with_discrepancy + shop inventory updated
 *         → GM rejects:        gm_rejected (→ Salesperson corrects + resubmits)
 *
 * SECURITY:
 *   - WM cannot approve their own transfer.
 *   - Dispatch only after approval.
 *   - Receive only when in_transit (or gm_rejected for resubmit).
 *   - Salesperson cannot approve their own discrepancy.
 *   - Only GM can approve/reject discrepancies.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Transfer\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Warehouse\Models\WarehouseInventoryModel;
use AAM\Warehouse\Models\TransferModel;
use AAM\Warehouse\Models\DiscrepancyModel;
use AAM\Warehouse\Services\WarehouseService;

class TransferController extends BaseController
{
    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function resolveWarehouseId(): int
    {
        $role = $this->userRole();
        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            $wid = (int)$this->query('wid', 1);
            return $wid > 0 ? $wid : 1;
        }
        $lid   = (int)SessionManager::get(SESSION_LOCATION_ID, 0);
        $ltype = SessionManager::get(SESSION_LOCATION_TYPE, '');
        if ($ltype === 'warehouse' && $lid > 0) return $lid;

        $db  = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT location_id FROM user_location_assignments
             WHERE user_id = :uid AND location_type = 'warehouse' LIMIT 1",
            ['uid' => $this->userId()]
        );
        return $row ? (int)$row['location_id'] : 1;
    }

    private function resolveShopId(): int
    {
        $role = $this->userRole();
        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            $sid = (int)$this->query('shop_id', 0);
            return $sid > 0 ? $sid : 0;
        }
        $lid   = (int)SessionManager::get(SESSION_LOCATION_ID, 0);
        $ltype = SessionManager::get(SESSION_LOCATION_TYPE, '');
        if ($ltype === 'shop' && $lid > 0) return $lid;

        $db  = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT location_id FROM user_location_assignments
             WHERE user_id = :uid AND location_type = 'shop' LIMIT 1",
            ['uid' => $this->userId()]
        );
        return $row ? (int)$row['location_id'] : 0;
    }

    private function isInitiator(array $transfer): bool
    {
        return (int)($transfer['initiated_by'] ?? -1) === (int)$this->userId();
    }

    /**
     * Compute the set of actions the current user may perform on this transfer.
     *
     * AAM Business Rules:
     *  • canApprove  : GM ONLY + status = pending
     *  • canReject   : GM ONLY + status = pending
     *  • canDispatch : WM ONLY + status = approved
     *  • canReceive  : Salesperson/GM + status = in_transit OR gm_rejected
     *                  AND current user is NOT the initiator
     *  • canCancel   : WM ONLY + status IN (pending, draft, approved)
     */
    private function resolveAllowedActions(array $transfer): array
    {
        $role   = $this->userRole();
        $status = $transfer['status'] ?? '';

        $isApprover      = in_array($role, [ROLE_GENERAL_MANAGER], true);
        $isWarehouseSide = in_array($role, [ROLE_WAREHOUSE_MANAGER], true);
        // Only Salesperson can physically receive at the shop — GM cannot submit receiving
        $isShopSide      = in_array($role, [ROLE_SALESPERSON], true);

        $receivableStatuses = [
            TransferModel::STATUS_IN_TRANSIT,
            TransferModel::STATUS_GM_REJECTED,
        ];

        // GM can reject: pending (not yet approved) OR approved (not yet dispatched)
        $rejectableByGM = in_array($status, [
            TransferModel::STATUS_PENDING,
            TransferModel::STATUS_APPROVED,
        ], true);

        return [
            'canApprove'  => $isApprover && $status === TransferModel::STATUS_PENDING,
            'canReject'   => $isApprover && $rejectableByGM,
            'canDispatch' => $isWarehouseSide && $status === TransferModel::STATUS_APPROVED,
            'canReceive'  => $isShopSide
                             && in_array($status, $receivableStatuses, true)
                             && !$this->isInitiator($transfer),
            'canCancel'   => $isWarehouseSide && in_array($status, [
                                 TransferModel::STATUS_DRAFT,
                                 TransferModel::STATUS_PENDING,
                                 TransferModel::STATUS_APPROVED,
                             ], true),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // WAREHOUSE-SIDE VIEWS
    // ─────────────────────────────────────────────────────────────────────

    /** GET /transfers — Warehouse-scoped list. Salesperson → /transfers/incoming */
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);

        if (in_array($this->userRole(), [ROLE_SALESPERSON], true)) {
            $this->redirect('/transfers/incoming');
            return;
        }

        $warehouseId = $this->resolveWarehouseId();
        $db          = Database::getInstance();

        $filters = [
            'status'    => $this->query('status',    ''),
            'shop_id'   => $this->query('shop_id',   ''),
            'search'    => $this->query('search',    ''),
            'date_from' => $this->query('date_from', ''),
            'date_to'   => $this->query('date_to',   ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $trnModel = new TransferModel();
        $result   = $trnModel->paginateWarehouse($warehouseId, $page, PAGINATION_DEFAULT, $filters);

        $shops     = $db->fetchAll("SELECT id, name FROM shops WHERE is_active = 1 ORDER BY name");
        $warehouse = $db->fetchOne("SELECT id, name FROM warehouses WHERE id = :id", ['id' => $warehouseId]);

        $this->render('transfer.index', [
            'pageTitle'   => 'Outgoing Transfers',
            'warehouse'   => $warehouse,
            'warehouseId' => $warehouseId,
            'transfers'   => $result['data'],
            'pagination'  => $result,
            'filters'     => $filters,
            'shops'       => $shops,
            'userRole'    => $this->userRole(),
        ]);
    }

    /** GET /transfers/incoming — Shop-scoped incoming list (Salesperson/GM) */
    public function incoming(): void
    {
        $this->requireAuth();
        // Only Salesperson sees the incoming (shop receiving) queue.
        $this->requireRole(ROLE_SALESPERSON);

        $shopId = $this->resolveShopId();
        $db     = Database::getInstance();

        $filters = [
            'status'    => $this->query('status',    ''),
            'search'    => $this->query('search',    ''),
            'date_from' => $this->query('date_from', ''),
            'date_to'   => $this->query('date_to',   ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $trnModel = new TransferModel();
        $result   = $trnModel->paginateShop($shopId, $page, PAGINATION_DEFAULT, $filters);

        $shop = $shopId > 0
            ? $db->fetchOne("SELECT id, name FROM shops WHERE id = :id", ['id' => $shopId])
            : null;

        $this->render('transfer.incoming', [
            'pageTitle'  => 'Incoming Transfers',
            'shop'       => $shop,
            'shopId'     => $shopId,
            'transfers'  => $result['data'],
            'pagination' => $result,
            'filters'    => $filters,
            'userRole'   => $this->userRole(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // CREATE / STORE
    // ─────────────────────────────────────────────────────────────────────

    /** GET /transfers/create */
    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);

        $warehouseId = $this->resolveWarehouseId();
        $db          = Database::getInstance();

        $invModel  = new WarehouseInventoryModel();
        $products  = $invModel->getAvailableProducts($warehouseId);
        $shops     = $db->fetchAll("SELECT id, name, location FROM shops WHERE is_active = 1 ORDER BY name");
        $warehouse = $db->fetchOne("SELECT id, name FROM warehouses WHERE id = :id", ['id' => $warehouseId]);

        $this->render('transfer.create', [
            'pageTitle'   => 'Create Transfer',
            'warehouse'   => $warehouse,
            'warehouseId' => $warehouseId,
            'products'    => $products,
            'shops'       => $shops,
            'today'       => date('Y-m-d'),
            'userRole'    => $this->userRole(),
        ]);
    }

    /** POST /transfers */
    public function store(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);
        $this->verifyCsrf();

        $warehouseId = $this->resolveWarehouseId();
        $shopId      = (int)$this->input('shop_id', 0);
        $notes       = trim($this->input('notes', ''));

        if ($shopId <= 0) {
            $this->flash(FLASH_ERROR, 'Please select a destination shop.');
            $this->redirect('/transfers/create');
            return;
        }

        $productIds  = $this->input('product_ids',   []);
        $unitIds     = $this->input('unit_ids',      []);
        $quantities  = $this->input('quantities',    []);
        $batches     = $this->input('batch_numbers', []);
        $expiryDates = $this->input('expiry_dates',  []);
        $itemNotes   = $this->input('item_notes',    []);

        if (empty($productIds)) {
            $this->flash(FLASH_ERROR, 'Please add at least one product to the transfer.');
            $this->redirect('/transfers/create');
            return;
        }

        $items = [];
        foreach ($productIds as $i => $pid) {
            $pid = (int)$pid;
            $qty = (float)($quantities[$i] ?? 0);
            if ($pid <= 0 || $qty <= 0) continue;
            $items[] = [
                'product_id'         => $pid,
                'unit_id'            => !empty($unitIds[$i])  ? (int)$unitIds[$i]  : null,
                'quantity_requested' => $qty,
                'batch_number'       => trim($batches[$i]     ?? '') ?: null,
                'expiry_date'        => trim($expiryDates[$i] ?? '') ?: null,
                'notes'              => trim($itemNotes[$i]   ?? ''),
            ];
        }

        if (empty($items)) {
            $this->flash(FLASH_ERROR, 'No valid items. Please check quantities.');
            $this->redirect('/transfers/create');
            return;
        }

        try {
            $service    = new WarehouseService();
            $transferId = $service->createTransfer($warehouseId, $shopId, $items, $notes);
            $this->flash(FLASH_SUCCESS, 'Transfer order created successfully.');
            $this->redirect("/transfers/{$transferId}");
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
            $this->redirect('/transfers/create');
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // SHOW
    // ─────────────────────────────────────────────────────────────────────

    /** GET /transfers/{id} */
    public function show(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);

        $trnModel = new TransferModel();
        $transfer = $trnModel->getWithItems((int)$id);

        if (!$transfer) {
            $this->flash(FLASH_ERROR, 'Transfer not found.');
            $this->redirect('/transfers');
            return;
        }

        $db        = Database::getInstance();
        $warehouse = $db->fetchOne("SELECT id, name FROM warehouses WHERE id = :id", ['id' => $transfer['warehouse_id']]);

        $allowedActions = $this->resolveAllowedActions($transfer);

        // Load discrepancies if any exist (for the discrepancy_pending / gm_rejected / received_with_discrepancy view)
        $discrepancies = [];
        $workflowLogs  = [];
        $showDiscrepancyPanel = in_array($transfer['status'], [
            TransferModel::STATUS_DISCREPANCY_PENDING,
            TransferModel::STATUS_GM_APPROVED,
            TransferModel::STATUS_GM_REJECTED,
            TransferModel::STATUS_RECEIVED_WITH_DISCREPANCY,
            TransferModel::STATUS_DISCREPANCY,
            TransferModel::STATUS_FINAL_REJECTED,
        ], true);

        if ($showDiscrepancyPanel) {
            $disModel      = new DiscrepancyModel();
            $discrepancies = $disModel->getForTransfer((int)$id);
            foreach ($discrepancies as $dis) {
                $workflowLogs[$dis['id']] = $disModel->getWorkflowLogForTransfer((int)$dis['id']);
            }
        }

        $this->render('transfer.show', [
            'pageTitle'            => 'Transfer — ' . ($transfer['transfer_number'] ?? ''),
            'transfer'             => $transfer,
            'warehouse'            => $warehouse,
            'userRole'             => $this->userRole(),
            'allowedActions'       => $allowedActions,
            'discrepancies'        => $discrepancies,
            'workflowLogs'         => $workflowLogs,
            'showDiscrepancyPanel' => $showDiscrepancyPanel,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // APPROVE / REJECT  (General Manager only — transfer creation approval)
    // ─────────────────────────────────────────────────────────────────────

    /** POST /transfers/{id}/approve */
    public function approve(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $transferId = (int)$id;
        try {
            (new WarehouseService())->approveTransfer($transferId);
            $this->flash(FLASH_SUCCESS, 'Transfer approved. The Warehouse Manager can now dispatch it.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }
        $this->redirect("/transfers/{$transferId}");
    }

    /** POST /transfers/{id}/reject */
    public function reject(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $transferId = (int)$id;
        $reason     = trim($this->input('reason', ''));

        // Enforce reason at controller level (double barrier)
        if ($reason === '') {
            $this->flash(FLASH_ERROR, 'A rejection reason is required.');
            $this->redirect("/transfers/{$transferId}");
            return;
        }

        try {
            (new WarehouseService())->rejectTransfer($transferId, $reason);
            $this->flash(FLASH_SUCCESS,
                'Transfer rejected. Stock reservations have been released and ' .
                'the Warehouse Manager has been notified.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }
        $this->redirect("/transfers/{$transferId}");
    }

    // ─────────────────────────────────────────────────────────────────────
    // DISPATCH
    // ─────────────────────────────────────────────────────────────────────

    /** POST /transfers/{id}/dispatch */
    public function dispatch(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);
        $this->verifyCsrf();

        $transferId = (int)$id;
        try {
            (new WarehouseService())->dispatchTransfer($transferId);
            $this->flash(FLASH_SUCCESS, 'Transfer dispatched. Status is now In Transit. Warehouse stock deducted.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }
        $this->redirect("/transfers/{$transferId}");
    }

    // ─────────────────────────────────────────────────────────────────────
    // RECEIVE (Destination Shop — Salesperson)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /transfers/{id}/receive
     * Shows the receiving form.
     * Accessible when status = in_transit (fresh) or gm_rejected (resubmit).
     */
    public function receiveForm(string $id): void
    {
        $this->requireAuth();
        // Only SALESPERSON can physically receive goods at the shop.
        // General Manager reviews discrepancies via /gm/transfers/{id}/discrepancy-review.
        $this->requireRole(ROLE_SALESPERSON);

        $trnModel = new TransferModel();
        $transfer = $trnModel->getWithItems((int)$id);

        if (!$transfer) {
            $this->flash(FLASH_ERROR, 'Transfer not found.');
            $this->redirect('/transfers/incoming');
            return;
        }

        // Allowed statuses for receiving/resubmitting
        $receivableStatuses = [
            TransferModel::STATUS_IN_TRANSIT,
            TransferModel::STATUS_GM_REJECTED,
        ];

        // Specific guard for final_rejected — show a clear permanent closure message
        if ($transfer['status'] === TransferModel::STATUS_FINAL_REJECTED) {
            $this->flash(FLASH_ERROR,
                'This transfer has been permanently closed after '
                . TransferModel::MAX_DISCREPANCY_REJECTIONS . ' General Manager rejections. '
                . 'Resubmission is no longer allowed.');
            $this->redirect("/transfers/{$transfer['id']}");
            return;
        }

        if (!in_array($transfer['status'], $receivableStatuses, true)) {
            $this->flash(FLASH_WARNING,
                'This transfer cannot be received in its current state. Status: '
                . TransferModel::statusLabel($transfer['status']));
            $this->redirect("/transfers/{$transfer['id']}");
            return;
        }

        // Self-receive prevention
        if ($this->isInitiator($transfer)) {
            $this->flash(FLASH_ERROR, 'You cannot confirm receipt of a transfer you initiated. This is a security control.');
            $this->redirect("/transfers/{$transfer['id']}");
            return;
        }

        $db        = Database::getInstance();
        $warehouse = $db->fetchOne("SELECT id, name FROM warehouses WHERE id = :id", ['id' => $transfer['warehouse_id']]);

        // If resubmitting after GM rejection, load previous discrepancies for context
        $prevDiscrepancies = [];
        $isResubmit        = $transfer['status'] === TransferModel::STATUS_GM_REJECTED;
        if ($isResubmit) {
            $disModel          = new DiscrepancyModel();
            $prevDiscrepancies = $disModel->getForTransfer((int)$id);
        }

        $this->render('transfer.receive', [
            'pageTitle'         => ($isResubmit ? 'Re-submit Receiving' : 'Confirm Receipt')
                                   . ' — ' . ($transfer['transfer_number'] ?? ''),
            'transfer'          => $transfer,
            'warehouse'         => $warehouse,
            'today'             => date('Y-m-d'),
            'userRole'          => $this->userRole(),
            'isResubmit'        => $isResubmit,
            'prevDiscrepancies' => $prevDiscrepancies,
            'gmRejectionReason' => $transfer['discrepancy_gm_notes'] ?? '',
        ]);
    }

    /**
     * POST /transfers/{id}/receive
     * Process receipt confirmation.
     * Handles both fresh receiving and resubmission after GM rejection.
     */
    public function receive(string $id): void
    {
        $this->requireAuth();
        // Only SALESPERSON submits receiving. GM cannot submit on behalf of a shop.
        $this->requireRole(ROLE_SALESPERSON);
        $this->verifyCsrf();

        $transferId = (int)$id;
        $trnModel   = new TransferModel();
        $transfer   = $trnModel->find($transferId);

        if (!$transfer) {
            $this->flash(FLASH_ERROR, 'Transfer not found.');
            $this->redirect('/transfers/incoming');
            return;
        }

        // Backend status check — in_transit or gm_rejected (resubmit)
        $receivableStatuses = [
            TransferModel::STATUS_IN_TRANSIT,
            TransferModel::STATUS_GM_REJECTED,
        ];

        // Specific guard for final_rejected
        if ($transfer['status'] === TransferModel::STATUS_FINAL_REJECTED) {
            $this->flash(FLASH_ERROR,
                'This transfer has been permanently closed after '
                . TransferModel::MAX_DISCREPANCY_REJECTIONS . ' General Manager rejections. '
                . 'Resubmission is not allowed.');
            $this->redirect("/transfers/{$transferId}");
            return;
        }

        if (!in_array($transfer['status'], $receivableStatuses, true)) {
            $this->flash(FLASH_ERROR,
                'Transfer cannot be received in its current state: '
                . TransferModel::statusLabel($transfer['status']) . '.');
            $this->redirect("/transfers/{$transferId}");
            return;
        }

        // Backend self-receive prevention
        if ($this->isInitiator($transfer)) {
            $this->flash(FLASH_ERROR, 'Security violation: You cannot confirm receipt of a transfer you initiated.');
            $this->redirect("/transfers/{$transferId}");
            return;
        }

        $notes       = trim($this->input('notes', ''));
        $itemIds     = $this->input('item_ids',            []);
        $qtyReceived = $this->input('quantity_received',   []);
        $disReasons  = $this->input('discrepancy_reasons', []);

        if (empty($itemIds)) {
            $this->flash(FLASH_ERROR, 'No items submitted.');
            $this->redirect("/transfers/{$transferId}/receive");
            return;
        }

        $itemsReceived = [];
        foreach ($itemIds as $i => $itemId) {
            $itemsReceived[] = [
                'item_id'            => (int)$itemId,
                'quantity_received'  => max(0, (float)($qtyReceived[$i] ?? 0)),
                'discrepancy_reason' => trim($disReasons[$i] ?? ''),
            ];
        }

        try {
            $service = new WarehouseService();
            $result  = $service->confirmTransferReceived($transferId, $itemsReceived, $notes);

            if ($result['has_discrepancy']) {
                $this->flash(FLASH_WARNING,
                    'Receiving discrepancy submitted. The General Manager has been notified and must '
                    . 'approve before shop inventory is updated. '
                    . 'Status: <strong>Pending GM Approval</strong>.');
            } else {
                $this->flash(FLASH_SUCCESS, 'Transfer fully received. Shop inventory has been updated.');
            }
            $this->redirect("/transfers/{$transferId}");
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
            $this->redirect("/transfers/{$transferId}/receive");
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CANCEL
    // ─────────────────────────────────────────────────────────────────────

    /** POST /transfers/{id}/cancel */
    public function cancel(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);
        $this->verifyCsrf();

        $transferId = (int)$id;
        try {
            (new WarehouseService())->cancelTransfer($transferId);
            $this->flash(FLASH_SUCCESS, 'Transfer cancelled. Stock reservations released.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }
        $this->redirect("/transfers/{$transferId}");
    }

    // ─────────────────────────────────────────────────────────────────────
    // LEGACY ALIAS (backwards-compat — old /confirm routes)
    // ─────────────────────────────────────────────────────────────────────

    /** GET /transfers/{id}/confirm → redirect to /receive */
    public function confirmForm(string $id): void
    {
        $this->requireAuth();
        $this->redirect("/transfers/{$id}/receive");
    }

    /** POST /transfers/{id}/confirm → error */
    public function confirm(string $id): void
    {
        $this->requireAuth();
        $this->flash(FLASH_ERROR, 'Please use the /receive endpoint to confirm transfer receipt.');
        $this->redirect("/transfers/{$id}/receive");
    }
}
