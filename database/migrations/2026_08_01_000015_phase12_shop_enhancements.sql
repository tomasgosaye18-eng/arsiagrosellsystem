-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_01_000015_phase12_shop_enhancements.sql
-- Purpose   : Phase 12 supplemental — adds missing columns
--             identified in cross-check:
--               1. shops.shop_code  — human-readable shop ID
--               2. shops.phone      — shop contact number
--               3. shops.email      — shop contact email
--             These are referenced in the shop card view
--             and were in the original spec.
-- Run after : 2026_08_01_000014_phase12_shop_pos.sql
-- =========================================================

USE `aam_db`;

-- Add shop_code if not already present
ALTER TABLE `shops`
    ADD COLUMN IF NOT EXISTS `shop_code`  VARCHAR(30)  NULL DEFAULT NULL
        COMMENT 'Human-readable shop identifier e.g. SHOP-001',
    ADD COLUMN IF NOT EXISTS `phone`      VARCHAR(30)  NULL DEFAULT NULL
        COMMENT 'Shop contact phone',
    ADD COLUMN IF NOT EXISTS `email`      VARCHAR(191) NULL DEFAULT NULL
        COMMENT 'Shop contact email';

CREATE INDEX IF NOT EXISTS `idx_shops_code` ON `shops`(`shop_code`);

-- Backfill shop_code for existing shops
UPDATE `shops` SET `shop_code` = CONCAT('SHOP-', LPAD(`id`, 3, '0'))
WHERE `shop_code` IS NULL OR `shop_code` = '';

SELECT 'Phase 12 shop enhancements applied.' AS status;
