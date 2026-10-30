<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Seed Distribution Module
 * =========================================================
 * File    : modules/seed_distribution/controllers/GMSeedDistributionController.php
 * Purpose : General Manager seed distribution controller.
 *           Overview, Seed Products, Seed Stock, Monitor
 *           Distributions, Approve/Reject, History.
 *
 * Authorization: ROLE_GENERAL_MANAGER only.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\SeedDistribution\Controllers;

use AAM\Core\BaseController;
use AAM\SeedDistribution\Models\SeedDistributionModel;
use AAM\SeedDistribution\Models\SeedGradeModel;
use AAM\SeedDistribution\Services\SeedDistributionService;
use AAM\Core\Database;

class GMSeedDistributionController extends BaseController
{
    private SeedDistributionService $service;
    private SeedDistributionModel   $model;
    private SeedGradeModel          $gradeModel;
    private Database                $db;

    public function __construct()
    {
        parent::__construct();
        $this->service    = new SeedDistributionService();
        $this->model      = new SeedDistributionModel();
        $this->gradeModel = new SeedGradeModel();
        $this->db         = Database::getInstance();
    }

    // ── Overview ─────────────────────────────────────────

    public function overview(): void
    {
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $filters = [
            'date_from'  => $this->query('date_from', ''),
            'date_to'    => $this->query('date_to', ''),
            'warehouse_id' => $this->query('warehouse_id', ''),
            'product_id' => $this->query('product_id', ''),
        ];

        $kpis      = $this->model->getKpis($filters);
        $bySeed    = $this->model->getSummaryByProduct($filters);
        $recent    = $this->model->getRecent(10, $filters);
        $seedStock = $this->service->getSeedStockAllWarehouses($filters);

        // Warehouses with seed dist enabled
        $seedWarehouses = $this->db->fetchAll(
            "SELECT id, name, COALESCE(warehouse_code,CONCAT('WH-',LPAD(id,3,'0'))) AS warehouse_code
             FROM warehouses WHERE seed_distribution_enabled=1 AND is_active=1 AND deleted_at IS NULL ORDER BY name ASC"
        );

        $seedProducts = $this->service->getAllSeedProducts();

        $this->render('seed_distribution/gm/overview', [
            'title'          => 'Seed Distribution Overview',
            'kpis'           => $kpis,
            'bySeed'         => $bySeed,
            'recent'         => $recent,
            'seedStock'      => $seedStock,
            'seedWarehouses' => $seedWarehouses,
            'seedProducts'   => $seedProducts,
            'filters'        => $filters,
        ]);
    }

    // ── Seed Products (+ grades management) ──────────────

    public function products(): void
    {
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $allGrades   = $this->gradeModel->getAllWithProducts();
        $seedProducts = $this->service->getAllSeedProducts();

        // All products (including non-seed) for marking is_seed
        $allProducts = $this->db->fetchAll(
            "SELECT p.id, p.name, p.sku, p.is_seed, p.is_active,
                    c.name AS category_name,
                    u.abbreviation AS unit_abbr
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN units_of_measure u ON u.id = p.unit_id
             WHERE p.deleted_at IS NULL
             ORDER BY p.name ASC"
        );

        $this->render('seed_distribution/gm/products', [
            'title'        => 'Seed Products',
            'allGrades'    => $allGrades,
            'seedProducts' => $seedProducts,
            'allProducts'  => $allProducts,
        ]);
    }

    // ── Seed Stock ────────────────────────────────────────

    public function stock(): void
    {
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $filters = [
            'warehouse_id' => $this->query('warehouse_id', ''),
            'product_id'   => $this->query('product_id', ''),
        ];

        $seedStock      = $this->service->getSeedStockAllWarehouses($filters);
        $seedProducts   = $this->service->getAllSeedProducts();
        $seedWarehouses = $this->db->fetchAll(
            "SELECT id, name FROM warehouses WHERE seed_distribution_enabled=1 AND is_active=1 AND deleted_at IS NULL ORDER BY name ASC"
        );

        $this->render('seed_distribution/gm/stock', [
            'title'          => 'Seed Stock',
            'seedStock'      => $seedStock,
            'seedProducts'   => $seedProducts,
            'seedWarehouses' => $seedWarehouses,
            'filters'        => $filters,
        ]);
    }

    // ── Distribution monitor ──────────────────────────────

    public function distributions(): void
    {
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $page    = max(1, (int)$this->query('page', 1));
        $filters = [
            'search'            => $this->query('search', ''),
            'status'            => $this->query('status', ''),
            'warehouse_id'      => $this->query('warehouse_id', ''),
            'product_id'        => $this->query('product_id', ''),
            'distribution_type' => $this->query('distribution_type', ''),
            'date_from'         => $this->query('date_from', ''),
            'date_to'           => $this->query('date_to', ''),
        ];

        $result   = $this->model->paginateList($page, 20, $filters);
        $seedProds = $this->service->getAllSeedProducts();
        $seedWarehouses = $this->db->fetchAll(
            "SELECT id, name FROM warehouses WHERE seed_distribution_enabled=1 AND deleted_at IS NULL ORDER BY name ASC"
        );

        $this->render('seed_distribution/gm/distributions', [
            'title'          => 'Distribution / Dispatch',
            'result'         => $result,
            'filters'        => $filters,
            'seedProds'      => $seedProds,
            'seedWarehouses' => $seedWarehouses,
        ]);
    }

    // ── Pending for GM approval ───────────────────────────

    public function pending(): void
    {
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $page    = max(1, (int)$this->query('page', 1));
        $filters = ['status' => 'pending'];

        $result = $this->model->paginateList($page, 20, $filters);

        $this->render('seed_distribution/gm/pending', [
            'title'  => 'Pending Approvals',
            'result' => $result,
        ]);
    }

    // ── Approve ───────────────────────────────────────────

    public function approve(int $id): void
    {
        $this->verifyCsrf();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $result = $this->service->approve($id, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(null, 'Distribution approved.');
        } else {
            $this->jsonError($result['error'] ?? 'Approval failed.', HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // ── Reject ────────────────────────────────────────────

    public function reject(int $id): void
    {
        $this->verifyCsrf();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $reason = trim($this->input('rejection_reason', ''));
        if ($reason === '') {
            $this->jsonError('Rejection reason is required.', HTTP_UNPROCESSABLE_ENTITY);
            return;
        }

        $result = $this->service->reject($id, $this->userId(), $reason);

        if ($result['success']) {
            $this->jsonSuccess(null, 'Distribution rejected.');
        } else {
            $this->jsonError($result['error'] ?? 'Rejection failed.', HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // ── Show single record ────────────────────────────────

    public function show(int $id): void
    {
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $dist = $this->model->getByIdWithRelations($id);
        if (!$dist) {
            $this->redirectWith(url('gm/seed-distribution'), FLASH_ERROR, 'Record not found.');
            return;
        }

        $this->render('seed_distribution/gm/show', [
            'title' => 'Distribution ' . $dist['distribution_number'],
            'dist'  => $dist,
        ]);
    }

    // ── Distribution History (completed) ─────────────────

    public function history(): void
    {
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $page    = max(1, (int)$this->query('page', 1));
        $filters = [
            'search'            => $this->query('search', ''),
            'warehouse_id'      => $this->query('warehouse_id', ''),
            'product_id'        => $this->query('product_id', ''),
            'distribution_type' => $this->query('distribution_type', ''),
            'date_from'         => $this->query('date_from', ''),
            'date_to'           => $this->query('date_to', ''),
            'status'            => $this->query('status', ''),
        ];

        $result         = $this->model->paginateList($page, 20, $filters);
        $seedProds      = $this->service->getAllSeedProducts();
        $seedWarehouses = $this->db->fetchAll(
            "SELECT id, name FROM warehouses WHERE seed_distribution_enabled=1 AND deleted_at IS NULL ORDER BY name ASC"
        );

        $this->render('seed_distribution/gm/history', [
            'title'          => 'Distribution History',
            'result'         => $result,
            'filters'        => $filters,
            'seedProds'      => $seedProds,
            'seedWarehouses' => $seedWarehouses,
        ]);
    }

    // ── Save grade (AJAX from products page) ─────────────

    public function storeGrade(): void
    {
        $this->verifyCsrf();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $result = $this->service->createGrade([
            'product_id'    => $this->input('product_id'),
            'grade_name'    => $this->input('grade_name'),
            'grade_code'    => $this->input('grade_code', ''),
            'description'   => $this->input('description', ''),
            'display_order' => $this->input('display_order', 0),
        ], $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(['id' => $result['id']], 'Grade saved.');
        } else {
            $this->jsonError($result['error'] ?? 'Failed.', HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // ── Toggle grade active ───────────────────────────────

    public function toggleGrade(int $id): void
    {
        $this->verifyCsrf();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $grade = $this->gradeModel->find($id);
        if (!$grade) { $this->jsonError('Not found.', HTTP_NOT_FOUND); return; }

        $this->gradeModel->toggleActive($id);
        $this->jsonSuccess(null, 'Grade status updated.');
    }

    // ── Mark product as seed (AJAX) ───────────────────────

    public function toggleProductSeed(int $id): void
    {
        $this->verifyCsrf();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $this->db->execute(
            "UPDATE products SET is_seed = NOT is_seed WHERE id = :id",
            ['id' => $id]
        );
        $row = $this->db->fetchOne("SELECT is_seed FROM products WHERE id = :id", ['id' => $id]);
        $this->jsonSuccess(['is_seed' => (bool)($row['is_seed'] ?? false)], 'Product seed flag updated.');
    }
}
