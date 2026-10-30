-- =========================================================
-- FIX: Create Missing Location Tables for aam_db
-- MariaDB compatible version (no ADD CONSTRAINT IF NOT EXISTS)
-- =========================================================

USE `aam_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. regions ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `regions` (
    `region_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `region_code` VARCHAR(20)  NOT NULL UNIQUE,
    `region_name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `is_active`   TINYINT(1)   DEFAULT 1,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  TIMESTAMP    NULL,
    INDEX `idx_region_active` (`is_active`, `deleted_at`),
    INDEX `idx_region_code`   (`region_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. zones ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `zones` (
    `zone_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `region_id`   INT UNSIGNED NOT NULL,
    `zone_code`   VARCHAR(20)  NOT NULL UNIQUE,
    `zone_name`   VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active`   TINYINT(1)   DEFAULT 1,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  TIMESTAMP    NULL,
    INDEX `idx_zone_region` (`region_id`),
    INDEX `idx_zone_active` (`is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. cities ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cities` (
    `city_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zone_id`     INT UNSIGNED NOT NULL,
    `city_code`   VARCHAR(20)  NOT NULL UNIQUE,
    `city_name`   VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active`   TINYINT(1)   DEFAULT 1,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  TIMESTAMP    NULL,
    INDEX `idx_city_zone`   (`zone_id`),
    INDEX `idx_city_active` (`is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. campuses ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `campuses` (
    `campus_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `city_id`     INT UNSIGNED NULL,
    `campus_code` VARCHAR(20)  NOT NULL UNIQUE,
    `campus_name` VARCHAR(150) NOT NULL,
    `address`     VARCHAR(255) NULL,
    `latitude`    DECIMAL(10,7) NULL,
    `longitude`   DECIMAL(10,7) NULL,
    `description` TEXT NULL,
    `is_active`   TINYINT(1)   DEFAULT 1,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  TIMESTAMP    NULL,
    INDEX `idx_campus_city`   (`city_id`),
    INDEX `idx_campus_active` (`is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. buildings ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `buildings` (
    `building_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campus_id`     INT UNSIGNED NOT NULL,
    `building_code` VARCHAR(20)  NOT NULL UNIQUE,
    `building_name` VARCHAR(150) NOT NULL,
    `floor_count`   TINYINT UNSIGNED DEFAULT 1,
    `description`   TEXT NULL,
    `is_active`     TINYINT(1)   DEFAULT 1,
    `created_by`    INT UNSIGNED NOT NULL,
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    TIMESTAMP    NULL,
    INDEX `idx_building_campus` (`campus_id`),
    INDEX `idx_building_active` (`is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. assignment_history ────────────────────────────────
CREATE TABLE IF NOT EXISTS `assignment_history` (
    `history_id`      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assignment_id`   INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NOT NULL,
    `location_type`   ENUM('farm','warehouse','shop') NOT NULL,
    `location_id`     INT UNSIGNED NOT NULL,
    `assignment_role` VARCHAR(60)  NOT NULL,
    `action`          ENUM('ASSIGNED','REASSIGNED','REMOVED') NOT NULL,
    `performed_by`    INT UNSIGNED NOT NULL,
    `performed_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `notes`           VARCHAR(255) NULL,
    INDEX `idx_ah_user`       (`user_id`),
    INDEX `idx_ah_assignment` (`assignment_id`),
    INDEX `idx_ah_performed`  (`performed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Add missing columns to farms if not present ──────────────────────────────
ALTER TABLE `farms`
    ADD COLUMN IF NOT EXISTS `campus_id`  INT UNSIGNED NULL AFTER `farm_type`,
    ADD COLUMN IF NOT EXISTS `city_id`    INT UNSIGNED NULL AFTER `campus_id`,
    ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP    NULL AFTER `updated_at`;

-- ── Add missing columns to warehouses if not present ─────────────────────────
ALTER TABLE `warehouses`
    ADD COLUMN IF NOT EXISTS `campus_id`   INT UNSIGNED NULL AFTER `warehouse_type`,
    ADD COLUMN IF NOT EXISTS `building_id` INT UNSIGNED NULL AFTER `campus_id`,
    ADD COLUMN IF NOT EXISTS `city_id`     INT UNSIGNED NULL AFTER `building_id`,
    ADD COLUMN IF NOT EXISTS `deleted_at`  TIMESTAMP    NULL AFTER `updated_at`;

-- ── Add missing columns to shops if not present ──────────────────────────────
ALTER TABLE `shops`
    ADD COLUMN IF NOT EXISTS `campus_id`   INT UNSIGNED NULL AFTER `shop_type`,
    ADD COLUMN IF NOT EXISTS `building_id` INT UNSIGNED NULL AFTER `campus_id`,
    ADD COLUMN IF NOT EXISTS `city_id`     INT UNSIGNED NULL AFTER `building_id`,
    ADD COLUMN IF NOT EXISTS `deleted_at`  TIMESTAMP    NULL AFTER `updated_at`;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Seed Data ─────────────────────────────────────────────────────────────────
SET @admin_id = (SELECT id FROM users WHERE role_id = 1 ORDER BY id LIMIT 1);

INSERT IGNORE INTO `regions` (`region_id`, `region_code`, `region_name`, `description`, `created_by`) VALUES
(1, 'OR', 'Oromia', 'Oromia National Regional State, Ethiopia', @admin_id);

INSERT IGNORE INTO `zones` (`zone_id`, `region_id`, `zone_code`, `zone_name`, `description`, `created_by`) VALUES
(1, 1, 'AZ', 'Arsi Zone', 'Arsi Zone, Oromia Region', @admin_id);

INSERT IGNORE INTO `cities` (`city_id`, `zone_id`, `city_code`, `city_name`, `description`, `created_by`) VALUES
(1, 1, 'ASL', 'Asella', 'Asella City, Arsi Zone', @admin_id);

INSERT IGNORE INTO `campuses` (`campus_id`, `city_id`, `campus_code`, `campus_name`, `address`, `description`, `created_by`) VALUES
(1, 1, 'MC', 'Main Campus',   'Asella, Arsi Zone, Oromia', 'Main academic and agricultural campus of Arsi University', @admin_id),
(2, 1, 'HC', 'Health Campus', 'Asella, Arsi Zone, Oromia', 'Health science campus', @admin_id);
