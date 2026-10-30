-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_05_000021_phase_role_separation.sql
-- Purpose   : Enforce strict separation of GENERAL_MANAGER
--             and SYSTEM_ADMINISTRATOR (administrator) roles.
--
--             Changes:
--             1. Ensure both roles exist with correct names/IDs.
--             2. Update permissions JSON for each role to
--                reflect the approved feature mapping.
--             3. GENERAL_MANAGER loses user management perms.
--             4. SYSTEM_ADMIN keeps all system perms and gains
--                explicit user management permissions.
--
-- Safe to run multiple times (uses UPDATE ... WHERE EXISTS).
-- =========================================================

USE `aam_db`;

-- =========================================================
-- 1. ENSURE ROLES EXIST (INSERT IGNORE — won't duplicate)
-- =========================================================

INSERT IGNORE INTO `roles` (`id`, `name`, `display_name`, `description`, `is_active`)
VALUES
    (1, 'administrator',     'System Administrator',
        'Full technical/system administration. User management, security, backup, maintenance.', 1),
    (6, 'general_manager',   'General Manager',
        'Business and operational management. Farm, warehouse, inventory, transfers, shops, reports, analytics.', 1);

-- =========================================================
-- 2. SYSTEM ADMINISTRATOR — permissions JSON
--    Owns: user management, roles, org, products, audit,
--          security, backup, maintenance, system dashboard.
--    Does NOT own: business operational workflows
--                  (those belong to GM and field roles).
-- =========================================================

UPDATE `roles`
SET
    `display_name` = 'System Administrator',
    `description`  = 'Technical system administration: user management, roles & permissions, security, backup, maintenance, audit trail, organisation structure.',
    `permissions`  = JSON_OBJECT(
        'dashboard.admin',          true,
        'users.view',               true,
        'users.create',             true,
        'users.edit',               true,
        'users.delete',             true,
        'users.restore',            true,
        'users.toggle',             true,
        'users.unlock',             true,
        'users.reset_password',     true,
        'roles.view',               true,
        'organization.view',        true,
        'organization.manage',      true,
        'products.view',            true,
        'products.manage',          true,
        'categories.manage',        true,
        'units.manage',             true,
        'barcode.manage',           true,
        'prices.manage',            true,
        'audit.view',               true,
        'audit.export',             true,
        'reports.admin.users',      true,
        'reports.admin.organization', true,
        'reports.admin.audit',      true,
        'settings.security',        true,
        'settings.backup',          true,
        'settings.maintenance',     true
    ),
    `updated_at`   = CURRENT_TIMESTAMP
WHERE `name` = 'administrator';

-- =========================================================
-- 3. GENERAL MANAGER — permissions JSON
--    Owns: business dashboard, farm oversight, warehouse
--          oversight, inventory approvals, transfer approvals,
--          shop oversight, POS monitoring, reports (business),
--          analytics, audit trail view, org structure view,
--          products/catalog management.
--    Does NOT own: user management, roles, system config,
--                  security settings, backup, maintenance.
-- =========================================================

UPDATE `roles`
SET
    `display_name` = 'General Manager',
    `description`  = 'Business and operational management: farm, warehouse, inventory, transfers (approve/reject), shops, POS monitoring, reports, analytics, audit trail, organization overview.',
    `permissions`  = JSON_OBJECT(
        'dashboard.general_manager',    true,
        'farm.view',                    true,
        'farm.manage',                  true,
        'harvest.view',                 true,
        'dispatch.view',                true,
        'warehouse.view',               true,
        'warehouse.manage',             true,
        'inventory.view',               true,
        'inventory.adjust',             true,
        'inventory.approve',            true,
        'inventory.counts',             true,
        'transfers.view',               true,
        'transfers.approve',            true,
        'transfers.reject',             true,
        'transfers.cancel',             true,
        'shops.view',                   true,
        'pos.view',                     true,
        'organization.view',            true,
        'organization.manage',          true,
        'products.view',                true,
        'products.manage',              true,
        'categories.manage',            true,
        'units.manage',                 true,
        'barcode.manage',               true,
        'prices.manage',                true,
        'audit.view',                   true,
        'audit.export',                 true,
        'reports.farm',                 true,
        'reports.warehouse',            true,
        'reports.admin.organization',   true,
        'reports.admin.audit',          true,
        'analytics.view',               true,
        'notifications.view',           true
    ),
    `updated_at`   = CURRENT_TIMESTAMP
WHERE `name` = 'general_manager';

-- =========================================================
-- 4. VERIFICATION QUERIES (informational only — not changes)
-- =========================================================

-- Show both roles after update:
-- SELECT id, name, display_name, description FROM roles
-- WHERE name IN ('administrator', 'general_manager');

-- Show any users currently assigned to each role:
-- SELECT u.id, u.username, r.name AS role, r.display_name
-- FROM users u JOIN roles r ON r.id = u.role_id
-- WHERE r.name IN ('administrator', 'general_manager')
-- ORDER BY r.name, u.username;
