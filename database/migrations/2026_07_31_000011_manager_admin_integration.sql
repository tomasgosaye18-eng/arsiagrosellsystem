-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_07_31_000011_manager_admin_integration.sql
-- Purpose   : Upgrade General Manager (role id=6) from
--             read-only to full Business Management +
--             System Administration access.
--
--             The General Manager is the unified role that
--             controls both business operations and system
--             administration. Future RBAC separation will
--             only require role/permission changes — not
--             module rewrites.
--
-- Run after : 2026_07_31_000010_phase9a_farm_module.sql
-- =========================================================

USE aam_db;

-- =========================================================
-- 1. UPDATE role description to reflect unified responsibility
-- =========================================================
UPDATE `roles`
SET
    `display_name` = 'General Manager',
    `description`  = 'Full business management and system administration access'
WHERE `name` = 'general_manager'
  AND `id` = 6;

-- =========================================================
-- 2. ENSURE all permission codes exist
--    (safe INSERT IGNORE — no duplicates)
-- =========================================================
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
-- Auth
('auth.login',                  'auth',         'Login',                   'Can log into the system',                              1),
('auth.password.change.own',    'auth',         'Change Own Password',     'Can change own password',                              1),
('auth.password.reset.any',     'auth',         'Reset Any Password',      'Can reset any user password',                          1),
-- Admin — Users
('admin.users.view',            'admin',        'View Users',              'Can view list of all users',                           1),
('admin.users.create',          'admin',        'Create Users',            'Can create new user accounts',                         1),
('admin.users.edit',            'admin',        'Edit Users',              'Can edit user details and role',                       1),
('admin.users.deactivate',      'admin',        'Deactivate Users',        'Can deactivate/activate user accounts',                1),
('admin.users.unlock',          'admin',        'Unlock Users',            'Can unlock locked user accounts',                      1),
-- Admin — Products
('admin.products.view',         'admin',        'View Products',           'Can view product catalog',                             1),
('admin.products.create',       'admin',        'Create Products',         'Can add new products to catalog',                      1),
('admin.products.edit',         'admin',        'Edit Products',           'Can edit product details',                             1),
('admin.products.deactivate',   'admin',        'Deactivate Products',     'Can deactivate products',                              1),
('admin.products.price.change', 'admin',        'Change Prices',           'Can change product selling prices',                    1),
('admin.categories.manage',     'admin',        'Manage Categories',       'Can manage product categories',                        1),
('admin.units.manage',          'admin',        'Manage Units',            'Can manage units of measure',                          1),
-- Admin — Organization
('admin.organization.view',     'admin',        'View Organization',       'Can view organization structure',                      1),
('admin.organization.manage',   'admin',        'Manage Organization',     'Can manage farms, warehouses, shops, assignments',     1),
-- Admin — System
('admin.audit.view',            'admin',        'View Audit Trail',        'Can view audit log records',                           1),
('admin.audit.export',          'admin',        'Export Audit Trail',      'Can export audit log records',                         1),
('admin.settings.view',         'admin',        'View Settings',           'Can view system settings',                             1),
('admin.settings.edit',         'admin',        'Edit Settings',           'Can modify system settings',                           1),
('admin.backup.create',         'admin',        'Create Backup',           'Can trigger database backup',                          1),
('admin.backup.restore',        'admin',        'Restore Backup',          'Can restore from backup',                              1),
('admin.maintenance.manage',    'admin',        'Manage Maintenance Mode', 'Can enable/disable maintenance mode',                  1),
-- Farm module
('farm.list.view',              'farm',         'View Farms List',         'Can view list of farms',                               1),
('farm.own.view',               'farm',         'View Own Farm',           'Can view own assigned farm details',                   1),
('farm.harvest.create',         'farm',         'Create Harvest',          'Can register new harvest records',                     1),
('farm.harvest.view',           'farm',         'View Harvests',           'Can view harvest history',                             1),
('farm.dispatch.create',        'farm',         'Create Dispatch',         'Can initiate product dispatches to warehouse',         1),
('farm.dispatch.view',          'farm',         'View Dispatches',         'Can view dispatch history and status',                 1),
('farm.inventory.view',         'farm',         'View Farm Inventory',     'Can view farm inventory levels',                       1),
-- Warehouse module
('warehouse.inventory.view',    'warehouse',    'View WH Inventory',       'Can view warehouse inventory',                         1),
('warehouse.receiving.view',    'warehouse',    'View Receiving',          'Can view pending and completed receiving records',      1),
('warehouse.receiving.confirm', 'warehouse',    'Confirm Receiving',       'Can confirm receipt of farm dispatches',               1),
('warehouse.adjustment.request','warehouse',    'Request Adjustment',      'Can request inventory adjustments',                    1),
('warehouse.adjustment.approve','warehouse',    'Approve Adjustment',      'Can approve inventory adjustment requests',            1),
('warehouse.discrepancy.view',  'warehouse',    'View Discrepancies',      'Can view receiving discrepancy records',               1),
-- Transfer module
('transfer.create',             'transfer',     'Create Transfer',         'Can create transfer orders',                           1),
('transfer.view.all',           'transfer',     'View All Transfers',      'Can view all transfer orders',                         1),
('transfer.view.own.shop',      'transfer',     'View Own Shop Transfers', 'Can view transfers for own shop',                      1),
('transfer.confirm',            'transfer',     'Confirm Transfer',        'Can confirm receipt of a transfer',                    1),
('transfer.discrepancy.view',   'transfer',     'View Transfer Discrepancy','Can view transfer discrepancies',                     1),
-- Shop module
('shop.list.view',              'shop',         'View All Shops',          'Can view list of all shops',                           1),
('shop.own.view',               'shop',         'View Own Shop',           'Can view own assigned shop details',                   1),
('shop.inventory.view',         'shop',         'View Shop Inventory',     'Can view own shop inventory levels',                   1),
('shop.sales.view.own',         'shop',         'View Own Shop Sales',     'Can view sales history at own shop',                   1),
('shop.sales.view.all',         'shop',         'View All Shop Sales',     'Can view sales history at all shops',                  1),
('shop.adjustment.request',     'shop',         'Request Shop Adjustment', 'Can request inventory adjustments for own shop',       1),
-- POS module
('pos.sale.create',             'pos',          'Create Sale',             'Can process new cash sales at POS terminal',           1),
('pos.sale.view.own',           'pos',          'View Own Sales',          'Can view own sales history',                           1),
('pos.sale.void',               'pos',          'Void Sale',               'Can void a completed sale',                            1),
('pos.return.create',           'pos',          'Process Return',          'Can process a sale return',                            1),
('pos.receipt.reprint',         'pos',          'Reprint Receipt',         'Can reprint a previously issued receipt',              1),
-- Reports module
('reports.farm.view',           'reports',      'Farm Reports',            'Can generate and view farm production reports',        1),
('reports.warehouse.view',      'reports',      'Warehouse Reports',       'Can generate and view warehouse inventory reports',    1),
('reports.transfer.view',       'reports',      'Transfer Reports',        'Can generate and view transfer reports',               1),
('reports.sales.view.own',      'reports',      'Own Shop Sales Reports',  'Can generate sales reports for own shop',              1),
('reports.sales.view.all',      'reports',      'All Sales Reports',       'Can generate sales reports for all shops',             1),
('reports.revenue.view',        'reports',      'Revenue Reports',         'Can generate and view revenue reports',                1),
('reports.discrepancy.view',    'reports',      'Discrepancy Reports',     'Can view discrepancy reports',                         1),
('reports.user.activity.view',  'reports',      'User Activity Reports',   'Can view user activity reports',                       1),
('reports.export',              'reports',      'Export Reports',          'Can export reports to PDF and CSV',                    1),
-- Analytics / dashboard
('dashboard.admin.view',        'dashboard',    'Admin Dashboard',         'Can view admin executive dashboard',                   1),
('dashboard.gm.view',           'dashboard',    'GM Dashboard',            'Can view general manager executive dashboard',         1),
('dashboard.farm.view',         'dashboard',    'Farm Dashboard',          'Can view farm manager dashboard',                      1),
('dashboard.warehouse.view',    'dashboard',    'Warehouse Dashboard',     'Can view warehouse manager dashboard',                 1),
('dashboard.shop.view',         'dashboard',    'Shop Dashboard',          'Can view storekeeper dashboard',                       1),
('dashboard.cashier.view',      'dashboard',    'Cashier Dashboard',       'Can view cashier summary dashboard',                   1),
-- Inventory module
('inventory.view.all',          'inventory',    'View All Inventory',      'Can view inventory across all locations',              1),
('inventory.batch.view',        'inventory',    'View Batches',            'Can view batch tracking records',                      1),
-- Notifications
('notifications.view',          'notifications','View Notifications',      'Can view own notifications',                           1);

-- =========================================================
-- 3. GRANT General Manager (role_id=6) FULL permissions
--    All business operations + full system administration
-- =========================================================

-- First remove old limited permissions for GM to start clean
DELETE FROM `role_permissions` WHERE `role_id` = 6;

-- Grant all permissions to General Manager
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, `id` FROM `permissions`;

-- =========================================================
-- 4. FLUSH session permission cache by touching the role
--    (so existing sessions re-load permissions on next request)
-- =========================================================
UPDATE `roles`
SET `updated_at` = NOW()
WHERE `id` = 6;

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT
    r.`name`          AS role_name,
    r.`display_name`,
    COUNT(rp.`permission_id`) AS permissions_granted
FROM `roles` r
LEFT JOIN `role_permissions` rp ON rp.`role_id` = r.`id`
WHERE r.`id` = 6
GROUP BY r.`id`, r.`name`, r.`display_name`;

SELECT 'General Manager integration migration complete.' AS `status`;
