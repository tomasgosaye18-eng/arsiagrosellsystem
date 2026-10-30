<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9A
 * =========================================================
 * File    : modules/farm/controllers/HarvestController.php
 * Purpose : Harvest batch management.
 *
 * Routes  :
 *   GET  /harvests                 → index()  — Harvest list
 *   GET  /farms/{id}/harvests/create → create() — New harvest form
 *   POST /farms/{id}/harvests        → store()  — Save harvest
 *   GET  /harvests/{hid}             → view()   — View single
 *   GET  /harvests/{hid}/edit        → edit()   — Edit form
 *   POST /harvests/{hid}/update      → update() — Save edit
 *   POST /harvests/{hid}/cancel      → cancel() — Cancel
 *
 * Standalone (all-farms) harvest list:
 *   GET  /harvests                   → index() with farmId=0 for admin
 *   GET  /farms/{farmId}/harvests    → index() scoped to farm
 *
 * Business Rules:
 *   RULE 1: Harvest must belong to valid Farm.
 *   RULE 2: Harvest must reference valid Product.
 *   RULE 3: Quantity > 0.
 *   RULE 8: No hard deletes — cancel only.
 *   RULE 9: All changes create Business Audit Trail.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Farm\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Farm\Models\FarmModel;
use AAM\Farm\Models\HarvestBatchModel;
use AAM\Admin\Models\ActivityLogModel;

class HarvestController extends BaseController
{
    private HarvestBatchModel $harvestModel;
    private FarmModel         $farmModel;
    private ActivityLogModel  $auditLog;

    public function __construct()
    {
        parent::__construct();
        $this->harvestModel = new HarvestBatchModel();
        $this->farmModel    = new FarmModel();
        $this->auditLog     = new ActivityLogModel();
    }

    // ──────────────────────────────────────────────────────
    // GET /farms/{farmId}/harvests  — Harvest list (scoped to farm)
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
            'search'     => $this->query('search', ''),
            'product_id' => (int)$this->query('product_id', 0) ?: null,
            'status'     => $this->query('status', ''),
            'quality'    => $this->query('quality', ''),
            'date_from'  => $this->query('date_from', ''),
            'date_to'    => $this->query('date_to', ''),
        ];

        $page    = max(1, (int)$this->query('page', 1));
        $result  = $this->harvestModel->paginateFarm($farmId, $page, PAGINATION_DEFAULT, $filters);

        // Products dropdown for filter
        $db       = Database::getInstance();
        $products = $db->fetchAll(
            "SELECT DISTINCT p.id, p.name
             FROM products p
             INNER JOIN harvest_batches hb ON hb.product_id = p.id
             WHERE hb.farm_id = :fid
             ORDER BY p.name ASC",
            ['fid' => $farmId]
        ) ?: [];

        $this->render('farm.harvests.index', [
            'pageTitle'  => 'Harvests — ' . $farm['name'],
            'farm'       => $farm,
            'harvests'   => $result['data'],
            'pagination' => $result,
            'filters'    => $filters,
            'products'   => $products,
            'userRole'   => $role,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // GET /farms/{farmId}/harvests/create  — Create harvest form
    // ──────────────────────────────────────────────────────

    public function create(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        $farmId = (int)$id;
        $role   = $this->userRole();
        $userId = (int)$this->userId();

        $farm = $this->requireFarmAccess($farmId, $userId, $role);

        $db       = Database::getInstance();
        $products = $db->fetchAll(
            "SELECT id, name, is_perishable, shelf_life_days, sku
             FROM products WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY name ASC"
        ) ?: [];

        $units = $db->fetchAll(
            "SELECT id, name, abbreviation FROM units_of_measure
             WHERE is_active = 1 ORDER BY name ASC"
        ) ?: [];

        $nextBatch = $this->harvestModel->generateBatchNumber();

        $this->render('farm.harvests.create', [
            'pageTitle' => 'Record Harvest — ' . $farm['name'],
            'farm'      => $farm,
            'products'  => $products,
            'units'     => $units,
            'nextBatch' => $nextBatch,
            'today'     => date('Y-m-d'),
            'userRole'  => $role,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // POST /farms/{farmId}/harvests  — Store harvest
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

        // Validation
        $errors = $this->validate($data, [
            'product_id'       => 'required|integer',
            'initial_quantity' => 'required|numeric|min_value:0.001',
            'unit_id'          => 'required|integer',
            'harvest_date'     => 'required|date',
            'production_date'  => 'required|date',
            'quality_grade'    => 'required',
        ], true);

        if (is_array($errors)) {
            $this->flash(FLASH_ERROR, 'Please correct the errors below.');
            SessionManager::set('validation_errors', $errors);
            SessionManager::set('old_input', $data);
            $this->redirect("/farms/{$farmId}/harvests/create");
            return;
        }

        // Generate unique batch number
        $batchNumber = trim($data['batch_number'] ?? '');
        if ($batchNumber === '') {
            $batchNumber = $this->harvestModel->generateBatchNumber();
        }

        $record = [
            'farm_id'          => $farmId,
            'product_id'       => (int)$data['product_id'],
            'batch_number'     => $batchNumber,
            'production_date'  => $data['production_date'],
            'harvest_date'     => $data['harvest_date'],
            'expiry_date'      => !empty($data['expiry_date']) ? $data['expiry_date'] : null,
            'initial_quantity' => (float)$data['initial_quantity'],
            'unit_id'          => (int)$data['unit_id'],
            'quality_grade'    => $data['quality_grade'],
            'notes'            => trim($data['notes'] ?? ''),
            'recorded_by'      => $userId,
        ];

        try {
            $newId = $this->harvestModel->createBatch($record);

            // Business Audit Trail
            $product = Database::getInstance()->fetchOne(
                "SELECT name FROM products WHERE id = :pid", ['pid' => $record['product_id']]
            );
            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_HARVEST_CREATED,
                'module'       => 'farm',
                'entity_type'  => 'harvest',
                'entity_label' => "{$batchNumber} — " . ($product['name'] ?? ''),
                'record_id'    => $newId,
                'description'  => "Harvest recorded: {$record['initial_quantity']} of " .
                                  ($product['name'] ?? '') . " at {$farm['name']}",
                'farm_id'      => $farmId,
                'new_values'   => $record,
            ]);

            $this->flash(FLASH_SUCCESS, "Harvest batch {$batchNumber} recorded successfully.");
            $this->redirect("/farms/{$farmId}/harvests");

        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, 'Failed to save harvest record. Please try again.');
            $this->redirect("/farms/{$farmId}/harvests/create");
        }
    }

    // ──────────────────────────────────────────────────────
    // GET /harvests/{hid}  — View single harvest
    // ──────────────────────────────────────────────────────

    public function view(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        $harvestId = (int)$id;
        $harvest   = $this->harvestModel->getWithDetails($harvestId);

        if (!$harvest) {
            $this->flash(FLASH_ERROR, 'Harvest record not found.');
            $this->redirect('/farms');
            return;
        }

        $this->checkFarmPermission((int)$harvest['farm_id']);

        // Dispatch history for this batch
        $db = Database::getInstance();
        $dispatches = $db->fetchAll(
            "SELECT d.dispatch_number, d.dispatch_date, d.status,
                    di.quantity_dispatched, w.name AS warehouse_name
             FROM farm_dispatch_items di
             JOIN farm_dispatches d ON d.id = di.dispatch_id
             JOIN warehouses      w ON w.id = d.warehouse_id
             WHERE di.harvest_batch_id = :hid
             ORDER BY d.dispatch_date DESC",
            ['hid' => $harvestId]
        ) ?: [];

        $this->render('farm.harvests.view', [
            'pageTitle'  => 'Harvest — ' . ($harvest['batch_number'] ?? $harvestId),
            'harvest'    => $harvest,
            'dispatches' => $dispatches,
            'userRole'   => $this->userRole(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // GET /harvests/{hid}/edit  — Edit harvest form
    // ──────────────────────────────────────────────────────

    public function edit(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        $harvestId = (int)$id;
        $harvest   = $this->harvestModel->getWithDetails($harvestId);

        if (!$harvest) {
            $this->flash(FLASH_ERROR, 'Harvest record not found.');
            $this->redirect('/farms');
            return;
        }

        $this->checkFarmPermission((int)$harvest['farm_id']);

        if (!HarvestBatchModel::isEditable($harvest['status'])) {
            $this->flash(FLASH_ERROR, 'This harvest record cannot be edited in its current status.');
            $this->redirect("/harvests/{$harvestId}");
            return;
        }

        $db       = Database::getInstance();
        $products = $db->fetchAll(
            "SELECT id, name, is_perishable, shelf_life_days FROM products
             WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC"
        ) ?: [];

        $units = $db->fetchAll(
            "SELECT id, name, abbreviation FROM units_of_measure
             WHERE is_active = 1 ORDER BY name ASC"
        ) ?: [];

        $farm = $this->farmModel->getWithDetails((int)$harvest['farm_id']);

        $this->render('farm.harvests.edit', [
            'pageTitle' => 'Edit Harvest — ' . $harvest['batch_number'],
            'harvest'   => $harvest,
            'farm'      => $farm,
            'products'  => $products,
            'units'     => $units,
            'userRole'  => $this->userRole(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // POST /harvests/{hid}/update  — Save harvest edits
    // ──────────────────────────────────────────────────────

    public function update(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $harvestId = (int)$id;
        $harvest   = $this->harvestModel->getWithDetails($harvestId);

        if (!$harvest) {
            $this->flash(FLASH_ERROR, 'Harvest record not found.');
            $this->redirect('/farms');
            return;
        }

        $this->checkFarmPermission((int)$harvest['farm_id']);

        if (!HarvestBatchModel::isEditable($harvest['status'])) {
            $this->flash(FLASH_ERROR, 'This harvest record cannot be edited in its current status.');
            $this->redirect("/harvests/{$harvestId}");
            return;
        }

        $data   = $this->inputAll();
        $errors = $this->validate($data, [
            'initial_quantity' => 'required|numeric|min_value:0.001',
            'unit_id'          => 'required|integer',
            'harvest_date'     => 'required|date',
            'production_date'  => 'required|date',
            'quality_grade'    => 'required',
        ], true);

        if (is_array($errors)) {
            $this->flash(FLASH_ERROR, 'Please correct the errors below.');
            SessionManager::set('validation_errors', $errors);
            SessionManager::set('old_input', $data);
            $this->redirect("/harvests/{$harvestId}/edit");
            return;
        }

        $oldValues = [
            'initial_quantity' => $harvest['initial_quantity'],
            'quality_grade'    => $harvest['quality_grade'],
            'notes'            => $harvest['notes'],
        ];

        $newQty    = (float)$data['initial_quantity'];
        $oldQty    = (float)$harvest['initial_quantity'];
        $dispatched = $oldQty - (float)$harvest['remaining_quantity'];

        if ($newQty < $dispatched) {
            $this->flash(FLASH_ERROR,
                "Initial quantity cannot be less than already dispatched quantity ({$dispatched})."
            );
            $this->redirect("/harvests/{$harvestId}/edit");
            return;
        }

        $update = [
            'initial_quantity'  => $newQty,
            'remaining_quantity'=> $newQty - $dispatched,
            'unit_id'           => (int)$data['unit_id'],
            'harvest_date'      => $data['harvest_date'],
            'production_date'   => $data['production_date'],
            'expiry_date'       => !empty($data['expiry_date']) ? $data['expiry_date'] : null,
            'quality_grade'     => $data['quality_grade'],
            'notes'             => trim($data['notes'] ?? ''),
        ];

        $this->harvestModel->updateBatch($harvestId, $update);

        $this->auditLog->logBusiness([
            'action'       => ActivityLogModel::ACTION_HARVEST_UPDATED,
            'module'       => 'farm',
            'entity_type'  => 'harvest',
            'entity_label' => $harvest['batch_number'],
            'record_id'    => $harvestId,
            'description'  => "Harvest batch {$harvest['batch_number']} updated.",
            'farm_id'      => (int)$harvest['farm_id'],
            'old_values'   => $oldValues,
            'new_values'   => $update,
        ]);

        $this->flash(FLASH_SUCCESS, 'Harvest record updated successfully.');
        $this->redirect("/harvests/{$harvestId}");
    }

    // ──────────────────────────────────────────────────────
    // POST /harvests/{hid}/cancel  — Cancel harvest
    // ──────────────────────────────────────────────────────

    public function cancel(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $harvestId = (int)$id;
        $harvest   = $this->harvestModel->getWithDetails($harvestId);

        if (!$harvest) {
            $this->flash(FLASH_ERROR, 'Harvest record not found.');
            $this->redirect('/farms');
            return;
        }

        if ((float)$harvest['initial_quantity'] !== (float)$harvest['remaining_quantity']) {
            $this->flash(FLASH_ERROR,
                'Cannot cancel a harvest batch that has already been (partially) dispatched.'
            );
            $this->redirect("/harvests/{$harvestId}");
            return;
        }

        $this->harvestModel->update($harvestId, ['status' => HarvestBatchModel::STATUS_QUARANTINE]);

        $this->auditLog->logBusiness([
            'action'       => ActivityLogModel::ACTION_HARVEST_CANCELLED,
            'module'       => 'farm',
            'entity_type'  => 'harvest',
            'entity_label' => $harvest['batch_number'],
            'record_id'    => $harvestId,
            'description'  => "Harvest batch {$harvest['batch_number']} cancelled/quarantined.",
            'farm_id'      => (int)$harvest['farm_id'],
        ]);

        $this->flash(FLASH_SUCCESS, 'Harvest record has been cancelled.');
        $this->redirect("/farms/{$harvest['farm_id']}/harvests");
    }

    // ──────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────

    /**
     * Load farm and verify access. Redirects on failure.
     */
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

    /**
     * For standalone harvest routes (not under /farms/{id}) verify access.
     */
    private function checkFarmPermission(int $farmId): void
    {
        $role   = $this->userRole();
        $userId = (int)$this->userId();

        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            return;
        }

        // Farm manager must be assigned to this farm
        $db  = $this->harvestModel->getDb();
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
