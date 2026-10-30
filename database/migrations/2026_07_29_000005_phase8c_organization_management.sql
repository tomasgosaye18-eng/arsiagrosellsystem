-- =========================================================
-- Migration: Phase 8C - Organization Management Module
-- File: 2026_07_29_000005_phase8c_organization_management.sql
-- Purpose: Create complete organization management schema
--          covering locations, farms, warehouses, shops,
--          and staff assignment with full history.
-- Dependencies: 2026_07_28_000004_phase8b_product_management.sql
-- =========================================================
-- Tables Created:
--   1.  regions
--   2.  zones
--   3.  cities
--   4.  campuses
--   5.  buildings
--   6.  farms
--   7.  warehouses
--   8.  shops
--   9.  organization_assignments
--   10. assignment_history
-- =========================================================

USE `arsiagro_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- TABLE: regions
-- Purpose: Top-level administrative region (e.g., Oromia)
-- =========================================================
CREATE TABLE IF NOT EXISTS `regions` (
    `region_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `region_code` VARCHAR(20)  NOT NULL UNIQUE COMMENT 'Short code (e.g., OR)',
    `region_name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Full region name',
    `description` TEXT NULL,
    `is_active`   TINYINT(1)   DEFAULT 1,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  TIMESTAMP    NULL,

    CONSTRAINT `fk_region_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,

    INDEX `idx_region_active`  (`is_active`, `deleted_at`),
    INDEX `idx_region_code`    (`region_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Administrative regions';

-- =========================================================
-- TABLE: zones
-- Purpose: Zone within a region (e.g., Arsi Zone)
-- =========================================================
CREATE TABLE IF NOT EXISTS `zones` (
    `zone_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `region_id` INT UNSIGNED NOT NULL COMMENT 'Parent region',
    `zone_code` VARCHAR(20)  NOT NULL UNIQUE,
    `zone_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active`   TINYINT(1)   DEFAULT 1,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  TIMESTAMP    NULL,

    CONSTRAINT `fk_zone_region`     FOREIGN KEY (`region_id`) REFERENCES `regions`(`region_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_zone_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`)  ON DELETE RESTRICT,

    INDEX `idx_zone_region` (`region_id`),
    INDEX `idx_zone_active` (`is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Administrative zones within regions';

-- =========================================================
-- TABLE: cities
-- Purpose: City / Woreda within a zone (e.g., Asella)
-- =========================================================
CREATE TABLE IF NOT EXISTS `cities` (
    `city_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zone_id`   INT UNSIGNED NOT NULL,
    `city_code` VARCHAR(20)  NOT NULL UNIQUE,
    `city_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active`   TINYINT(1)   DEFAULT 1,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  TIMESTAMP    NULL,

    CONSTRAINT `fk_city_zone`       FOREIGN KEY (`zone_id`)    REFERENCES `zones`(`zone_id`)  ON DELETE RESTRICT,
    CONSTRAINT `fk_city_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,

    INDEX `idx_city_zone`   (`zone_id`),
    INDEX `idx_city_active` (`is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cities / woredas within zones';

-- =========================================================
-- TABLE: campuses
-- Purpose: University campus (e.g., Main Campus, Health Campus)
-- =========================================================
CREATE TABLE IF NOT EXISTS `campuses` (
    `campus_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `city_id`     INT UNSIGNED NULL COMMENT 'City where campus is located',
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

    CONSTRAINT `fk_campus_city`       FOREIGN KEY (`city_id`)    REFERENCES `cities`(`city_id`)  ON DELETE SET NULL,
    CONSTRAINT `fk_campus_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,

    INDEX `idx_campus_city`   (`city_id`),
    INDEX `idx_campus_active` (`is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='University campuses';

-- =========================================================
-- TABLE: buildings
-- Purpose: Physical building on a campus
-- =========================================================
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

    CONSTRAINT `fk_building_campus`     FOREIGN KEY (`campus_id`)  REFERENCES `campuses`(`campus_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_building_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`)  ON DELETE RESTRICT,

    INDEX `idx_building_campus` (`campus_id`),
    INDEX `idx_building_active` (`is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Physical buildings on campus';

-- =========================================================
-- TABLE: farms
-- Purpose: University farm locations managed by the system
-- =========================================================
CREATE TABLE IF NOT EXISTS `farms` (
    `farm_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `farm_code`    VARCHAR(20)  NOT NULL UNIQUE COMMENT 'e.g. FRM001',
    `farm_name`    VARCHAR(150) NOT NULL,
    `farm_type`    ENUM('CROP','LIVESTOCK','MIXED','DAIRY','POULTRY','HORTICULTURE','OTHER')
                   DEFAULT 'MIXED' COMMENT 'Type of farm',
    -- Location
    `campus_id`    INT UNSIGNED NULL COMMENT 'Affiliated campus',
    `city_id`      INT UNSIGNED NULL COMMENT 'City where farm is located',
    `address`      VARCHAR(255) NULL,
    `latitude`     DECIMAL(10,7) NULL COMMENT 'GPS latitude',
    `longitude`    DECIMAL(10,7) NULL COMMENT 'GPS longitude',
    `land_area_ha` DECIMAL(10,2) NULL COMMENT 'Farm land area in hectares',
    -- Manager
    `manager_id`   INT UNSIGNED NULL COMMENT 'Assigned farm manager (user_id)',
    `phone`        VARCHAR(20)  NULL,
    `email`        VARCHAR(191) NULL,
    -- Description
    `description`  TEXT NULL,
    -- Status
    `is_active`    TINYINT(1)   DEFAULT 1,
    `is_featured`  TINYINT(1)   DEFAULT 0,
    -- Audit
    `created_by`   INT UNSIGNED NOT NULL,
    `updated_by`   INT UNSIGNED NULL,
    `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   TIMESTAMP    NULL,

    CONSTRAINT `fk_farm_campus`      FOREIGN KEY (`campus_id`)  REFERENCES `campuses`(`campus_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_farm_city`        FOREIGN KEY (`city_id`)    REFERENCES `cities`(`city_id`)   ON DELETE SET NULL,
    CONSTRAINT `fk_farm_manager`     FOREIGN KEY (`manager_id`) REFERENCES `users`(`user_id`)    ON DELETE SET NULL,
    CONSTRAINT `fk_farm_created_by`  FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`)    ON DELETE RESTRICT,
    CONSTRAINT `fk_farm_updated_by`  FOREIGN KEY (`updated_by`) REFERENCES `users`(`user_id`)    ON DELETE SET NULL,

    INDEX `idx_farm_code`    (`farm_code`),
    INDEX `idx_farm_active`  (`is_active`, `deleted_at`),
    INDEX `idx_farm_manager` (`manager_id`),
    INDEX `idx_farm_campus`  (`campus_id`),
    INDEX `idx_farm_type`    (`farm_type`),
    FULLTEXT INDEX `ft_farm_search` (`farm_name`, `farm_code`, `description`, `address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Farm locations managed by the university';

-- =========================================================
-- TABLE: warehouses
-- Purpose: Central warehouse(s) for product storage
-- =========================================================
CREATE TABLE IF NOT EXISTS `warehouses` (
    `warehouse_id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `warehouse_code`     VARCHAR(20)  NOT NULL UNIQUE COMMENT 'e.g. WH001',
    `warehouse_name`     VARCHAR(150) NOT NULL,
    `warehouse_type`     ENUM('CENTRAL','COLD_STORAGE','DRY_STORAGE','BONDED','DISTRIBUTION','OTHER')
                         DEFAULT 'CENTRAL',
    -- Location
    `campus_id`          INT UNSIGNED NULL,
    `building_id`        INT UNSIGNED NULL COMMENT 'Building on campus',
    `city_id`            INT UNSIGNED NULL,
    `address`            VARCHAR(255) NULL,
    `latitude`           DECIMAL(10,7) NULL,
    `longitude`          DECIMAL(10,7) NULL,
    -- Capacity
    `capacity_sqm`       DECIMAL(10,2) NULL COMMENT 'Floor area in sq meters',
    `capacity_tons`      DECIMAL(10,2) NULL COMMENT 'Weight capacity in metric tons',
    -- Manager
    `manager_id`         INT UNSIGNED NULL COMMENT 'Assigned warehouse manager',
    `phone`              VARCHAR(20)  NULL,
    `email`              VARCHAR(191) NULL,
    -- Description
    `description`        TEXT NULL,
    -- Status
    `is_active`          TINYINT(1)   DEFAULT 1,
    -- Audit
    `created_by`         INT UNSIGNED NOT NULL,
    `updated_by`         INT UNSIGNED NULL,
    `created_at`         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`         TIMESTAMP    NULL,

    CONSTRAINT `fk_wh_campus`      FOREIGN KEY (`campus_id`)    REFERENCES `campuses`(`campus_id`)   ON DELETE SET NULL,
    CONSTRAINT `fk_wh_building`    FOREIGN KEY (`building_id`)  REFERENCES `buildings`(`building_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_wh_city`        FOREIGN KEY (`city_id`)      REFERENCES `cities`(`city_id`)       ON DELETE SET NULL,
    CONSTRAINT `fk_wh_manager`     FOREIGN KEY (`manager_id`)   REFERENCES `users`(`user_id`)        ON DELETE SET NULL,
    CONSTRAINT `fk_wh_created_by`  FOREIGN KEY (`created_by`)   REFERENCES `users`(`user_id`)        ON DELETE RESTRICT,
    CONSTRAINT `fk_wh_updated_by`  FOREIGN KEY (`updated_by`)   REFERENCES `users`(`user_id`)        ON DELETE SET NULL,

    INDEX `idx_wh_code`    (`warehouse_code`),
    INDEX `idx_wh_active`  (`is_active`, `deleted_at`),
    INDEX `idx_wh_manager` (`manager_id`),
    INDEX `idx_wh_campus`  (`campus_id`),
    INDEX `idx_wh_type`    (`warehouse_type`),
    FULLTEXT INDEX `ft_wh_search` (`warehouse_name`, `warehouse_code`, `description`, `address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Warehouse locations';

-- =========================================================
-- TABLE: shops
-- Purpose: Retail shop locations selling to end customers
-- =========================================================
CREATE TABLE IF NOT EXISTS `shops` (
    `shop_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `shop_code`    VARCHAR(20)  NOT NULL UNIQUE COMMENT 'e.g. SHP001',
    `shop_name`    VARCHAR(150) NOT NULL,
    `shop_type`    ENUM('RETAIL','CAFETERIA','KIOSK','OUTLET','MOBILE','OTHER') DEFAULT 'RETAIL',
    -- Location
    `campus_id`    INT UNSIGNED NULL,
    `building_id`  INT UNSIGNED NULL,
    `city_id`      INT UNSIGNED NULL,
    `address`      VARCHAR(255) NULL,
    `latitude`     DECIMAL(10,7) NULL,
    `longitude`    DECIMAL(10,7) NULL,
    -- Staff
    `manager_id`   INT UNSIGNED NULL COMMENT 'Shop storekeeper / manager',
    `phone`        VARCHAR(20)  NULL,
    `email`        VARCHAR(191) NULL,
    -- Operating hours
    `opening_time` TIME NULL COMMENT 'Daily opening time',
    `closing_time` TIME NULL COMMENT 'Daily closing time',
    -- Description
    `description`  TEXT NULL,
    -- Status
    `is_active`    TINYINT(1)   DEFAULT 1,
    -- Audit
    `created_by`   INT UNSIGNED NOT NULL,
    `updated_by`   INT UNSIGNED NULL,
    `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   TIMESTAMP    NULL,

    CONSTRAINT `fk_shop_campus`     FOREIGN KEY (`campus_id`)   REFERENCES `campuses`(`campus_id`)   ON DELETE SET NULL,
    CONSTRAINT `fk_shop_building`   FOREIGN KEY (`building_id`) REFERENCES `buildings`(`building_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_shop_city`       FOREIGN KEY (`city_id`)     REFERENCES `cities`(`city_id`)       ON DELETE SET NULL,
    CONSTRAINT `fk_shop_manager`    FOREIGN KEY (`manager_id`)  REFERENCES `users`(`user_id`)        ON DELETE SET NULL,
    CONSTRAINT `fk_shop_created_by` FOREIGN KEY (`created_by`)  REFERENCES `users`(`user_id`)        ON DELETE RESTRICT,
    CONSTRAINT `fk_shop_updated_by` FOREIGN KEY (`updated_by`)  REFERENCES `users`(`user_id`)        ON DELETE SET NULL,

    INDEX `idx_shop_code`    (`shop_code`),
    INDEX `idx_shop_active`  (`is_active`, `deleted_at`),
    INDEX `idx_shop_manager` (`manager_id`),
    INDEX `idx_shop_campus`  (`campus_id`),
    INDEX `idx_shop_type`    (`shop_type`),
    FULLTEXT INDEX `ft_shop_search` (`shop_name`, `shop_code`, `description`, `address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Retail shop locations';

-- =========================================================
-- TABLE: organization_assignments
-- Purpose: Active assignments of users to locations
-- =========================================================
CREATE TABLE IF NOT EXISTS `organization_assignments` (
    `assignment_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT UNSIGNED NOT NULL COMMENT 'Assigned user',
    `location_type`   ENUM('farm','warehouse','shop') NOT NULL,
    `location_id`     INT UNSIGNED NOT NULL COMMENT 'ID within the location table',
    `assignment_role` VARCHAR(60)  NOT NULL COMMENT 'Role at this location (farm_manager, warehouse_manager, storekeeper, salesperson)',
    `assigned_by`     INT UNSIGNED NOT NULL COMMENT 'Admin who made the assignment',
    `assigned_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `notes`           VARCHAR(255) NULL,
    `is_active`       TINYINT(1)   DEFAULT 1 COMMENT '1=Current assignment, 0=Superseded',

    CONSTRAINT `fk_assign_user`        FOREIGN KEY (`user_id`)     REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_assign_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,

    INDEX `idx_assign_user`      (`user_id`, `is_active`),
    INDEX `idx_assign_location`  (`location_type`, `location_id`, `is_active`),
    INDEX `idx_assign_role`      (`assignment_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Current staff location assignments';

-- =========================================================
-- TABLE: assignment_history
-- Purpose: Full audit trail of all assignment changes
-- =========================================================
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

    CONSTRAINT `fk_ah_user`         FOREIGN KEY (`user_id`)       REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ah_performed_by` FOREIGN KEY (`performed_by`)  REFERENCES `users`(`user_id`) ON DELETE RESTRICT,

    INDEX `idx_ah_user`       (`user_id`),
    INDEX `idx_ah_assignment` (`assignment_id`),
    INDEX `idx_ah_performed`  (`performed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Full history of organization assignment changes';

-- =========================================================
-- SEED DATA: Default Region / Zone / City / Campus
-- =========================================================
INSERT IGNORE INTO `regions` (`region_id`, `region_code`, `region_name`, `description`, `created_by`) VALUES
(1, 'OR', 'Oromia', 'Oromia National Regional State, Ethiopia', 1);

INSERT IGNORE INTO `zones` (`zone_id`, `region_id`, `zone_code`, `zone_name`, `description`, `created_by`) VALUES
(1, 1, 'AZ', 'Arsi Zone', 'Arsi Zone, Oromia Region', 1);

INSERT IGNORE INTO `cities` (`city_id`, `zone_id`, `city_code`, `city_name`, `description`, `created_by`) VALUES
(1, 1, 'ASL', 'Asella', 'Asella City, Arsi Zone — home of Arsi University', 1);

INSERT IGNORE INTO `campuses` (`campus_id`, `city_id`, `campus_code`, `campus_name`, `address`, `description`, `created_by`) VALUES
(1, 1, 'MC', 'Main Campus', 'Asella, Arsi Zone, Oromia, Ethiopia', 'Main academic and agricultural campus of Arsi University', 1),
(2, 1, 'HC', 'Health Campus', 'Asella, Arsi Zone, Oromia, Ethiopia', 'Health science campus', 1);

-- =========================================================
-- SEED DATA: Demo Farms
-- =========================================================
INSERT IGNORE INTO `farms` (`farm_id`, `farm_code`, `farm_name`, `farm_type`, `campus_id`, `city_id`, `address`, `description`, `created_by`) VALUES
(1, 'FRM001', 'Arsi University Main Farm', 'MIXED',         1, 1, 'Main Campus, Asella', 'Primary mixed-crop research and production farm', 1),
(2, 'FRM002', 'Cattle & Dairy Farm',       'DAIRY',         1, 1, 'Main Campus, Asella', 'Dairy cattle farm producing milk and related products', 1),
(3, 'FRM003', 'Horticulture Farm',         'HORTICULTURE',  1, 1, 'Main Campus, Asella', 'Fruit and vegetable production farm', 1);

-- =========================================================
-- SEED DATA: Demo Warehouse
-- =========================================================
INSERT IGNORE INTO `warehouses` (`warehouse_id`, `warehouse_code`, `warehouse_name`, `warehouse_type`, `campus_id`, `city_id`, `address`, `capacity_tons`, `description`, `created_by`) VALUES
(1, 'WH001', 'Central Warehouse', 'CENTRAL', 1, 1, 'Main Campus, Asella', 500.00, 'Main storage facility for all agricultural products', 1);

-- =========================================================
-- SEED DATA: Demo Shops
-- =========================================================
INSERT IGNORE INTO `shops` (`shop_id`, `shop_code`, `shop_name`, `shop_type`, `campus_id`, `city_id`, `address`, `opening_time`, `closing_time`, `description`, `created_by`) VALUES
(1, 'SHP001', 'Main Campus Shop',   'RETAIL',     1, 1, 'Main Campus, Building A', '08:00:00', '18:00:00', 'Primary retail outlet on main campus', 1),
(2, 'SHP002', 'Health Campus Shop', 'RETAIL',     2, 1, 'Health Campus, Block 1', '08:00:00', '17:00:00', 'Retail shop serving health campus community', 1),
(3, 'SHP003', 'Cafeteria Outlet',   'CAFETERIA',  1, 1, 'Main Campus, Cafeteria', '07:00:00', '20:00:00', 'Cafeteria product sales point', 1);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- Update migrations README
-- =========================================================
-- Run order: 1→core, 2→inventory, 3→pos, 4→final, 5→auth,
--            6→login_history, 7→phase8a, 8→phase8b, 9→phase8c
-- =========================================================
