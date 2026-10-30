-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_16_000039_fix_admin_gm_permissions.sql
-- Purpose   : Enforce strict separation between System
--             Administrator and General Manager roles.
--
--             PROBLEM (migration 38): System Administrator
--             was given ALL 129 permissions, including
--             business operational permissions that belong
--             exclusively to General Manager and field roles
--             (e.g. gm.dispatch.approve, farm.harvest.create,
--             pos.sale.create, transfer.receive, etc.)
--
--             CORRECT DESIGN:
--
--             System Administrator owns:
--               - User management
--               - Roles & permissions viewing
--               - Organization structure (CRUD)
--               - Product catalog management
--               - System settings, backup, maintenance
--               - Audit trail viewing
--               - Payment methods configuration
--               - Admin dashboard
--               - Admin reports (users, org)
--               - Auth (own password change)
--               - Notifications (own)
--               - Send notifications
--
--             System Administrator does NOT own:
--               - Farm operations (harvest, dispatch)
--               - Warehouse receiving
--               - Transfer workflow
--               - POS terminal
--               - Shop operations
--               - GM approval actions
--               - Business analytics/KPIs
--               - Business reports
--
--             General Manager owns:
--               - All business oversight + approvals
--               - Farm/warehouse/shop monitoring
--               - Transfer/dispatch/discrepancy approvals
--               - Business reports & analytics
--               - Price management
--               - GM dashboard
--
--             General Manager does NOT own:
--               - User management (CRUD)
--               - System settings (edit)
--               - Backup / restore
--               - Maintenance mode
--               - Payment method configuration
--
-- SAFE TO RE-RUN:
--   Uses DELETE + re-insert so it is idempotent.
-- =========================================================

USE `aam_db`;

-- =========================================================
-- 1. FIX SYSTEM ADMINISTRATOR (role_id = 1)
--    Remove ALL, then grant only system-admin permissions.
-- =========================================================

DELETE FROM `role_permissions` WHERE `role_id` = 1;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.`id` FROM `permissions` p
WHERE p.`is_active` = 1
  AND p.`code` IN (

    -- Auth
    'auth.login',
    'auth.password.change.own',
    'auth.password.reset.any',

    -- Dashboard (admin only)
    'dashboard.admin.view',

    -- User Management
    'admin.users.view',
    'admin.users.create',
    'admin.users.edit',
    'admin.users.deactivate',
    'admin.users.unlock',
    'admin.users.reset_password',
    'admin.users.assign_location',
    'admin.login_history.view',

    -- Roles & Permissions (view only — changes via migrations)
    'admin.roles.view',

    -- Organization Management (full CRUD)
    'admin.organization.view',
    'admin.organization.manage',

    -- Product Catalog Management
    'admin.products.view',
    'admin.products.create',
    'admin.products.edit',
    'admin.products.deactivate',
    'admin.products.price.change',
    'admin.categories.manage',
    'admin.units.manage',

    -- System Configuration
    'admin.settings.view',
    'admin.settings.edit',
    'admin.backup.create',
    'admin.backup.restore',
    'admin.maintenance.manage',

    -- Payment Methods Configuration (admin manages the methods)
    'settings.payment_methods.view',
    'settings.payment_methods.manage',

    -- Audit & Reports
    'admin.audit.view',
    'admin.audit.export',
    'admin.reports.users',
    'admin.reports.organization',

    -- Notifications
    'notifications.view'
);

-- =========================================================
-- 2. FIX GENERAL MANAGER (role_id = 6)
--    Business oversight + approvals only.
--    No user management, no system config, no backup.
-- =========================================================

DELETE FROM `role_permissions` WHERE `role_id` = 6;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, p.`id` FROM `permissions` p
WHERE p.`is_active` = 1
  AND p.`code` IN (

    -- Auth
    'auth.login',
    'auth.password.change.own',

    -- Dashboard
    'dashboard.gm.view',
    'gm.analytics.view',

    -- Farm oversight
    'farm.list.view',
    'farm.harvest.view',
    'farm.dispatch.view',
    'farm.inventory.view',

    -- GM Farm approvals
    'gm.dispatch.approve',
    'gm.dispatch.reject',

    -- Warehouse oversight
    'warehouse.inventory.view',
    'warehouse.receiving.view',
    'warehouse.discrepancy.view',
    'warehouse.adjustment.approve',
    'gm.receiving.discrepancy.review',

    -- Transfer oversight + GM approvals
    'transfer.view',
    'transfer.view.all',
    'transfer.discrepancy.view',
    'transfer.discrepancy.report',
    'transfer.approve',
    'transfer.reject',
    'gm.transfer.approve',
    'gm.transfer.reject',
    'gm.transfer.discrepancy.review',
    'transfer.discrepancy.gm_review',

    -- Inventory oversight
    'inventory.overview',
    'inventory.view.all',
    'inventory.view.warehouse',
    'inventory.batch.view',
    'inventory.movements.view',
    'inventory.adjustment.view',
    'inventory.adjustment.approve',
    'gm.inventory.adjustments.approve',

    -- Shop oversight (read-only)
    'shop.list.view',
    'shop.view',
    'shop.inventory.view',
    'shop.sales.view',
    'shop.sales.view.all',
    'shop.sales.manage',
    'shop.receiving.view',
    'shop.discrepancy.view',
    'shop.movements.view',

    -- POS monitoring (view only — GM does not operate POS)
    'pos.sale.view',
    'pos.receipt.view',
    'pos.shift.view',

    -- Products (view + pricing)
    'admin.products.view',
    'gm.price.manage',

    -- Organization (view only)
    'admin.organization.view',

    -- Audit (view only — cannot modify system)
    'admin.audit.view',
    'admin.audit.export',

    -- Payment methods (view status only — cannot configure)
    'settings.payment_methods.view',

    -- Reports
    'reports.farm.view',
    'reports.warehouse.view',
    'reports.transfer.view',
    'reports.sales.view.all',
    'reports.revenue.view',
    'reports.discrepancy.view',
    'reports.export',
    'admin.reports.organization',

    -- Notifications
    'notifications.view'
);

-- =========================================================
-- VERIFICATION
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

-- Confirm admin has NO business operational codes
SELECT 'Admin should NOT have these:' AS check_label;
SELECT p.code FROM role_permissions rp
JOIN permissions p ON p.id = rp.permission_id
WHERE rp.role_id = 1
  AND p.code IN (
    'farm.harvest.create','farm.dispatch.create',
    'warehouse.receiving.confirm','pos.sale.create',
    'pos.terminal','transfer.receive','gm.dispatch.approve'
  );

-- Confirm GM has NO system admin codes
SELECT 'GM should NOT have these:' AS check_label;
SELECT p.code FROM role_permissions rp
JOIN permissions p ON p.id = rp.permission_id
WHERE rp.role_id = 6
  AND p.code IN (
    'admin.users.create','admin.users.edit','admin.backup.create',
    'admin.settings.edit','admin.maintenance.manage',
    'settings.payment_methods.manage'
  );
