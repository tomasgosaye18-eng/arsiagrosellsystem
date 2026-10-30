<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/routes.php
 * Purpose  : Application route definitions.
 *            All URLs are registered here.
 *            Modules register their routes in this file.
 *            Uses $router (Router instance from index.php).
 * =========================================================
 */

declare(strict_types=1);

use AAM\Middleware\AuthMiddleware;
use AAM\Middleware\CsrfMiddleware;
use AAM\Middleware\RoleMiddleware;
use AAM\Middleware\CustomerAuthMiddleware;


/**
 * @var \AAM\Core\Router $router  Injected from index.php
 */

// ─── Public Routes (no authentication required) ───────────────────────────────
$router->get('/',                'AAM\\Auth\\Controllers\\AuthController@showLogin');
$router->get('/login',           'AAM\\Auth\\Controllers\\AuthController@showLogin');
$router->post('/login',          'AAM\\Auth\\Controllers\\AuthController@login',         [CsrfMiddleware::class]);
$router->get('/logout',          'AAM\\Auth\\Controllers\\AuthController@logout',        [AuthMiddleware::class]);
$router->get('/session-expired', 'AAM\\Auth\\Controllers\\AuthController@sessionExpired');

// ─── Change Password (forced on first login) ──────────────────────────────────
$router->get('/change-password',  'AAM\\Auth\\Controllers\\AuthController@showChangePassword', [AuthMiddleware::class]);
$router->post('/change-password', 'AAM\\Auth\\Controllers\\AuthController@changePassword',     [AuthMiddleware::class, CsrfMiddleware::class]);

// ─── Forgot / Reset Password (disabled — admin resets passwords via dashboard) ─
// $router->get('/forgot-password',         'AAM\\Auth\\Controllers\\PasswordController@showForgot');
// $router->post('/forgot-password',        'AAM\\Auth\\Controllers\\PasswordController@forgot',         [CsrfMiddleware::class]);
// $router->get('/reset-password/{token}',  'AAM\\Auth\\Controllers\\PasswordController@showReset');
// $router->post('/reset-password/{token}', 'AAM\\Auth\\Controllers\\PasswordController@reset',          [CsrfMiddleware::class]);

// ─── Profile (own) ────────────────────────────────────────────────────────────
$router->get('/profile',  'AAM\\Auth\\Controllers\\ProfileController@show', [AuthMiddleware::class]);

// ─── Profile Edit ─────────────────────────────────────────────────────────────
$router->group('/profile', function($r) {
    $r->get('/edit',                   'AAM\\Auth\\Controllers\\ProfileController@edit');
    $r->post('/edit',                  'AAM\\Auth\\Controllers\\ProfileController@update',           [CsrfMiddleware::class]);
    $r->post('/photo',                 'AAM\\Auth\\Controllers\\ProfileController@uploadPhoto',      [CsrfMiddleware::class]);
    $r->post('/photo/delete',          'AAM\\Auth\\Controllers\\ProfileController@deletePhoto',      [CsrfMiddleware::class]);
    $r->get('/change-password',        'AAM\\Auth\\Controllers\\ProfileController@showChangePassword');
    $r->post('/change-password',       'AAM\\Auth\\Controllers\\ProfileController@changePassword',   [CsrfMiddleware::class]);
    $r->post('/revoke-session',        'AAM\\Auth\\Controllers\\ProfileController@revokeSession',    [CsrfMiddleware::class]);
    $r->post('/revoke-all-sessions',   'AAM\\Auth\\Controllers\\ProfileController@revokeAllSessions',[CsrfMiddleware::class]);
}, [AuthMiddleware::class]);

// ─── Dashboard (role-aware — one route, controller renders correct view) ──────
$router->get('/dashboard', 'AAM\\Dashboard\\Controllers\\DashboardController@index', [AuthMiddleware::class]);

// ─── System Administrator Dashboard ──────────────────────────────────────────
$router->get('/admin/dashboard', 'AAM\\Dashboard\\Controllers\\DashboardController@adminDashboard',
    [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN])]);

// ─── Notifications ────────────────────────────────────────────────────────────
$router->group('/notifications', function($r) {
    $r->get('/',           'AAM\\Notifications\\Controllers\\NotificationController@index');
    $r->post('/{id}/read', 'AAM\\Notifications\\Controllers\\NotificationController@markRead',   [CsrfMiddleware::class]);
    $r->post('/read-all',  'AAM\\Notifications\\Controllers\\NotificationController@markAllRead',[CsrfMiddleware::class]);
    // GM — send business/operational notification (General Manager only)
    $r->get('/send',       'AAM\\Notifications\\Controllers\\NotificationController@sendForm',
        [new RoleMiddleware([ROLE_GENERAL_MANAGER])]);
    $r->post('/send',      'AAM\\Notifications\\Controllers\\NotificationController@send',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_GENERAL_MANAGER])]);
}, [AuthMiddleware::class]);

// ─── Admin — System Notifications (System Administrator only) ─────────────────
$router->group('/admin/notifications', function($r) {
    $r->get('/send',  'AAM\\Notifications\\Controllers\\NotificationController@adminSendForm');
    $r->post('/send', 'AAM\\Notifications\\Controllers\\NotificationController@adminSend', [CsrfMiddleware::class]);
}, [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN])]);

// ─── Admin Routes — SYSTEM ADMINISTRATOR only ────────────────────────────────
// User Management: exclusively ROLE_ADMIN (System Administrator).
// ROLE_GENERAL_MANAGER does NOT have access to user management.
$router->group('/admin/users', function($r) {
    $r->get('/',               'AAM\\Admin\\Controllers\\UserController@index');
    $r->get('/search',         'AAM\\Admin\\Controllers\\UserController@liveSearch');
    $r->get('/create',         'AAM\\Admin\\Controllers\\UserController@create');
    $r->post('/',              'AAM\\Admin\\Controllers\\UserController@store',         [CsrfMiddleware::class]);
    $r->get('/{id}',           'AAM\\Admin\\Controllers\\UserController@view');
    $r->get('/{id}/edit',      'AAM\\Admin\\Controllers\\UserController@edit');
    $r->post('/{id}',          'AAM\\Admin\\Controllers\\UserController@update',        [CsrfMiddleware::class]);
    $r->post('/{id}/toggle',   'AAM\\Admin\\Controllers\\UserController@toggle',        [CsrfMiddleware::class]);
    $r->post('/{id}/delete',   'AAM\\Admin\\Controllers\\UserController@destroy',       [CsrfMiddleware::class]);
    $r->post('/{id}/restore',  'AAM\\Admin\\Controllers\\UserController@restore',       [CsrfMiddleware::class]);
    $r->post('/{id}/unlock',   'AAM\\Admin\\Controllers\\UserController@unlock',        [CsrfMiddleware::class]);
    $r->post('/{id}/reset',    'AAM\\Admin\\Controllers\\UserController@resetPassword', [CsrfMiddleware::class]);
}, [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN])]);

// ─── Settings — SYSTEM ADMINISTRATOR only ────────────────────────────────────
$router->group('/settings', function($r) {
    $r->get('/backups',               'AAM\\Settings\\Controllers\\BackupController@index');
    $r->post('/backups/create',       'AAM\\Settings\\Controllers\\BackupController@create',            [CsrfMiddleware::class]);
    $r->get('/backups/{id}/download', 'AAM\\Settings\\Controllers\\BackupController@download');
    $r->post('/backups/{id}/verify',  'AAM\\Settings\\Controllers\\BackupController@verify',            [CsrfMiddleware::class]);
    $r->post('/backups/{id}/delete',  'AAM\\Settings\\Controllers\\BackupController@destroy',           [CsrfMiddleware::class]);
    $r->post('/backups/{id}/restore', 'AAM\\Settings\\Controllers\\BackupController@restore',           [CsrfMiddleware::class]);
    $r->get('/backup',                'AAM\\Settings\\Controllers\\SettingsController@backup');
    $r->post('/backup',               'AAM\\Settings\\Controllers\\SettingsController@saveBackup',      [CsrfMiddleware::class]);
    // ── Payment Methods Management (System Administrator only) ────────────
    $r->get('/payment-methods',              'AAM\\Settings\\Controllers\\PaymentMethodController@index');
    $r->get('/payment-methods/{id}',         'AAM\\Settings\\Controllers\\PaymentMethodController@detail');
    $r->post('/payment-methods/{id}/toggle', 'AAM\\Settings\\Controllers\\PaymentMethodController@toggle',     [CsrfMiddleware::class]);
    $r->get('/payment-methods/{id}/config',  'AAM\\Settings\\Controllers\\PaymentMethodController@configForm');
    $r->post('/payment-methods/{id}/config', 'AAM\\Settings\\Controllers\\PaymentMethodController@saveConfig', [CsrfMiddleware::class]);
    // ── User Activity ──────────────────────────────────────────────────────
    $r->get('/user-activity', 'AAM\\Settings\\Controllers\\UserActivityController@index');
}, [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN])]);

// ─── Settings root redirect → security page ───────────────────────────────────
$router->get('/settings', 'AAM\\Settings\\Controllers\\SettingsController@index',
    [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN])]);


// ─── Products — Read-only generic route (warehouse, salesperson) ──
// Farm Manager now uses /farm/products (dedicated view).
// Admin uses /admin/products. GM uses /gm/products.
// This generic /products route remains for warehouse/salesperson only.
$router->get('/products',      'AAM\\Admin\\Controllers\\ProductController@index', [AuthMiddleware::class, new RoleMiddleware([ROLE_WAREHOUSE_MANAGER, ROLE_SALESPERSON])]);
$router->get('/products/{id}', 'AAM\\Admin\\Controllers\\ProductController@show',  [AuthMiddleware::class, new RoleMiddleware([ROLE_WAREHOUSE_MANAGER, ROLE_SALESPERSON])]);

// ─── Farm Routes ──────────────────────────────────────────────────────────────
$router->get('/farm/dashboard', 'AAM\\Farm\\Controllers\\FarmDashboardController@index', [AuthMiddleware::class]);

// ─── Farm Manager Product Catalog — read-only, ROLE_FARM_MANAGER only ─────────
// /farm/products is the Farm Manager's own catalog view.
// Farm Manager CANNOT access /admin/products or /gm/products.
$router->get('/farm/products',      'AAM\\Farm\\Controllers\\FarmProductController@index', [AuthMiddleware::class, new RoleMiddleware([ROLE_FARM_MANAGER])]);
$router->get('/farm/products/{id}', 'AAM\\Farm\\Controllers\\FarmProductController@show',  [AuthMiddleware::class, new RoleMiddleware([ROLE_FARM_MANAGER])]);

$router->group('/farms', function($r) {
    $r->get('/',                          'AAM\\Farm\\Controllers\\FarmController@index');
    $r->get('/{id}',                      'AAM\\Farm\\Controllers\\FarmController@show');
    $r->get('/{id}/harvests',             'AAM\\Farm\\Controllers\\HarvestController@index');
    $r->get('/{id}/harvests/create',      'AAM\\Farm\\Controllers\\HarvestController@create');
    $r->post('/{id}/harvests',            'AAM\\Farm\\Controllers\\HarvestController@store',    [CsrfMiddleware::class]);
    $r->get('/{id}/dispatches',           'AAM\\Farm\\Controllers\\DispatchController@index');
    $r->get('/{id}/dispatches/create',    'AAM\\Farm\\Controllers\\DispatchController@create');
    $r->post('/{id}/dispatches',          'AAM\\Farm\\Controllers\\DispatchController@store',   [CsrfMiddleware::class]);
}, [AuthMiddleware::class]);

// ─── Standalone Harvest Routes ────────────────────────────────────────────────
$router->group('/harvests', function($r) {
    $r->get('/{id}',            'AAM\\Farm\\Controllers\\HarvestController@view');
    $r->get('/{id}/edit',       'AAM\\Farm\\Controllers\\HarvestController@edit');
    $r->post('/{id}/update',    'AAM\\Farm\\Controllers\\HarvestController@update',   [CsrfMiddleware::class]);
    $r->post('/{id}/cancel',    'AAM\\Farm\\Controllers\\HarvestController@cancel',   [CsrfMiddleware::class]);
}, [AuthMiddleware::class]);

// ─── Standalone Dispatch Routes ───────────────────────────────────────────────
$router->group('/dispatches', function($r) {
    $r->get('/{id}',           'AAM\\Farm\\Controllers\\DispatchController@view');
    // Mark dispatched — Farm Manager only, only after GM approval
    $r->post('/{id}/dispatch', 'AAM\\Farm\\Controllers\\DispatchController@markDispatched',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_FARM_MANAGER])]);
    $r->post('/{id}/cancel',   'AAM\\Farm\\Controllers\\DispatchController@cancel',          [CsrfMiddleware::class]);
    // GM approval / rejection — General Manager only
    $r->post('/{id}/approve',  'AAM\\Farm\\Controllers\\DispatchController@approveDispatch',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_GENERAL_MANAGER])]);
    $r->post('/{id}/reject',   'AAM\\Farm\\Controllers\\DispatchController@rejectDispatch',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_GENERAL_MANAGER])]);
}, [AuthMiddleware::class]);

// ─── Warehouse Routes — Phase 10 ─────────────────────────────────────────────
$router->group('/warehouse', function($r) {
    // Overview Dashboard
    $r->get('/',                                'AAM\\Warehouse\\Controllers\\WarehouseController@index');
    // Inventory
    $r->get('/inventory',                       'AAM\\Warehouse\\Controllers\\WarehouseController@inventory');
    // Receiving
    $r->get('/receiving',                       'AAM\\Warehouse\\Controllers\\ReceivingController@queue');
    $r->get('/receiving/{id}/confirm',          'AAM\\Warehouse\\Controllers\\ReceivingController@confirmForm');
    $r->post('/receiving/{id}/confirm',         'AAM\\Warehouse\\Controllers\\ReceivingController@confirm',  [CsrfMiddleware::class]);
    $r->post('/receiving/{id}/complete',        'AAM\\Warehouse\\Controllers\\ReceivingController@complete', [CsrfMiddleware::class]);
    $r->get('/receiving/{id}/resubmit',         'AAM\\Warehouse\\Controllers\\ReceivingController@resubmitForm');
    $r->post('/receiving/{id}/resubmit',        'AAM\\Warehouse\\Controllers\\ReceivingController@resubmit', [CsrfMiddleware::class]);
    $r->get('/receiving/{id}',                  'AAM\\Warehouse\\Controllers\\ReceivingController@show');
    // Discrepancies
    $r->get('/discrepancies',                   'AAM\\Warehouse\\Controllers\\WarehouseController@discrepancies');
    $r->post('/discrepancies/{id}/status',      'AAM\\Warehouse\\Controllers\\WarehouseController@updateDiscrepancyStatus', [CsrfMiddleware::class]);
    $r->get('/discrepancies/{id}',              'AAM\\Warehouse\\Controllers\\WarehouseController@discrepancyShow');
    // Stock Movement History
    $r->get('/movements',                       'AAM\\Warehouse\\Controllers\\WarehouseController@movements');
    // Legacy alias
    $r->get('/dispatches',                      'AAM\\Warehouse\\Controllers\\WarehouseController@dispatches');
}, [AuthMiddleware::class]);

// ─── Transfer Routes — Phase 10 (corrected workflow) ─────────────────────────
// Workflow: Warehouse Manager creates → GM approves/rejects → WM dispatches → Shop receives
// RBAC summary:
//   /transfers/incoming         → SALESPERSON only
//   /transfers/{id}/receive     → SALESPERSON only (submit receiving / re-submit after rejection)
//   /transfers/{id}/approve|reject → GENERAL MANAGER only
//   /transfers/{id}/dispatch    → WAREHOUSE MANAGER only
//   /transfers/{id}/cancel      → WAREHOUSE MANAGER only
//   /transfers/{id} (show)      → all authenticated roles
$router->group('/transfers', function($r) {
    $r->get('/',                     'AAM\\Transfer\\Controllers\\TransferController@index');
    // Incoming view — Salesperson ONLY (shop receiver perspective)
    $r->get('/incoming',             'AAM\\Transfer\\Controllers\\TransferController@incoming',
        [new RoleMiddleware([ROLE_SALESPERSON])]);
    // Create/store — Warehouse Manager ONLY
    $r->get('/create',               'AAM\\Transfer\\Controllers\\TransferController@create',
        [new RoleMiddleware([ROLE_WAREHOUSE_MANAGER])]);
    $r->post('/',                    'AAM\\Transfer\\Controllers\\TransferController@store',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_WAREHOUSE_MANAGER])]);
    $r->get('/{id}',                 'AAM\\Transfer\\Controllers\\TransferController@show');
    // Approve / Reject — General Manager ONLY
    $r->post('/{id}/approve',        'AAM\\Transfer\\Controllers\\TransferController@approve',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_GENERAL_MANAGER])]);
    $r->post('/{id}/reject',         'AAM\\Transfer\\Controllers\\TransferController@reject',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_GENERAL_MANAGER])]);
    // Dispatch — Warehouse Manager ONLY (only after GM approval)
    $r->post('/{id}/dispatch',       'AAM\\Transfer\\Controllers\\TransferController@dispatch',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_WAREHOUSE_MANAGER])]);
    // Shop receiving — Salesperson ONLY
    // GM cannot submit receiving — GM approves/rejects via /gm/transfers/{id}/discrepancy-review
    $r->get('/{id}/receive',         'AAM\\Transfer\\Controllers\\TransferController@receiveForm',
        [new RoleMiddleware([ROLE_SALESPERSON])]);
    $r->post('/{id}/receive',        'AAM\\Transfer\\Controllers\\TransferController@receive',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_SALESPERSON])]);
    // Legacy /confirm routes — redirect to /receive
    $r->get('/{id}/confirm',         'AAM\\Transfer\\Controllers\\TransferController@confirmForm');
    $r->post('/{id}/confirm',        'AAM\\Transfer\\Controllers\\TransferController@confirm',    [CsrfMiddleware::class]);
    // Cancel — Warehouse Manager ONLY
    $r->post('/{id}/cancel',         'AAM\\Transfer\\Controllers\\TransferController@cancel',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_WAREHOUSE_MANAGER])]);
}, [AuthMiddleware::class]);

// ─── Inventory Routes — Phase 11 ─────────────────────────────────────────────
$router->group('/inventory', function($r) {
    // Overview
    $r->get('/',                              'AAM\\Inventory\\Controllers\\InventoryController@overview');
    // Stock Movements ledger
    $r->get('/movements',                     'AAM\\Inventory\\Controllers\\InventoryController@movements');
    // Batch inventory (internal — no sidebar link, accessible directly if needed)
    $r->get('/batches',                       'AAM\\Inventory\\Controllers\\InventoryController@batches');
    // Stock Adjustments — WM creates/views; GM approves/rejects via /gm/*
    $r->get('/adjustments',               'AAM\\Inventory\\Controllers\\InventoryController@adjustments');
    $r->get('/adjustments/create',        'AAM\\Inventory\\Controllers\\InventoryController@adjustForm',
        [new RoleMiddleware([ROLE_WAREHOUSE_MANAGER])]);
    $r->post('/adjustments',              'AAM\\Inventory\\Controllers\\InventoryController@adjust',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_WAREHOUSE_MANAGER])]);
    $r->get('/adjustments/{id}',          'AAM\\Inventory\\Controllers\\InventoryController@adjustShow');
    $r->post('/adjustments/{id}/approve', 'AAM\\Inventory\\Controllers\\InventoryController@adjustApprove',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_GENERAL_MANAGER])]);
    $r->post('/adjustments/{id}/reject',  'AAM\\Inventory\\Controllers\\InventoryController@adjustReject',
        [CsrfMiddleware::class, new RoleMiddleware([ROLE_GENERAL_MANAGER])]);
    // Legacy redirect (old /inventory/adjust path)
    $r->get('/adjust',                        'AAM\\Inventory\\Controllers\\InventoryController@adjustForm');
    $r->post('/adjust',                       'AAM\\Inventory\\Controllers\\InventoryController@adjust',        [CsrfMiddleware::class]);
}, [AuthMiddleware::class]);

// ─── Shop Routes ──────────────────────────────────────────────────────────────
$router->group('/shops', function($r) {
    $r->get('/',              'AAM\\Shop\\Controllers\\ShopController@index');
    $r->get('/{id}',          'AAM\\Shop\\Controllers\\ShopController@show');
    $r->get('/{id}/inventory','AAM\\Shop\\Controllers\\ShopController@inventory');
    $r->get('/{id}/sales',    'AAM\\Shop\\Controllers\\ShopController@sales');
}, [AuthMiddleware::class]);

// ─── Shop Receiving & Inventory (salesperson scoped) ───────────────────────────
$router->group('/shop', function($r) {
    // Discrepancies for the assigned shop
    $r->get('/discrepancies',         'AAM\\Shop\\Controllers\\ShopController@discrepancies');
    $r->get('/discrepancies/{id}',    'AAM\\Shop\\Controllers\\ShopController@discrepancyShow');
    $r->post('/discrepancies/{id}/status',
             'AAM\\Shop\\Controllers\\ShopController@discrepancyStatus',
             [CsrfMiddleware::class]);
    // Stock movements for the assigned shop
    $r->get('/stock-movements',       'AAM\\Shop\\Controllers\\ShopController@stockMovements');
}, [AuthMiddleware::class]);

// ─── POS Routes — Phase 12 + Mobile Payment ──────────────────────────────────
$router->group('/pos', function($r) {
    // Terminal
    $r->get('/',                   'AAM\\POS\\Controllers\\POSController@terminal');
    // Cash sale (existing — unchanged)
    $r->post('/sale',              'AAM\\POS\\Controllers\\POSController@sale',              [CsrfMiddleware::class]);
    // Receipt & void
    $r->get('/receipt/{id}',       'AAM\\POS\\Controllers\\POSController@receipt');
    $r->post('/void/{id}',         'AAM\\POS\\Controllers\\POSController@voidSale',          [CsrfMiddleware::class]);
    // History
    $r->get('/history',            'AAM\\POS\\Controllers\\POSController@history');
    // Shift management
    $r->get('/shift',              'AAM\\POS\\Controllers\\POSController@shiftStatus');
    $r->post('/shift/open',        'AAM\\POS\\Controllers\\POSController@openShift',         [CsrfMiddleware::class]);
    $r->post('/shift/close',       'AAM\\POS\\Controllers\\POSController@closeShift',        [CsrfMiddleware::class]);
    $r->get('/shift/{id}',         'AAM\\POS\\Controllers\\POSController@shiftDetail');
    // Mobile payment endpoints (new — Phase mobile)
    $r->post('/payment/mobile',    'AAM\\POS\\Controllers\\POSController@initiatePayment',   [CsrfMiddleware::class]);
    $r->post('/payment/verify',    'AAM\\POS\\Controllers\\POSController@verifyPayment',     [CsrfMiddleware::class]);
    $r->post('/payment/cancel',    'AAM\\POS\\Controllers\\POSController@cancelPayment',     [CsrfMiddleware::class]);
    // Legacy stub aliases
    $r->post('/open',              'AAM\\POS\\Controllers\\POSController@openShift',         [CsrfMiddleware::class]);
    $r->post('/close',             'AAM\\POS\\Controllers\\POSController@closeShift',        [CsrfMiddleware::class]);
}, [AuthMiddleware::class]);

// ─── Reports Routes ───────────────────────────────────────────────────────────
$router->group('/reports', function($r) {
    $r->get('/',                   'AAM\\Reports\\Controllers\\ReportController@index');

    // ── Farm Manager Reports ──────────────────────────────────────────────────
    $r->get('/farm',               'AAM\\Reports\\Controllers\\FarmReportController@index');
    $r->get('/farm/production',    'AAM\\Reports\\Controllers\\FarmReportController@production');
    $r->get('/farm/harvest',       'AAM\\Reports\\Controllers\\FarmReportController@harvest');
    $r->get('/farm/dispatch',      'AAM\\Reports\\Controllers\\FarmReportController@dispatch');

    // ── Admin / GM Reports ────────────────────────────────────────────────────
    $r->get('/admin',              'AAM\\Reports\\Controllers\\AdminReportController@index');
    $r->get('/admin/users',        'AAM\\Reports\\Controllers\\AdminReportController@users');
    $r->get('/admin/organization', 'AAM\\Reports\\Controllers\\AdminReportController@organization');
    $r->get('/admin/audit',        'AAM\\Reports\\Controllers\\AdminReportController@audit');

    // ── Warehouse Reports (Phase 10) ──────────────────────────────────────────
    $r->get('/warehouse',                'AAM\\Reports\\Controllers\\WarehouseReportController@index');
    $r->get('/warehouse/stock',          'AAM\\Reports\\Controllers\\WarehouseReportController@stock');
    $r->get('/warehouse/receiving',      'AAM\\Reports\\Controllers\\WarehouseReportController@receiving');
    $r->get('/warehouse/transfers',      'AAM\\Reports\\Controllers\\WarehouseReportController@transfers');
    $r->get('/warehouse/discrepancies',  'AAM\\Reports\\Controllers\\WarehouseReportController@discrepancies');
    $r->get('/warehouse/expiry',         'AAM\\Reports\\Controllers\\WarehouseReportController@expiry');

    // ── Legacy stubs ──────────────────────────────────────────────────────────
    $r->get('/farm-production',    'AAM\\Reports\\Controllers\\ReportController@farmProduction');
    $r->get('/warehouse-inventory','AAM\\Reports\\Controllers\\ReportController@warehouseInventory');
    $r->get('/transfers',          'AAM\\Reports\\Controllers\\ReportController@transfers');
    $r->get('/sales',              'AAM\\Reports\\Controllers\\ReportController@sales');
}, [AuthMiddleware::class]);

// ─── Analytics Routes — legacy aliases, kept for backward compat ─────────────
// New canonical analytics: GET /gm/analytics  (registered in GM block below)
$router->group('/analytics', function($r) {
    $r->get('/',         'AAM\\GeneralManager\\Controllers\\GMAnalyticsController@index');
    $r->get('/products', 'AAM\\GeneralManager\\Controllers\\GMAnalyticsController@products');
}, [AuthMiddleware::class, new RoleMiddleware([ROLE_GENERAL_MANAGER])]);

// ═════════════════════════════════════════════════════════════════════════════
// GENERAL MANAGER MODULE — Dedicated /gm/* namespace
// GM monitors, reviews and approves. Does NOT operate farm/warehouse/POS.
// System Administration remains at /admin/* — completely separate.
// ═════════════════════════════════════════════════════════════════════════════
$router->group('/gm', function($r) {

    // Dashboard
    $r->get('/',          'AAM\\GeneralManager\\Controllers\\GMDashboardController@index');
    $r->get('/dashboard', 'AAM\\GeneralManager\\Controllers\\GMDashboardController@index');

    // Farm monitoring (read-only) + GM dispatch approval
    $r->get('/farms',                            'AAM\\GeneralManager\\Controllers\\GMFarmController@index');
    $r->get('/farms/harvests',                   'AAM\\GeneralManager\\Controllers\\GMFarmController@harvests');
    $r->get('/farms/dispatches',                 'AAM\\GeneralManager\\Controllers\\GMFarmController@dispatches');
    $r->get('/farms/dispatches/pending',         'AAM\\GeneralManager\\Controllers\\GMFarmController@pendingDispatches');

    // Warehouse monitoring (read-only)
    $r->get('/warehouses',               'AAM\\GeneralManager\\Controllers\\GMWarehouseController@index');
    $r->get('/warehouses/receiving',     'AAM\\GeneralManager\\Controllers\\GMWarehouseController@receiving');
    $r->get('/warehouses/discrepancies', 'AAM\\GeneralManager\\Controllers\\GMWarehouseController@discrepancies');

    // Discrepancy Review — GM approves/rejects receiving discrepancies
    $r->get('/warehouses/discrepancies/pending-review',           'AAM\\GeneralManager\\Controllers\\GMWarehouseController@discrepanciesPendingReview');
    $r->get('/warehouses/receiving/{id}/discrepancy-review',      'AAM\\GeneralManager\\Controllers\\GMWarehouseController@discrepancyReview');
    $r->post('/warehouses/receiving/{id}/discrepancy-approve',    'AAM\\GeneralManager\\Controllers\\GMWarehouseController@discrepancyApprove',  [CsrfMiddleware::class]);
    $r->post('/warehouses/receiving/{id}/discrepancy-reject',     'AAM\\GeneralManager\\Controllers\\GMWarehouseController@discrepancyReject',   [CsrfMiddleware::class]);

    // Transfer pipeline — GM approves/rejects, does NOT create or dispatch
    $r->get('/transfers',                            'AAM\\GeneralManager\\Controllers\\GMTransferController@index');
    $r->get('/transfers/pending',                    'AAM\\GeneralManager\\Controllers\\GMTransferController@pending');
    $r->post('/transfers/{id}/approve',              'AAM\\GeneralManager\\Controllers\\GMTransferController@approve',  [CsrfMiddleware::class]);
    $r->post('/transfers/{id}/reject',               'AAM\\GeneralManager\\Controllers\\GMTransferController@reject',   [CsrfMiddleware::class]);

    // Transfer Receiving Discrepancy Review — GM approves/rejects shop receiving discrepancies
    $r->get('/transfers/discrepancies/pending-review',          'AAM\\GeneralManager\\Controllers\\GMTransferController@discrepanciesPendingReview');
    $r->get('/transfers/{id}/discrepancy-review',               'AAM\\GeneralManager\\Controllers\\GMTransferController@discrepancyReview');
    $r->post('/transfers/{id}/discrepancy-approve',             'AAM\\GeneralManager\\Controllers\\GMTransferController@discrepancyApprove',  [CsrfMiddleware::class]);
    $r->post('/transfers/{id}/discrepancy-reject',              'AAM\\GeneralManager\\Controllers\\GMTransferController@discrepancyReject',   [CsrfMiddleware::class]);

    // Customer Order monitoring (read-only — GM monitors, does NOT process)
    $r->get('/orders',      'AAM\\GeneralManager\\Controllers\\GMOrderController@index');
    $r->get('/orders/{id}', 'AAM\\GeneralManager\\Controllers\\GMOrderController@show');

    // ── Unified Sales Receipts — read-only visibility (POS + E-Commerce) ──
    // IMPORTANT: /receipts/pos/{id} and /receipts/order/{id} must be registered
    // BEFORE the potential wildcard for any future /receipts/{id} route.
    $r->get('/receipts',            'AAM\\GeneralManager\\Controllers\\GMSalesReceiptsController@index');
    $r->get('/receipts/pos/{id}',   'AAM\\GeneralManager\\Controllers\\GMSalesReceiptsController@showPOS');
    $r->get('/receipts/order/{id}', 'AAM\\GeneralManager\\Controllers\\GMSalesReceiptsController@showOrder');

    // Shop monitoring (read-only)
    $r->get('/shops',       'AAM\\GeneralManager\\Controllers\\GMShopController@index');
    $r->get('/shops/sales', 'AAM\\GeneralManager\\Controllers\\GMShopController@sales');

    // Inventory overview (read-only)
    $r->get('/inventory', 'AAM\\GeneralManager\\Controllers\\GMInventoryController@index');

    // Inventory adjustments — GM reviews and approves/rejects pending adjustments
    $r->get('/adjustments',              'AAM\\GeneralManager\\Controllers\\GMInventoryController@adjustments');
    $r->get('/adjustments/pending',      'AAM\\GeneralManager\\Controllers\\GMInventoryController@adjustmentsPending');
    $r->get('/adjustments/{id}',         'AAM\\GeneralManager\\Controllers\\GMInventoryController@adjustmentShow');
    $r->post('/adjustments/{id}/approve','AAM\\GeneralManager\\Controllers\\GMInventoryController@adjustmentApprove', [CsrfMiddleware::class]);
    $r->post('/adjustments/{id}/reject', 'AAM\\GeneralManager\\Controllers\\GMInventoryController@adjustmentReject',  [CsrfMiddleware::class]);

    // Product catalog (read-only — NOT /admin/products)
    $r->get('/products', 'AAM\\GeneralManager\\Controllers\\GMProductController@index');

    // ── Selling Price Management — GENERAL MANAGER ONLY ──────────────────
    // GM is the only role authorised to set / update selling prices.
    // GET  /gm/products/{id}/pricing      → view current price + history
    // POST /gm/products/{id}/pricing      → update the official selling price
    // GET  /gm/products/{id}/price-history → full price history & analytics
    $r->get('/products/{id}/pricing',       'AAM\\GeneralManager\\Controllers\\GMPricingController@pricing');
    $r->post('/products/{id}/pricing',      'AAM\\GeneralManager\\Controllers\\GMPricingController@updatePrice', [CsrfMiddleware::class]);
    $r->get('/products/{id}/price-history', 'AAM\\Admin\\Controllers\\PriceController@history');

    // ── Payment Methods — read-only status view (General Manager) ─────────
    // GM can see which methods are active/inactive. Cannot modify.
    $r->get('/payment-methods', 'AAM\\GeneralManager\\Controllers\\GMPaymentMethodController@index');

    // Reports hub
    $r->get('/reports',       'AAM\\GeneralManager\\Controllers\\GMReportsController@index');
    $r->get('/reports/audit', 'AAM\\GeneralManager\\Controllers\\GMReportsController@audit');

    // Analytics
    $r->get('/analytics',          'AAM\\GeneralManager\\Controllers\\GMAnalyticsController@index');
    $r->get('/analytics/products', 'AAM\\GeneralManager\\Controllers\\GMAnalyticsController@products');

}, [AuthMiddleware::class, new RoleMiddleware([ROLE_GENERAL_MANAGER])]);

// ─── AJAX / API Endpoints (session protected) ────────────────────────────────
$router->group('/api', function($r) {
    $r->get('/products',                    'AAM\\POS\\Controllers\\POSController@searchProducts');
    $r->get('/stock/{product_id}',          'AAM\\Inventory\\Controllers\\InventoryController@checkStock');
    $r->get('/notifications',               'AAM\\Notifications\\Controllers\\NotificationController@apiList');
    $r->post('/notifications/{id}/read',    'AAM\\Notifications\\Controllers\\NotificationController@apiMarkRead', [CsrfMiddleware::class]);
    $r->get('/dashboard/charts',            'AAM\\Dashboard\\Controllers\\DashboardController@chartData');
    $r->get('/dashboard/farm-charts',       'AAM\\Farm\\Controllers\\FarmDashboardController@chartData');
    $r->get('/dashboard/wh-charts',         'AAM\\Dashboard\\Controllers\\DashboardController@warehouseChartData');
    $r->get('/gm/revenue-trend',            'AAM\\GeneralManager\\Controllers\\GMDashboardController@revenueTrend');
    $r->get('/search',                      'AAM\\Admin\\Controllers\\AdminController@globalSearch');
    // Phase 11 — Inventory AJAX
    $r->get('/inventory/search',            'AAM\\Inventory\\Controllers\\InventoryController@search');
    $r->get('/inventory/barcode/{barcode}', 'AAM\\Inventory\\Controllers\\InventoryController@barcodeSearch');
    $r->get('/inventory/stock-check',       'AAM\\Inventory\\Controllers\\InventoryController@stockCheck');
    // Phase 12 — POS AJAX (shop-scoped)
    $r->get('/pos/products',                'AAM\\POS\\Controllers\\POSController@apiProducts');
    $r->get('/pos/stock/{pid}',             'AAM\\POS\\Controllers\\POSController@apiStock');
    $r->get('/pos/categories',              'AAM\\POS\\Controllers\\POSController@apiCategories');
    // Mobile payment status polling (no CSRF — GET poll)
    $r->get('/pos/payment/status',          'AAM\\POS\\Controllers\\POSController@apiPaymentStatus');
    // Payment methods list for POS (active methods only, no credentials)
    $r->get('/pos/payment-methods',         'AAM\\POS\\Controllers\\POSController@apiPaymentMethods');
}, [AuthMiddleware::class]);

// ═════════════════════════════════════════════════════════════════════════════
// PHASE 8B – PRODUCT MANAGEMENT MODULE
// ═════════════════════════════════════════════════════════════════════════════

// ─── Product Categories — SYSTEM ADMINISTRATOR only ───────────────────────────
$router->group('/admin/categories', function($r) {
    $r->get('/',              'AAM\\Admin\\Controllers\\CategoryController@index');
    $r->get('/create',        'AAM\\Admin\\Controllers\\CategoryController@create');
    $r->post('/',             'AAM\\Admin\\Controllers\\CategoryController@store',        [CsrfMiddleware::class]);
    $r->get('/{id}',          'AAM\\Admin\\Controllers\\CategoryController@show');
    $r->get('/{id}/edit',     'AAM\\Admin\\Controllers\\CategoryController@edit');
    $r->post('/{id}/update',  'AAM\\Admin\\Controllers\\CategoryController@update',       [CsrfMiddleware::class]);
    $r->post('/{id}/toggle',  'AAM\\Admin\\Controllers\\CategoryController@toggleStatus', [CsrfMiddleware::class]);
    $r->post('/{id}/delete',  'AAM\\Admin\\Controllers\\CategoryController@delete',       [CsrfMiddleware::class]);
    $r->post('/{id}/restore', 'AAM\\Admin\\Controllers\\CategoryController@restore',      [CsrfMiddleware::class]);
    $r->get('/data/active',   'AAM\\Admin\\Controllers\\CategoryController@getActiveCategories');
}, [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN])]);

// ─── Units of Measurement — SYSTEM ADMINISTRATOR only ─────────────────────────
$router->group('/admin/units', function($r) {
    $r->get('/',              'AAM\\Admin\\Controllers\\UnitController@index');
    $r->get('/create',        'AAM\\Admin\\Controllers\\UnitController@create');
    $r->post('/',             'AAM\\Admin\\Controllers\\UnitController@store',        [CsrfMiddleware::class]);
    $r->get('/{id}/edit',     'AAM\\Admin\\Controllers\\UnitController@edit');
    $r->post('/{id}/update',  'AAM\\Admin\\Controllers\\UnitController@update',       [CsrfMiddleware::class]);
    $r->post('/{id}/toggle',  'AAM\\Admin\\Controllers\\UnitController@toggleStatus', [CsrfMiddleware::class]);
    $r->post('/{id}/delete',  'AAM\\Admin\\Controllers\\UnitController@delete',       [CsrfMiddleware::class]);
    $r->post('/{id}/restore', 'AAM\\Admin\\Controllers\\UnitController@restore',      [CsrfMiddleware::class]);
    $r->get('/data/active',   'AAM\\Admin\\Controllers\\UnitController@getActiveUnits');
    $r->get('/data/grouped',  'AAM\\Admin\\Controllers\\UnitController@getGroupedUnits');
}, [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN])]);

// ─── Products — SYSTEM ADMINISTRATOR only (full CRUD) ────────────────────────
// General Manager uses /gm/products for read-only catalog view.
// /admin/products is ADMIN-ONLY. GM must not access this.
$router->group('/admin/products', function($r) {
    // Read — Admin only
    $r->get('/',                          'AAM\\Admin\\Controllers\\ProductController@index');
    $r->get('/create',                    'AAM\\Admin\\Controllers\\ProductController@create');
    // Export — Admin only
    $r->get('/export/csv',                'AAM\\Admin\\Controllers\\ProductController@exportCsv');
    $r->get('/export/categories-csv',     'AAM\\Admin\\Controllers\\ProductController@exportCategoriesCsv');
    // AJAX data endpoints — Admin only
    $r->get('/data/search',               'AAM\\Admin\\Controllers\\ProductController@search');
    $r->get('/data/barcode',              'AAM\\Admin\\Controllers\\ProductController@barcodeSearch');
    $r->get('/data/price-history/{id}',   'AAM\\Admin\\Controllers\\ProductController@priceHistory');
    // Single product CRUD — Admin only
    $r->get('/{id}',                      'AAM\\Admin\\Controllers\\ProductController@show');
    $r->get('/{id}/edit',                 'AAM\\Admin\\Controllers\\ProductController@edit');
    $r->post('/',                         'AAM\\Admin\\Controllers\\ProductController@store',               [CsrfMiddleware::class]);
    $r->post('/{id}/update',              'AAM\\Admin\\Controllers\\ProductController@update',              [CsrfMiddleware::class]);
    $r->post('/{id}/toggle',              'AAM\\Admin\\Controllers\\ProductController@toggleStatus',        [CsrfMiddleware::class]);
    $r->post('/{id}/toggle-featured',     'AAM\\Admin\\Controllers\\ProductController@toggleFeatured',      [CsrfMiddleware::class]);
    $r->post('/{id}/status',              'AAM\\Admin\\Controllers\\ProductController@updateProductStatus', [CsrfMiddleware::class]);
    $r->post('/{id}/delete',              'AAM\\Admin\\Controllers\\ProductController@delete',              [CsrfMiddleware::class]);
    $r->post('/{id}/restore',             'AAM\\Admin\\Controllers\\ProductController@restore',             [CsrfMiddleware::class]);
    // Images — Admin only
    $r->post('/{id}/images',                          'AAM\\Admin\\Controllers\\ProductController@uploadImage',     [CsrfMiddleware::class]);
    $r->post('/{productId}/images/{imageId}/primary', 'AAM\\Admin\\Controllers\\ProductController@setPrimaryImage', [CsrfMiddleware::class]);
    $r->post('/{productId}/images/{imageId}/delete',  'AAM\\Admin\\Controllers\\ProductController@deleteImage',     [CsrfMiddleware::class]);
    // Price — History view for Admin (read-only). Updating selling price is GM-only via /gm/products/{id}/pricing.
    $r->get('/{id}/price-history',        'AAM\\Admin\\Controllers\\PriceController@history');
    // Admin price-update route is intentionally REMOVED — selling price is managed by General Manager only.
    // Barcode — Admin only
    $r->get('/{id}/barcode/print',        'AAM\\Admin\\Controllers\\BarcodeController@print');
    $r->get('/{id}/barcode/image',        'AAM\\Admin\\Controllers\\BarcodeController@image');
}, [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN])]);

// ═════════════════════════════════════════════════════════════════════════════
// PHASE 8C – ORGANIZATION MANAGEMENT MODULE
// ═════════════════════════════════════════════════════════════════════════════

// ─── Organization — READ (shared: Admin + GM can view) ──────────────────────
$router->group('/admin/organization', function($r) {
    $r->get('/',            'AAM\\Organization\\Controllers\\OrganizationController@dashboard');
    $r->get('/dashboard',   'AAM\\Organization\\Controllers\\OrganizationController@dashboard');
    $r->get('/chart-data',  'AAM\\Organization\\Controllers\\OrganizationController@chartData');

    // ── Farms — read ──────────────────────────────────────────────────────
    $r->get('/farms',                   'AAM\\Organization\\Controllers\\FarmController@index');
    $r->get('/farms/create',            'AAM\\Organization\\Controllers\\FarmController@create');
    $r->get('/farms/export/csv',        'AAM\\Organization\\Controllers\\FarmController@exportCsv');
    $r->get('/farms/export/excel',      'AAM\\Organization\\Controllers\\FarmController@exportExcel');
    $r->get('/farms/export/pdf',        'AAM\\Organization\\Controllers\\FarmController@exportPdf');
    $r->get('/farms/data/search',       'AAM\\Organization\\Controllers\\FarmController@search');
    $r->get('/farms/data/active',       'AAM\\Organization\\Controllers\\FarmController@getActiveFarms');
    $r->get('/farms/{id}',              'AAM\\Organization\\Controllers\\FarmController@show');
    $r->get('/farms/{id}/edit',         'AAM\\Organization\\Controllers\\FarmController@edit');

    // ── Warehouses — read ──────────────────────────────────────────────────
    $r->get('/warehouses',                          'AAM\\Organization\\Controllers\\WarehouseController@index');
    $r->get('/warehouses/create',                   'AAM\\Organization\\Controllers\\WarehouseController@create');
    $r->get('/warehouses/export/csv',               'AAM\\Organization\\Controllers\\WarehouseController@exportCsv');
    $r->get('/warehouses/export/excel',             'AAM\\Organization\\Controllers\\WarehouseController@exportExcel');
    $r->get('/warehouses/export/pdf',               'AAM\\Organization\\Controllers\\WarehouseController@exportPdf');
    $r->get('/warehouses/data/search',              'AAM\\Organization\\Controllers\\WarehouseController@search');
    $r->get('/warehouses/data/active',              'AAM\\Organization\\Controllers\\WarehouseController@getActiveWarehouses');
    $r->get('/warehouses/{id}',                     'AAM\\Organization\\Controllers\\WarehouseController@show');
    $r->get('/warehouses/{id}/edit',                'AAM\\Organization\\Controllers\\WarehouseController@edit');

    // ── Shops — read ───────────────────────────────────────────────────────
    $r->get('/shops',               'AAM\\Organization\\Controllers\\ShopController@index');
    $r->get('/shops/create',        'AAM\\Organization\\Controllers\\ShopController@create');
    $r->get('/shops/export/csv',    'AAM\\Organization\\Controllers\\ShopController@exportCsv');
    $r->get('/shops/export/excel',  'AAM\\Organization\\Controllers\\ShopController@exportExcel');
    $r->get('/shops/export/pdf',    'AAM\\Organization\\Controllers\\ShopController@exportPdf');
    $r->get('/shops/data/search',   'AAM\\Organization\\Controllers\\ShopController@search');
    $r->get('/shops/data/active',   'AAM\\Organization\\Controllers\\ShopController@getActiveShops');
    $r->get('/shops/{id}',          'AAM\\Organization\\Controllers\\ShopController@show');
    $r->get('/shops/{id}/edit',     'AAM\\Organization\\Controllers\\ShopController@edit');

    // ── Assignments — read ─────────────────────────────────────────────────
    $r->get('/assignments',                              'AAM\\Organization\\Controllers\\AssignmentController@index');
    $r->get('/assignments/user/{userId}/history',        'AAM\\Organization\\Controllers\\AssignmentController@userHistory');
    $r->get('/assignments/location/{type}/{id}/history', 'AAM\\Organization\\Controllers\\AssignmentController@locationHistory');
    $r->get('/assignments/data/users-by-role',           'AAM\\Organization\\Controllers\\AssignmentController@getUsersByRole');
    $r->get('/assignments/data/locations-by-type',       'AAM\\Organization\\Controllers\\AssignmentController@getLocationsByType');

}, [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN, ROLE_GENERAL_MANAGER])]);

// ─── Organization — WRITE (System Administrator only) ────────────────────────
$router->group('/admin/organization', function($r) {

    // ── Farms — write ─────────────────────────────────────────────────────
    $r->get('/farms/create',            'AAM\\Organization\\Controllers\\FarmController@create');
    $r->post('/farms',                  'AAM\\Organization\\Controllers\\FarmController@store',        [CsrfMiddleware::class]);
    $r->get('/farms/{id}/edit',         'AAM\\Organization\\Controllers\\FarmController@edit');
    $r->post('/farms/{id}/update',      'AAM\\Organization\\Controllers\\FarmController@update',       [CsrfMiddleware::class]);
    $r->post('/farms/{id}/toggle',      'AAM\\Organization\\Controllers\\FarmController@toggleStatus', [CsrfMiddleware::class]);
    $r->post('/farms/{id}/delete',      'AAM\\Organization\\Controllers\\FarmController@delete',       [CsrfMiddleware::class]);
    $r->post('/farms/{id}/restore',     'AAM\\Organization\\Controllers\\FarmController@restore',      [CsrfMiddleware::class]);

    // ── Warehouses — write ────────────────────────────────────────────────
    $r->get('/warehouses/create',                   'AAM\\Organization\\Controllers\\WarehouseController@create');
    $r->post('/warehouses',                         'AAM\\Organization\\Controllers\\WarehouseController@store',        [CsrfMiddleware::class]);
    $r->get('/warehouses/{id}/edit',                'AAM\\Organization\\Controllers\\WarehouseController@edit');
    $r->post('/warehouses/{id}/update',             'AAM\\Organization\\Controllers\\WarehouseController@update',       [CsrfMiddleware::class]);
    $r->post('/warehouses/{id}/toggle',             'AAM\\Organization\\Controllers\\WarehouseController@toggleStatus', [CsrfMiddleware::class]);
    $r->post('/warehouses/{id}/delete',             'AAM\\Organization\\Controllers\\WarehouseController@delete',       [CsrfMiddleware::class]);
    $r->post('/warehouses/{id}/restore',            'AAM\\Organization\\Controllers\\WarehouseController@restore',      [CsrfMiddleware::class]);

    // ── Shops — write ─────────────────────────────────────────────────────
    $r->get('/shops/create',               'AAM\\Organization\\Controllers\\ShopController@create');
    $r->post('/shops',                     'AAM\\Organization\\Controllers\\ShopController@store',        [CsrfMiddleware::class]);
    $r->get('/shops/{id}/edit',            'AAM\\Organization\\Controllers\\ShopController@edit');
    $r->post('/shops/{id}/update',         'AAM\\Organization\\Controllers\\ShopController@update',       [CsrfMiddleware::class]);
    $r->post('/shops/{id}/toggle',         'AAM\\Organization\\Controllers\\ShopController@toggleStatus', [CsrfMiddleware::class]);
    $r->post('/shops/{id}/delete',         'AAM\\Organization\\Controllers\\ShopController@delete',       [CsrfMiddleware::class]);
    $r->post('/shops/{id}/restore',        'AAM\\Organization\\Controllers\\ShopController@restore',      [CsrfMiddleware::class]);
    $r->post('/shops/{id}/remove-image',   'AAM\\Organization\\Controllers\\ShopController@removeImage',  [CsrfMiddleware::class]);

    // ── Assignments — write ────────────────────────────────────────────────
    $r->post('/assignments',                             'AAM\\Organization\\Controllers\\AssignmentController@store',  [CsrfMiddleware::class]);
    $r->post('/assignments/{id}/remove',                 'AAM\\Organization\\Controllers\\AssignmentController@remove', [CsrfMiddleware::class]);

}, [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN])]);

// ─── Roles & Permissions — SYSTEM ADMINISTRATOR only ─────────────────────────
$router->group('/admin/roles', function($r) {
    $r->get('/',      'AAM\\Admin\\Controllers\\RolesController@index');
    $r->get('/{id}',  'AAM\\Admin\\Controllers\\RolesController@show');
}, [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN])]);

// ─── Barcode utilities — SYSTEM ADMINISTRATOR only ───────────────────────────
$router->group('/admin/barcode', function($r) {
    $r->get('/generate',     'AAM\\Admin\\Controllers\\BarcodeController@generate');
    $r->get('/validate',     'AAM\\Admin\\Controllers\\BarcodeController@validateBarcode');
    $r->get('/render',       'AAM\\Admin\\Controllers\\BarcodeController@renderImage');
    $r->get('/svg',          'AAM\\Admin\\Controllers\\BarcodeController@svg');
    $r->post('/batch-print', 'AAM\\Admin\\Controllers\\BarcodeController@batchPrint', [CsrfMiddleware::class]);
}, [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN])]);

// ─── Price utilities — GENERAL MANAGER only (selling price is a business decision) ──────────────────────
// Admin can VIEW price history but cannot CHANGE selling prices.
$router->post('/admin/prices/bulk-update',
    'AAM\\Admin\\Controllers\\PriceController@bulkUpdate',
    [AuthMiddleware::class, CsrfMiddleware::class, new RoleMiddleware([ROLE_GENERAL_MANAGER])]);
$router->get('/admin/prices/recent',
    'AAM\\Admin\\Controllers\\PriceController@recent',
    [AuthMiddleware::class, new RoleMiddleware([ROLE_ADMIN, ROLE_GENERAL_MANAGER])]);

// ═════════════════════════════════════════════════════════════════════════════
// CUSTOMER MODULE — Public mini e-commerce storefront
// Phase 2: Home page only. No authentication required for browsing.
// Cart, checkout, payment, and orders require customer session (Phase 3+).
// Route namespace: /customer/*
// ═════════════════════════════════════════════════════════════════════════════

$router->get('/customer', 'AAM\\Customer\\Controllers\\HomeController@index');
$router->get('/customer/about', 'AAM\\Customer\\Controllers\\AboutController@index');

// ─── Customer Authentication — Phase Auth ─────────────────────────────────────
// GET  /customer/auth/login   → Show sliding Sign In / Sign Up page
// POST /customer/auth/login   → Process Sign In (CSRF protected)
// POST /customer/auth/register → Process Sign Up (CSRF protected)
// These routes are PUBLIC (no CustomerAuthMiddleware) — guests must reach them.
// If already authenticated, the controller redirects away.
$router->get('/customer/auth/login',     'AAM\\Customer\\Controllers\\AuthController@showLogin');
$router->post('/customer/auth/login',    'AAM\\Customer\\Controllers\\AuthController@login',    [CsrfMiddleware::class]);
$router->post('/customer/auth/register', 'AAM\\Customer\\Controllers\\AuthController@register', [CsrfMiddleware::class]);

// ─── Customer Forgot / Reset Password ────────────────────────────────────────
// All routes are PUBLIC — unauthenticated guests must be able to reach them.
$router->get('/customer/auth/forgot-password',          'AAM\\Customer\\Controllers\\PasswordController@showForgot');
$router->post('/customer/auth/forgot-password',         'AAM\\Customer\\Controllers\\PasswordController@forgot',      [CsrfMiddleware::class]);
$router->get('/customer/auth/verify-otp',               'AAM\\Customer\\Controllers\\PasswordController@showVerifyOtp');
$router->post('/customer/auth/verify-otp',              'AAM\\Customer\\Controllers\\PasswordController@verifyOtp',   [CsrfMiddleware::class]);
$router->get('/customer/auth/reset-password/{token}',   'AAM\\Customer\\Controllers\\PasswordController@showReset');
$router->post('/customer/auth/reset-password/{token}',  'AAM\\Customer\\Controllers\\PasswordController@reset',       [CsrfMiddleware::class]);

// ─── Customer Product Catalog — Phase 3 ──────────────────────────────────────
// IMPORTANT: /customer/products/search must be registered BEFORE /customer/products/{id}
// so the router matches 'search' as a literal segment, not a {id} parameter.
$router->get('/customer/products',        'AAM\\Customer\\Controllers\\ProductController@index');
$router->get('/customer/products/search', 'AAM\\Customer\\Controllers\\ProductController@search');
$router->get('/customer/products/{id}',   'AAM\\Customer\\Controllers\\ProductController@show');

// Stock validation endpoint (called before add-to-cart — server-side guard)
$router->post('/customer/cart/validate-stock', 'AAM\\Customer\\Controllers\\ProductController@validateStock');

// ─── Customer Shop Selector & Availability — Phase 3 ─────────────────────────
$router->get('/customer/shops',                      'AAM\\Customer\\Controllers\\ShopController@index');
$router->post('/customer/shops/select',              'AAM\\Customer\\Controllers\\ShopController@select');
$router->get('/customer/shops/{id}/availability',    'AAM\\Customer\\Controllers\\ShopController@availability');

// ─── Customer Search API (AJAX suggestions) — Phase 3 ────────────────────────
// Endpoint: GET /api/customer/search?q=...&shop_id=...
// Used by search.js for suggestion dropdown. Returns JSON.
$router->get('/api/customer/search', 'AAM\\Customer\\Controllers\\ProductController@apiSearch');

// ─── Customer Cart — Phase 4 (session-based, server-validated, full recheck) ──
// Cart is PUBLIC — guests may add items and view cart without logging in.
// Authentication is only required at Checkout.
$router->get('/customer/cart',                'AAM\\Customer\\Controllers\\CartController@index');
$router->post('/customer/cart/add',           'AAM\\Customer\\Controllers\\CartController@add');
$router->post('/customer/cart/update',        'AAM\\Customer\\Controllers\\CartController@update');
$router->post('/customer/cart/remove',        'AAM\\Customer\\Controllers\\CartController@remove');
$router->post('/customer/cart/clear',         'AAM\\Customer\\Controllers\\CartController@clear');
$router->post('/customer/cart/recheck',       'AAM\\Customer\\Controllers\\CartController@recheck');
$router->post('/customer/cart/check-shop',    'AAM\\Customer\\Controllers\\CartController@checkShop');
$router->post('/customer/cart/change-shop',   'AAM\\Customer\\Controllers\\CartController@changeShop');

// ─── Shop-Side Customer Order Management — Phase 5 ───────────────────────────
// Salesperson sees + processes pickup orders for their assigned shop.
// IMPORTANT: /customer/shop-orders must be registered BEFORE
// /customer/checkout so 'shop-orders' literal is not confused.
$router->group('/customer/shop-orders', function($r) {
    $r->get('/',                         'AAM\\Shop\\Controllers\\CustomerOrderController@queue');
    $r->get('/{id}',                     'AAM\\Shop\\Controllers\\CustomerOrderController@show');
    $r->post('/{id}/prepare',            'AAM\\Shop\\Controllers\\CustomerOrderController@markPreparing', [CsrfMiddleware::class]);
    $r->post('/{id}/ready',              'AAM\\Shop\\Controllers\\CustomerOrderController@markReady',     [CsrfMiddleware::class]);
    $r->post('/{id}/complete',           'AAM\\Shop\\Controllers\\CustomerOrderController@complete',      [CsrfMiddleware::class]);
    $r->post('/{id}/confirm-cash',       'AAM\\Shop\\Controllers\\CustomerOrderController@confirmCash',   [CsrfMiddleware::class]);
}, [AuthMiddleware::class, new RoleMiddleware([ROLE_SALESPERSON, ROLE_GENERAL_MANAGER, ROLE_ADMIN])]);

// ─── Customer Order Search API — Phase 6 ─────────────────────────────────────
// GET /api/customer/shop-orders/search?q=... (AJAX — returns JSON)
// Used by the pickup search modal in the shop order queue.
$router->get('/api/customer/shop-orders/search',
    'AAM\\Shop\\Controllers\\CustomerOrderController@apiSearch',
    [AuthMiddleware::class, new RoleMiddleware([ROLE_SALESPERSON, ROLE_GENERAL_MANAGER, ROLE_ADMIN])]);

// ─── Customer Checkout — Phase 5 + Auth ──────────────────────────────────────
// IMPORTANT: /customer/checkout/success/{ref} must be registered before
// /customer/checkout/{id} so 'success' is not treated as an {id} segment.
//
// ALL checkout routes are protected by CustomerAuthMiddleware.
// Unauthenticated guests are redirected to /customer/auth/login.
// After login, the customer is returned here (intended_url mechanism).
$router->get('/customer/checkout',                  'AAM\\Customer\\Controllers\\CheckoutController@index',   [new CustomerAuthMiddleware()]);
$router->post('/customer/checkout/place',           'AAM\\Customer\\Controllers\\CheckoutController@place',   [new CustomerAuthMiddleware(), CsrfMiddleware::class]);
$router->get('/customer/checkout/payment/{id}',     'AAM\\Customer\\Controllers\\CheckoutController@payment', [new CustomerAuthMiddleware()]);
$router->post('/customer/checkout/verify',          'AAM\\Customer\\Controllers\\CheckoutController@verify',  [new CustomerAuthMiddleware(), CsrfMiddleware::class]);
$router->get('/customer/checkout/success/{ref}',    'AAM\\Customer\\Controllers\\CheckoutController@success', [new CustomerAuthMiddleware()]);
$router->post('/customer/checkout/cancel',          'AAM\\Customer\\Controllers\\CheckoutController@cancel',  [new CustomerAuthMiddleware(), CsrfMiddleware::class]);

// ─── Customer Payment — Phase 5 ──────────────────────────────────────────────
$router->get('/customer/payment/status/{id}',       'AAM\\Customer\\Controllers\\PaymentController@status',     [new CustomerAuthMiddleware()]);
$router->post('/customer/payment/dev-confirm',      'AAM\\Customer\\Controllers\\PaymentController@devConfirm', [new CustomerAuthMiddleware(), CsrfMiddleware::class]);

// ─── Customer Orders — Phase 5 + Auth ────────────────────────────────────────
// All order routes require authentication.
$router->get('/customer/orders',                    'AAM\\Customer\\Controllers\\OrderController@index',   [new CustomerAuthMiddleware()]);
$router->get('/customer/orders/{id}/receipt',       'AAM\\Customer\\Controllers\\OrderController@receipt', [new CustomerAuthMiddleware()]);
$router->get('/customer/orders/{id}',               'AAM\\Customer\\Controllers\\OrderController@show',    [new CustomerAuthMiddleware()]);

// ─── Customer Account — Phase 10 + Auth ──────────────────────────────────────
// IMPORTANT: Specific sub-routes must be registered BEFORE the generic /customer/account
// to prevent 'pickup', 'security', 'help' being treated as {section} segments.
// All account routes require authentication (CustomerAuthMiddleware).
$router->get('/customer/account',                         'AAM\\Customer\\Controllers\\AccountController@index',        [new CustomerAuthMiddleware()]);
$router->get('/customer/account/orders',                  'AAM\\Customer\\Controllers\\AccountController@orders',       [new CustomerAuthMiddleware()]);
$router->get('/customer/account/orders/{id}/receipt',     'AAM\\Customer\\Controllers\\AccountController@orderReceipt', [new CustomerAuthMiddleware()]);
$router->get('/customer/account/orders/{id}',             'AAM\\Customer\\Controllers\\AccountController@orderShow',    [new CustomerAuthMiddleware()]);
$router->get('/customer/account/receipts',                'AAM\\Customer\\Controllers\\AccountController@receipts',     [new CustomerAuthMiddleware()]);
$router->get('/customer/account/favorites',               'AAM\\Customer\\Controllers\\AccountController@favorites',    [new CustomerAuthMiddleware()]);
$router->get('/customer/account/pickup',                  'AAM\\Customer\\Controllers\\AccountController@pickup',       [new CustomerAuthMiddleware()]);
$router->get('/customer/account/security',                'AAM\\Customer\\Controllers\\AccountController@security',     [new CustomerAuthMiddleware()]);
$router->get('/customer/account/help',                    'AAM\\Customer\\Controllers\\AccountController@help',         [new CustomerAuthMiddleware()]);
// Signout is public — guests hitting it should just redirect to home safely
$router->get('/customer/account/signout',                 'AAM\\Customer\\Controllers\\AccountController@signout');
$router->post('/customer/account/profile',                'AAM\\Customer\\Controllers\\AccountController@updateProfile', [new CustomerAuthMiddleware()]);

// ─── Customer Favorites — Phase 6 ────────────────────────────────────────────
// POST /customer/favorites/toggle — AJAX toggle (add/remove) — Auth + CSRF required
$router->post('/customer/favorites/toggle',
    'AAM\\Customer\\Controllers\\FavoritesController@toggle',
    [new CustomerAuthMiddleware(), CsrfMiddleware::class]);

// ═════════════════════════════════════════════════════════════════════════════
// SEED DISTRIBUTION MODULE
// Warehouse-side: /seed-distribution/*  → WAREHOUSE_MANAGER only
//                 + warehouse must have seed_distribution_enabled = 1
// GM-side: /gm/seed-distribution/*      → GENERAL_MANAGER only
// ═════════════════════════════════════════════════════════════════════════════

// ─── Warehouse Seed Distribution — WAREHOUSE MANAGER only ────────────────────
// Server-side capability check is inside the controller (checkWarehouseAccess).
$router->group('/seed-distribution', function($r) {
    // Available seeds view
    $r->get('/seeds',        'AAM\\SeedDistribution\\Controllers\\SeedDistributionController@seeds');
    // New distribution form & store
    $r->get('/new',          'AAM\\SeedDistribution\\Controllers\\SeedDistributionController@createForm');
    $r->post('/',            'AAM\\SeedDistribution\\Controllers\\SeedDistributionController@store',          [CsrfMiddleware::class]);
    // Status views
    $r->get('/pending',      'AAM\\SeedDistribution\\Controllers\\SeedDistributionController@pending');
    $r->get('/approved',     'AAM\\SeedDistribution\\Controllers\\SeedDistributionController@approved');
    $r->get('/released',     'AAM\\SeedDistribution\\Controllers\\SeedDistributionController@released');
    $r->get('/history',      'AAM\\SeedDistribution\\Controllers\\SeedDistributionController@history');
    // AJAX: release, cancel, grades
    $r->post('/{id}/release','AAM\\SeedDistribution\\Controllers\\SeedDistributionController@release',        [CsrfMiddleware::class]);
    $r->post('/{id}/cancel', 'AAM\\SeedDistribution\\Controllers\\SeedDistributionController@cancel',         [CsrfMiddleware::class]);
    $r->post('/{id}/complete','AAM\\SeedDistribution\\Controllers\\SeedDistributionController@complete',       [CsrfMiddleware::class]);
    $r->get('/api/grades',   'AAM\\SeedDistribution\\Controllers\\SeedDistributionController@gradesForProduct');
    $r->get('/api/price',    'AAM\\SeedDistribution\\Controllers\\SeedDistributionController@getPrice');
    // Receipt (must come before {id} wildcard)
    $r->get('/{id}/receipt', 'AAM\\SeedDistribution\\Controllers\\SeedDistributionController@receipt');
    // Single record (must come last — {id} is a wildcard)
    $r->get('/{id}',         'AAM\\SeedDistribution\\Controllers\\SeedDistributionController@show');
}, [AuthMiddleware::class, new RoleMiddleware([ROLE_WAREHOUSE_MANAGER])]);

// ─── GM Seed Distribution — GENERAL_MANAGER only ─────────────────────────────
$router->group('/gm/seed-distribution', function($r) {
    // Overview
    $r->get('/',                         'AAM\\SeedDistribution\\Controllers\\GMSeedDistributionController@overview');
    // Seed products + grades management
    $r->get('/products',                 'AAM\\SeedDistribution\\Controllers\\GMSeedDistributionController@products');
    $r->post('/grades',                  'AAM\\SeedDistribution\\Controllers\\GMSeedDistributionController@storeGrade',        [CsrfMiddleware::class]);
    $r->post('/grades/{id}/toggle',      'AAM\\SeedDistribution\\Controllers\\GMSeedDistributionController@toggleGrade',       [CsrfMiddleware::class]);
    $r->post('/products/{id}/toggle-seed','AAM\\SeedDistribution\\Controllers\\GMSeedDistributionController@toggleProductSeed',[CsrfMiddleware::class]);
    // Seed stock
    $r->get('/stock',                    'AAM\\SeedDistribution\\Controllers\\GMSeedDistributionController@stock');
    // Distribution monitor
    $r->get('/distributions',            'AAM\\SeedDistribution\\Controllers\\GMSeedDistributionController@distributions');
    $r->get('/pending',                  'AAM\\SeedDistribution\\Controllers\\GMSeedDistributionController@pending');
    // Approve / Reject
    $r->post('/{id}/approve',            'AAM\\SeedDistribution\\Controllers\\GMSeedDistributionController@approve',           [CsrfMiddleware::class]);
    $r->post('/{id}/reject',             'AAM\\SeedDistribution\\Controllers\\GMSeedDistributionController@reject',            [CsrfMiddleware::class]);
    // Distribution history
    $r->get('/history',                  'AAM\\SeedDistribution\\Controllers\\GMSeedDistributionController@history');
    // Single record (must come last)
    $r->get('/{id}',                     'AAM\\SeedDistribution\\Controllers\\GMSeedDistributionController@show');
}, [AuthMiddleware::class, new RoleMiddleware([ROLE_GENERAL_MANAGER])]);
