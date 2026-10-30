-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_02_000018_phase13_shop_receiving.sql
-- Purpose   : Phase 13 — Shop Receiving & Inventory
--             Adds permission codes for:
--               shop.receiving.view   — view incoming transfers
--               shop.receiving.confirm— confirm receipt
--               shop.discrepancy.view — view discrepancy reports
--               shop.discrepancy.report — report discrepancy
--               shop.movements.view   — view stock movements
--             Grants to correct roles.
-- Safe to run multiple times (INSERT IGNORE).
-- =========================================================

USE `aam_db`;

INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('shop.receiving.view',     'shop', 'View Incoming Transfers',  'Can view transfers in-transit to shop',          1),
('shop.receiving.confirm',  'shop', 'Confirm Transfer Receipt', 'Can confirm receipt of an in-transit transfer',  1),
('shop.discrepancy.view',   'shop', 'View Discrepancies',       'Can view shop discrepancy reports',              1),
('shop.discrepancy.report', 'shop', 'Report Discrepancy',       'Can report quantity discrepancy on receipt',     1),
('shop.movements.view',     'shop', 'View Stock Movements',     'Can view shop stock movement history',           1);

-- Storekeeper (role_id = 4)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions`
WHERE `code` IN (
    'shop.receiving.view',
    'shop.receiving.confirm',
    'shop.discrepancy.view',
    'shop.discrepancy.report',
    'shop.movements.view'
);

-- Admin (role_id = 1) and GM (role_id = 6) — all shop permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions` WHERE `code` LIKE 'shop.%';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions` WHERE `code` LIKE 'shop.%';

SELECT 'Phase 13 shop receiving permissions applied.' AS status;

SELECT r.name AS role_name, p.code AS permission_code
FROM role_permissions rp
JOIN roles r ON r.id=rp.role_id
JOIN permissions p ON p.id=rp.permission_id
WHERE p.code LIKE 'shop.%'
ORDER BY r.name, p.code;
