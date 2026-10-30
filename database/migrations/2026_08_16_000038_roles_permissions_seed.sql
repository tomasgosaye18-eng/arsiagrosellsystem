-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_16_000038_roles_permissions_seed.sql
-- Purpose   : Fix role_permissions for all 5 active roles.
--
--             PROBLEM: Earlier migrations seeded Farm Manager,
--             Warehouse Manager, and Salesperson with minimal
--             or no permissions. General Manager was given ALL
--             permissions (including system admin) which is
--             incorrect per the role separation requirement.
--
--             SOLUTION: Re-seed all 5 roles using ONLY the
--             permission codes that already exist in the DB
--             (codes seeded in migrations 11-21).
--
--             After this migration:
--               System Administrator → system admin permissions only
--               General Manager      → business permissions only
--               Farm Manager         → own farm operations only
--               Warehouse Manager    → own warehouse operations only
--               Salesperson          → own shop POS only
--
-- SAFE TO RE-RUN:
--   - Adds missing permissions with INSERT IGNORE
--   - Uses DELETE + INSERT for role_permissions (clean re-seed)
--   - Does NOT delete any users, roles, or other data
-- =========================================================

USE `aam_db`;

-- =========================================================
-- STEP 1: ADD ALL CORE PERMISSION CODES
-- Inserts the complete permission set needed by all roles.
-- Uses INSERT IGNORE — safe if already present.
-- These cover all codes from migrations 11 through 37.
-- =========================================================

INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES

-- ── Auth ──────────────────────────────────────────────────
('auth.login',                   'auth', 'Login',                    'Can log into the system',                               1),
('auth.password.change.own',     'auth', 'Change Own Password',      'Can change own password',                               1),
('auth.password.reset.any',      'auth', 'Reset Any Password',       'Can reset any user password',                           1),

-- ── Admin — Users ─────────────────────────────────────────
('admin.users.view',             'admin', 'View Users',              'Can view list of all users',                            1),
('admin.users.create',           'admin', 'Create Users',            'Can create new user accounts',                          1),
('admin.users.edit',             'admin', 'Edit Users',              'Can edit user details and role',                        1),
('admin.users.deactivate',       'admin', 'Deactivate Users',        'Can deactivate/activate user accounts',                 1),
('admin.users.unlock',           'admin', 'Unlock Users',            'Can unlock locked user accounts',                       1),
('admin.users.reset_password',   'admin', 'Reset User Password',     'Can reset any user password via admin panel',           1),
('admin.users.assign_location',  'admin', 'Assign User to Location', 'Can assign users to farms/warehouses/shops',            1),
('admin.login_history.view',     'admin', 'View Login History',      'Can view user login history',                           1),
('admin.roles.view',             'admin', 'View Roles & Permissions','Can view roles and permission assignments',              1),

-- ── Admin — Products ──────────────────────────────────────
('admin.products.view',          'admin', 'View Products',           'Can view product catalog',                              1),
('admin.products.create',        'admin', 'Create Products',         'Can add new products to catalog',                       1),
('admin.products.edit',          'admin', 'Edit Products',           'Can edit product details',                              1),
('admin.products.deactivate',    'admin', 'Deactivate Products',     'Can deactivate products',                               1),
('admin.products.price.change',  'admin', 'Change Prices',           'Can change product selling prices',                     1),
('admin.categories.manage',      'admin', 'Manage Categories',       'Can manage product categories',                         1),
('admin.units.manage',           'admin', 'Manage Units',            'Can manage units of measure',                           1),

-- ── Admin — Organization ──────────────────────────────────
('admin.organization.view',      'admin', 'View Organization',       'Can view organization structure',                       1),
('admin.organization.manage',    'admin', 'Manage Organization',     'Can manage farms, warehouses, shops, assignments',      1),

-- ── Admin — System ────────────────────────────────────────
('admin.audit.view',             'admin', 'View Audit Trail',        'Can view audit log records',                            1),
('admin.audit.export',           'admin', 'Export Audit Trail',      'Can export audit log records',                          1),
('admin.settings.view',          'admin', 'View Settings',           'Can view system settings',                              1),
('admin.settings.edit',          'admin', 'Edit Settings',           'Can modify system settings',                            1),
('admin.backup.create',          'admin', 'Create Backup',           'Can trigger database backup',                           1),
('admin.backup.restore',         'admin', 'Restore Backup',          'Can restore from backup',                               1),
('admin.maintenance.manage',     'admin', 'Manage Maintenance Mode', 'Can enable/disable maintenance mode',                   1),
('admin.reports.users',          'reports','User Reports',           'Can view user management reports',                      1),
('admin.reports.organization',   'reports','Organization Reports',   'Can view organization structure reports',               1),

-- ── Farm ──────────────────────────────────────────────────
('farm.list.view',               'farm', 'View Farms List',          'Can view list of farms',                                1),
('farm.own.view',                'farm', 'View Own Farm',            'Can view own assigned farm details',                    1),
('farm.harvest.create',          'farm', 'Create Harvest',           'Can register new harvest records',                      1),
('farm.harvest.view',            'farm', 'View Harvests',            'Can view harvest history',                              1),
('farm.dispatch.create',         'farm', 'Create Dispatch',          'Can initiate product dispatches to warehouse',          1),
('farm.dispatch.view',           'farm', 'View Dispatches',          'Can view dispatch history and status',                  1),
('farm.inventory.view',          'farm', 'View Farm Inventory',      'Can view farm inventory levels',                        1),
('farm.products.view',           'farm', 'View Assigned Products',   'Can view products assigned to their farm',              1),

-- ── GM Approvals — Farm ───────────────────────────────────
('gm.dispatch.approve',          'farm', 'Approve Dispatch',         'Can approve farm dispatch requests',                    1),
('gm.dispatch.reject',           'farm', 'Reject Dispatch',          'Can reject farm dispatch requests',                     1),

-- ── Warehouse ─────────────────────────────────────────────
('warehouse.inventory.view',     'warehouse', 'View WH Inventory',      'Can view warehouse inventory',                       1),
('warehouse.receiving.view',     'warehouse', 'View Receiving',         'Can view pending and completed receiving records',   1),
('warehouse.receiving.confirm',  'warehouse', 'Confirm Receiving',      'Can confirm receipt of farm dispatches',             1),
('warehouse.adjustment.request', 'warehouse', 'Request Adjustment',     'Can request inventory adjustments',                  1),
('warehouse.adjustment.approve', 'warehouse', 'Approve Adjustment',     'Can approve inventory adjustment requests',          1),
('warehouse.discrepancy.view',   'warehouse', 'View Discrepancies',     'Can view receiving discrepancy records',             1),
('gm.receiving.discrepancy.review','warehouse','Review Receiving Discrepancy','Can review and approve/reject receiving discrepancies', 1),

-- ── Transfer ──────────────────────────────────────────────
('transfer.view',                'transfer', 'View Transfers',          'Can view transfer list and detail',                  1),
('transfer.create',              'transfer', 'Create Transfer',         'Can create a new warehouse-to-shop transfer',        1),
('transfer.approve',             'transfer', 'Approve Transfer',        'Can approve a pending transfer',                     1),
('transfer.dispatch',            'transfer', 'Dispatch Transfer',       'Can dispatch an approved transfer',                  1),
('transfer.cancel',              'transfer', 'Cancel Transfer',         'Can cancel a pending or approved transfer',          1),
('transfer.receive',             'transfer', 'Receive Transfer',        'Can confirm receipt of an in-transit transfer',      1),
('transfer.discrepancy.report',  'transfer', 'Report Discrepancy',      'Can record a quantity discrepancy on receipt',       1),
('transfer.view.incoming',       'transfer', 'View Incoming Transfers', 'Can view transfers addressed to own shop',           1),
('transfer.view.all',            'transfer', 'View All Transfers',      'Can view all transfers across all locations',        1),
('transfer.view.own.shop',       'transfer', 'View Own Shop Transfers', 'Can view transfers for own shop',                    1),
('transfer.discrepancy.view',    'transfer', 'View Transfer Discrepancy','Can view transfer discrepancies',                   1),
('gm.transfer.approve',          'transfer', 'Approve Transfer (GM)',   'Can approve warehouse-to-shop transfers',            1),
('gm.transfer.reject',           'transfer', 'Reject Transfer (GM)',    'Can reject warehouse-to-shop transfers',             1),
('gm.transfer.discrepancy.review','transfer','Review Transfer Discrepancy','Can review and approve/reject transfer discrepancies', 1),

-- ── Shop ──────────────────────────────────────────────────
('shop.list.view',               'shop', 'View All Shops',             'Can view list of all shops',                         1),
('shop.own.view',                'shop', 'View Own Shop',              'Can view own assigned shop details',                  1),
('shop.view',                    'shop', 'View Shop',                  'Can view own shop details and overview',              1),
('shop.inventory.view',          'shop', 'View Shop Inventory',        'Can view own shop inventory',                         1),
('shop.sales.view.own',          'shop', 'View Own Shop Sales',        'Can view sales history at own shop',                  1),
('shop.sales.view.all',          'shop', 'View All Shop Sales',        'Can view sales history at all shops',                 1),
('shop.sales.view',              'shop', 'View Shop Sales',            'Can view shop sales (all sellers)',                   1),
('shop.sales.manage',            'shop', 'Manage Shop Sales',          'Can view/void all shop sales (manager role)',         1),
('shop.receiving.view',          'shop', 'View Incoming Transfers',    'Can view transfers in-transit to shop',               1),
('shop.receiving.confirm',       'shop', 'Confirm Transfer Receipt',   'Can confirm receipt of an in-transit transfer',       1),
('shop.discrepancy.view',        'shop', 'View Discrepancies',         'Can view shop discrepancy reports',                   1),
('shop.discrepancy.report',      'shop', 'Report Discrepancy',         'Can report quantity discrepancy on receipt',          1),
('shop.movements.view',          'shop', 'View Stock Movements',       'Can view shop stock movement history',                1),
('shop.adjustment.request',      'shop', 'Request Shop Adjustment',    'Can request inventory adjustments for own shop',      1),

-- ── POS ───────────────────────────────────────────────────
('pos.terminal',                 'pos', 'Access POS Terminal',         'Can open and use the POS terminal',                   1),
('pos.sale.create',              'pos', 'Create Sale',                 'Can process a cash sale transaction',                 1),
('pos.sale.view',                'pos', 'View Sales',                  'Can view own sales history',                          1),
('pos.sale.view.own',            'pos', 'View Own Sales',              'Can view own sales history',                          1),
('pos.sale.void',                'pos', 'Void Sale',                   'Can void a completed sale',                           1),
('pos.receipt.view',             'pos', 'View Receipt',                'Can view and print sale receipts',                    1),
('pos.receipt.reprint',          'pos', 'Reprint Receipt',             'Can reprint a previously issued receipt',             1),
('pos.return.create',            'pos', 'Process Return',              'Can process a sale return',                           1),
('pos.shift.open',               'pos', 'Open Shift',                  'Can open a seller shift',                             1),
('pos.shift.close',              'pos', 'Close Shift',                 'Can close own shift',                                 1),
('pos.shift.view',               'pos', 'View Shifts',                 'Can view own shift history',                          1),

-- ── Reports ───────────────────────────────────────────────
('reports.farm.view',            'reports', 'Farm Reports',            'Can generate and view farm production reports',       1),
('reports.warehouse.view',       'reports', 'Warehouse Reports',       'Can generate and view warehouse inventory reports',   1),
('reports.transfer.view',        'reports', 'Transfer Reports',        'Can generate and view transfer reports',              1),
('reports.sales.view.own',       'reports', 'Own Shop Sales Reports',  'Can generate sales reports for own shop',             1),
('reports.sales.view.all',       'reports', 'All Sales Reports',       'Can generate sales reports for all shops',            1),
('reports.revenue.view',         'reports', 'Revenue Reports',         'Can generate and view revenue reports',               1),
('reports.discrepancy.view',     'reports', 'Discrepancy Reports',     'Can view discrepancy reports',                        1),
('reports.user.activity.view',   'reports', 'User Activity Reports',   'Can view user activity reports',                      1),
('reports.export',               'reports', 'Export Reports',          'Can export reports to PDF and CSV',                   1),

-- ── Dashboard ─────────────────────────────────────────────
('dashboard.admin.view',         'dashboard', 'Admin Dashboard',       'Can view admin executive dashboard',                  1),
('dashboard.gm.view',            'dashboard', 'GM Dashboard',          'Can view general manager executive dashboard',        1),
('dashboard.farm.view',          'dashboard', 'Farm Dashboard',        'Can view farm manager dashboard',                     1),
('dashboard.warehouse.view',     'dashboard', 'Warehouse Dashboard',   'Can view warehouse manager dashboard',                1),
('dashboard.shop.view',          'dashboard', 'Shop Dashboard',        'Can view shop dashboard',                             1),
('dashboard.cashier.view',       'dashboard', 'Cashier Dashboard',     'Can view cashier summary dashboard',                  1),
('gm.analytics.view',            'dashboard', 'View Business Analytics','Can view business analytics and charts',             1),

-- ── Inventory ─────────────────────────────────────────────
('inventory.overview',           'inventory', 'Inventory Overview',    'Can view inventory overview dashboard',               1),
('inventory.view.warehouse',     'inventory', 'View Warehouse Inventory','Can view warehouse location inventory',              1),
('inventory.view.shop',          'inventory', 'View Shop Inventory',   'Can view shop location inventory',                    1),
('inventory.view.all',           'inventory', 'View All Inventory',    'Can view inventory across all locations',             1),
('inventory.batch.view',         'inventory', 'View Batches',          'Can view batch tracking records',                     1),
('inventory.movements.view',     'inventory', 'View Stock Movements',  'Can view stock movement ledger',                      1),
('inventory.adjustment.request', 'inventory', 'Request Adjustment',    'Can submit stock adjustment requests',                1),
('inventory.adjustment.approve', 'inventory', 'Approve Adjustment',    'Can approve or reject stock adjustments',             1),
('inventory.adjustment.view',    'inventory', 'View Adjustments',      'Can view stock adjustment history',                   1),
('inventory.count.create',       'inventory', 'Create Stock Count',    'Can start a new physical stock count',                1),
('inventory.count.enter',        'inventory', 'Enter Stock Count',     'Can enter physical quantities in a count',            1),
('inventory.count.approve',      'inventory', 'Approve Stock Count',   'Can approve a completed stock count',                 1),
('inventory.count.view',         'inventory', 'View Stock Counts',     'Can view stock count history',                        1),
('gm.inventory.adjustments.approve','inventory','Approve Inventory Adjustments','Can approve/reject inventory adjustment requests', 1),

-- ── Notifications ─────────────────────────────────────────
('notifications.view',           'notifications', 'View Notifications', 'Can view own notifications',                        1),

-- ── GM Pricing ────────────────────────────────────────────
('gm.price.manage',              'admin', 'Manage Selling Prices',     'Can set and update product selling prices',           1),

-- ── Settings / Payment Methods ────────────────────────────
('settings.payment_methods.view',   'settings', 'View Payment Methods',   'Can view the payment methods management page',    1),
('settings.payment_methods.manage', 'settings', 'Manage Payment Methods', 'Can activate/deactivate and configure payment methods', 1);

-- =========================================================
-- STEP 2: SYSTEM ADMINISTRATOR (role_id = 1)
-- Gets ALL permissions — system + business oversight.
-- The route layer restricts which business workflows admin
-- can access; the permission layer grants full admin scope.
-- =========================================================

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions` WHERE `is_active` = 1;

-- =========================================================
-- STEP 3: GENERAL MANAGER (role_id = 6)
-- Business oversight ONLY. No user management, no backup,
-- no system config, no payment method configuration.
-- GM approves dispatches, transfers, discrepancies.
-- =========================================================

DELETE FROM `role_permissions` WHERE `role_id` = 6;

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, p.`id` FROM `permissions` p
WHERE p.`is_active` = 1
  AND p.`code` IN (
    -- Auth
    'auth.login',
    'auth.password.change.own',
    -- Dashboard
    'dashboard.gm.view',
    -- Farm oversight & approval
    'farm.list.view',
    'farm.harvest.view',
    'farm.dispatch.view',
    'farm.inventory.view',
    'gm.dispatch.approve',
    'gm.dispatch.reject',
    -- Warehouse oversight
    'warehouse.inventory.view',
    'warehouse.receiving.view',
    'warehouse.discrepancy.view',
    'warehouse.adjustment.approve',
    'gm.receiving.discrepancy.review',
    -- Transfers (view + approve/reject via GM workflow)
    'transfer.view',
    'transfer.view.all',
    'transfer.discrepancy.view',
    'transfer.discrepancy.report',
    'gm.transfer.approve',
    'gm.transfer.reject',
    'gm.transfer.discrepancy.review',
    -- Inventory
    'inventory.overview',
    'inventory.view.all',
    'inventory.view.warehouse',
    'inventory.batch.view',
    'inventory.movements.view',
    'inventory.adjustment.view',
    'inventory.adjustment.approve',
    'gm.inventory.adjustments.approve',
    -- Shops (view only)
    'shop.list.view',
    'shop.view',
    'shop.inventory.view',
    'shop.sales.view',
    'shop.sales.view.all',
    'shop.sales.manage',
    'shop.receiving.view',
    'shop.discrepancy.view',
    'shop.movements.view',
    -- POS (monitoring only — GM can view, not operate)
    'pos.sale.view',
    'pos.receipt.view',
    'pos.shift.view',
    -- Products & pricing
    'admin.products.view',
    'gm.price.manage',
    -- Organization (view only)
    'admin.organization.view',
    -- Reports
    'reports.farm.view',
    'reports.warehouse.view',
    'reports.transfer.view',
    'reports.sales.view.all',
    'reports.revenue.view',
    'reports.discrepancy.view',
    'reports.export',
    'admin.reports.organization',
    -- Analytics
    'gm.analytics.view',
    -- Audit (view only)
    'admin.audit.view',
    'admin.audit.export',
    -- Payment methods (view status only — cannot configure)
    'settings.payment_methods.view',
    -- Notifications
    'notifications.view'
);

-- =========================================================
-- STEP 4: FARM MANAGER (role_id = 2)
-- Own assigned farm only. Location enforced in controller
-- via user_location_assignments / organization_assignments.
-- Cannot approve, cannot touch warehouse/shop/POS.
-- =========================================================

DELETE FROM `role_permissions` WHERE `role_id` = 2;

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, p.`id` FROM `permissions` p
WHERE p.`is_active` = 1
  AND p.`code` IN (
    -- Auth
    'auth.login',
    'auth.password.change.own',
    -- Dashboard
    'dashboard.farm.view',
    -- Farm operations (own assigned farm only)
    'farm.own.view',
    'farm.harvest.create',
    'farm.harvest.view',
    'farm.dispatch.create',
    'farm.dispatch.view',
    'farm.inventory.view',
    -- Assigned products view (not global catalog)
    'farm.products.view',
    -- Inventory (read-only overview)
    'inventory.overview',
    'inventory.movements.view',
    'inventory.batch.view',
    -- Reports (farm only)
    'reports.farm.view',
    -- Notifications
    'notifications.view'
);

-- =========================================================
-- STEP 5: WAREHOUSE MANAGER (role_id = 3)
-- Own assigned warehouse only. Location enforced in controller.
-- Can create/dispatch/cancel transfers (not approve — GM approves).
-- Cannot approve own discrepancies — GM approves those.
-- =========================================================

DELETE FROM `role_permissions` WHERE `role_id` = 3;

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, p.`id` FROM `permissions` p
WHERE p.`is_active` = 1
  AND p.`code` IN (
    -- Auth
    'auth.login',
    'auth.password.change.own',
    -- Dashboard
    'dashboard.warehouse.view',
    -- Warehouse (own assigned warehouse)
    'warehouse.inventory.view',
    'warehouse.receiving.view',
    'warehouse.receiving.confirm',
    'warehouse.discrepancy.view',
    'warehouse.adjustment.request',
    -- Transfers (create + dispatch + cancel — approve is GM's job)
    'transfer.view',
    'transfer.view.all',
    'transfer.create',
    'transfer.dispatch',
    'transfer.cancel',
    'transfer.discrepancy.view',
    'transfer.discrepancy.report',
    -- Inventory
    'inventory.overview',
    'inventory.view.warehouse',
    'inventory.view.all',
    'inventory.batch.view',
    'inventory.movements.view',
    'inventory.adjustment.request',
    'inventory.adjustment.view',
    'inventory.count.create',
    'inventory.count.enter',
    'inventory.count.view',
    -- Products (view only)
    'admin.products.view',
    -- Reports
    'reports.warehouse.view',
    'reports.transfer.view',
    'reports.discrepancy.view',
    -- Notifications
    'notifications.view'
);

-- =========================================================
-- STEP 6: SALESPERSON (role_id = 5)
-- Own assigned shop POS only. Location enforced in controller.
-- Can receive transfers at shop and report discrepancies.
-- Cannot create products, set prices, manage users or org.
-- =========================================================

DELETE FROM `role_permissions` WHERE `role_id` = 5;

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, p.`id` FROM `permissions` p
WHERE p.`is_active` = 1
  AND p.`code` IN (
    -- Auth
    'auth.login',
    'auth.password.change.own',
    -- Dashboard
    'dashboard.cashier.view',
    -- Shop (own assigned shop)
    'shop.own.view',
    'shop.view',
    'shop.inventory.view',
    'shop.sales.view.own',
    'shop.sales.view',
    'shop.receiving.view',
    'shop.receiving.confirm',
    'shop.discrepancy.view',
    'shop.discrepancy.report',
    'shop.movements.view',
    'shop.adjustment.request',
    -- POS operations
    'pos.terminal',
    'pos.sale.create',
    'pos.sale.view',
    'pos.sale.view.own',
    'pos.receipt.view',
    'pos.receipt.reprint',
    'pos.shift.open',
    'pos.shift.close',
    'pos.shift.view',
    -- Transfer receiving (accept incoming from warehouse)
    'transfer.view.own.shop',
    'transfer.view.incoming',
    'transfer.receive',
    'transfer.discrepancy.report',
    -- Reports
    'reports.sales.view.own',
    -- Notifications
    'notifications.view'
);

-- =========================================================
-- VERIFICATION — shows permission count per role
-- =========================================================
SELECT
    r.`id`,
    r.`name`,
    r.`display_name`,
    COUNT(rp.`permission_id`) AS `permissions_granted`
FROM `roles` r
LEFT JOIN `role_permissions` rp ON rp.`role_id` = r.`id`
WHERE r.`is_active` = 1
GROUP BY r.`id`, r.`name`, r.`display_name`
ORDER BY r.`id`;
