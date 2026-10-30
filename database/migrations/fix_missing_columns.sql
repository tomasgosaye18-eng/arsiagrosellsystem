-- =========================================================
-- FIX: Add missing columns to farms, warehouses, shops
-- =========================================================
USE `aam_db`;

-- ── farms: add farm_name, address, phone, email, land_area_ha, created_by, updated_by ──
ALTER TABLE `farms`
    ADD COLUMN IF NOT EXISTS `farm_name`    VARCHAR(150) NULL AFTER `name`,
    ADD COLUMN IF NOT EXISTS `address`      VARCHAR(255) NULL AFTER `location`,
    ADD COLUMN IF NOT EXISTS `phone`        VARCHAR(30)  NULL AFTER `manager_id`,
    ADD COLUMN IF NOT EXISTS `email`        VARCHAR(191) NULL AFTER `phone`,
    ADD COLUMN IF NOT EXISTS `land_area_ha` DECIMAL(10,2) NULL AFTER `area_hectares`,
    ADD COLUMN IF NOT EXISTS `created_by`   INT UNSIGNED  NULL AFTER `updated_at`,
    ADD COLUMN IF NOT EXISTS `updated_by`   INT UNSIGNED  NULL AFTER `created_by`;

-- Copy existing name → farm_name where farm_name is null
UPDATE `farms` SET `farm_name` = `name` WHERE `farm_name` IS NULL;

-- ── warehouses: add missing columns ──────────────────────────────────────────
ALTER TABLE `warehouses`
    ADD COLUMN IF NOT EXISTS `warehouse_code` VARCHAR(30)  NULL AFTER `name`,
    ADD COLUMN IF NOT EXISTS `warehouse_name` VARCHAR(150) NULL AFTER `warehouse_code`,
    ADD COLUMN IF NOT EXISTS `warehouse_type` VARCHAR(60)  NULL AFTER `warehouse_name`,
    ADD COLUMN IF NOT EXISTS `address`        VARCHAR(255) NULL AFTER `location`,
    ADD COLUMN IF NOT EXISTS `latitude`       DECIMAL(10,7) NULL AFTER `address`,
    ADD COLUMN IF NOT EXISTS `longitude`      DECIMAL(10,7) NULL AFTER `latitude`,
    ADD COLUMN IF NOT EXISTS `capacity_sqm`   DECIMAL(10,2) NULL AFTER `capacity`,
    ADD COLUMN IF NOT EXISTS `capacity_tons`  DECIMAL(10,2) NULL AFTER `capacity_sqm`,
    ADD COLUMN IF NOT EXISTS `phone`          VARCHAR(30)   NULL AFTER `manager_id`,
    ADD COLUMN IF NOT EXISTS `email`          VARCHAR(191)  NULL AFTER `phone`,
    ADD COLUMN IF NOT EXISTS `created_by`     INT UNSIGNED  NULL AFTER `updated_at`,
    ADD COLUMN IF NOT EXISTS `updated_by`     INT UNSIGNED  NULL AFTER `created_by`;

-- Copy name → warehouse_name, capacity → capacity_tons where null
UPDATE `warehouses` SET `warehouse_name` = `name`     WHERE `warehouse_name` IS NULL;
UPDATE `warehouses` SET `capacity_tons`  = `capacity` WHERE `capacity_tons`  IS NULL AND `capacity` IS NOT NULL;

-- Add unique index for warehouse_code (only if column is not already unique)
ALTER TABLE `warehouses` ADD UNIQUE INDEX IF NOT EXISTS `uidx_warehouse_code` (`warehouse_code`);

-- ── shops: add missing columns ────────────────────────────────────────────────
ALTER TABLE `shops`
    ADD COLUMN IF NOT EXISTS `shop_name`     VARCHAR(150) NULL AFTER `name`,
    ADD COLUMN IF NOT EXISTS `shop_type`     VARCHAR(60)  NULL AFTER `shop_name`,
    ADD COLUMN IF NOT EXISTS `address`       VARCHAR(255) NULL AFTER `location`,
    ADD COLUMN IF NOT EXISTS `latitude`      DECIMAL(10,7) NULL AFTER `address`,
    ADD COLUMN IF NOT EXISTS `longitude`     DECIMAL(10,7) NULL AFTER `latitude`,
    ADD COLUMN IF NOT EXISTS `opening_time`  TIME NULL AFTER `email`,
    ADD COLUMN IF NOT EXISTS `closing_time`  TIME NULL AFTER `opening_time`,
    ADD COLUMN IF NOT EXISTS `created_by`    INT UNSIGNED  NULL AFTER `updated_at`,
    ADD COLUMN IF NOT EXISTS `updated_by`    INT UNSIGNED  NULL AFTER `created_by`;

-- Copy name → shop_name where null
UPDATE `shops` SET `shop_name` = `name` WHERE `shop_name` IS NULL;

-- Update existing farms with proper farm_code if null
UPDATE `farms` SET `farm_code` = CONCAT('FRM', LPAD(id, 3, '0')) WHERE `farm_code` IS NULL;

-- Update existing warehouses with proper warehouse_code if null
UPDATE `warehouses` SET `warehouse_code` = CONCAT('WH', LPAD(id, 3, '0')) WHERE `warehouse_code` IS NULL;

-- Update existing shops with proper shop_code if null
UPDATE `shops` SET `shop_code` = CONCAT('SHP', LPAD(id, 3, '0')) WHERE `shop_code` IS NULL;
