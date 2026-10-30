<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) â€” Warehouse Reports
 * =========================================================
 * File    : modules/reports/controllers/WarehouseReportController.php
 * Routes  :
 *   GET /reports/warehouse              â†’ index()
 *   GET /reports/warehouse/stock        â†’ stock()
 *   GET /reports/warehouse/receiving    â†’ receiving()
 *   GET /reports/warehouse/transfers    â†’ transfers()
 *   GET /reports/warehouse/discrepanciesâ†’ discrepancies()
 *   GET /reports/warehouse/expiry       â†’ expiry()
 * * Access  : warehouse_manager, admin, general_manager
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Reports\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Reports\Models\WarehouseReportModel;

class WarehouseReportController extends BaseController
{
    private WarehouseReportModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new WarehouseReportModel();
    }

    // â”€â”€ Shared: resolve warehouse & filters â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function resolveWarehouseId(): int
    {
        $role = $this->userRole();
        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            return (int)$this->query('wid', 1) ?: 1;
        }
        $lid   = (int)SessionManager::get(SESSION_LOCATION_ID, 0);
        $ltype = SessionManager::get(SESSION_LOCATION_TYPE, '');
        if ($ltype === 'warehouse' && $lid > 0) return $lid;
        $row = Database::getInstance()->fetchOne(
            "SELECT location_id FROM user_location_assignments
             WHERE user_id = :uid AND location_type = 'warehouse' LIMIT 1",
            ['uid' => $this->userId()]
        );
        return $row ? (int)$row['location_id'] : 1;
    }

    private function getFilters(): array
    {
        $preset = $this->query('preset', 'month');
        [$dateFrom, $dateTo] = match($preset) {
            'today'  => [date('Y-m-d'), date('Y-m-d')],
            'week'   => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
            'custom' => [$this->query('date_from', date('Y-m-01')), $this->query('date_to', date('Y-m-d'))],
            default  => [date('Y-m-01'), date('Y-m-d')],
        };
        return [
            'preset'     => $preset,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
            'product_id' => (int)$this->query('product_id', 0) ?: null,
            'category_id'=> (int)$this->query('category_id', 0) ?: null,
            'farm_id'    => (int)$this->query('farm_id', 0) ?: null,
            'shop_id'    => (int)$this->query('shop_id', 0) ?: null,
            'status'     => $this->query('status', ''),
            'type'       => $this->query('type', ''),
            'batch'      => $this->query('batch', ''),
            'search'     => $this->query('search', ''),
        ];
    }

    private function requireAccess(): void
    {
        $this->requireAuth();
        $this->requireRole(
            ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER
        );
    }

    private function getWarehouseInfo(int $warehouseId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT id, name, location FROM warehouses WHERE id = :id",
            ['id' => $warehouseId]
        );
    }

    // =========================================================
    // GET /reports/warehouse  â€” Hub
    // =========================================================
    public function index(): void
    {
        $this->requireAccess();
        $warehouseId = $this->resolveWarehouseId();
        $warehouse   = $this->getWarehouseInfo($warehouseId);
        $summary     = $this->model->getStockSummary($warehouseId);
        $disSummary  = $this->model->getDiscrepancySummary($warehouseId);

        $this->render('reports.warehouse.index', [
            'pageTitle'   => 'Warehouse Reports',
            'warehouse'   => $warehouse,
            'warehouseId' => $warehouseId,
            'summary'     => $summary,
            'disSummary'  => $disSummary,
            'userRole'    => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /reports/warehouse/stock  â€” Stock Report
    // =========================================================
    public function stock(): void
    {
        $this->requireAccess();
        $warehouseId = $this->resolveWarehouseId();
        $filters     = $this->getFilters();
        $warehouse   = $this->getWarehouseInfo($warehouseId);

        $rows    = $this->model->getStockReport($warehouseId, $filters);
        $summary = $this->model->getStockSummary($warehouseId);

        $db         = Database::getInstance();
        $categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active=1 ORDER BY name");
        $products   = $this->model->getWarehouseProducts($warehouseId);

        $this->render('reports.warehouse.stock', [
            'pageTitle'   => 'Stock Report',
            'warehouse'   => $warehouse,
            'warehouseId' => $warehouseId,
            'rows'        => $rows,
            'summary'     => $summary,
            'filters'     => $filters,
            'categories'  => $categories,
            'products'    => $products,
            'userRole'    => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /reports/warehouse/receiving  â€” Receiving Report
    // =========================================================
    public function receiving(): void
    {
        $this->requireAccess();
        $warehouseId = $this->resolveWarehouseId();
        $filters     = $this->getFilters();
        $page        = max(1, (int)$this->query('page', 1));
        $warehouse   = $this->getWarehouseInfo($warehouseId);

        $result  = $this->model->getReceivingReport($warehouseId, $filters, $page, 25);
        $summary = $this->model->getReceivingSummary(
            $warehouseId, $filters['date_from'], $filters['date_to']
        );
        $farms    = $this->model->getWarehouseFarms($warehouseId);
        $products = $this->model->getWarehouseProducts($warehouseId);

        $this->render('reports.warehouse.receiving', [
            'pageTitle'  => 'Receiving Report',
            'warehouse'  => $warehouse,
            'warehouseId'=> $warehouseId,
            'rows'       => $result['data'],
            'pagination' => $result,
            'summary'    => $summary,
            'filters'    => $filters,
            'farms'      => $farms,
            'products'   => $products,
            'userRole'   => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /reports/warehouse/transfers  â€” Transfer Report
    // =========================================================
    public function transfers(): void
    {
        $this->requireAccess();
        $warehouseId = $this->resolveWarehouseId();
        $filters     = $this->getFilters();
        $page        = max(1, (int)$this->query('page', 1));
        $warehouse   = $this->getWarehouseInfo($warehouseId);

        $result  = $this->model->getTransferReport($warehouseId, $filters, $page, 25);
        $summary = $this->model->getTransferSummary(
            $warehouseId, $filters['date_from'], $filters['date_to']
        );
        $shops    = $this->model->getWarehouseShops($warehouseId);
        $products = $this->model->getWarehouseProducts($warehouseId);

        $this->render('reports.warehouse.transfers', [
            'pageTitle'  => 'Transfer Report',
            'warehouse'  => $warehouse,
            'warehouseId'=> $warehouseId,
            'rows'       => $result['data'],
            'pagination' => $result,
            'summary'    => $summary,
            'filters'    => $filters,
            'shops'      => $shops,
            'products'   => $products,
            'userRole'   => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /reports/warehouse/discrepancies  â€” Discrepancy Report
    // =========================================================
    public function discrepancies(): void
    {
        $this->requireAccess();
        $warehouseId = $this->resolveWarehouseId();
        $filters     = $this->getFilters();
        $page        = max(1, (int)$this->query('page', 1));
        $warehouse   = $this->getWarehouseInfo($warehouseId);

        $result  = $this->model->getDiscrepancyReport($warehouseId, $filters, $page, 25);
        $summary = $this->model->getDiscrepancySummary($warehouseId);
        $products = $this->model->getWarehouseProducts($warehouseId);

        $this->render('reports.warehouse.discrepancies', [
            'pageTitle'  => 'Discrepancy Report',
            'warehouse'  => $warehouse,
            'warehouseId'=> $warehouseId,
            'rows'       => $result['data'],
            'pagination' => $result,
            'summary'    => $summary,
            'filters'    => $filters,
            'products'   => $products,
            'userRole'   => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /reports/warehouse/expiry  â€” Expiry Report
    // =========================================================
    public function expiry(): void
    {
        $this->requireAccess();
        $warehouseId = $this->resolveWarehouseId();
        $filters     = $this->getFilters();
        $warehouse   = $this->getWarehouseInfo($warehouseId);

        $rows     = $this->model->getExpiryReport($warehouseId, $filters);
        $products = $this->model->getWarehouseProducts($warehouseId);

        // Split into groups for summary
        $expired      = array_filter($rows, fn($r) => (int)($r['days_remaining'] ?? 1) < 0);
        $expiringSoon = array_filter($rows, fn($r) => ($r['days_remaining'] ?? 999) >= 0 && ($r['days_remaining'] ?? 999) <= 30);
        $normal       = array_filter($rows, fn($r) => ($r['days_remaining'] ?? 999) > 30);

        $this->render('reports.warehouse.expiry', [
            'pageTitle'    => 'Expiry Report',
            'warehouse'    => $warehouse,
            'warehouseId'  => $warehouseId,
            'rows'         => $rows,
            'expiredCount' => count($expired),
            'soonCount'    => count($expiringSoon),
            'normalCount'  => count($normal),
            'filters'      => $filters,
            'products'     => $products,
            'userRole'     => $this->userRole(),
        ]);
    }
}

