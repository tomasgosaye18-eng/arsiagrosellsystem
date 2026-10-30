<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 12
 * =========================================================
 * File    : modules/pos/controllers/POSController.php
 * Purpose : Full POS terminal controller.
 *           Cash-only sales, shift management, receipts,
 *           sales history, void.
 *
 * SECURITY: shop_id is ALWAYS resolved from session.
 *           Never trusted from form/query input.
 *
 * Routes  :
 *   GET  /pos                       → terminal()
 *   POST /pos/sale                  → sale()
 *   GET  /pos/receipt/{id}          → receipt()
 *   GET  /pos/history               → history()
 *   GET  /pos/shift                 → shiftStatus()
 *   POST /pos/shift/open            → openShift()
 *   POST /pos/shift/close           → closeShift()
 *   GET  /pos/shift/{id}            → shiftDetail()
 *   POST /pos/void/{id}             → voidSale()
 *   GET  /api/pos/products          → apiProducts()  (AJAX)
 *   GET  /api/pos/stock/{pid}       → apiStock()     (AJAX)
 *   GET  /api/pos/categories        → apiCategories()(AJAX)
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\POS\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\Logger;
use AAM\Core\SessionManager;
use AAM\POS\Models\SalesModel;
use AAM\POS\Models\ShiftModel;
use AAM\POS\Services\POSService;
use AAM\POS\Services\MobilePaymentService;
use AAM\Settings\Services\PaymentMethodService;

class POSController extends BaseController
{
    // ── Resolve shop from session (NEVER from form input) ─
    private function resolveShopId(): int
    {
        $role  = $this->userRole();
        $ltype = SessionManager::get(SESSION_LOCATION_TYPE, '');
        $lid   = (int)SessionManager::get(SESSION_LOCATION_ID, 0);

        // Admin / GM: may pass ?shop_id= query param for management view
        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            $qid = (int)$this->query('shop_id', 0);
            return $qid > 0 ? $qid : max(1, $lid);
        }

        // Salesperson: strictly their assigned shop
        if ($ltype === 'shop' && $lid > 0) return $lid;

        // Fallback from DB
        $db  = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT location_id FROM user_location_assignments
             WHERE user_id=:uid AND location_type='shop' ORDER BY assigned_at DESC LIMIT 1",
            ['uid' => $this->userId()]
        );
        return $row ? (int)$row['location_id'] : 0;
    }

    // ── Resolve active shift for current seller ────────────
    private function resolveActiveShift(int $shopId): ?array
    {
        $model = new ShiftModel();
        return $model->getActiveShift((int)$this->userId(), $shopId);
    }

    // =========================================================
    // GET /pos — POS Terminal
    // =========================================================
    public function terminal(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);

        $shopId = $this->resolveShopId();
        if ($shopId <= 0) {
            $this->flash(FLASH_ERROR, 'You are not assigned to any shop. Contact your administrator.');
            $this->redirect('/dashboard');
            return;
        }

        $db   = Database::getInstance();
        $shop = $db->fetchOne("SELECT * FROM shops WHERE id=:id AND is_active=1", ['id' => $shopId]);
        if (!$shop) {
            $this->flash(FLASH_ERROR, 'Shop not found or inactive.');
            $this->redirect('/dashboard');
            return;
        }

        $service    = new POSService();
        $activeShift = $this->resolveActiveShift($shopId);

        // ── Active payment methods from system configuration ───────────────
        // These are loaded from the payment_methods table (centrally managed
        // by System Admin). Only ACTIVE methods appear in the POS.
        $pmService      = new PaymentMethodService();
        $paymentMethods = $pmService->getActiveForPOS();

        // Categories with stock in this shop
        $categories = $db->fetchAll(
            "SELECT DISTINCT c.id, c.name
             FROM shop_inventory si
             JOIN products p ON p.id = si.product_id
             JOIN categories c ON c.id = p.category_id
             WHERE si.shop_id=:sid AND si.available_quantity>0 AND p.is_active=1
             ORDER BY c.name",
            ['sid' => $shopId]
        );

        // Low-stock alerts
        $lowStock = $service->getLowStockProducts($shopId);

        // Today's totals for current seller
        $salesModel = new SalesModel();
        $dayTotals  = $salesModel->getSellerDayTotals((int)$this->userId(), $shopId);

        $this->render('pos.terminal', [
            'pageTitle'      => 'POS Terminal — ' . e($shop['name']),
            'shop'           => $shop,
            'shopId'         => $shopId,
            'activeShift'    => $activeShift,
            'categories'     => $categories,
            'lowStock'       => $lowStock,
            'dayTotals'      => $dayTotals,
            'userRole'       => $this->userRole(),
            'paymentMethods' => $paymentMethods,
        ]);
    }

    // =========================================================
    // POST /pos/sale — Process Cash Sale
    // =========================================================
    public function sale(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $shopId = $this->resolveShopId();
        if ($shopId <= 0) {
            $this->jsonError('You are not assigned to any shop.', HTTP_FORBIDDEN);
            return;
        }

        // ── PAYMENT METHOD ACTIVE CHECK ───────────────────────────────────
        // Cash must be ACTIVE in payment_methods table.
        // If System Administrator has deactivated Cash, no cash sale is allowed.
        try {
            $pmService = new PaymentMethodService();
            if (!$pmService->isProviderActive('cash')) {
                $this->jsonError(
                    'Cash payment is currently inactive. Please use an active payment method or contact System Administration.',
                    HTTP_BAD_REQUEST
                );
                return;
            }
        } catch (\Throwable $e) {
            // Table missing (pre-migration) — allow through as legacy fallback
            Logger::warning('sale(): could not verify cash payment method status', ['error' => $e->getMessage()]);
        }

        // Parse cart items from POST
        $cartJson    = trim($this->input('cart_items', ''));
        $cashReceived = (float)$this->input('cash_received', 0);
        $notes        = trim($this->input('notes', ''));

        $items = [];
        if ($cartJson !== '') {
            $decoded = json_decode($cartJson, true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }

        if (empty($items)) {
            $this->jsonError('Cart is empty. Please add products before completing the sale.');
            return;
        }

        $activeShift = $this->resolveActiveShift($shopId);

        // Salesperson MUST have an open shift to process sales.
        // Admin / GM are exempt (they may process test sales without a shift).
        $role = $this->userRole();
        if (in_array($role, [ROLE_SALESPERSON], true) && !$activeShift) {
            $this->jsonError(
                'No active shift. Please open a shift before processing sales.',
                HTTP_UNPROCESSABLE_ENTITY
            );
            return;
        }

        try {
            $service = new POSService();
            $saleId  = $service->processSale(
                $shopId,
                (int)$this->userId(),
                $activeShift ? (int)$activeShift['id'] : null,
                $items,
                $cashReceived,
                $notes
            );

            $this->jsonSuccess(['sale_id' => $saleId], 'Sale completed successfully.');
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            $this->jsonError('An unexpected error occurred. Please try again.', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // =========================================================
    // GET /pos/receipt/{id} — View/Print Receipt
    // =========================================================
    public function receipt(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);

        $saleId     = (int)$id;
        $shopId     = $this->resolveShopId();
        $salesModel = new SalesModel();
        $sale       = $salesModel->getWithItems($saleId);

        if (!$sale) {
            $this->flash(FLASH_ERROR, 'Sale not found.');
            $this->redirect('/pos/history');
            return;
        }

        // Security: non-admin must only see their own shop's receipts
        $role = $this->userRole();
        if (!in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            if ((int)$sale['shop_id'] !== $shopId) {
                $this->flash(FLASH_ERROR, 'Access denied.');
                $this->redirect('/pos/history');
                return;
            }
        }

        // Audit: RECEIPT_GENERATED (only on first view, not reprints — best-effort, non-blocking)
        try {
            (new \AAM\Admin\Models\ActivityLogModel())->logBusiness([
                'action'       => \AAM\Admin\Models\ActivityLogModel::ACTION_RECEIPT_GENERATED,
                'module'       => 'shop',
                'entity_type'  => 'sale',
                'entity_label' => $sale['receipt_number'] ?? "Sale #{$saleId}",
                'record_id'    => $saleId,
                'description'  => "Receipt {$sale['receipt_number']} viewed for sale {$sale['sale_number']}",
                'shop_id'      => (int)$sale['shop_id'],
            ]);
        } catch (\Throwable) {
            // Non-critical — never break receipt view over audit failure
        }

        $this->render('pos.receipt', [
            'pageTitle' => 'Receipt — ' . e($sale['receipt_number']),
            'sale'      => $sale,
            'userRole'  => $this->userRole(),
        ], 'print');
    }

    // =========================================================
    // GET /pos/history — Sales History
    // =========================================================
    public function history(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);

        $shopId  = $this->resolveShopId();
        $role    = $this->userRole();

        $filters = [
            'status'    => $this->query('status',    ''),
            'date_from' => $this->query('date_from', date('Y-m-d')),
            'date_to'   => $this->query('date_to',   date('Y-m-d')),
            'search'    => $this->query('search',    ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $salesModel = new SalesModel();

        // Salesperson sees ONLY their own sales
        if ($role === ROLE_SALESPERSON) {
            $result = $salesModel->paginateBySeller(
                (int)$this->userId(), $shopId, $page, PAGINATION_DEFAULT, $filters
            );
        } else {
            $result = $salesModel->paginateByShop($shopId, $page, PAGINATION_DEFAULT, $filters);
        }

        $dailyTotals = $salesModel->getDailyTotals($shopId, date('Y-m-d'));

        $db   = Database::getInstance();
        $shop = $db->fetchOne("SELECT id, name FROM shops WHERE id=:id", ['id' => $shopId]);

        $this->render('pos.history', [
            'pageTitle'   => 'Sales History',
            'shop'        => $shop,
            'sales'       => $result['data'],
            'pagination'  => $result,
            'filters'     => $filters,
            'dailyTotals' => $dailyTotals,
            'userRole'    => $role,
        ]);
    }

    // =========================================================
    // GET /pos/shift — Current Shift Status
    // =========================================================
    public function shiftStatus(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);

        $shopId      = $this->resolveShopId();
        $activeShift = $this->resolveActiveShift($shopId);
        $db          = Database::getInstance();
        $shop        = $db->fetchOne("SELECT id, name FROM shops WHERE id=:id", ['id' => $shopId]);

        $shiftModel = new ShiftModel();
        $history    = $shiftModel->paginateBySeller((int)$this->userId(), $shopId, 1, 5);

        $this->render('pos.shift', [
            'pageTitle'   => 'Shift Management',
            'shop'        => $shop,
            'activeShift' => $activeShift,
            'shiftHistory'=> $history['data'],
            'userRole'    => $this->userRole(),
        ]);
    }

    // =========================================================
    // POST /pos/shift/open — Open a Shift
    // =========================================================
    public function openShift(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $shopId  = $this->resolveShopId();
        $model   = new ShiftModel();

        // Check no open shift already
        $existing = $model->getActiveShift((int)$this->userId(), $shopId);
        if ($existing) {
            $this->flash(FLASH_WARNING, 'You already have an open shift.');
            $this->redirect('/pos/shift');
            return;
        }

        $notes       = trim($this->input('opening_notes', ''));
        $shiftNumber = $model->nextShiftNumber();

        $model->create([
            'shift_number'  => $shiftNumber,
            'shop_id'       => $shopId,
            'seller_id'     => $this->userId(),
            'status'        => 'open',
            'opening_notes' => $notes,
            'opened_at'     => date('Y-m-d H:i:s'),
        ]);

        // Audit: SHIFT_OPENED
        (new \AAM\Admin\Models\ActivityLogModel())->logBusiness([
            'action'       => \AAM\Admin\Models\ActivityLogModel::ACTION_SHIFT_OPENED,
            'module'       => 'shop',
            'entity_type'  => 'shift',
            'entity_label' => $shiftNumber,
            'description'  => "Shift {$shiftNumber} opened by seller #{$this->userId()} at shop #{$shopId}",
            'shop_id'      => $shopId,
            'new_values'   => ['shift_number' => $shiftNumber, 'shop_id' => $shopId],
        ]);

        $this->flash(FLASH_SUCCESS, "Shift {$shiftNumber} opened successfully.");
        $this->redirect('/pos');
    }

    // =========================================================
    // POST /pos/shift/close — Close Current Shift
    // =========================================================
    public function closeShift(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $shopId      = $this->resolveShopId();
        $model       = new ShiftModel();
        $activeShift = $model->getActiveShift((int)$this->userId(), $shopId);

        if (!$activeShift) {
            $this->flash(FLASH_ERROR, 'No open shift found.');
            $this->redirect('/pos/shift');
            return;
        }

        $notes    = trim($this->input('closing_notes', ''));
        $shiftId  = (int)$activeShift['id'];

        Database::getInstance()->execute(
            "UPDATE seller_shifts
             SET status='closed', closed_at=NOW(), closing_notes=:notes
             WHERE id=:id",
            ['notes' => $notes, 'id' => $shiftId]
        );

        // Audit: SHIFT_CLOSED
        (new \AAM\Admin\Models\ActivityLogModel())->logBusiness([
            'action'       => \AAM\Admin\Models\ActivityLogModel::ACTION_SHIFT_CLOSED,
            'module'       => 'shop',
            'entity_type'  => 'shift',
            'entity_label' => $activeShift['shift_number'] ?? "Shift #{$shiftId}",
            'record_id'    => $shiftId,
            'description'  => sprintf(
                "Shift %s closed. Transactions: %d, Total: ETB %.2f",
                $activeShift['shift_number'] ?? "#{$shiftId}",
                (int)($activeShift['total_transactions'] ?? 0),
                (float)($activeShift['total_sales_amount'] ?? 0)
            ),
            'shop_id'      => $shopId,
            'old_values'   => ['status' => 'open'],
            'new_values'   => [
                'status'             => 'closed',
                'total_transactions' => (int)($activeShift['total_transactions'] ?? 0),
                'total_sales_amount' => (float)($activeShift['total_sales_amount'] ?? 0),
            ],
        ]);

        $this->flash(FLASH_SUCCESS, 'Shift closed. See summary below.');
        $this->redirect("/pos/shift/{$shiftId}");
    }

    // =========================================================
    // GET /pos/shift/{id} — Shift Detail / Summary
    // =========================================================
    public function shiftDetail(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);

        $shiftId = (int)$id;
        $model   = new ShiftModel();
        $shift   = $model->getWithSummary($shiftId);

        if (!$shift) {
            $this->flash(FLASH_ERROR, 'Shift not found.');
            $this->redirect('/pos/shift');
            return;
        }

        // Non-admin: only their own shifts
        $role = $this->userRole();
        if (!in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            if ((int)$shift['seller_id'] !== (int)$this->userId()) {
                $this->flash(FLASH_ERROR, 'Access denied.');
                $this->redirect('/pos/shift');
                return;
            }
        }

        $this->render('pos.shift_detail', [
            'pageTitle' => 'Shift Summary — ' . e($shift['shift_number']),
            'shift'     => $shift,
            'userRole'  => $this->userRole(),
        ]);
    }

    // =========================================================
    // POST /pos/void/{id} — Void a Sale
    // =========================================================
    public function voidSale(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $saleId = (int)$id;
        $reason = trim($this->input('void_reason', ''));

        if (empty($reason)) {
            $this->flash(FLASH_ERROR, 'Please provide a reason for voiding this sale.');
            $this->redirect("/pos/receipt/{$saleId}");
            return;
        }

        try {
            $service = new POSService();
            $service->voidSale($saleId, (int)$this->userId(), $reason);
            $this->flash(FLASH_SUCCESS, 'Sale voided successfully. Stock has been restored.');
        } catch (\RuntimeException $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, 'Failed to void sale. Please try again.');
        }

        $this->redirect("/pos/receipt/{$saleId}");
    }

    // =========================================================
    // GET /api/pos/products — AJAX product search for POS
    // Returns only products available in seller's shop.
    // =========================================================
    public function apiProducts(): void
    {
        $this->requireAuth();

        $shopId     = $this->resolveShopId();
        $q          = trim($this->query('q', ''));
        $categoryId = (int)$this->query('category_id', 0);

        if ($shopId <= 0) {
            \AAM\Core\Logger::warning('apiProducts: no shop resolved', [
                'user_id'       => $this->userId(),
                'role'          => $this->userRole(),
                'session_loc'   => \AAM\Core\SessionManager::get(SESSION_LOCATION_TYPE),
                'session_loc_id'=> \AAM\Core\SessionManager::get(SESSION_LOCATION_ID),
            ]);
            $this->jsonSuccess([], 'No shop assigned to this user.');
            return;
        }

        try {
            $service  = new POSService();
            $products = $service->getShopProductsForPOS($shopId, $q, $categoryId);
            \AAM\Core\Logger::info('apiProducts: ok', ['shop_id' => $shopId, 'count' => count($products)]);
            $this->jsonSuccess($products);
        } catch (\Throwable $e) {
            \AAM\Core\Logger::error('apiProducts query failed', ['error' => $e->getMessage(), 'shop_id' => $shopId]);
            $this->jsonSuccess([]);
        }
    }

    // =========================================================
    // GET /api/pos/stock/{pid} — AJAX single product stock check
    // =========================================================
    public function apiStock(string $pid): void
    {
        $this->requireAuth();

        $shopId    = $this->resolveShopId();
        $productId = (int)$pid;

        if ($shopId <= 0 || $productId <= 0) {
            $this->jsonSuccess(['available_quantity' => 0]);
            return;
        }

        try {
            $db  = Database::getInstance();
            $row = $db->fetchOne(
                "SELECT available_quantity FROM shop_inventory
                 WHERE shop_id=:sid AND product_id=:pid LIMIT 1",
                ['sid' => $shopId, 'pid' => $productId]
            );
            $this->jsonSuccess(['available_quantity' => (float)($row['available_quantity'] ?? 0)]);
        } catch (\Throwable $e) {
            $this->jsonSuccess(['available_quantity' => 0]);
        }
    }

    // =========================================================
    // GET /api/pos/categories — AJAX categories with stock
    // =========================================================
    public function apiCategories(): void
    {
        $this->requireAuth();

        $shopId = $this->resolveShopId();

        if ($shopId <= 0) {
            $this->jsonSuccess([]);
            return;
        }

        try {
            $db = Database::getInstance();
            $cats = $db->fetchAll(
                "SELECT DISTINCT c.id, c.name
                 FROM shop_inventory si
                 JOIN products p ON p.id=si.product_id
                 JOIN categories c ON c.id=p.category_id
                 WHERE si.shop_id=:sid AND si.available_quantity>0 AND p.is_active=1
                 ORDER BY c.name",
                ['sid' => $shopId]
            );
            $this->jsonSuccess($cats);
        } catch (\Throwable $e) {
            $this->jsonSuccess([]);
        }
    }

    // =========================================================
    // GET /api/products (legacy route kept for backward compat)
    // =========================================================
    public function searchProducts(): void
    {
        $this->requireAuth();

        $shopId = $this->resolveShopId();
        $q      = trim($this->query('q', ''));

        if ($shopId > 0) {
            // Use the shop-scoped product search
            $this->apiProducts();
            return;
        }

        // Fallback: global product search (no shop stock filter)
        $products = [];
        try {
            $db    = \AAM\Core\Database::getInstance();
            $where = "p.is_active = 1";
            $params = [];
            if ($q !== '') {
                $where .= " AND (p.name LIKE :q OR p.sku LIKE :q2 OR p.barcode LIKE :q3)";
                $params['q']  = "%{$q}%";
                $params['q2'] = "%{$q}%";
                $params['q3'] = "%{$q}%";
            }
            $rows = $db->fetchAll(
                "SELECT p.id AS product_id, p.name AS product_name, p.sku AS product_sku,
                        p.barcode, u.abbreviation AS unit_abbr, p.unit_price AS current_price
                 FROM products p
                 LEFT JOIN units_of_measure u ON u.id = p.unit_id
                 WHERE {$where}
                 ORDER BY p.name ASC LIMIT 20",
                $params
            );
            foreach ($rows as &$r) {
                $r['price_available'] = ($r['current_price'] !== null && (float)$r['current_price'] > 0);
            }
            unset($r);
            $products = $rows;
        } catch (\Throwable $e) {}

        $this->jsonSuccess($products);
    }

    // =========================================================
    // POST /pos/payment/mobile — Initiate a mobile payment
    // Called when salesperson clicks "Generate Payment QR".
    // Does NOT complete sale or deduct inventory.
    // =========================================================
    public function initiatePayment(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $shopId = $this->resolveShopId();
        if ($shopId <= 0) {
            $this->jsonError('You are not assigned to any shop.', HTTP_FORBIDDEN);
            return;
        }

        $role        = $this->userRole();
        $activeShift = $this->resolveActiveShift($shopId);
        if (in_array($role, [ROLE_SALESPERSON], true) && !$activeShift) {
            $this->jsonError('No active shift. Please open a shift before processing payments.', HTTP_UNPROCESSABLE_ENTITY);
            return;
        }

        $cartJson     = trim($this->input('cart_items', ''));
        $providerCode = trim($this->input('provider', ''));
        $notes        = trim($this->input('notes', ''));

        if (!in_array($providerCode, ['cbe_birr', 'telebirr'], true)) {
            $this->jsonError('Invalid payment provider. Choose CBE Birr or Telebirr.', HTTP_BAD_REQUEST);
            return;
        }

        // Confirm the provider is ACTIVE in system configuration
        // (System Administrator may deactivate a provider — this is the controller-level guard)
        $pmService = new PaymentMethodService();
        if (!$pmService->isProviderActive($providerCode)) {
            $this->jsonError(
                'This payment method is currently inactive. Please use a different payment method or contact System Administration.',
                HTTP_BAD_REQUEST
            );
            return;
        }

        $items = [];
        if ($cartJson !== '') {
            $decoded = json_decode($cartJson, true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }

        if (empty($items)) {
            $this->jsonError('Cart is empty. Please add products before initiating payment.');
            return;
        }

        try {
            $service = new MobilePaymentService();
            $result  = $service->initiatePayment(
                $shopId,
                (int)$this->userId(),
                $activeShift ? (int)$activeShift['id'] : null,
                $providerCode,
                $items,
                $notes
            );

            $this->jsonSuccess($result, 'Payment initiated. Waiting for customer payment.');
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Logger::error('initiatePayment: unexpected error', ['error' => $e->getMessage()]);
            $this->jsonError('An unexpected error occurred. Please try again.', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // =========================================================
    // POST /pos/payment/verify — Verify a pending mobile payment
    // Called by browser polling or manual "Check Payment" button.
    // If PAID → atomically creates sale + deducts inventory.
    // =========================================================
    public function verifyPayment(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $paymentId = (int)$this->input('payment_id', 0);
        if ($paymentId <= 0) {
            $this->jsonError('Invalid payment ID.', HTTP_BAD_REQUEST);
            return;
        }

        try {
            $service = new MobilePaymentService();
            $result  = $service->verifyPayment($paymentId, (int)$this->userId());
            $this->jsonSuccess($result, $result['message'] ?? 'Payment status checked.');
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Logger::error('verifyPayment: unexpected error', ['error' => $e->getMessage()]);
            $this->jsonError('Verification failed. Please retry.', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // =========================================================
    // POST /pos/payment/cancel — Cancel a pending mobile payment
    // =========================================================
    public function cancelPayment(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_SALESPERSON, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $paymentId = (int)$this->input('payment_id', 0);
        if ($paymentId <= 0) {
            $this->jsonError('Invalid payment ID.', HTTP_BAD_REQUEST);
            return;
        }

        try {
            $service = new MobilePaymentService();
            $service->cancelPayment($paymentId, (int)$this->userId());
            $this->jsonSuccess([], 'Payment cancelled.');
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            $this->jsonError('Cancellation failed. Please try again.', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // =========================================================
    // GET /api/pos/payment/status — Poll payment status (no CSRF)
    // Lightweight endpoint polled every few seconds by QR modal.
    // Query param: payment_id=int
    // =========================================================
    public function apiPaymentStatus(): void
    {
        $this->requireAuth();

        $paymentId = (int)$this->query('payment_id', 0);
        if ($paymentId <= 0) {
            $this->jsonError('Invalid payment ID.', HTTP_BAD_REQUEST);
            return;
        }

        try {
            $service = new MobilePaymentService();
            $status  = $service->getPaymentStatus($paymentId);
            $this->jsonSuccess($status);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            $this->jsonError('Status check failed.', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // =========================================================
    // GET /api/pos/payment-methods — Active payment methods for POS
    //
    // Returns ONLY active payment methods in a format safe for
    // the browser. No provider config, no credentials.
    //
    // Response shape:
    //   {
    //     "cash":           [{ id, code, name }],
    //     "mobile_banking": [{ id, code, name, provider_code }]
    //   }
    //
    // The POS JS calls this on terminal load and uses the result
    // to render the Payment Method selector dynamically.
    // If a method is deactivated by System Admin, it disappears
    // from POS on the next page load (or AJAX refresh).
    // =========================================================
    public function apiPaymentMethods(): void
    {
        $this->requireAuth();

        try {
            $pmService = new PaymentMethodService();
            $methods   = $pmService->getActiveForPOS();
            $this->jsonSuccess($methods);
        } catch (\Throwable $e) {
            // Non-fatal fallback: return cash-only so POS still works
            Logger::error('apiPaymentMethods failed', ['error' => $e->getMessage()]);
            $this->jsonSuccess([
                'cash'           => [['id' => 1, 'code' => 'cash', 'name' => 'Cash', 'provider_code' => null]],
                'mobile_banking' => [],
            ]);
        }
    }
}
