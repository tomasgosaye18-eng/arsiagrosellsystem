-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_02_000017_phase13_transfer_workflow_fix.sql
-- Purpose   : Phase 13 — Transfer Workflow Correction
--             1. Adds transfer-specific permission codes (was missing).
--             2. Assigns permissions to correct roles:
--                  warehouse_manager : create, approve, dispatch, view
--                  storekeeper       : view, receive, report discrepancy
--                  general_manager   : all transfer permissions
--                  administrator     : all permissions
-- Safe to run multiple times (INSERT IGNORE).
-- =========================================================

USE `aam_db`;

-- =========================================================
-- 1. TRANSFER PERMISSION CODES
-- =========================================================
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
-- Warehouse Manager / source side
('transfer.view',              'transfer', 'View Transfers',           'Can view transfer list and detail',              1),
('transfer.create',            'transfer', 'Create Transfer',          'Can create a new warehouse→shop transfer',       1),
('transfer.approve',           'transfer', 'Approve Transfer',         'Can approve a pending transfer',                 1),
('transfer.dispatch',          'transfer', 'Dispatch Transfer',        'Can dispatch a transfer (deducts warehouse stock)',1),
('transfer.cancel',            'transfer', 'Cancel Transfer',          'Can cancel a pending or approved transfer',      1),
-- Destination / receiving side
('transfer.receive',           'transfer', 'Receive Transfer',         'Can confirm receipt of an in-transit transfer',  1),
('transfer.discrepancy.report','transfer', 'Report Discrepancy',       'Can record a quantity discrepancy on receipt',   1),
('transfer.view.incoming',     'transfer', 'View Incoming Transfers',  'Can view transfers addressed to own shop',       1),
-- Oversight
('transfer.view.all',          'transfer', 'View All Transfers',       'Can view all transfers across all locations',    1);

-- =========================================================
-- 2. WAREHOUSE MANAGER (role_id = 3)
--    Source side: create, approve, dispatch, cancel, view
--    NOT: receive (that belongs to destination)
-- =========================================================
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions`
WHERE `code` IN (
    'transfer.view',
    'transfer.create',
    'transfer.approve',
    'transfer.dispatch',
    'transfer.cancel'
);

-- =========================================================
-- 3. STOREKEEPER (role_id = 4)
--    Destination side: view incoming, receive, report discrepancy
--    NOT: create, approve, dispatch, cancel (those are source-side)
-- =========================================================
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions`
WHERE `code` IN (
    'transfer.view',
    'transfer.view.incoming',
    'transfer.receive',
    'transfer.discrepancy.report'
);

-- =========================================================
-- 4. GENERAL MANAGER (role_id = 6)
--    Full oversight — all transfer permissions
-- =========================================================
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions`
WHERE `code` LIKE 'transfer.%';

-- =========================================================
-- 5. ADMINISTRATOR (role_id = 1) — all permissions (already has *)
-- =========================================================
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`
WHERE `code` LIKE 'transfer.%';

SELECT 'Phase 13 transfer workflow fix migration applied.' AS status;

SELECT
    r.name AS role_name,
    p.code AS permission_code
FROM role_permissions rp
JOIN roles       r ON r.id  = rp.role_id
JOIN permissions p ON p.id  = rp.permission_id
WHERE p.code LIKE 'transfer.%'
ORDER BY r.name, p.code;
