-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_03_000020_phase12_pos_workflow_fix.sql
-- Purpose   : Direct Cash Sale workflow correctness fix.
--
--   1. Add payment_method column to sales (CASH only)
--   2. Ensure shop_inventory has all Phase 11 columns
--      (batch_number, min_stock_level, expiry_date,
--       last_movement_type) — safe ALTER IF NOT EXISTS
--   3. Ensure stock_movement_log ENUM includes 'sale'
--   4. Add indexes on sales for performance
--   5. Verify seller shift and POS permissions
--   6. Seed sample shop inventory for demo shops
--      (only if shop_inventory is empty for that shop)
--
-- SAFE TO RUN MULTIPLE TIMES — all statements are
-- idempotent (IF NOT EXISTS / INSERT IGNORE / UPDATE).
--
-- Run after : 2026_08_03_000019_phase12_complete_fix.sql
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. ADD payment_method TO sales TABLE
--    Default = 'cash' — this system is cash-only.
--    Stored for auditability; never changes from 'cash'
--    in the current workflow.
-- =========================================================
ALTER TABLE `sales`
    ADD COLUMN IF NOT EXISTS `payment_method`
        ENUM('cash','credit','mobile_money','other')
        NOT NULL DEFAULT 'cash'
        COMMENT 'Payment method used — always cash in current workflow'
        AFTER `change_given`;

-- Backfill any existing rows
UPDATE `sales` SET `payment_method` = 'cash' WHERE `payment_method` IS NULL OR `payment_method` = '';

-- =========================================================
-- 2. ENSURE shop_inventory HAS ALL PHASE 11 COLUMNS
--    (safe — uses IF NOT EXISTS)
-- =========================================================
ALTER TABLE `shop_inventory`
    ADD COLUMN IF NOT EXISTS `batch_number`
        VARCHAR(60) NULL DEFAULT NULL
        COMMENT 'Primary batch tracking',
    ADD COLUMN IF NOT EXISTS `min_stock_level`
        DECIMAL(14,3) NOT NULL DEFAULT 0.000
        COMMENT 'Minimum stock threshold for low-stock alerts',
    ADD COLUMN IF NOT EXISTS `expiry_date`
        DATE NULL DEFAULT NULL
        COMMENT 'Expiry date for perishable tracking',
    ADD COLUMN IF NOT EXISTS `last_movement_type`
        VARCHAR(40) NULL DEFAULT NULL
        COMMENT 'Type of last stock movement';

CREATE INDEX IF NOT EXISTS `idx_si_expiry`         ON `shop_inventory`(`expiry_date`);
CREATE INDEX IF NOT EXISTS `idx_si_min_stock`      ON `shop_inventory`(`min_stock_level`);
CREATE INDEX IF NOT EXISTS `idx_si_avail_quantity` ON `shop_inventory`(`available_quantity`);

-- =========================================================
-- 3. EXTEND stock_movement_log ENUM TO INCLUDE 'sale'
--    (and all other Phase 11 types)
-- =========================================================
ALTER TABLE `stock_movement_log`
    MODIFY COLUMN `movement_type`
        ENUM('receiving','transfer_out','transfer_in',
             'adjustment','discrepancy','return','initial',
             'sale','count','damage','expiry')
        NOT NULL;

-- =========================================================
-- 4. ADD MISSING sales TABLE INDEXES FOR PERFORMANCE
-- =========================================================
CREATE INDEX IF NOT EXISTS `idx_sales_shift`     ON `sales`(`shift_id`);
CREATE INDEX IF NOT EXISTS `idx_sales_completed` ON `sales`(`completed_at`);
CREATE INDEX IF NOT EXISTS `idx_sales_seller`    ON `sales`(`seller_id`);

-- =========================================================
-- 5. ENSURE seller_shifts HAS ALL REQUIRED COLUMNS
-- =========================================================
ALTER TABLE `seller_shifts`
    ADD COLUMN IF NOT EXISTS `opening_cash_float`
        DECIMAL(14,2) NOT NULL DEFAULT 0.00
        COMMENT 'Opening cash float amount';

-- =========================================================
-- 6. POS PERMISSIONS — ENSURE ALL REQUIRED CODES EXIST
--    (INSERT IGNORE — no duplicates)
-- =========================================================
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
-- POS Terminal
('pos.terminal',          'pos',  'Access POS Terminal', 'Can open and use the POS terminal',           1),
('pos.sale.create',       'pos',  'Create Sale',         'Can process a cash sale transaction',         1),
('pos.sale.view',         'pos',  'View Sales',          'Can view own sales history',                  1),
('pos.sale.void',         'pos',  'Void Sale',           'Can void a completed sale',                   1),
('pos.receipt.view',      'pos',  'View Receipt',        'Can view and print sale receipts',            1),
-- Shift
('pos.shift.open',        'pos',  'Open Shift',          'Can open a seller shift',                     1),
('pos.shift.close',       'pos',  'Close Shift',         'Can close own shift',                         1),
('pos.shift.view',        'pos',  'View Shifts',         'Can view own shift history',                  1),
-- Shop
('shop.view',             'shop', 'View Shop',           'Can view own shop details',                   1),
('shop.inventory.view',   'shop', 'View Shop Inventory', 'Can view own shop inventory',                 1),
('shop.sales.view',       'shop', 'View Shop Sales',     'Can view shop sales (all sellers)',           1),
('shop.sales.manage',     'shop', 'Manage Shop Sales',   'Can view/void all shop sales (manager)',      1);

-- Salesperson (role_id=5): full POS + shift access
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, p.id FROM `permissions` p
WHERE p.`code` IN (
    'pos.terminal','pos.sale.create','pos.sale.view','pos.receipt.view',
    'pos.shift.open','pos.shift.close','pos.shift.view',
    'shop.view','shop.inventory.view'
);

-- Storekeeper (role_id=4): POS oversight + void + all shop views
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, p.id FROM `permissions` p
WHERE p.`code` IN (
    'pos.terminal','pos.sale.view','pos.sale.void','pos.receipt.view',
    'pos.shift.open','pos.shift.close','pos.shift.view',
    'shop.view','shop.inventory.view','shop.sales.view','shop.sales.manage'
);

-- Admin (role_id=1) + GM (role_id=6): all POS/shop permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.`code` LIKE 'pos.%' OR p.`code` LIKE 'shop.%';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, p.id FROM `permissions` p
WHERE p.`code` LIKE 'pos.%' OR p.`code` LIKE 'shop.%';

-- =========================================================
-- 7. ENSURE DEMO USER SHOP ASSIGNMENTS EXIST
--    seller_01 → Shop 1 (Main Campus Shop)
--    seller_02 → Shop 2 (Health Campus Shop)
--    storekeeper_01 → Shop 1
-- =========================================================

-- seller_01 → Shop 1
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

-- seller_02 → Shop 2
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

-- storekeeper_01 → Shop 1
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

-- =========================================================
-- 8. SEED SAMPLE SHOP INVENTORY FOR DEMO TESTING
--    Only inserts rows if shop has NO inventory yet.
--    Uses INSERT IGNORE so it's safe to re-run.
--    Products must exist (seeded in Phase 5A/INSTALL_ALL).
-- =========================================================

-- Only seed if Shop 1 has no inventory
INSERT IGNORE INTO `shop_inventory`
    (`shop_id`, `product_id`, `current_quantity`, `reserved_quantity`,
     `available_quantity`, `min_stock_level`, `unit_id`, `last_movement_at`,
     `last_movement_type`)
SELECT
    1,
    p.id,
    100.000,    -- current_quantity
    0.000,      -- reserved_quantity
    100.000,    -- available_quantity
    10.000,     -- min_stock_level
    p.unit_id,
    NOW(),
    'initial'
FROM `products` p
WHERE p.is_active = 1
  AND NOT EXISTS (
      SELECT 1 FROM `shop_inventory` si
      WHERE si.shop_id = 1
      LIMIT 1
  )
LIMIT 15;

-- Only seed if Shop 2 has no inventory
INSERT IGNORE INTO `shop_inventory`
    (`shop_id`, `product_id`, `current_quantity`, `reserved_quantity`,
     `available_quantity`, `min_stock_level`, `unit_id`, `last_movement_at`,
     `last_movement_type`)
SELECT
    2,
    p.id,
    75.000,
    0.000,
    75.000,
    10.000,
    p.unit_id,
    NOW(),
    'initial'
FROM `products` p
WHERE p.is_active = 1
  AND NOT EXISTS (
      SELECT 1 FROM `shop_inventory` si
      WHERE si.shop_id = 2
      LIMIT 1
  )
LIMIT 15;

-- Log the seeding in stock_movement_log for traceability
INSERT IGNORE INTO `stock_movement_log`
    (movement_type, location_type, location_id, product_id,
     quantity_before, quantity_in, quantity_out, quantity_after,
     reference_type, reference_number, performed_by, notes, moved_at)
SELECT
    'initial', 'shop', si.shop_id, si.product_id,
    0, si.current_quantity, 0, si.current_quantity,
    'migration', '2026_08_03_000020', 1,
    'Demo inventory seed for POS testing', NOW()
FROM `shop_inventory` si
WHERE si.last_movement_type = 'initial'
  AND NOT EXISTS (
      SELECT 1 FROM `stock_movement_log` sml
      WHERE sml.location_type = 'shop'
        AND sml.location_id = si.shop_id
        AND sml.product_id = si.product_id
        AND sml.movement_type = 'initial'
  );

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT 'Phase 12 POS workflow fix applied.' AS status;

SELECT
    'sales.payment_method' AS check_item,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result
FROM information_schema.columns
WHERE table_schema = 'aam_db'
  AND table_name = 'sales'
  AND column_name = 'payment_method';

SELECT
    'shop_inventory.min_stock_level' AS check_item,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result
FROM information_schema.columns
WHERE table_schema = 'aam_db'
  AND table_name = 'shop_inventory'
  AND column_name = 'min_stock_level';

SELECT
    'shop_inventory.last_movement_type' AS check_item,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result
FROM information_schema.columns
WHERE table_schema = 'aam_db'
  AND table_name = 'shop_inventory'
  AND column_name = 'last_movement_type';

SELECT
    si.shop_id,
    sh.name AS shop_name,
    COUNT(*) AS inventory_rows,
    ROUND(SUM(si.available_quantity), 1) AS total_available
FROM shop_inventory si
JOIN shops sh ON sh.id = si.shop_id
GROUP BY si.shop_id, sh.name
ORDER BY si.shop_id;

SELECT
    r.name AS role_name,
    COUNT(rp.id) AS permission_count
FROM roles r
LEFT JOIN role_permissions rp ON rp.role_id = r.id
WHERE r.id IN (4, 5)
GROUP BY r.id, r.name;
