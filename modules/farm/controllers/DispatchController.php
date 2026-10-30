<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/farm/controllers/DispatchController.php
 * Purpose : Farm dispatch management.
 *
 * WORKFLOW (with GM approval gate):
 *   Farm Manager creates dispatch  → status: pending_approval
 *   GM approves                    → status: approved
 *   GM rejects                     → status: rejected  (dispatch blocked)
 *   Farm Manager marks dispatched  → status: in_transit
 *     (harvest stock deducted HERE — not at creation)
 *   Warehouse receives             → status: received / partially_received
 *
 * Routes  :
 *   GET  /farms/{id}/dispatches          → index()
 *   GET  /farms/{id}/dispatches/create   → create()
 *   POST /farms/{id}/dispatches          → store()
 *   GET  /dispatches/{did}               → view()
 *   POST /dispatches/{did}/dispatch      → markDispatched()  [FM only, requires approved]
 *   POST /dispatches/{did}/approve       → approveDispatch() [GM only]
 *   POST /dispatches/{did}/reject        → rejectDispatch()  [GM only]
 *   POST /dispatches/{did}/cancel        → cancel()
 *
 * Security Rules:
 *   - Farm Manager CANNOT dispatch without GM approval.
 *   - GM CANNOT approve their own dispatch (Farm Manager created it).
 *   - Warehouse Manager and Salesperson have no access here.
 *   - Harvest stock is only deducted when dispatch is physically sent (markDispatched).
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Farm\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Farm\Models\FarmModel;
use AAM\Farm\Models\FarmDispatchModel;
use AAM\Farm\Models\HarvestBatchModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Notifications\NotificationService;

class DispatchController extends BaseController
{
    private FarmDispatchModel  $dispatchModel;
    private FarmModel          $farmModel;
    private HarvestBatchModel  $harvestModel;
    private ActivityLogModel   $auditLog;
    private NotificationService $notifService;

    public function __construct()
    {
        parent::__construct();
        $this->dispatchModel  = new FarmDispatchModel();
        $this->farmModel      = new FarmModel();
        $this->harvestModel   = new HarvestBatchModel();
        $this->auditLog       = new ActivityLogModel();
        $this->notifService   = new NotificationService();
    }

    // ──────────────────────────────────────────────────────
    // GET /farms/{id}/dispatches  — Dispatch list
    // ──────────────────────────────────────────────────────

    public function index(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        $farmId = (int)$id;
        $role   = $this->userRole();
        $userId = (int)$this->userId();

        $farm = $this->requireFarmAccess($farmId, $userId, $role);

        $filters = [
            'status'    => $this->query('status', ''),
            'date_from' => $this->query('date_from', ''),
            'date_to'   => $this->query('date_to', ''),
            'search'    => $this->query('search', ''),
        ];

        $page   = max(1, (int)$this->query('page', 1));
        $result = $this->dispatchModel->paginateFarm($farmId, $page, PAGINATION_DEFAULT, $filters);

        $this->render('farm.dispatches.index', [
            'pageTitle'   => 'Dispatches — ' . $farm['name'],
            'farm'        => $farm,
            'dispatches'  => $result['data'],
            'pagination'  => $result,
            'filters'     => $filters,
            'userRole'    => $role,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // GET /farms/{id}/dispatches/create  — Create dispatch form
    // ──────────────────────────────────────────────────────

    public function create(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        $farmId = (int)$id;
        $role   = $this->userRole();
        $userId = (int)$this->userId();

        $farm = $this->requireFarmAccess($farmId, $userId, $role);

        // Dispatchable harvest batches (have remaining qty, not quarantine/grade-C)
        $dispatchable = $this->harvestModel->getDispatchable($farmId);

        if (empty($dispatchable)) {
            $this->flash(FLASH_WARNING, 'No eligible harvest batches available for dispatch.');
            $this->redirect("/farms/{$farmId}/dispatches");
            return;
        }

        $db         = Database::getInstance();
        $warehouses = $db->fetchAll(
            "SELECT id, name, location FROM warehouses WHERE is_active = 1 ORDER BY name ASC"
        ) ?: [];

        $nextDispatch = $this->dispatchModel->generateDispatchNumber();

        $this->render('farm.dispatches.create', [
            'pageTitle'    => 'Create Dispatch Request — ' . $farm['name'],
            'farm'         => $farm,
            'dispatchable' => $dispatchable,
            'warehouses'   => $warehouses,
            'nextDispatch' => $nextDispatch,
            'today'        => date('Y-m-d'),
            'userRole'     => $role,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // POST /farms/{id}/dispatches  — Store dispatch request
    // Creates the dispatch with status=pending_approval.
    // Does NOT deduct harvest stock. Does NOT go in_transit.
    // ──────────────────────────────────────────────────────

    public function store(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $farmId = (int)$id;
        $role   = $this->userRole();
        $userId = (int)$this->userId();

        $farm = $this->requireFarmAccess($farmId, $userId, $role);

        $data = $this->inputAll();

        $errors = $this->validate($data, [
            'warehouse_id'  => 'required|integer',
            'dispatch_date' => 'required|date',
        ], true);

        if (is_array($errors)) {
            $this->flash(FLASH_ERROR, 'Please correct the errors below.');
            SessionManager::set('validation_errors', $errors);
            SessionManager::set('old_input', $data);
            $this->redirect("/farms/{$farmId}/dispatches/create");
            return;
        }

        $batchIds   = $data['batch_ids']  ?? [];
        $quantities = $data['quantities'] ?? [];

        if (empty($batchIds) || !is_array($batchIds)) {
            $this->flash(FLASH_ERROR, 'Please select at least one harvest batch to dispatch.');
            $this->redirect("/farms/{$farmId}/dispatches/create");
            return;
        }

        $items = [];

        foreach ($batchIds as $i => $batchId) {
            $batchId = (int)$batchId;
            $qty     = (float)($quantities[$i] ?? 0);

            if ($qty <= 0) {
                $this->flash(FLASH_ERROR, 'All dispatch quantities must be greater than zero.');
                $this->redirect("/farms/{$farmId}/dispatches/create");
                return;
            }

            $batch = $this->harvestModel->find($batchId);
            if (!$batch || (int)$batch['farm_id'] !== $farmId) {
                $this->flash(FLASH_ERROR, 'Invalid harvest batch selected.');
                $this->redirect("/farms/{$farmId}/dispatches/create");
                return;
            }

            $available = (float)$batch['remaining_quantity'];
            if ($qty > $available + 0.0001) {
                $this->flash(FLASH_ERROR,
                    "Dispatch quantity ({$qty}) exceeds available harvest quantity ({$available})."
                );
                $this->redirect("/farms/{$farmId}/dispatches/create");
                return;
            }

            $items[] = [
                'harvest_batch_id'    => $batchId,
                'product_id'          => (int)$batch['product_id'],
                'quantity_dispatched' => $qty,
                'unit_id'             => (int)$batch['unit_id'],
            ];
        }

        $dispatchNumber = trim($data['dispatch_number'] ?? '');
        if ($dispatchNumber === '') {
            $dispatchNumber = $this->dispatchModel->generateDispatchNumber();
        }

        $header = [
            'farm_id'               => $farmId,
            'warehouse_id'          => (int)$data['warehouse_id'],
            'dispatch_number'       => $dispatchNumber,
            'dispatch_date'         => $data['dispatch_date'],
            'expected_arrival_date' => !empty($data['expected_arrival_date'])
                                           ? $data['expected_arrival_date'] : null,
            'notes'                 => trim($data['notes'] ?? ''),
            'initiated_by'          => $userId,
        ];

        try {
            $dispatchId = $this->dispatchModel->createWithItems($header, $items);

            $totalQty = array_sum(array_column($items, 'quantity_dispatched'));

            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_DISPATCH_CREATED,
                'module'       => 'farm',
                'entity_type'  => 'dispatch',
                'entity_label' => $dispatchNumber,
                'record_id'    => $dispatchId,
                'description'  => "Dispatch request {$dispatchNumber} submitted from {$farm['name']} — "
                                  . "{$totalQty} total qty, " . count($items) . " batch(es). Awaiting GM approval.",
                'farm_id'      => $farmId,
                'new_values'   => $header,
            ]);

            // ── Notify all General Managers ─────────────────────────────
            try {
                $totalQtyFmt = number_format($totalQty, 3);
                $productRows = $db->fetchAll(
                    "SELECT DISTINCT p.name
                     FROM farm_dispatch_items fdi
                     JOIN products p ON p.id = fdi.product_id
                     WHERE fdi.dispatch_id = :did",
                    ['did' => $dispatchId]
                );
                $productList = implode(', ', array_column($productRows ?: [], 'name'));
                if ($productList === '') {
                    $productList = count($items) . ' product(s)';
                }

                $this->notifService->sendToRole(
                    ROLE_GENERAL_MANAGER,
                    NOTIF_DISPATCH_PENDING,
                    "Dispatch Approval Required — {$dispatchNumber}",
                    "Farm Manager submitted dispatch {$dispatchNumber} from {$farm['name']}.\n"
                    . "Products: {$productList}\n"
                    . "Total Quantity: {$totalQtyFmt}\n"
                    . "Please review and approve or reject this dispatch request.",
                    'high',
                    $userId,
                    'farm_dispatches',
                    $dispatchId,
                    url("dispatches/{$dispatchId}")
                );
            } catch (\Throwable $notifEx) {
                // Notification failure must never block the dispatch creation
            }
            // ────────────────────────────────────────────────────────────

            $this->flash(
                FLASH_SUCCESS,
                "Dispatch request {$dispatchNumber} submitted successfully. "
                . "Awaiting General Manager approval before it can be dispatched."
            );
            $this->redirect("/dispatches/{$dispatchId}");

        } catch (\RuntimeException $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
            $this->redirect("/farms/{$farmId}/dispatches/create");
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, 'Failed to create dispatch request. Please try again.');
            $this->redirect("/farms/{$farmId}/dispatches/create");
        }
    }

    // ──────────────────────────────────────────────────────
    // GET /dispatches/{did}  — View dispatch detail
    // ──────────────────────────────────────────────────────

    public function view(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        $dispatchId = (int)$id;
        $dispatch   = $this->dispatchModel->getWithItems($dispatchId);

        if (!$dispatch) {
            $this->flash(FLASH_ERROR, 'Dispatch record not found.');
            $this->redirect('/farms');
            return;
        }

        $this->checkFarmPermission((int)$dispatch['farm_id']);

        $this->render('farm.dispatches.view', [
            'pageTitle' => 'Dispatch — ' . $dispatch['dispatch_number'],
            'dispatch'  => $dispatch,
            'userRole'  => $this->userRole(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // POST /dispatches/{did}/dispatch  — Mark as in_transit
    // SECURITY: Only allowed when status = 'approved' by GM.
    // This is where harvest stock is physically deducted.
    // ──────────────────────────────────────────────────────

    public function markDispatched(string $id): void
    {
        $this->requireAuth();
        // Only Farm Manager can physically dispatch — GM does not dispatch.
        $this->requireRole(ROLE_FARM_MANAGER);
        $this->verifyCsrf();

        $dispatchId = (int)$id;
        $dispatch   = $this->dispatchModel->find($dispatchId);

        if (!$dispatch) {
            $this->flash(FLASH_ERROR, 'Dispatch record not found.');
            $this->redirect('/farms');
            return;
        }

        $this->checkFarmPermission((int)$dispatch['farm_id']);

        // Hard security check: block if not approved
        if (!FarmDispatchModel::isDispatchable($dispatch['status'])) {
            if ($dispatch['status'] === FarmDispatchModel::STATUS_PENDING_APPROVAL) {
                $this->flash(
                    FLASH_WARNING,
                    'This dispatch is awaiting General Manager approval. '
                    . 'You cannot dispatch until it has been approved.'
                );
            } elseif ($dispatch['status'] === FarmDispatchModel::STATUS_REJECTED) {
                $this->flash(
                    FLASH_ERROR,
                    'This dispatch was rejected by the General Manager and cannot be dispatched.'
                );
            } else {
                $this->flash(FLASH_ERROR, 'This dispatch cannot be sent in its current state.');
            }
            $this->redirect("/dispatches/{$dispatchId}");
            return;
        }

        try {
            // markDispatched() deducts harvest quantities inside a transaction
            $this->dispatchModel->markDispatched($dispatchId);

            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_DISPATCH_UPDATED,
                'module'       => 'farm',
                'entity_type'  => 'dispatch',
                'entity_label' => $dispatch['dispatch_number'],
                'record_id'    => $dispatchId,
                'description'  => "Dispatch {$dispatch['dispatch_number']} marked as In Transit. "
                                  . "Harvest stock deducted.",
                'farm_id'      => (int)$dispatch['farm_id'],
                'old_values'   => ['status' => $dispatch['status']],
                'new_values'   => ['status' => 'in_transit'],
            ]);

            $this->flash(FLASH_SUCCESS, 'Dispatch sent. Status is now In Transit and harvest stock has been deducted.');
        } catch (\RuntimeException $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }

        $this->redirect("/dispatches/{$dispatchId}");
    }

    // ──────────────────────────────────────────────────────
    // POST /dispatches/{did}/approve  — GM approves dispatch
    // SECURITY: General Manager only. Not Farm Manager.
    // ──────────────────────────────────────────────────────

    public function approveDispatch(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $dispatchId = (int)$id;
        $dispatch   = $this->dispatchModel->find($dispatchId);

        if (!$dispatch) {
            $this->flash(FLASH_ERROR, 'Dispatch record not found.');
            $this->redirect('/gm/farms/dispatches/pending');
            return;
        }

        $gmNotes = trim($this->input('gm_notes', ''));

        try {
            $this->dispatchModel->approve($dispatchId, (int)$this->userId(), $gmNotes);

            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_DISPATCH_UPDATED,
                'module'       => 'farm',
                'entity_type'  => 'dispatch',
                'entity_label' => $dispatch['dispatch_number'],
                'record_id'    => $dispatchId,
                'description'  => "Dispatch {$dispatch['dispatch_number']} approved by GM. "
                                  . "Farm Manager can now physically dispatch.",
                'farm_id'      => (int)$dispatch['farm_id'],
                'old_values'   => ['status' => 'pending_approval'],
                'new_values'   => ['status' => 'approved', 'gm_notes' => $gmNotes],
            ]);

            // ── Notify the Farm Manager who created this dispatch ────────
            try {
                $initiatorId = (int)($dispatch['initiated_by'] ?? 0);
                if ($initiatorId) {
                    $dispatchFull  = $this->dispatchModel->getWithItems($dispatchId);
                    $farmName      = $dispatchFull['farm_name']      ?? "Farm #{$dispatch['farm_id']}";
                    $warehouseName = $dispatchFull['warehouse_name'] ?? "Warehouse #{$dispatch['warehouse_id']}";
                    $totalQty      = array_sum(array_column($dispatchFull['items'] ?? [], 'quantity_dispatched'));
                    $products      = array_unique(array_column($dispatchFull['items'] ?? [], 'product_name'));
                    $productList   = implode(', ', array_filter($products)) ?: 'N/A';

                    $notesPart = $gmNotes !== ''
                        ? "\nGM Note: {$gmNotes}"
                        : '';

                    $this->notifService->sendToUser(
                        $initiatorId,
                        NOTIF_DISPATCH_APPROVED,
                        "Dispatch {$dispatch['dispatch_number']} Approved ✓",
                        "Your dispatch {$dispatch['dispatch_number']} from {$farmName} to {$warehouseName} "
                        . "has been approved by the General Manager.\n"
                        . "Products: {$productList}\n"
                        . "Total Quantity: " . number_format($totalQty, 3)
                        . $notesPart . "\n\n"
                        . "You can now physically dispatch the goods.",
                        'high',
                        (int)$this->userId(),
                        'farm_dispatches',
                        $dispatchId,
                        url("dispatches/{$dispatchId}")
                    );
                }
            } catch (\Throwable $notifEx) {
                // Notification failure must not block the approval
            }
            // ────────────────────────────────────────────────────────────

            $this->flash(
                FLASH_SUCCESS,
                "Dispatch {$dispatch['dispatch_number']} approved. "
                . "The Farm Manager can now physically dispatch the goods."
            );
        } catch (\RuntimeException $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }

        $this->redirect('/gm/farms/dispatches/pending');
    }

    // ──────────────────────────────────────────────────────
    // POST /dispatches/{did}/reject  — GM rejects dispatch
    // SECURITY: General Manager only. Reason is mandatory.
    // ──────────────────────────────────────────────────────

    public function rejectDispatch(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $dispatchId = (int)$id;
        $dispatch   = $this->dispatchModel->find($dispatchId);

        if (!$dispatch) {
            $this->flash(FLASH_ERROR, 'Dispatch record not found.');
            $this->redirect('/gm/farms/dispatches/pending');
            return;
        }

        $reason = trim($this->input('rejection_reason', ''));

        if ($reason === '') {
            $this->flash(FLASH_ERROR, 'A rejection reason is required.');
            $this->redirect("/dispatches/{$dispatchId}");
            return;
        }

        try {
            $this->dispatchModel->reject($dispatchId, (int)$this->userId(), $reason);

            // Notify the Farm Manager who created the dispatch
            $initiatorUserId = (int)($dispatch['initiated_by'] ?? 0);
            if ($initiatorUserId) {
                $dispatchFull  = $this->dispatchModel->getWithItems($dispatchId);
                $farmName      = $dispatchFull['farm_name']      ?? "Farm #{$dispatch['farm_id']}";
                $warehouseName = $dispatchFull['warehouse_name'] ?? "Warehouse #{$dispatch['warehouse_id']}";
                $totalQty      = array_sum(array_column($dispatchFull['items'] ?? [], 'quantity_dispatched'));
                $products      = array_unique(array_column($dispatchFull['items'] ?? [], 'product_name'));
                $productList   = implode(', ', array_filter($products)) ?: 'N/A';

                try {
                    $this->notifService->sendToUser(
                        $initiatorUserId,
                        NOTIF_DISPATCH_REJECTED,
                        "Dispatch {$dispatch['dispatch_number']} Rejected",
                        "Dispatch {$dispatch['dispatch_number']} from {$farmName} to "
                        . "{$warehouseName} has been rejected by the General Manager.\n"
                        . "Products: {$productList}\n"
                        . "Total Quantity: " . number_format($totalQty, 3) . "\n"
                        . "Rejection Reason: {$reason}\n\n"
                        . "No harvest stock has been deducted. "
                        . "You may create a new dispatch request after addressing the issue.",
                        'high',
                        (int)$this->userId(),
                        'farm_dispatches',
                        $dispatchId,
                        url("dispatches/{$dispatchId}")
                    );
                } catch (\Throwable $notifEx) {
                    // Notification failure must not block the rejection
                }
            }

            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_DISPATCH_UPDATED,
                'module'       => 'farm',
                'entity_type'  => 'dispatch',
                'entity_label' => $dispatch['dispatch_number'],
                'record_id'    => $dispatchId,
                'description'  => "Dispatch {$dispatch['dispatch_number']} rejected by GM. " .
                                  "Reason: {$reason}. No stock was deducted.",
                'farm_id'      => (int)$dispatch['farm_id'],
                'old_values'   => ['status' => 'pending_approval'],
                'new_values'   => ['status' => 'rejected', 'rejection_reason' => $reason],
            ]);

            $this->flash(
                FLASH_WARNING,
                "Dispatch {$dispatch['dispatch_number']} rejected. " .
                "The Farm Manager has been notified. No stock was deducted."
            );
        } catch (\RuntimeException $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }

        $this->redirect('/gm/farms/dispatches/pending');
    }

    // ──────────────────────────────────────────────────────
    // POST /dispatches/{did}/cancel  — Cancel dispatch
    // ──────────────────────────────────────────────────────

    public function cancel(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $dispatchId = (int)$id;
        $dispatch   = $this->dispatchModel->find($dispatchId);

        if (!$dispatch) {
            $this->flash(FLASH_ERROR, 'Dispatch record not found.');
            $this->redirect('/farms');
            return;
        }

        $this->checkFarmPermission((int)$dispatch['farm_id']);

        $oldStatus = $dispatch['status'];

        try {
            $this->dispatchModel->cancel($dispatchId);

            // Quantities are only restored if the dispatch was already in_transit
            // (deduction only happened at markDispatched time)
            $qtyNote = ($oldStatus === FarmDispatchModel::STATUS_IN_TRANSIT)
                ? ' Harvest quantities have been restored.'
                : ' No stock was deducted (dispatch was not yet sent).';

            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_DISPATCH_CANCELLED,
                'module'       => 'farm',
                'entity_type'  => 'dispatch',
                'entity_label' => $dispatch['dispatch_number'],
                'record_id'    => $dispatchId,
                'description'  => "Dispatch {$dispatch['dispatch_number']} cancelled.{$qtyNote}",
                'farm_id'      => (int)$dispatch['farm_id'],
                'old_values'   => ['status' => $oldStatus],
                'new_values'   => ['status' => 'cancelled'],
            ]);

            $this->flash(FLASH_SUCCESS, "Dispatch cancelled.{$qtyNote}");
        } catch (\RuntimeException $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }

        $this->redirect("/farms/{$dispatch['farm_id']}/dispatches");
    }

    // ──────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────

    private function requireFarmAccess(int $farmId, int $userId, string $role): array
    {
        $farm = $this->farmModel->findAssigned($farmId, $userId, $role);

        if (!$farm) {
            $this->flash(FLASH_ERROR, 'Farm not found or you do not have permission.');
            $this->redirect('/farms');
            exit;
        }

        return $farm;
    }

    private function checkFarmPermission(int $farmId): void
    {
        $role   = $this->userRole();
        $userId = (int)$this->userId();

        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            return;
        }

        $db  = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT 1 AS found FROM user_location_assignments
             WHERE user_id = :uid1 AND location_type = 'farm' AND location_id = :fid1
             UNION
             SELECT 1 FROM organization_assignments
             WHERE user_id = :uid2 AND location_type = 'farm'
               AND location_id = :fid2 AND is_active = 1
             LIMIT 1",
            ['uid1' => $userId, 'fid1' => $farmId, 'uid2' => $userId, 'fid2' => $farmId]
        );

        if (!$row) {
            $this->flash(FLASH_ERROR, 'You do not have permission to access this farm.');
            $this->redirect('/farms');
            exit;
        }
    }
}
