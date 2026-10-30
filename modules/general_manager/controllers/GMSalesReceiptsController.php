<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMSalesReceiptsController.php
 * Purpose : GM read-only unified receipts view.
 *           Covers POS/Salesperson sales AND Customer
 *           E-Commerce orders in one interface.
 *           GM cannot modify any record — observation only.
 *
 * Routes  :
 *   GET  /gm/receipts              → index()    (unified list)
 *   GET  /gm/receipts/pos/{id}     → showPOS()  (POS receipt detail + print)
 *   GET  /gm/receipts/order/{id}   → showOrder()(Customer receipt detail + print)
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\GeneralManager\Services\GMSalesReceiptsService;

class GMSalesReceiptsController extends BaseController
{
    private GMSalesReceiptsService $svc;

    public function __construct()
    {
        parent::__construct();
        $this->svc = new GMSalesReceiptsService();
    }

    // =========================================================
    // GET /gm/receipts — Unified receipts list
    // =========================================================
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $filters = [
            'channel'        => trim($this->query('channel',        '')),
            'shop_id'        => (int)$this->query('shop_id',         0),
            'status'         => trim($this->query('status',          '')),
            'payment_method' => trim($this->query('payment_method',  '')),
            'date_from'      => trim($this->query('date_from',       '')),
            'date_to'        => trim($this->query('date_to',         '')),
            'search'         => trim($this->query('search',          '')),
        ];

        // Normalise
        if (!in_array($filters['channel'], ['pos', 'ecommerce', 'seed', ''], true)) {
            $filters['channel'] = '';
        }
        if ($filters['shop_id'] <= 0) {
            unset($filters['shop_id']);
            $filters['shop_id'] = 0;
        }

        // Default to current month when no date is set
        if (empty($filters['date_from']) && empty($filters['date_to']) && empty($filters['search'])) {
            $filters['date_from'] = date('Y-m-01');
            $filters['date_to']   = date('Y-m-d');
        }

        $page   = max(1, (int)$this->query('page', 1));
        $result = $this->svc->paginateReceipts($filters, $page, 25);
        $kpis   = $this->svc->getKpis();
        $shops  = $this->svc->getShopsList();

        $this->render('general_manager.receipts.index', [
            'pageTitle'  => 'Sales Receipts',
            'kpis'       => $kpis,
            'receipts'   => $result['data'],
            'pagination' => [
                'total'      => $result['total'],
                'page'       => $result['page'],
                'perPage'    => $result['perPage'],
                'totalPages' => $result['totalPages'],
                'from'       => $result['from'],
                'to'         => $result['to'],
            ],
            'filters'    => $filters,
            'shops'      => $shops,
        ]);
    }

    // =========================================================
    // GET /gm/receipts/pos/{id} — POS sale receipt (view + print)
    // =========================================================
    public function showPOS(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $saleId = (int)$id;
        $sale   = $this->svc->getPOSSaleWithItems($saleId);

        if (!$sale) {
            $this->flash(FLASH_ERROR, 'POS sale not found.');
            $this->redirect('/gm/receipts');
            return;
        }

        // Determine if printable layout requested
        $print = $this->query('print', '') === '1';

        $this->render('general_manager.receipts.pos_receipt', [
            'pageTitle' => 'Receipt — ' . e($sale['receipt_number'] ?? "Sale #{$saleId}"),
            'sale'      => $sale,
            'userRole'  => ROLE_GENERAL_MANAGER,
        ], $print ? 'print' : 'master');
    }

    // =========================================================
    // GET /gm/receipts/order/{id} — Customer order receipt (view + print)
    // =========================================================
    public function showOrder(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $orderId = (int)$id;
        $order   = $this->svc->getCustomerOrderWithItems($orderId);

        if (!$order) {
            $this->flash(FLASH_ERROR, 'Customer order not found.');
            $this->redirect('/gm/receipts');
            return;
        }

        $print = $this->query('print', '') === '1';

        $this->render('general_manager.receipts.order_receipt', [
            'pageTitle' => 'Receipt — ' . e($order['order_reference'] ?? "Order #{$orderId}"),
            'order'     => $order,
            'userRole'  => ROLE_GENERAL_MANAGER,
        ], $print ? 'print' : 'master');
    }
}
