-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_07_000027_gm_price_management.sql
-- Purpose   : Selling Price Management for General Manager.
--             1. Adds price_type, effective_date columns to
--                product_price_history if missing (graceful).
--             2. Adds selling_price column alias (generated)
--                to products table if missing (graceful).
--             3. Adds GM price management permissions.
-- Run after : 2026_08_07_000026_backup_history.sql
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. Extend product_price_history (graceful ALTER)
--    The phase5a schema created a simple version.
--    We add the missing columns if they don't exist.
-- =========================================================

-- Add price_type ENUM if missing
ALTER TABLE `product_price_history`
    ADD COLUMN IF NOT EXISTS `price_type`
        ENUM('COST','SELLING') NOT NULL DEFAULT 'SELLING'
        COMMENT 'Type of price that changed'
        AFTER `product_id`;

-- Add effective_date if missing
ALTER TABLE `product_price_history`
    ADD COLUMN IF NOT EXISTS `effective_date`
        DATE NULL
        COMMENT 'Date the price becomes effective'
        AFTER `new_price`;

-- Add price_difference (generated) if missing
ALTER TABLE `product_price_history`
    ADD COLUMN IF NOT EXISTS `price_difference`
        DECIMAL(15,2) GENERATED ALWAYS AS (`new_price` - `old_price`) STORED
        COMMENT 'Computed price change amount'
        AFTER `effective_date`;

-- Add percentage_change if missing
ALTER TABLE `product_price_history`
    ADD COLUMN IF NOT EXISTS `percentage_change`
        DECIMAL(8,2) NULL
        COMMENT 'Percentage change from old price'
        AFTER `price_difference`;

-- Rename change_reason → reason if old column exists, else add reason
-- Use a safe approach: add reason column, migrate data, keep both
ALTER TABLE `product_price_history`
    ADD COLUMN IF NOT EXISTS `reason`
        VARCHAR(255) NULL
        COMMENT 'Reason for price change'
        AFTER `percentage_change`;

-- Migrate existing change_reason values to reason where reason is null
UPDATE `product_price_history`
    SET `reason` = `change_reason`
    WHERE `reason` IS NULL AND `change_reason` IS NOT NULL;

-- Add index if not exists
CREATE INDEX IF NOT EXISTS `idx_pph_product_type`
    ON `product_price_history` (`product_id`, `price_type`);

CREATE INDEX IF NOT EXISTS `idx_pph_changed_at`
    ON `product_price_history` (`changed_at` DESC);

-- =========================================================
-- 2. Ensure products.selling_price column exists.
--    The canonical phase5a schema uses unit_price.
--    If selling_price was added by phase8b migration
--    (which targeted arsiagro_db), it may or may not exist.
--    We add it as a generated alias if missing.
--    If it already exists, this is a no-op.
-- =========================================================

-- Try adding selling_price as a virtual generated column
-- pointing to unit_price. This is the safest approach:
-- all existing queries using unit_price continue to work,
-- and new GM queries can use selling_price.
ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `selling_price`
        DECIMAL(12,2) GENERATED ALWAYS AS (`unit_price`) VIRTUAL
        COMMENT 'Alias for unit_price — official selling price set by GM';

-- =========================================================
-- 3. Add GM price management permissions
-- =========================================================

INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('product.price.view',   'products', 'View Product Prices',   'Can view current selling price and price history', 1),
('product.price.manage', 'products', 'Manage Selling Prices', 'Can set and update official selling prices (GM only)', 1);

-- Grant price.view to everyone who can view products
-- (salesperson, warehouse_manager, farm_manager)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r, `permissions` p
WHERE p.code = 'product.price.view'
  AND r.name IN ('salesperson','warehouse_manager','farm_manager','general_manager','administrator');

-- Grant price.manage to General Manager ONLY
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r, `permissions` p
WHERE p.code = 'product.price.manage'
  AND r.name = 'general_manager';

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'GM Price Management migration applied.' AS status;
