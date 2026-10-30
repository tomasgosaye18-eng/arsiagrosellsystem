<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Seed Distribution Module
 * =========================================================
 * File    : modules/seed_distribution/controllers/SeedDistributionController.php
 * Purpose : Warehouse-level seed distribution controller.
 *           Available Seeds, New Distribution, Pending,
 *           Approved/Ready, Distribution History, Release.
 *
 * Authorization:
 *   - Role must be ROLE_WAREHOUSE_MANAGER
 *   - Warehouse must have seed_distribution_enabled = 1
 *   - User must be assigned to that warehouse
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\SeedDistribution\Controllers;

use AAM\Core\BaseController;
use AAM\Core\SessionManager;
use AAM\SeedDistribution\Models\SeedDistributionModel;
use AAM\SeedDistribution\Models\SeedGradeModel;
use AAM\SeedDistribution\Services\SeedDistributionService;
use AAM\Warehouse\Models\WarehouseInventoryModel;

class SeedDistributionController extends BaseController
{
    private SeedDistributionService $service;
    private SeedDistributionModel   $model;
    private SeedGradeModel          $gradeModel;

    public function __construct()
    {
        parent::__construct();
        $this->service    = new SeedDistributionService();
        $this->model      = new SeedDistributionModel();
        $this->gradeModel = new SeedGradeModel();
    }

    // ── Authorization guard ────────────────────────────────

    private function requireSeedAccess(): array
    {
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);

        $access = $this->service->checkWarehouseAccess($this->userId());
        if (!$access['allowed']) {
            $this->redirectWith(url('warehouse'), FLASH_ERROR, $access['error'] ?? 'Seed Distribution not available.');
            exit;
        }
        return $access['warehouse'];
    }

    // ── Available Seeds ────────────────────────────────────

    public function seeds(): void
    {
        $warehouse = $this->requireSeedAccess();
        $warehouseId = (int)$warehouse['id'];

        $seedStock = $this->service->getSeedStock($warehouseId);

        // Attach grades to each seed product
        $gradesMap = [];
        foreach ($seedStock as $row) {
            $pid = (int)$row['product_id'];
            if (!isset($gradesMap[$pid])) {
                $gradesMap[$pid] = $this->gradeModel->getForProduct($pid);
            }
        }

        $this->render('seed_distribution/warehouse/seeds', [
            'title'       => 'Available Seeds',
            'warehouse'   => $warehouse,
            'seedStock'   => $seedStock,
            'gradesMap'   => $gradesMap,
        ]);
    }

    // ── New Distribution form ─────────────────────────────

    public function createForm(): void
    {
        $warehouse = $this->requireSeedAccess();
        $warehouseId = (int)$warehouse['id'];

        $seedStock    = $this->service->getSeedStock($warehouseId);
        $allGrades    = $this->gradeModel->getActiveSeedGrades();

        $this->render('seed_distribution/warehouse/create', [
            'title'       => 'New Seed Distribution',
            'warehouse'   => $warehouse,
            'seedStock'   => $seedStock,
            'allGrades'   => $allGrades,
        ]);
    }

    // ── Store new distribution ────────────────────────────

    public function store(): void
    {
        $this->verifyCsrf();
        $warehouse = $this->requireSeedAccess();

        $data = [
            'warehouse_id'           => (int)$warehouse['id'],
            'product_id'             => (int)$this->input('product_id', 0),
            'seed_grade_id'          => $this->input('seed_grade_id', ''),
            'unit_id'                => $this->input('unit_id', ''),
            'distribution_type'      => $this->input('distribution_type', 'warehouse_pickup'),
            'quantity'               => (float)$this->input('quantity', 0),
            'destination_location'   => $this->input('destination_location', ''),
            'recipient_name'         => $this->input('recipient_name', ''),
            'recipient_phone'        => $this->input('recipient_phone', ''),
            'recipient_organization' => $this->input('recipient_organization', ''),
            'recipient_notes'        => $this->input('recipient_notes', ''),
            'program_name'           => $this->input('program_name', ''),
            'distribution_date'      => $this->input('distribution_date', date('Y-m-d')),
            'notes'                  => $this->input('notes', ''),
        ];

        $result = $this->service->createDistribution($data, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(
                ['redirect' => url('seed-distribution/pending')],
                'Distribution submitted for approval. Ref: ' . $result['distribution_number']
            );
        } else {
            $this->jsonError($result['error'] ?? 'Validation failed', HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // ── Pending Approvals ─────────────────────────────────

    public function pending(): void
    {
        $warehouse   = $this->requireSeedAccess();
        $warehouseId = (int)$warehouse['id'];

        $page    = max(1, (int)$this->query('page', 1));
        $filters = ['status' => 'pending', 'warehouse_id' => $warehouseId];

        $result = $this->model->paginateList($page, 20, $filters);

        $this->render('seed_distribution/warehouse/pending', [
            'title'      => 'Pending Approval',
            'warehouse'  => $warehouse,
            'result'     => $result,
        ]);
    }

    // ── Approved / Ready ─────────────────────────────────

    public function approved(): void
    {
        $warehouse   = $this->requireSeedAccess();
        $warehouseId = (int)$warehouse['id'];

        $page    = max(1, (int)$this->query('page', 1));
        $filters = ['status' => 'approved', 'warehouse_id' => $warehouseId];

        $result = $this->model->paginateList($page, 20, $filters);

        $this->render('seed_distribution/warehouse/approved', [
            'title'     => 'Approved / Ready to Release',
            'warehouse' => $warehouse,
            'result'    => $result,
        ]);
    }

    // ── Released / Dispatched ────────────────────────────

    public function released(): void
    {
        $warehouse   = $this->requireSeedAccess();
        $warehouseId = (int)$warehouse['id'];

        $page    = max(1, (int)$this->query('page', 1));
        $filters = ['status' => 'released', 'warehouse_id' => $warehouseId];

        $result = $this->model->paginateList($page, 20, $filters);

        $this->render('seed_distribution/warehouse/released', [
            'title'     => 'Released / Dispatched',
            'warehouse' => $warehouse,
            'result'    => $result,
        ]);
    }

    // ── Release / Dispatch ────────────────────────────────

    public function release(int $id): void
    {
        $this->verifyCsrf();
        $this->requireSeedAccess();

        $result = $this->service->release($id, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(['redirect' => url('seed-distribution/approved')], 'Seed released successfully. Inventory updated.');
        } else {
            $this->jsonError($result['error'] ?? 'Release failed', HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // ── Cancel ───────────────────────────────────────────

    public function cancel(int $id): void
    {
        $this->verifyCsrf();
        $this->requireSeedAccess();

        $result = $this->service->cancel($id, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(null, 'Distribution cancelled.');
        } else {
            $this->jsonError($result['error'] ?? 'Cancel failed', HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // ── Mark Complete ─────────────────────────────────────

    public function complete(int $id): void
    {
        $this->verifyCsrf();
        $this->requireSeedAccess();

        $result = $this->service->complete($id, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(null, 'Distribution marked as completed.');
        } else {
            $this->jsonError($result['error'] ?? 'Could not complete.', HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // ── Distribution History ──────────────────────────────

    public function history(): void
    {
        $warehouse   = $this->requireSeedAccess();
        $warehouseId = (int)$warehouse['id'];

        $page    = max(1, (int)$this->query('page', 1));
        $filters = [
            'warehouse_id'      => $warehouseId,
            'search'            => $this->query('search', ''),
            'status'            => $this->query('status', ''),
            'distribution_type' => $this->query('distribution_type', ''),
            'date_from'         => $this->query('date_from', ''),
            'date_to'           => $this->query('date_to', ''),
            'product_id'        => $this->query('product_id', ''),
        ];

        $result    = $this->model->paginateList($page, 20, $filters);
        $seedProds = $this->service->getAllSeedProducts();

        $this->render('seed_distribution/warehouse/history', [
            'title'     => 'Distribution History',
            'warehouse' => $warehouse,
            'result'    => $result,
            'filters'   => $filters,
            'seedProds' => $seedProds,
        ]);
    }

    // ── Show single record ────────────────────────────────

    public function show(int $id): void
    {
        $warehouse = $this->requireSeedAccess();
        $dist      = $this->model->getByIdWithRelations($id);

        if (!$dist || (int)$dist['warehouse_id'] !== (int)$warehouse['id']) {
            $this->redirectWith(url('seed-distribution/history'), FLASH_ERROR, 'Record not found.');
            return;
        }

        $this->render('seed_distribution/warehouse/show', [
            'title'     => 'Distribution #' . $dist['distribution_number'],
            'warehouse' => $warehouse,
            'dist'      => $dist,
        ]);
    }

    // ── AJAX: get grades for a product ───────────────────

    public function gradesForProduct(): void
    {
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);
        $productId = (int)$this->query('product_id', 0);
        $grades    = $productId ? $this->gradeModel->getForProduct($productId) : [];
        $this->json(['success' => true, 'grades' => $grades]);
    }

    // ── AJAX: get GM-configured price for a product ───────

    public function getPrice(): void
    {
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);
        $productId = (int)$this->query('product_id', 0);

        if (!$productId) {
            $this->json(['success' => false, 'error' => 'Product ID required.']);
            return;
        }

        $priceInfo = $this->service->getPriceForProduct($productId);
        $this->json([
            'success'    => true,
            'has_price'  => $priceInfo['has_price'],
            'unit_price' => $priceInfo['unit_price'],
            'unit_abbr'  => $priceInfo['unit_abbr'],
        ]);
    }

    // ── Receipt view ──────────────────────────────────────

    public function receipt(int $id): void
    {
        $warehouse = $this->requireSeedAccess();
        $dist      = $this->model->getByIdWithRelations($id);

        if (!$dist || (int)$dist['warehouse_id'] !== (int)$warehouse['id']) {
            $this->redirectWith(url('seed-distribution/history'), FLASH_ERROR, 'Record not found.');
            return;
        }

        // Only released/completed distributions have a valid receipt
        if (!in_array($dist['status'] ?? '', ['released', 'completed'], true)) {
            $this->redirectWith(
                url('seed-distribution/' . $id),
                FLASH_ERROR,
                'Receipt is only available after the distribution has been released.'
            );
            return;
        }

        $this->render('seed_distribution/warehouse/receipt', [
            'pageTitle' => 'Seed Distribution Receipt — ' . $dist['distribution_number'],
            'title'     => 'Seed Distribution Receipt',
            'warehouse' => $warehouse,
            'dist'      => $dist,
        ], 'print');
    }
}
