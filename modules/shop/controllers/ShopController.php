<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) â€” Phase 11
 * =========================================================
 * File    : modules/shop/controllers/ShopController.php
 * Purpose : Retail shop operations â€” shop list, detail,
 *           shop inventory view, sales history.
 * Routes  :
 *   GET /shops               â†’ index()
 *   GET /shops/{id}          â†’ show()
 *   GET /shops/{id}/inventoryâ†’ inventory()
 *   GET /shops/{id}/sales    â†’ sales()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Shop\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;

class ShopController extends BaseController
{
    // â”€â”€ Resolve shop access â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    private function resolveShopId(string $requestedId = '0'): int
    {
        $role      = $this->userRole();
        $requested = (int)$requestedId;

        // Admin / GM can see any shop
        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            return $requested > 0 ? $requested : 1;
        }

        // Salesperson: must match their assigned shop
        $assigned = (int)SessionManager::get(SESSION_LOCATION_ID, 0);
        $ltype    = SessionManager::get(SESSION_LOCATION_TYPE, '');

        if ($ltype === 'shop' && $assigned > 0) {
            // They can only view their own shop
            return ($requested > 0 && $requested === $assigned) ? $assigned : $assigned;
        }

        // Fallback from DB
        $db  = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT location_id FROM user_location_assignments
             WHERE user_id = :uid AND location_type = 'shop'
             ORDER BY assigned_at DESC LIMIT 1",
            ['uid' => $this->userId()]
        );
        if ($row) {
            return (int)$row['location_id'];
        }

        // Non-admin with no shop assignment â€” return 0 (caller must handle)
        // NEVER fall back to an arbitrary shop_id for restricted roles.
        if (in_array($this->userRole(), [ROLE_SALESPERSON], true)) {
            return 0;
        }

        return $requested > 0 ? $requested : 1;
    }

    // =========================================================
    // GET /shops â€” Shop List
    // =========================================================
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(
            ROLE_GENERAL_MANAGER,
            ROLE_SALESPERSON
        );

        $db   = Database::getInstance();
        $role = $this->userRole();

        // Shared subquery for shop KPIs including today's sales
        $shopKpiSql = "SELECT s.*,
                        (SELECT COUNT(*) FROM shop_inventory si WHERE si.shop_id = s.id)
                            AS product_count,
                        (SELECT COALESCE(SUM(si.current_quantity),0)
                         FROM shop_inventory si WHERE si.shop_id = s.id)
                            AS total_stock,
                        (SELECT COALESCE(SUM(si.available_quantity * COALESCE(p2.unit_price,0)),0)
                         FROM shop_inventory si JOIN products p2 ON p2.id=si.product_id
                         WHERE si.shop_id = s.id)
                            AS stock_value,
                        (SELECT COUNT(*) FROM shop_inventory si WHERE si.shop_id = s.id
                         AND si.available_quantity <= 0)
                            AS out_of_stock,
                        (SELECT COALESCE(SUM(sa.total_amount),0) FROM sales sa
                         WHERE sa.shop_id = s.id AND sa.sale_date = CURDATE()
                         AND sa.status = 'completed')
                            AS today_sales,
                        (SELECT COUNT(*) FROM sales sa
                         WHERE sa.shop_id = s.id AND sa.sale_date = CURDATE()
                         AND sa.status = 'completed')
                            AS today_transactions,
                        (SELECT COUNT(DISTINCT ula.user_id)
                         FROM user_location_assignments ula
                         JOIN users u2 ON u2.id = ula.user_id AND u2.is_active = 1
                         WHERE ula.location_type = 'shop' AND ula.location_id = s.id)
                            AS sellers_count";

        // Admin / GM see all shops with KPIs
        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            $shops = $db->fetchAll(
                $shopKpiSql . " FROM shops s WHERE s.is_active = 1 ORDER BY s.name ASC"
            );
        } else {
            // Salesperson: just their shop
            $shopId = $this->resolveShopId();
            $shops  = $db->fetchAll(
                $shopKpiSql . " FROM shops s WHERE s.id = :sid AND s.is_active = 1",
                ['sid' => $shopId]
            );
        }

        $this->render('shop.index', [
            'pageTitle' => 'Retail Shops',
            'shops'     => $shops,
            'userRole'  => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /shops/{id} â€” Shop Detail
    // =========================================================
    public function show(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(
            ROLE_GENERAL_MANAGER,
            ROLE_SALESPERSON
        );

        $shopId = $this->resolveShopId($id);
        $db     = Database::getInstance();

        $shop = $db->fetchOne(
            "SELECT * FROM shops WHERE id = :id AND is_active = 1",
            ['id' => $shopId]
        );

        if (!$shop) {
            $this->flash(FLASH_ERROR, 'Shop not found.');
            $this->redirect('/shops');
            return;
        }

        // KPIs â€” include today's sales
        $kpis = $db->fetchOne(
            "SELECT
                COUNT(*)                                               AS total_products,
                COALESCE(SUM(current_quantity),0)                     AS total_stock,
                COALESCE(SUM(available_quantity),0)                   AS available_stock,
                SUM(CASE WHEN available_quantity<=0 THEN 1 ELSE 0 END) AS out_of_stock,
                SUM(CASE WHEN min_stock_level>0 AND available_quantity>0
                          AND available_quantity<=min_stock_level THEN 1 ELSE 0 END) AS low_stock
             FROM shop_inventory WHERE shop_id = :sid",
            ['sid' => $shopId]
        ) ?? [];

        // Today's sales
        $todaySales = $db->fetchOne(
            "SELECT
                COALESCE(SUM(total_amount),0) AS total_sales,
                COUNT(*)                      AS total_transactions
             FROM sales
             WHERE shop_id=:sid AND sale_date=CURDATE() AND status='completed'",
            ['sid' => $shopId]
        ) ?? [];

        // Recent transfers to this shop
        $recentTransfers = $db->fetchAll(
            "SELECT wt.*, w.name AS warehouse_name
             FROM warehouse_transfers wt
             JOIN warehouses w ON w.id = wt.warehouse_id
             WHERE wt.shop_id = :sid
             ORDER BY wt.transfer_date DESC
             LIMIT 5",
            ['sid' => $shopId]
        );

        $this->render('shop.show', [
            'pageTitle'       => e($shop['name']),
            'shop'            => $shop,
            'kpis'            => $kpis,
            'todaySales'      => $todaySales,
            'recentTransfers' => $recentTransfers,
            'userRole'        => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /shops/{id}/inventory â€” Shop Inventory
    // =========================================================
    public function inventory(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(
            ROLE_GENERAL_MANAGER,
            ROLE_SALESPERSON,
            ROLE_WAREHOUSE_MANAGER
        );

        $shopId = $this->resolveShopId($id);
        $db     = Database::getInstance();

        $shop = $db->fetchOne(
            "SELECT id, name, location FROM shops WHERE id = :id",
            ['id' => $shopId]
        );

        if (!$shop) {
            $this->flash(FLASH_ERROR, 'Shop not found.');
            $this->redirect('/shops');
            return;
        }

        $filters = [
            'search'      => $this->query('search',      ''),
            'category_id' => $this->query('category_id', ''),
            'status'      => $this->query('status',      ''),
        ];
        $page    = max(1, (int)$this->query('page', 1));
        $perPage = PAGINATION_DEFAULT;
        $offset  = ($page - 1) * $perPage;

        $where  = ['si.shop_id = :sid'];
        $params = ['sid' => $shopId];

        if (!empty($filters['search'])) {
            $where[]      = "(p.name LIKE :q OR p.sku LIKE :q2 OR si.batch_number LIKE :q3)";
            $params['q']  = '%' . $filters['search'] . '%';
            $params['q2'] = '%' . $filters['search'] . '%';
            $params['q3'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $where[]       = 'p.category_id = :cat';
            $params['cat'] = (int)$filters['category_id'];
        }
        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'in_stock':    $where[] = 'si.available_quantity > 0'; break;
                case 'out_of_stock':$where[] = 'si.available_quantity <= 0'; break;
                case 'low_stock':   $where[] = 'si.min_stock_level > 0 AND si.available_quantity > 0 AND si.available_quantity <= si.min_stock_level'; break;
            }
        }

        $w = implode(' AND ', $where);

        $total = (int)($db->fetchOne(
            "SELECT COUNT(*) AS c
             FROM shop_inventory si
             JOIN products p ON p.id = si.product_id
             WHERE {$w}",
            $params
        )['c'] ?? 0);

        $params['lim'] = $perPage;
        $params['off'] = $offset;

        $inventory = $db->fetchAll(
            "SELECT si.*,
                    p.name          AS product_name,
                    p.sku           AS product_sku,
                    p.is_perishable,
                    c.name          AS category_name,
                    u.abbreviation  AS unit_abbr
             FROM shop_inventory si
             JOIN products p ON p.id = si.product_id
             LEFT JOIN categories c       ON c.id = p.category_id
             LEFT JOIN units_of_measure u ON u.id = si.unit_id
             WHERE {$w}
             ORDER BY
               CASE WHEN si.available_quantity<=0 THEN 0
                    WHEN si.min_stock_level>0 AND si.available_quantity<=si.min_stock_level THEN 1
                    ELSE 2 END ASC,
               p.name ASC
             LIMIT :lim OFFSET :off",
            $params
        );

        $categories = $db->fetchAll(
            "SELECT DISTINCT c.id, c.name
             FROM shop_inventory si
             JOIN products p ON p.id = si.product_id
             JOIN categories c ON c.id = p.category_id
             WHERE si.shop_id = :sid
             ORDER BY c.name",
            ['sid' => $shopId]
        );

        $totalPages = (int)ceil($total / max(1, $perPage));
        $pagination = [
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
            'from'       => $total > 0 ? $offset + 1 : 0,
            'to'         => min($offset + $perPage, $total),
        ];

        $this->render('shop.inventory', [
            'pageTitle'  => e($shop['name']) . ' â€” Inventory',
            'shop'       => $shop,
            'shopId'     => $shopId,
            'inventory'  => $inventory,
            'pagination' => $pagination,
            'filters'    => $filters,
            'categories' => $categories,
            'userRole'   => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /shop/discrepancies â€” Shop-scoped discrepancy list
    // =========================================================
    public function discrepancies(): void
    {
        $this->requireAuth();
        $this->requireRole(
            ROLE_SALESPERSON, ROLE_GENERAL_MANAGER
        );

        $shopId = $this->resolveShopId();
        $db     = Database::getInstance();

        $shop = $shopId > 0
            ? $db->fetchOne("SELECT id, name FROM shops WHERE id=:id", ['id' => $shopId])
            : null;

        $filters = [
            'status'    => $this->query('status',    ''),
            'search'    => $this->query('search',    ''),
            'date_from' => $this->query('date_from', ''),
            'date_to'   => $this->query('date_to',   ''),
        ];
        $page    = max(1, (int)$this->query('page', 1));
        $perPage = PAGINATION_DEFAULT;
        $offset  = ($page - 1) * $perPage;

        $where  = ['wd.shop_id = :sid'];
        $params = ['sid' => $shopId];

        if (!empty($filters['status'])) {
            $where[]          = 'wd.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]      = "(wd.discrepancy_number LIKE :q OR p.name LIKE :q2 OR wt.transfer_number LIKE :q3)";
            $params['q']  = '%' . $filters['search'] . '%';
            $params['q2'] = '%' . $filters['search'] . '%';
            $params['q3'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[]         = 'wd.reported_at >= :dfrom';
            $params['dfrom'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]       = 'wd.reported_at <= :dto';
            $params['dto'] = $filters['date_to'] . ' 23:59:59';
        }

        $w = implode(' AND ', $where);

        $total = (int)($db->fetchOne(
            "SELECT COUNT(*) AS c FROM warehouse_discrepancies wd
             LEFT JOIN products p ON p.id=wd.product_id
             LEFT JOIN warehouse_transfers wt ON wt.id=wd.reference_id AND wd.reference_type='transfer'
             WHERE {$w}", $params
        )['c'] ?? 0);

        $params['lim'] = $perPage;
        $params['off'] = $offset;

        $discrepancies = $db->fetchAll(
            "SELECT wd.*,
                    p.name AS product_name, p.sku AS product_sku,
                    w.name AS warehouse_name,
                    wt.transfer_number,
                    wt.status AS transfer_status,
                    wt.discrepancy_final_rejected,
                    CONCAT(rp.first_name,' ',rp.last_name) AS reported_by_name
             FROM warehouse_discrepancies wd
             LEFT JOIN products p ON p.id=wd.product_id
             LEFT JOIN warehouses w ON w.id=wd.warehouse_id
             LEFT JOIN warehouse_transfers wt ON wt.id=wd.reference_id AND wd.reference_type='transfer'
             LEFT JOIN users rp ON rp.id=wd.reported_by
             WHERE {$w}
             ORDER BY wd.reported_at DESC
             LIMIT :lim OFFSET :off",
            $params
        );

        $totalPages = (int)ceil($total / max(1, $perPage));
        $pagination = [
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
            'from'       => $total > 0 ? $offset + 1 : 0,
            'to'         => min($offset + $perPage, $total),
        ];

        $this->render('shop.discrepancies', [
            'pageTitle'     => 'Discrepancy Reports â€” ' . ($shop['name'] ?? 'Shop'),
            'shop'          => $shop,
            'shopId'        => $shopId,
            'discrepancies' => $discrepancies,
            'pagination'    => $pagination,
            'filters'       => $filters,
            'userRole'      => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /shop/discrepancies/{id} â€” Single discrepancy detail
    // =========================================================
    public function discrepancyShow(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);

        $db  = Database::getInstance();
        $dis = $db->fetchOne(
            "SELECT wd.*,
                    p.name AS product_name, p.sku AS product_sku,
                    w.name AS warehouse_name,
                    s.name AS shop_name,
                    wt.transfer_number,
                    CONCAT(rp.first_name,' ',rp.last_name) AS reported_by_name,
                    CONCAT(rv.first_name,' ',rv.last_name) AS resolved_by_name
             FROM warehouse_discrepancies wd
             LEFT JOIN products p ON p.id=wd.product_id
             LEFT JOIN warehouses w ON w.id=wd.warehouse_id
             LEFT JOIN shops s ON s.id=wd.shop_id
             LEFT JOIN warehouse_transfers wt ON wt.id=wd.reference_id AND wd.reference_type='transfer'
             LEFT JOIN users rp ON rp.id=wd.reported_by
             LEFT JOIN users rv ON rv.id=wd.resolved_by
             WHERE wd.id=:id",
            ['id' => (int)$id]
        );

        if (!$dis) {
            $this->flash(FLASH_ERROR, 'Discrepancy not found.');
            $this->redirect('/shop/discrepancies');
            return;
        }

        $canResolve = in_array($this->userRole(), [ROLE_GENERAL_MANAGER], true);

        $this->render('shop.discrepancy_show', [
            'pageTitle'  => 'Discrepancy â€” ' . ($dis['discrepancy_number'] ?? ''),
            'dis'        => $dis,
            'canResolve' => $canResolve,
            'userRole'   => $this->userRole(),
        ]);
    }

    // =========================================================
    // POST /shop/discrepancies/{id}/status â€” Update status
    // =========================================================
    public function discrepancyStatus(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $status = trim($this->input('status', ''));
        $notes  = trim($this->input('notes',  ''));

        $allowed = ['open', 'under_review', 'resolved', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            $this->flash(FLASH_ERROR, 'Invalid status.');
            $this->redirect("/shop/discrepancies/{$id}");
            return;
        }

        try {
            $disModel = new \AAM\Warehouse\Models\DiscrepancyModel();
            $disModel->updateStatus((int)$id, $status, (int)$this->userId(), $notes ?: null);
            $this->flash(FLASH_SUCCESS, 'Discrepancy status updated.');
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        }
        $this->redirect("/shop/discrepancies/{$id}");
    }

    // =========================================================
    // GET /shop/stock-movements â€” Shop stock movement history
    // =========================================================
    public function stockMovements(): void
    {
        $this->requireAuth();
        $this->requireRole(
            ROLE_SALESPERSON, ROLE_GENERAL_MANAGER
        );

        $shopId = $this->resolveShopId();
        $db     = Database::getInstance();

        $shop = $shopId > 0
            ? $db->fetchOne("SELECT id, name FROM shops WHERE id=:id", ['id' => $shopId])
            : null;

        $filters = [
            'movement_type' => $this->query('movement_type', ''),
            'date_from'     => $this->query('date_from', ''),
            'date_to'       => $this->query('date_to',   ''),
            'search'        => $this->query('search',    ''),
        ];
        $page    = max(1, (int)$this->query('page', 1));
        $perPage = PAGINATION_DEFAULT;
        $offset  = ($page - 1) * $perPage;

        $where  = ["sml.location_type='shop'", 'sml.location_id=:sid'];
        $params = ['sid' => $shopId];

        if (!empty($filters['movement_type'])) {
            $where[]         = 'sml.movement_type=:mtype';
            $params['mtype'] = $filters['movement_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[]         = 'DATE(sml.moved_at)>=:dfrom';
            $params['dfrom'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]       = 'DATE(sml.moved_at)<=:dto';
            $params['dto'] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $where[]      = "(p.name LIKE :q OR sml.reference_number LIKE :q2)";
            $params['q']  = '%' . $filters['search'] . '%';
            $params['q2'] = '%' . $filters['search'] . '%';
        }

        $w = implode(' AND ', $where);

        $total = (int)($db->fetchOne(
            "SELECT COUNT(*) AS c FROM stock_movement_log sml
             JOIN products p ON p.id=sml.product_id WHERE {$w}", $params
        )['c'] ?? 0);

        $params['lim'] = $perPage;
        $params['off'] = $offset;

        $movements = $db->fetchAll(
            "SELECT sml.*, p.name AS product_name, p.sku AS product_sku,
                    CONCAT(u.first_name,' ',u.last_name) AS performed_by_name
             FROM stock_movement_log sml
             JOIN products p ON p.id=sml.product_id
             LEFT JOIN users u ON u.id=sml.performed_by
             WHERE {$w}
             ORDER BY sml.moved_at DESC
             LIMIT :lim OFFSET :off",
            $params
        );

        $totalPages = (int)ceil($total / max(1, $perPage));
        $pagination = [
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
            'from'       => $total > 0 ? $offset + 1 : 0,
            'to'         => min($offset + $perPage, $total),
        ];

        $this->render('shop.stock_movements', [
            'pageTitle'  => 'Stock Movements â€” ' . ($shop['name'] ?? 'Shop'),
            'shop'       => $shop,
            'shopId'     => $shopId,
            'movements'  => $movements,
            'pagination' => $pagination,
            'filters'    => $filters,
            'userRole'   => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /shops/{id}/sales â€” Shop Sales History (Phase 12)
    // =========================================================
    public function sales(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(
            ROLE_GENERAL_MANAGER,
            ROLE_SALESPERSON
        );

        $shopId = $this->resolveShopId($id);
        $db     = Database::getInstance();
        $shop   = $db->fetchOne(
            "SELECT id, name, location FROM shops WHERE id = :id AND is_active = 1",
            ['id' => $shopId]
        );

        if (!$shop) {
            $this->flash(FLASH_ERROR, 'Shop not found.');
            $this->redirect('/shops');
            return;
        }

        $filters = [
            'status'    => $this->query('status',    ''),
            'date_from' => $this->query('date_from', date('Y-m-d')),
            'date_to'   => $this->query('date_to',   date('Y-m-d')),
            'search'    => $this->query('search',    ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $salesModel  = new \AAM\POS\Models\SalesModel();
        $result      = $salesModel->paginateByShop($shopId, $page, PAGINATION_DEFAULT, $filters);
        $dailyTotals = $salesModel->getDailyTotals($shopId, date('Y-m-d'));

        $this->render('shop.sales', [
            'pageTitle'   => e($shop['name']) . ' â€” Sales',
            'shop'        => $shop,
            'sales'       => $result['data'],
            'pagination'  => $result,
            'filters'     => $filters,
            'dailyTotals' => $dailyTotals,
            'userRole'    => $this->userRole(),
        ]);
    }
}

