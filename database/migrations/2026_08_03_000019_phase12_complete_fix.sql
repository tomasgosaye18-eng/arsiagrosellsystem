-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_03_000019_phase12_complete_fix.sql
-- Purpose   : Phase 12 completeness fix — applies all
--             missing permissions and role grants identified
--             during Phase 12 implementation review.
--
--   1. Transfer permissions for salesperson (shop-receiver)
--   2. Shop receiving / discrepancy / movement permissions
--      for both salesperson AND storekeeper
--   3. Ensures salesperson has access to incoming transfers
--      and can confirm receipt (same as storekeeper)
--   4. GM and Admin catch-all refresh
--
-- Safe to run multiple times (INSERT IGNORE).
-- Run after : 2026_08_02_000018_phase13_shop_receiving.sql
-- =========================================================

USE `aam_db`;

-- ─────────────────────────────────────────────────────────
-- 1. Transfer permissions (ensure codes exist)
-- ─────────────────────────────────────────────────────────
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('transfer.view',               'transfer', 'View Transfers',          'Can view transfer list and detail',               1),
('transfer.create',             'transfer', 'Create Transfer',         'Can create a warehouse→shop transfer',            1),
('transfer.approve',            'transfer', 'Approve Transfer',        'Can approve a pending transfer',                  1),
('transfer.dispatch',           'transfer', 'Dispatch Transfer',       'Can dispatch an approved transfer',               1),
('transfer.cancel',             'transfer', 'Cancel Transfer',         'Can cancel a pending or approved transfer',       1),
('transfer.receive',            'transfer', 'Receive Transfer',        'Can confirm receipt of an in-transit transfer',   1),
('transfer.discrepancy.report', 'transfer', 'Report Discrepancy',      'Can record a quantity discrepancy on receipt',    1),
('transfer.view.incoming',      'transfer', 'View Incoming Transfers', 'Can view transfers addressed to own shop',        1),
('transfer.view.all',           'transfer', 'View All Transfers',      'Can view all transfers across all locations',     1);

-- ─────────────────────────────────────────────────────────
-- 2. Shop operation permissions (ensure codes exist)
-- ─────────────────────────────────────────────────────────
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('shop.receiving.view',     'shop', 'View Incoming Transfers',  'Can view transfers in-transit to shop',          1),
('shop.receiving.confirm',  'shop', 'Confirm Transfer Receipt', 'Can confirm receipt of an in-transit transfer',  1),
('shop.discrepancy.view',   'shop', 'View Discrepancies',       'Can view shop discrepancy reports',              1),
('shop.discrepancy.report', 'shop', 'Report Discrepancy',       'Can report quantity discrepancy on receipt',     1),
('shop.movements.view',     'shop', 'View Stock Movements',     'Can view shop stock movement history',           1);

-- ─────────────────────────────────────────────────────────
-- 3. Salesperson (role_id = 5) — full shop-side workflow
--    Can view incoming, receive, report discrepancy,
--    view stock movements. CANNOT approve/dispatch.
-- ─────────────────────────────────────────────────────────
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, p.id FROM `permissions` p
WHERE p.`code` IN (
    -- Transfer (shop receiver side)
    'transfer.view.incoming',
    'transfer.receive',
    'transfer.discrepancy.report',
    -- Shop operations
    'shop.receiving.view',
    'shop.receiving.confirm',
    'shop.discrepancy.view',
    'shop.discrepancy.report',
    'shop.movements.view',
    -- POS (already set in migration 014, kept for safety)
    'pos.terminal',
    'pos.sale.create',
    'pos.sale.view',
    'pos.receipt.view',
    'pos.shift.open',
    'pos.shift.close',
    'pos.shift.view',
    'shop.view',
    'shop.inventory.view'
);

-- ─────────────────────────────────────────────────────────
-- 4. Storekeeper (role_id = 4) — shop-side when shop-assigned
-- ─────────────────────────────────────────────────────────
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, p.id FROM `permissions` p
WHERE p.`code` IN (
    'transfer.view',
    'transfer.view.incoming',
    'transfer.receive',
    'transfer.discrepancy.report',
    'shop.receiving.view',
    'shop.receiving.confirm',
    'shop.discrepancy.view',
    'shop.discrepancy.report',
    'shop.movements.view',
    -- POS access for storekeeper (oversight + operational)
    'pos.terminal',
    'pos.sale.view',
    'pos.sale.void',
    'pos.receipt.view',
    'pos.shift.open',
    'pos.shift.close',
    'pos.shift.view',
    'shop.view',
    'shop.inventory.view',
    'shop.sales.view',
    'shop.sales.manage'
);

-- ─────────────────────────────────────────────────────────
-- 5. Warehouse Manager (role_id = 3) — source side only
-- ─────────────────────────────────────────────────────────
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, p.id FROM `permissions` p
WHERE p.`code` IN (
    'transfer.view',
    'transfer.create',
    'transfer.approve',
    'transfer.dispatch',
    'transfer.cancel'
);

-- ─────────────────────────────────────────────────────────
-- 6. General Manager (role_id = 6) — all transfer + shop
-- ─────────────────────────────────────────────────────────
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, p.id FROM `permissions` p
WHERE p.`code` LIKE 'transfer.%'
   OR p.`code` LIKE 'shop.%'
   OR p.`code` LIKE 'pos.%';

-- ─────────────────────────────────────────────────────────
-- 7. Admin (role_id = 1) — all permissions (catch-all)
-- ─────────────────────────────────────────────────────────
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.`code` LIKE 'transfer.%'
   OR p.`code` LIKE 'shop.%'
   OR p.`code` LIKE 'pos.%';

-- ─────────────────────────────────────────────────────────
-- 8. Ensure seller_01 and seller_02 shop assignments exist
--    (idempotent — skips if already assigned)
-- ─────────────────────────────────────────────────────────

-- seller_01 → Shop 1 (Main Campus)
INSERT INTO `user_location_assignments`
    (`user_id`, `location_type`, `location_id`, `assigned_at`, `assigned_by`)
SELECT u.id, 'shop', 1, NOW(), 1
FROM `users` u
WHERE u.username = 'seller_01'
  AND NOT EXISTS (
      SELECT 1 FROM `user_location_assignments` ula
      WHERE ula.user_id = u.id AND ula.location_type = 'shop'
  )
LIMIT 1;

-- seller_02 → Shop 2 (Health Campus)
INSERT INTO `user_location_assignments`
    (`user_id`, `location_type`, `location_id`, `assigned_at`, `assigned_by`)
SELECT u.id, 'shop', 2, NOW(), 1
FROM `users` u
WHERE u.username = 'seller_02'
  AND NOT EXISTS (
      SELECT 1 FROM `user_location_assignments` ula
      WHERE ula.user_id = u.id AND ula.location_type = 'shop'
  )
LIMIT 1;

-- storekeeper_01 → Shop 1 (Main Campus)
INSERT INTO `user_location_assignments`
    (`user_id`, `location_type`, `location_id`, `assigned_at`, `assigned_by`)
SELECT u.id, 'shop', 1, NOW(), 1
FROM `users` u
WHERE u.username = 'storekeeper_01'
  AND NOT EXISTS (
      SELECT 1 FROM `user_location_assignments` ula
      WHERE ula.user_id = u.id AND ula.location_type = 'shop'
  )
LIMIT 1;

-- ─────────────────────────────────────────────────────────
-- VERIFICATION
-- ─────────────────────────────────────────────────────────
SELECT 'Phase 12 complete fix applied.' AS status;

SELECT
    r.name       AS role_name,
    COUNT(rp.id) AS permission_count
FROM `roles` r
LEFT JOIN `role_permissions` rp ON rp.role_id = r.id
GROUP BY r.id, r.name
ORDER BY r.id;
