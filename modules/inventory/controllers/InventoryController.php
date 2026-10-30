<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) â€” Phase 11
 * =========================================================
 * File    : modules/inventory/controllers/InventoryController.php
 * Purpose : Inventory Overview, Stock Movements (cross-location),
 *           Stock Adjustments, Batch Inventory.
 *
 * Routes  :
 *   GET  /inventory                    â†’ overview()
 *   GET  /inventory/movements          â†’ movements()
 *   GET  /inventory/batches            â†’ batches()
 *   GET  /inventory/adjustments        â†’ adjustments()
 *   GET  /inventory/adjustments/create â†’ adjustForm()
 *   POST /inventory/adjustments        â†’ adjust()
 *   GET  /inventory/adjustments/{id}   â†’ adjustShow()
 *   POST /inventory/adjustments/{id}/approve â†’ adjustApprove()  [GM only]
 *   POST /inventory/adjustments/{id}/reject  â†’ adjustReject()   [GM only]
 *   GET  /api/stock/{product_id}       â†’ checkStock() (AJAX)
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Inventory\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Inventory\Models\InventoryModel;
use AAM\Inventory\Models\StockAdjustmentModel;
use AAM\Inventory\Models\InventoryBatchModel;
use AAM\Inventory\Services\InventoryService;
use AAM\Warehouse\Models\StockMovementModel;
use AAM\Warehouse\Models\WarehouseInventoryModel;

class InventoryController extends BaseController
{
    // â”€â”€ Location resolution â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    private function resolveLocation(): array
    {
        $role  = $this->userRole();
        $admin = in_array($role, [ROLE_GENERAL_MANAGER], true);

        if ($admin) {
            $ltype = $this->query('lt', 'warehouse');
            $lid   = (int)$this->query('lid', 1);
            return [$ltype, $lid > 0 ? $lid : 1, $admin];
        }

        $lid   = (int)SessionManager::get(SESSION_LOCATION_ID, 0);
        $ltype = SessionManager::get(SESSION_LOCATION_TYPE, 'warehouse');

        if ($lid > 0) return [$ltype, $lid, false];

        // Fallback from DB
        $db  = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT location_type, location_id FROM user_location_assignments
             WHERE user_id=:uid ORDER BY assigned_at DESC LIMIT 1",
            ['uid' => $this->userId()]
        );
        return $row
            ? [(string)$row['location_type'], (int)$row['location_id'], false]
            : ['warehouse', 1, false];
    }

    // =========================================================
    // GET /inventory â€” Overview Dashboard
    // =========================================================
    public function overview(): void
    {
        $this->requireAuth();
        // GM has a dedicated /gm/inventory overview â€” not this WM operational view.
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);

        [$locationType, $locationId, $isAdmin] = $this->resolveLocation();
        $db       = Database::getInstance();
        $invModel = new InventoryModel();

        $kpis      = $isAdmin
            ? $invModel->getOrganizationKpis()
            : ($locationType === 'warehouse'
                ? $invModel->getWarehouseKpis($locationId)
                : $invModel->getShopKpis($locationId));

        $locationName = $this->getLocationName($locationType, $locationId);

        // Recent adjustments
        $adjModel       = new StockAdjustmentModel();
        $recentAdj      = $adjModel->paginateLocation($locationType, $locationId, 1, 5)['data'];

        // Expiring soon (warehouse only)
        $expiringSoon = [];
        if ($locationType === 'warehouse' || $isAdmin) {
            $whInv = new WarehouseInventoryModel();
            $expiringSoon = $whInv->getExpiringSoon($locationId, 7);
        }

        // Low stock
        $lowStock = [];
        if ($locationType === 'warehouse') {
            $whInv     = new WarehouseInventoryModel();
            $lowStock  = $whInv->getLowStock($locationId);
        }

        $warehouses = $isAdmin ? $db->fetchAll("SELECT id, name FROM warehouses WHERE is_active=1 ORDER BY name") : [];
        $shops      = $isAdmin ? $db->fetchAll("SELECT id, name FROM shops WHERE is_active=1 ORDER BY name")      : [];

        // Inventory snapshot (top 10 items sorted by priority: expired/low/normal)
        $invSnapshot = [];
        if ($locationType === 'warehouse') {
            $whInv = new WarehouseInventoryModel();
            $invSnapshot = $whInv->paginateWarehouse($locationId, 1, 10)['data'];
        } elseif ($locationType === 'shop') {
            $invSnapshot = $db->fetchAll(
                "SELECT si.*,
                        p.name AS product_name, p.sku AS product_sku, p.is_perishable,
                        c.name AS category_name, u.abbreviation AS unit_abbr
                 FROM shop_inventory si
                 JOIN products p ON p.id=si.product_id
                 LEFT JOIN categories c ON c.id=p.category_id
                 LEFT JOIN units_of_measure u ON u.id=si.unit_id
                 WHERE si.shop_id=:sid
                 ORDER BY si.available_quantity ASC
                 LIMIT 10",
                ['sid' => $locationId]
            );
        }

        $this->render('inventory.overview', [
            'pageTitle'     => 'Inventory Overview',
            'kpis'          => $kpis,
            'locationType'  => $locationType,
            'locationId'    => $locationId,
            'locationName'  => $locationName,
            'isAdmin'       => $isAdmin,
            'expiringSoon'  => $expiringSoon,
            'lowStock'      => $lowStock,
            'recentAdj'     => $recentAdj,
            'invSnapshot'   => $invSnapshot,
            'warehouses'    => $warehouses,
            'shops'         => $shops,
            'userRole'      => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /inventory/movements â€” Cross-location movements
    // =========================================================
    public function movements(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);

        [$locationType, $locationId] = $this->resolveLocation();
        $db = Database::getInstance();

        $filters = [
            'product_id'    => (int)$this->query('product_id', 0) ?: null,
            'movement_type' => $this->query('movement_type', ''),
            'date_from'     => $this->query('date_from', ''),
            'date_to'       => $this->query('date_to', ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $smlModel = new StockMovementModel();
        $result   = $smlModel->paginateByLocation(
            $locationId, $locationType, $page, PAGINATION_DEFAULT, array_filter($filters)
        );

        $products = $db->fetchAll(
            "SELECT DISTINCT p.id, p.name
             FROM stock_movement_log sml
             JOIN products p ON p.id = sml.product_id
             WHERE sml.location_type=:lt AND sml.location_id=:lid
             ORDER BY p.name",
            ['lt' => $locationType, 'lid' => $locationId]
        );

        $this->render('inventory.movements', [
            'pageTitle'    => 'Stock Movements',
            'movements'    => $result['data'],
            'pagination'   => $result,
            'filters'      => $filters,
            'products'     => $products,
            'locationType' => $locationType,
            'locationId'   => $locationId,
            'locationName' => $this->getLocationName($locationType, $locationId),
            'userRole'     => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /inventory/batches
    // =========================================================
    public function batches(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);

        [$locationType, $locationId] = $this->resolveLocation();

        $filters = [
            'search' => $this->query('search', ''),
            'status' => $this->query('status', ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $batchModel = new InventoryBatchModel();
        $result     = $batchModel->paginateLocation($locationType, $locationId, $page, PAGINATION_DEFAULT, $filters);

        $this->render('inventory.batches', [
            'pageTitle'    => 'Batch Inventory',
            'batches'      => $result['data'],
            'pagination'   => $result,
            'filters'      => $filters,
            'locationType' => $locationType,
            'locationId'   => $locationId,
            'locationName' => $this->getLocationName($locationType, $locationId),
            'userRole'     => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /inventory/adjustments â€” Adjustment List
    // =========================================================
    public function adjustments(): void
    {
        $this->requireAuth();
        // GM views adjustments via /gm/adjustments â€” not this WM operational list.
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);

        [$locationType, $locationId] = $this->resolveLocation();

        $filters = [
            'status'    => $this->query('status', ''),
            'search'    => $this->query('search', ''),
            'date_from' => $this->query('date_from', ''),
            'date_to'   => $this->query('date_to', ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $adjModel = new StockAdjustmentModel();
        $result   = $adjModel->paginateLocation($locationType, $locationId, $page, PAGINATION_DEFAULT, $filters);

        $this->render('inventory.adjustments', [
            'pageTitle'    => 'Stock Adjustments',
            'adjustments'  => $result['data'],
            'pagination'   => $result,
            'filters'      => $filters,
            'locationType' => $locationType,
            'locationId'   => $locationId,
            'locationName' => $this->getLocationName($locationType, $locationId),
            'userRole'     => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /inventory/adjustments/create
    // =========================================================
    // GET /inventory/adjustments/create
    // =========================================================
    public function adjustForm(): void
    {
        $this->requireAuth();
        // Only Warehouse Manager can CREATE adjustments.
        // General Manager only approves/rejects â€” never creates.
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);

        [$locationType, $locationId] = $this->resolveLocation();
        $db = Database::getInstance();

        $products = $locationType === 'warehouse'
            ? $db->fetchAll(
                "SELECT wi.product_id, wi.current_quantity, wi.available_quantity,
                        wi.batch_number, wi.unit_id, p.name AS product_name, p.sku,
                        u.abbreviation AS unit_abbr
                 FROM warehouse_inventory wi
                 JOIN products p ON p.id=wi.product_id
                 LEFT JOIN units_of_measure u ON u.id=wi.unit_id
                 WHERE wi.warehouse_id=:lid ORDER BY p.name",
                ['lid' => $locationId]
              )
            : $db->fetchAll(
                "SELECT si.product_id, si.current_quantity, si.available_quantity,
                        si.batch_number, si.unit_id, p.name AS product_name, p.sku,
                        u.abbreviation AS unit_abbr
                 FROM shop_inventory si
                 JOIN products p ON p.id=si.product_id
                 LEFT JOIN units_of_measure u ON u.id=si.unit_id
                 WHERE si.shop_id=:lid ORDER BY p.name",
                ['lid' => $locationId]
              );

        $this->render('inventory.adjustment_form', [
            'pageTitle'    => 'New Stock Adjustment',
            'products'     => $products,
            'reasons'      => StockAdjustmentModel::REASONS,
            'locationType' => $locationType,
            'locationId'   => $locationId,
            'locationName' => $this->getLocationName($locationType, $locationId),
            'userRole'     => $this->userRole(),
        ]);
    }

    // =========================================================
    // POST /inventory/adjustments â€” Submit Adjustment
    // =========================================================
    public function adjust(): void
    {
        $this->requireAuth();
        // Only Warehouse Manager can SUBMIT adjustments.
        // General Manager only approves/rejects â€” never submits.
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);
        $this->verifyCsrf();

        [$locationType, $locationId] = $this->resolveLocation();

        $productId       = (int)$this->input('product_id', 0);
        $physicalQty     = (float)$this->input('physical_quantity', -1);
        $reason          = trim($this->input('reason', ''));
        $notes           = trim($this->input('notes', ''));
        $batchNumber     = trim($this->input('batch_number', '')) ?: null;
        $unitId          = (int)$this->input('unit_id', 0) ?: null;

        if ($productId <= 0 || $physicalQty < 0 || empty($reason)) {
            $this->flash(FLASH_ERROR, 'Please fill in all required fields.');
            $this->redirect('/inventory/adjustments/create');
            return;
        }

        try {
            $service = new InventoryService();
            $adjId   = $service->requestAdjustment(
                $locationType, $locationId, $productId,
                $physicalQty, $reason, $notes, $batchNumber, $unitId
            );
            $this->flash(FLASH_SUCCESS, 'Adjustment request submitted successfully.');
            $this->redirect("/inventory/adjustments/{$adjId}");
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
            $this->redirect('/inventory/adjustments/create');
        }
    }

    // =========================================================
    // GET /inventory/adjustments/{id}
    // =========================================================
    public function adjustShow(string $id): void
    {
        $this->requireAuth();
        // WM can view their own submitted adjustments.
        // GM views adjustments via /gm/adjustments/{id} instead.
        $this->requireRole(ROLE_WAREHOUSE_MANAGER);

        $adjModel = new StockAdjustmentModel();
        $adj      = $adjModel->getWithDetails((int)$id);

        if (!$adj) {
            $this->flash(FLASH_ERROR, 'Adjustment not found.');
            $this->redirect('/inventory/adjustments');
            return;
        }

        // Only GM can approve/reject. WM can view but NOT approve their own.
        $canApprove = $this->userRole() === ROLE_GENERAL_MANAGER;

        $this->render('inventory.adjustment_show', [
            'pageTitle'  => 'Adjustment â€” ' . $adj['adjustment_number'],
            'adj'        => $adj,
            'canApprove' => $canApprove,
            'userRole'   => $this->userRole(),
        ]);
    }

    // =========================================================
    // POST /inventory/adjustments/{id}/approve
    // =========================================================
    public function adjustApprove(string $id): void
    {
        $this->requireAuth();
        // Only General Manager can approve adjustments.
        // Warehouse Manager must NOT approve their own requests.
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $adjModel = new StockAdjustmentModel();
        $adj      = $adjModel->find((int)$id);
        if (!$adj) {
            $this->flash(FLASH_ERROR, 'Adjustment not found.');
            $this->redirect('/inventory/adjustments');
            return;
        }
        // Self-approval prevention (belt-and-suspenders)
        if ((int)($adj['requested_by'] ?? 0) === (int)$this->userId()) {
            $this->flash(FLASH_ERROR, 'You cannot approve an adjustment you submitted.');
            $this->redirect("/inventory/adjustments/{$id}");
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

        $this->redirect("/inventory/adjustments/{$id}");
    }

    // =========================================================
    // POST /inventory/adjustments/{id}/reject
    // =========================================================
    public function adjustReject(string $id): void
    {
        $this->requireAuth();
        // Only General Manager can reject adjustments.
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

        $this->redirect("/inventory/adjustments/{$id}");
    }

    // =========================================================
    // GET /api/stock/{product_id} â€” AJAX POS stock check
    // =========================================================
    public function checkStock(string $product_id): void
    {
        $this->requireAuth();

        $qty    = 0;
        $shopId = 0;

        try {
            $shopId = (int)SessionManager::get(SESSION_LOCATION_ID, 0);
            if ($shopId > 0) {
                $row = Database::getInstance()->fetchOne(
                    "SELECT available_quantity FROM shop_inventory
                     WHERE product_id=:pid AND shop_id=:sid LIMIT 1",
                    ['pid' => (int)$product_id, 'sid' => $shopId]
                );
                $qty = $row ? (float)($row['available_quantity'] ?? 0) : 0;
            }
        } catch (\Throwable $e) {
            // Table may not exist yet â€” return 0 safely
        }

        $this->jsonSuccess([
            'quantity'   => $qty,
            'product_id' => (int)$product_id,
            'shop_id'    => $shopId,
        ]);
    }

    // =========================================================
    // GET /api/inventory/search â€” Enterprise inventory search
    // Query params: q, lt, lid, category_id, status, batch
    // =========================================================
    public function search(): void
    {
        $this->requireAuth();

        $q          = trim($this->query('q', ''));
        $locType    = $this->query('lt', '');
        $locId      = (int)$this->query('lid', 0);
        $categoryId = (int)$this->query('category_id', 0);
        $status     = $this->query('status', '');
        $batch      = $this->query('batch', '');
        $limit      = min(50, max(1, (int)$this->query('limit', 20)));

        if (strlen($q) < 1 && empty($batch)) {
            $this->jsonSuccess(['results' => []]);
            return;
        }

        $db     = Database::getInstance();
        $where  = ['1=1'];
        $params = [];

        if ($q !== '') {
            $where[]       = "(p.name LIKE :q OR p.sku LIKE :q2 OR p.barcode LIKE :q3)";
            $params['q']   = "%{$q}%";
            $params['q2']  = "%{$q}%";
            $params['q3']  = "%{$q}%";
        }
        if ($batch !== '') {
            $where[]       = "wi.batch_number LIKE :bn";
            $params['bn']  = "%{$batch}%";
        }
        if ($categoryId > 0) {
            $where[]       = "p.category_id = :cat";
            $params['cat'] = $categoryId;
        }
        if ($locId > 0 && $locType !== '') {
            $where[]       = "wi.warehouse_id = :lid";
            $params['lid'] = $locId;
        }
        if ($status !== '') {
            switch ($status) {
                case 'in_stock':      $where[] = 'wi.available_quantity > 0'; break;
                case 'out_of_stock':  $where[] = 'wi.available_quantity <= 0'; break;
                case 'low_stock':     $where[] = 'wi.min_stock_level > 0 AND wi.available_quantity <= wi.min_stock_level'; break;
                case 'expiring_soon': $where[] = 'wi.expiry_date IS NOT NULL AND wi.expiry_date > CURDATE() AND wi.expiry_date <= DATE_ADD(CURDATE(),INTERVAL 7 DAY)'; break;
                case 'expired':       $where[] = 'wi.expiry_date IS NOT NULL AND wi.expiry_date < CURDATE()'; break;
            }
        }

        $w = implode(' AND ', $where);
        $params['lim'] = $limit;

        $rows = $db->fetchAll(
            "SELECT wi.product_id, wi.warehouse_id AS location_id,
                    'warehouse' AS location_type,
                    w.name AS location_name,
                    p.name AS product_name, p.sku, p.barcode,
                    c.name AS category_name,
                    u.abbreviation AS unit_abbr,
                    wi.batch_number, wi.available_quantity,
                    wi.current_quantity, wi.expiry_date
             FROM warehouse_inventory wi
             JOIN products p ON p.id = wi.product_id
             LEFT JOIN warehouses w ON w.id = wi.warehouse_id
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN units_of_measure u ON u.id = wi.unit_id
             WHERE {$w}
             ORDER BY p.name ASC
             LIMIT :lim",
            $params
        );

        $this->jsonSuccess(['results' => $rows, 'count' => count($rows)]);
    }

    // =========================================================
    // GET /api/inventory/barcode/{barcode} â€” Barcode lookup
    // =========================================================
    public function barcodeSearch(string $barcode): void
    {
        $this->requireAuth();

        $db  = Database::getInstance();
        $barcode = trim($barcode);

        // Find product by barcode
        $product = $db->fetchOne(
            "SELECT p.id, p.name, p.sku, p.barcode, p.unit_id,
                    u.abbreviation AS unit_abbr, c.name AS category_name
             FROM products p
             LEFT JOIN units_of_measure u ON u.id = p.unit_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.barcode = :bc AND p.is_active = 1
             LIMIT 1",
            ['bc' => $barcode]
        );

        if (!$product) {
            $this->jsonError("No product found for barcode: {$barcode}", 404);
            return;
        }

        // Get all location stock for this product
        $warehouseStock = $db->fetchAll(
            "SELECT wi.warehouse_id AS location_id, w.name AS location_name,
                    'warehouse' AS location_type,
                    wi.current_quantity, wi.available_quantity,
                    wi.reserved_quantity, wi.batch_number, wi.expiry_date,
                    u.abbreviation AS unit_abbr
             FROM warehouse_inventory wi
             JOIN warehouses w ON w.id = wi.warehouse_id
             LEFT JOIN units_of_measure u ON u.id = wi.unit_id
             WHERE wi.product_id = :pid AND wi.current_quantity > 0
             ORDER BY w.name",
            ['pid' => $product['id']]
        );

        $shopStock = $db->fetchAll(
            "SELECT si.shop_id AS location_id, s.name AS location_name,
                    'shop' AS location_type,
                    si.current_quantity, si.available_quantity,
                    si.reserved_quantity, NULL AS batch_number, NULL AS expiry_date,
                    u.abbreviation AS unit_abbr
             FROM shop_inventory si
             JOIN shops s ON s.id = si.shop_id
             LEFT JOIN units_of_measure u ON u.id = si.unit_id
             WHERE si.product_id = :pid AND si.current_quantity > 0
             ORDER BY s.name",
            ['pid' => $product['id']]
        );

        $this->jsonSuccess([
            'product'         => $product,
            'warehouse_stock' => $warehouseStock,
            'shop_stock'      => $shopStock,
            'total_locations' => count($warehouseStock) + count($shopStock),
        ]);
    }

    // =========================================================
    // GET /api/inventory/stock-check â€” Validate available stock
    // Params: product_id, location_type, location_id, quantity
    // Used before creating a transfer to check availability
    // =========================================================
    public function stockCheck(): void
    {
        $this->requireAuth();

        $productId    = (int)$this->query('product_id', 0);
        $locationType = $this->query('location_type', 'warehouse');
        $locationId   = (int)$this->query('location_id', 0);
        $requested    = (float)$this->query('quantity', 0);

        if ($productId <= 0 || $locationId <= 0) {
            $this->jsonError('product_id and location_id are required.', 400);
            return;
        }

        $db  = Database::getInstance();
        $row = $locationType === 'warehouse'
            ? $db->fetchOne(
                "SELECT current_quantity, available_quantity, reserved_quantity,
                        batch_number, expiry_date
                 FROM warehouse_inventory
                 WHERE warehouse_id=:lid AND product_id=:pid",
                ['lid' => $locationId, 'pid' => $productId]
              )
            : $db->fetchOne(
                "SELECT current_quantity, available_quantity, reserved_quantity,
                        NULL AS batch_number, NULL AS expiry_date
                 FROM shop_inventory
                 WHERE shop_id=:lid AND product_id=:pid",
                ['lid' => $locationId, 'pid' => $productId]
              );

        $available = (float)($row['available_quantity'] ?? 0);
        $sufficient = $requested <= 0 || $available >= $requested;

        $this->jsonSuccess([
            'product_id'        => $productId,
            'location_type'     => $locationType,
            'location_id'       => $locationId,
            'available_quantity'=> $available,
            'current_quantity'  => (float)($row['current_quantity']  ?? 0),
            'reserved_quantity' => (float)($row['reserved_quantity'] ?? 0),
            'requested'         => $requested,
            'sufficient'        => $sufficient,
            'message'           => $sufficient
                ? 'Stock available.'
                : "Insufficient stock. Available: {$available}, Requested: {$requested}.",
        ]);
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================
    private function getLocationName(string $locationType, int $locationId): string
    {
        $db    = Database::getInstance();
        $table = $locationType === 'warehouse' ? 'warehouses' : 'shops';
        $row   = $db->fetchOne("SELECT name FROM {$table} WHERE id=:id", ['id' => $locationId]);
        return $row['name'] ?? ucfirst($locationType) . " #{$locationId}";
    }
}

