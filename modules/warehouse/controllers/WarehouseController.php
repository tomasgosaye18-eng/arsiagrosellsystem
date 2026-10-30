<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) â€” Phase 10
 * =========================================================
 * File    : modules/warehouse/controllers/WarehouseController.php
 * Purpose : Warehouse Overview + Inventory + Discrepancies
 *           + Stock Movement History.
 *
 * Routes  :
 *   GET /warehouse              â†’ index()    (Overview)
 *   GET /warehouse/inventory    â†’ inventory()
 *   GET /warehouse/discrepancies â†’ discrepancies()
 *   POST /warehouse/discrepancies/{id}/status â†’ updateDiscrepancyStatus()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Warehouse\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Warehouse\Models\WarehouseInventoryModel;
use AAM\Warehouse\Models\ReceivingModel;
use AAM\Warehouse\Models\DiscrepancyModel;
use AAM\Warehouse\Models\TransferModel;
use AAM\Warehouse\Models\StockMovementModel;
use AAM\Warehouse\Services\WarehouseService;

class WarehouseController extends BaseController
{
    private int $warehouseId;

    public function __construct()
    {
        parent::__construct();
    }

    // â”€â”€ Resolve warehouse assignment â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    private function resolveWarehouseId(): int
    {
        $role = $this->userRole();
        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            // Admin sees warehouse 1 by default (can be extended)
            $wid = (int)$this->query('wid', 1);
            return $wid > 0 ? $wid : 1;
        }
        // Warehouse manager: use assigned location
        $lid  = (int)SessionManager::get(SESSION_LOCATION_ID, 0);
        $ltype = SessionManager::get(SESSION_LOCATION_TYPE, '');
        if ($ltype === 'warehouse' && $lid > 0) return $lid;

        // Fallback: query assignments
        $db  = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT location_id FROM user_location_assignments
             WHERE user_id = :uid AND location_type = 'warehouse' LIMIT 1",
            ['uid' => $this->userId()]
        );
        if ($row) return (int)$row['location_id'];

        $row = $db->fetchOne(
            "SELECT location_id FROM organization_assignments
             WHERE user_id = :uid AND location_type = 'warehouse' AND is_active = 1
             ORDER BY assigned_at DESC LIMIT 1",
            ['uid' => $this->userId()]
        );
        return $row ? (int)$row['location_id'] : 1;
    }

    // =========================================================
    // GET /warehouse  â€” Overview / Dashboard
    // =========================================================
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);

        $warehouseId = $this->resolveWarehouseId();
        $db          = Database::getInstance();

        // Warehouse info
        $warehouse = $db->fetchOne(
            "SELECT * FROM warehouses WHERE id = :id", ['id' => $warehouseId]
        );

        $invModel = new WarehouseInventoryModel();
        $rcvModel = new ReceivingModel();
        $trnModel = new TransferModel();
        $disModel = new DiscrepancyModel();

        $kpis = $invModel->getDashboardKpis($warehouseId);
        $kpis['pending_receipts']  = $rcvModel->countPending($warehouseId);
        $kpis['pending_transfers'] = $trnModel->countPending($warehouseId);
        $kpis['open_discrepancies']= $disModel->countOpen($warehouseId);
        $kpis['today_received']    = $rcvModel->getTodayReceived($warehouseId);
        $kpis['today_dispatched']  = $trnModel->getTodayDispatched($warehouseId);

        $expiringSoon = $invModel->getExpiringSoon($warehouseId, 7);
        $lowStock     = $invModel->getLowStock($warehouseId);

        // Recent receivings (last 5)
        $recentReceiving = $rcvModel->paginateWarehouse($warehouseId, 1, 5)['data'];

        // Recent transfers (last 5)
        $recentTransfers = $trnModel->paginateWarehouse($warehouseId, 1, 5)['data'];

        // All warehouses for switcher (admin/GM)
        $warehouses = [];
        if (in_array($this->userRole(), [ROLE_GENERAL_MANAGER], true)) {
            $warehouses = $db->fetchAll("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name");
        }

        $this->render('warehouse.overview', [
            'pageTitle'       => 'Warehouse Overview',
            'warehouse'       => $warehouse,
            'warehouseId'     => $warehouseId,
            'kpis'            => $kpis,
            'expiringSoon'    => $expiringSoon,
            'lowStock'        => $lowStock,
            'recentReceiving' => $recentReceiving,
            'recentTransfers' => $recentTransfers,
            'warehouses'      => $warehouses,
            'userRole'        => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /warehouse/inventory  â€” Inventory List
    // =========================================================
    public function inventory(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);

        $warehouseId = $this->resolveWarehouseId();
        $db          = Database::getInstance();

        $filters = [
            'search'      => $this->query('search',      ''),
            'category_id' => $this->query('category_id', ''),
            'status'      => $this->query('status',      ''),
            'batch'       => $this->query('batch',       ''),
        ];
        $page    = max(1, (int)$this->query('page', 1));

        $invModel  = new WarehouseInventoryModel();
        $result    = $invModel->paginateWarehouse($warehouseId, $page, PAGINATION_DEFAULT, $filters);
        $categories = $db->fetchAll(
            "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name"
        );
        $warehouse = $db->fetchOne("SELECT id, name FROM warehouses WHERE id = :id", ['id' => $warehouseId]);

        $this->render('warehouse.inventory', [
            'pageTitle'   => 'Warehouse Inventory',
            'warehouse'   => $warehouse,
            'warehouseId' => $warehouseId,
            'inventory'   => $result['data'],
            'pagination'  => $result,
            'filters'     => $filters,
            'categories'  => $categories,
            'userRole'    => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /warehouse/discrepancies
    // =========================================================
    public function discrepancies(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);

        $warehouseId = $this->resolveWarehouseId();

        $filters = [
            'status'    => $this->query('status',    ''),
            'search'    => $this->query('search',    ''),
            'date_from' => $this->query('date_from', ''),
            'date_to'   => $this->query('date_to',   ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $disModel = new DiscrepancyModel();
        $result    = $disModel->paginateWarehouse($warehouseId, $page, PAGINATION_DEFAULT, $filters);

        $db        = Database::getInstance();
        $warehouse = $db->fetchOne("SELECT id, name FROM warehouses WHERE id = :id", ['id' => $warehouseId]);

        $this->render('warehouse.discrepancies', [
            'pageTitle'       => 'Discrepancies',
            'warehouse'       => $warehouse,
            'warehouseId'     => $warehouseId,
            'discrepancies'   => $result['data'],
            'pagination'      => $result,
            'filters'         => $filters,
            'userRole'        => $this->userRole(),
        ]);
    }

    // =========================================================
    // POST /warehouse/discrepancies/{id}/status
    // =========================================================
    public function updateDiscrepancyStatus(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $discrepancyId = (int)$id;
        $status        = trim($this->input('status', ''));
        $notes         = trim($this->input('notes',  ''));

        $allowed = [DiscrepancyModel::STATUS_UNDER_REVIEW, DiscrepancyModel::STATUS_RESOLVED, DiscrepancyModel::STATUS_REJECTED];
        if (!in_array($status, $allowed, true)) {
            $this->flash(FLASH_ERROR, 'Invalid status.');
            $this->redirectBack('/warehouse/discrepancies');
            return;
        }

        try {
            $service = new WarehouseService();

            // Block manual status updates on transfer discrepancies — those are
            // managed exclusively by the GM approval workflow.
            $disModel  = new DiscrepancyModel();
            $existing  = $disModel->find($discrepancyId);
            if ($existing && ($existing['reference_type'] ?? '') === 'transfer') {
                $this->flash(FLASH_ERROR,
                    'Transfer receiving discrepancies are managed by the General Manager workflow '
                    . 'and cannot be manually updated.');
                $this->redirect('/warehouse/discrepancies');
                return;
            }

            $service->updateDiscrepancyStatus($discrepancyId, $status, $notes);
            $this->flash(FLASH_SUCCESS, "Discrepancy #{$discrepancyId} updated to: " . ucfirst(str_replace('_', ' ', $status)) . '.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }

        $this->redirect('/warehouse/discrepancies');
    }

    // =========================================================
    // GET /warehouse/dispatches â€” alias kept for backward compat
    // =========================================================
    public function dispatches(): void
    {
        $this->redirect('/warehouse/receiving');
    }

    // =========================================================
    // GET /warehouse/discrepancies/{id}  â€” Show single discrepancy
    // =========================================================
    public function discrepancyShow(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);

        $disModel     = new DiscrepancyModel();
        $discrepancy  = $disModel->getWithDetails((int)$id);

        if (!$discrepancy) {
            $this->flash(FLASH_ERROR, 'Discrepancy not found.');
            $this->redirect('/warehouse/discrepancies');
            return;
        }

        $db        = Database::getInstance();
        $warehouse = $db->fetchOne(
            "SELECT id, name FROM warehouses WHERE id = :id",
            ['id' => $discrepancy['warehouse_id']]
        );

        $this->render('warehouse.discrepancy_show', [
            'pageTitle'   => 'Discrepancy â€” ' . ($discrepancy['discrepancy_number'] ?? ''),
            'discrepancy' => $discrepancy,
            'warehouse'   => $warehouse,
            'userRole'    => $this->userRole(),
            'canResolve'  => in_array($this->userRole(), [ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER], true),
        ]);
    }

    // =========================================================
    // GET /warehouse/movements  â€” Stock Movement History
    // =========================================================
    public function movements(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER, ROLE_GENERAL_MANAGER);

        $warehouseId = $this->resolveWarehouseId();
        $db          = Database::getInstance();

        $filters = [
            'product_id'    => (int)$this->query('product_id',    0) ?: null,
            'movement_type' => $this->query('movement_type', ''),
            'date_from'     => $this->query('date_from',     ''),
            'date_to'       => $this->query('date_to',       ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $smlModel = new StockMovementModel();
        $result   = $smlModel->paginateByLocation($warehouseId, 'warehouse', $page, PAGINATION_DEFAULT, array_filter($filters));

        $products  = $db->fetchAll(
            "SELECT DISTINCT p.id, p.name
             FROM stock_movement_log sml
             JOIN products p ON p.id = sml.product_id
             WHERE sml.location_type = 'warehouse' AND sml.location_id = :wid
             ORDER BY p.name ASC",
            ['wid' => $warehouseId]
        );
        $warehouse = $db->fetchOne("SELECT id, name FROM warehouses WHERE id = :id", ['id' => $warehouseId]);

        $this->render('warehouse.movements', [
            'pageTitle'    => 'Stock Movement History',
            'warehouse'    => $warehouse,
            'warehouseId'  => $warehouseId,
            'movements'    => $result['data'],
            'pagination'   => $result,
            'filters'      => $filters,
            'products'     => $products,
            'userRole'     => $this->userRole(),
        ]);
    }
}
