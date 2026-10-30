<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) â€” Phase 10
 * =========================================================
 * File    : modules/warehouse/controllers/ReceivingController.php
 * Purpose : Handles warehouse receiving workflow.
 *
 * Routes  :
 *   GET  /warehouse/receiving               â†’ queue()
 *   GET  /warehouse/receiving/{id}/confirm  â†’ confirmForm()
 *   POST /warehouse/receiving/{id}/confirm  â†’ confirm()
 *   GET  /warehouse/receiving/{id}          â†’ show()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Warehouse\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Warehouse\Models\ReceivingModel;
use AAM\Warehouse\Services\WarehouseService;

class ReceivingController extends BaseController
{
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
        if ($row) return (int)$row['location_id'];
        return 1;
    }

    // =========================================================
    // GET /warehouse/receiving  â€” Receiving Queue
    // =========================================================
    public function queue(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);

        $warehouseId = $this->resolveWarehouseId();
        $db          = Database::getInstance();

        $tab     = $this->query('tab', 'pending');
        $filters = [
            'status'    => $this->query('status',    ''),
            'search'    => $this->query('search',    ''),
            'date_from' => $this->query('date_from', ''),
            'date_to'   => $this->query('date_to',   ''),
            'farm_id'   => $this->query('farm_id',   ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $rcvModel          = new ReceivingModel();
        $pendingDispatches = $rcvModel->getPendingDispatches($warehouseId);

        // Discrepancy tab: load all without limit; other tabs use filtered pagination
        if ($tab === 'discrepancy') {
            $result = $rcvModel->paginateWarehouse($warehouseId, 1, 200, []);
        } else {
            $result = $rcvModel->paginateWarehouse($warehouseId, $page, PAGINATION_DEFAULT, $filters);
        }

        $warehouse = $db->fetchOne("SELECT id, name FROM warehouses WHERE id = :id", ['id' => $warehouseId]);
        $farms     = $db->fetchAll("SELECT id, name FROM farms WHERE is_active = 1 ORDER BY name");

        $this->render('warehouse.receiving.queue', [
            'pageTitle'         => 'Receiving',
            'warehouse'         => $warehouse,
            'warehouseId'       => $warehouseId,
            'pendingDispatches' => $pendingDispatches,
            'receivings'        => $result['data'],
            'pagination'        => $result,
            'filters'           => $filters,
            'farms'             => $farms,
            'tab'               => $tab,
            'userRole'          => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /warehouse/receiving/{id}/confirm  â€” Receiving Form
    // =========================================================
    public function confirmForm(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);

        $dispatchId  = (int)$id;
        $warehouseId = $this->resolveWarehouseId();

        $rcvModel = new ReceivingModel();
        $dispatch = $rcvModel->getDispatchForReceiving($dispatchId);

        if (!$dispatch) {
            $this->flash(FLASH_ERROR, 'Dispatch not found.');
            $this->redirect('/warehouse/receiving');
            return;
        }

        // Check dispatch belongs to this warehouse
        if ((int)$dispatch['warehouse_id'] !== $warehouseId &&
            !in_array($this->userRole(), [ROLE_GENERAL_MANAGER], true)) {
            $this->flash(FLASH_ERROR, 'Access denied: this dispatch does not belong to your warehouse.');
            $this->redirect('/warehouse/receiving');
            return;
        }

        // Already received?
        if ($rcvModel->existsForDispatch($dispatchId)) {
            $this->flash(FLASH_WARNING, 'This dispatch has already been processed.');
            $this->redirect('/warehouse/receiving');
            return;
        }

        $db        = Database::getInstance();
        $warehouse = $db->fetchOne("SELECT id, name FROM warehouses WHERE id = :id", ['id' => $warehouseId]);

        $this->render('warehouse.receiving.confirm', [
            'pageTitle'   => 'Confirm Receiving â€” ' . ($dispatch['dispatch_number'] ?? ''),
            'dispatch'    => $dispatch,
            'warehouse'   => $warehouse,
            'warehouseId' => $warehouseId,
            'today'       => date('Y-m-d'),
            'userRole'    => $this->userRole(),
        ]);
    }

    // =========================================================
    // POST /warehouse/receiving/{id}/confirm  â€” Process Receiving
    // =========================================================
    public function confirm(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $dispatchId  = (int)$id;
        $warehouseId = $this->resolveWarehouseId();
        $notes       = trim($this->input('notes', ''));

        // Build items array from POST
        $itemIds         = $this->input('item_ids',             []);
        $dispatchItemIds = $this->input('dispatch_item_ids',    []);
        $productIds      = $this->input('product_ids',          []);
        $unitIds         = $this->input('unit_ids',             []);
        $qtyExpected     = $this->input('quantity_expected',    []);
        $qtyReceived     = $this->input('quantity_received',    []);
        $batchNumbers    = $this->input('batch_numbers',        []);
        $productionDates = $this->input('production_dates',     []);
        $expiryDates     = $this->input('expiry_dates',         []);
        $disReasons      = $this->input('discrepancy_reasons',  []);
        $itemNotes       = $this->input('item_notes',           []);

        if (empty($productIds)) {
            $this->flash(FLASH_ERROR, 'No items submitted.');
            $this->redirect("/warehouse/receiving/{$dispatchId}/confirm");
            return;
        }

        $items = [];
        foreach ($productIds as $i => $productId) {
            $items[] = [
                'dispatch_item_id'  => (int)($dispatchItemIds[$i] ?? 0),
                'product_id'        => (int)$productId,
                'unit_id'           => !empty($unitIds[$i])         ? (int)$unitIds[$i]         : null,
                'quantity_expected' => (float)($qtyExpected[$i]     ?? 0),
                'quantity_received' => max(0, (float)($qtyReceived[$i] ?? 0)),
                'batch_number'      => trim($batchNumbers[$i]       ?? ''),
                'production_date'   => trim($productionDates[$i]    ?? '') ?: null,
                'expiry_date'       => trim($expiryDates[$i]        ?? '') ?: null,
                'discrepancy_reason'=> trim($disReasons[$i]         ?? ''),
                'notes'             => trim($itemNotes[$i]          ?? ''),
            ];
        }

        try {
            $service = new WarehouseService();
            $result  = $service->confirmReceiving($dispatchId, $warehouseId, $items, $notes);

            if (!empty($result['discrepancy_pending'])) {
                // Discrepancy workflow: inventory NOT yet updated, GM must review first
                $this->flash(FLASH_WARNING,
                    'Discrepancy submitted for General Manager review. '
                    . 'Warehouse inventory will NOT be updated until the GM approves. '
                    . 'You will be notified when the GM has reviewed the discrepancy.');
                $this->redirect("/warehouse/receiving/{$result['receiving_id']}");
            } else {
                $this->flash(FLASH_SUCCESS, 'Receiving confirmed. Warehouse inventory updated.');
                $this->redirect('/warehouse/receiving');
            }

        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
            $this->redirect("/warehouse/receiving/{$dispatchId}/confirm");
        }
    }

    // =========================================================
    // GET /warehouse/receiving/{id}  â€” Show receiving record
    // =========================================================
    public function show(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);

        $rcvModel  = new ReceivingModel();
        $receiving = $rcvModel->getWithItems((int)$id);

        if (!$receiving) {
            $this->flash(FLASH_ERROR, 'Receiving record not found.');
            $this->redirect('/warehouse/receiving');
            return;
        }

        $db        = Database::getInstance();
        $warehouse = $db->fetchOne("SELECT id, name FROM warehouses WHERE id = :id", ['id' => $receiving['warehouse_id']]);

        // Load discrepancies + workflow log when status involves discrepancy
        $discrepancies = [];
        $workflowLogs  = [];
        if (in_array($receiving['status'], [
            'discrepancy_pending', 'gm_approved', 'gm_rejected',
            'final_rejected', 'discrepancy', 'received',
        ], true)) {
            $disModel      = new \AAM\Warehouse\Models\DiscrepancyModel();
            $discrepancies = $disModel->getForReceiving((int)$id);
            foreach ($discrepancies as &$dis) {
                $workflowLogs[$dis['id']] = $disModel->getWorkflowLog((int)$dis['id']);
            }
            unset($dis);
        }

        $this->render('warehouse.receiving.show', [
            'pageTitle'     => 'Receiving — ' . ($receiving['receiving_number'] ?? ''),
            'receiving'     => $receiving,
            'warehouse'     => $warehouse,
            'userRole'      => $this->userRole(),
            'discrepancies' => $discrepancies,
            'workflowLogs'  => $workflowLogs,
        ]);
    }

    // =========================================================
    // POST /warehouse/receiving/{id}/complete
    //   WM completes receiving after GM approval
    // =========================================================
    public function complete(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $receivingId = (int)$id;
        try {
            $service = new WarehouseService();
            $service->completeReceivingAfterGMApproval($receivingId);
            $this->flash(FLASH_SUCCESS,
                'Receiving completed. Warehouse inventory updated with the approved actual quantity. '
                . 'The Farm Manager has been notified.');
            $this->redirect("/warehouse/receiving/{$receivingId}");
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
            $this->redirect("/warehouse/receiving/{$receivingId}");
        }
    }

    // =========================================================
    // GET /warehouse/receiving/{id}/resubmit
    //   Show the resubmit form after GM rejects a discrepancy
    // =========================================================
    public function resubmitForm(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);

        $receivingId = (int)$id;
        $rcvModel    = new ReceivingModel();
        $receiving   = $rcvModel->getWithItems($receivingId);

        if (!$receiving) {
            $this->flash(FLASH_ERROR, 'Receiving record not found.');
            $this->redirect('/warehouse/receiving');
            return;
        }

        if ((int)($receiving['final_rejected'] ?? 0) === 1
            || $receiving['status'] === 'final_rejected') {
            $this->flash(FLASH_ERROR,
                'This receiving has been permanently closed after '
                . \AAM\Warehouse\Models\ReceivingModel::MAX_REJECTIONS
                . ' General Manager rejections. Resubmission is not allowed.');
            $this->redirect("/warehouse/receiving/{$receivingId}");
            return;
        }

        if ($receiving['status'] !== 'gm_rejected') {
            $this->flash(FLASH_WARNING,
                'This receiving cannot be resubmitted. '
                . 'Status: ' . ReceivingModel::statusLabel($receiving['status']) . '.');
            $this->redirect("/warehouse/receiving/{$receivingId}");
            return;
        }

        $db        = Database::getInstance();
        $warehouse = $db->fetchOne("SELECT id, name FROM warehouses WHERE id = :id",
            ['id' => $receiving['warehouse_id']]);

        // Load the GM rejection reason
        $disModel      = new \AAM\Warehouse\Models\DiscrepancyModel();
        $discrepancies = $disModel->getForReceiving($receivingId);

        $this->render('warehouse.receiving.resubmit', [
            'pageTitle'     => 'Resubmit Discrepancy — ' . ($receiving['receiving_number'] ?? ''),
            'receiving'     => $receiving,
            'warehouse'     => $warehouse,
            'discrepancies' => $discrepancies,
            'userRole'      => $this->userRole(),
        ]);
    }

    // =========================================================
    // POST /warehouse/receiving/{id}/resubmit
    //   WM resubmits corrected quantities after GM rejection
    // =========================================================
    public function resubmit(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $receivingId    = (int)$id;
        $rcvItemIds     = $this->input('receiving_item_ids',   []);
        $qtyReceived    = $this->input('quantity_received',    []);
        $disReasons     = $this->input('discrepancy_reasons',  []);

        if (empty($rcvItemIds)) {
            $this->flash(FLASH_ERROR, 'No items submitted.');
            $this->redirect("/warehouse/receiving/{$receivingId}/resubmit");
            return;
        }

        $items = [];
        foreach ($rcvItemIds as $i => $rcvItemId) {
            $items[] = [
                'receiving_item_id'  => (int)$rcvItemId,
                'quantity_received'  => max(0.0, (float)($qtyReceived[$i] ?? 0)),
                'discrepancy_reason' => trim($disReasons[$i] ?? ''),
            ];
        }

        try {
            (new WarehouseService())->resubmitReceivingDiscrepancy($receivingId, $items);
            $this->flash(FLASH_SUCCESS,
                'Discrepancy resubmitted for General Manager review. '
                . 'Warehouse inventory will be updated after GM approval.');
            $this->redirect("/warehouse/receiving/{$receivingId}");
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
            $this->redirect("/warehouse/receiving/{$receivingId}/resubmit");
        }
    }
}