-- =========================================================
-- ARSI AGRI-MANAGE (AAM) — XAMPP Installation Script
-- =========================================================
-- Purpose   : Complete one-shot installation.
--             Run this single file in phpMyAdmin or MySQL CLI
--             to set up the entire AAM database.
-- Usage     : In phpMyAdmin: Import this file
--             In MySQL CLI:  mysql -u root < INSTALL_ALL.sql
-- Password  : admin / Admin@123
-- =========================================================

-- =========================================================
-- CREATE DATABASE
-- =========================================================
CREATE DATABASE IF NOT EXISTS `aam_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `aam_db`;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = '';
SET time_zone = '+03:00';

-- =========================================================
-- 1. ROLES TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `roles` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(60)     NOT NULL UNIQUE,
    `display_name` VARCHAR(100)    NOT NULL,
    `description`  VARCHAR(255)    NULL,
    `permissions`  JSON            NULL,
    `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 2. USERS TABLE (complete, all columns from all phases)
-- =========================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`                        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `employee_id`               VARCHAR(30)   NULL DEFAULT NULL UNIQUE,
    `role_id`                   INT UNSIGNED  NOT NULL DEFAULT 1,
    `username`                  VARCHAR(80)   NOT NULL UNIQUE,
    `password_hash`             VARCHAR(255)  NOT NULL,
    `first_name`                VARCHAR(80)   NOT NULL DEFAULT '',
    `middle_name`               VARCHAR(80)   NULL DEFAULT NULL,
    `last_name`                 VARCHAR(80)   NOT NULL DEFAULT '',
    `gender`                    ENUM('male','female','other','prefer_not_to_say') NULL DEFAULT NULL,
    `phone`                     VARCHAR(30)   NULL DEFAULT NULL,
    `email`                     VARCHAR(191)  NULL DEFAULT NULL UNIQUE,
    `profile_photo`             VARCHAR(255)  NULL DEFAULT NULL,
    `is_active`                 TINYINT(1)    NOT NULL DEFAULT 1,
    `account_status`            ENUM('active','inactive','suspended','locked','pending','deleted') NOT NULL DEFAULT 'active',
    `failed_login_attempts`     TINYINT       NOT NULL DEFAULT 0,
    `is_locked`                 TINYINT(1)    NOT NULL DEFAULT 0,
    `must_change_password`      TINYINT(1)    NOT NULL DEFAULT 0,
    `last_login_at`             TIMESTAMP     NULL DEFAULT NULL,
    `password_changed_at`       TIMESTAMP     NULL DEFAULT NULL,
    `password_reset_token`      VARCHAR(64)   NULL DEFAULT NULL,
    `password_reset_expires_at` TIMESTAMP     NULL DEFAULT NULL,
    `remember_token`            VARCHAR(64)   NULL DEFAULT NULL,
    `suspension_reason`         VARCHAR(500)  NULL DEFAULT NULL,
    `created_by`                INT UNSIGNED  NULL DEFAULT NULL,
    `created_at`                TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_users_role_id`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_users_account_status` ON `users` (`account_status`);
CREATE INDEX IF NOT EXISTS `idx_users_role_id`        ON `users` (`role_id`);
CREATE INDEX IF NOT EXISTS `idx_users_phone`          ON `users` (`phone`);

-- =========================================================
-- 3. CATEGORIES
-- =========================================================
CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100)  NOT NULL,
    `slug`        VARCHAR(110)  NULL DEFAULT NULL UNIQUE,
    `description` TEXT          NULL,
    `parent_id`   INT UNSIGNED  NULL DEFAULT NULL,
    `sort_order`  SMALLINT      NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 4. UNITS OF MEASURE
-- =========================================================
CREATE TABLE IF NOT EXISTS `units_of_measure` (
    `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(60)    NOT NULL UNIQUE,
    `abbreviation`     VARCHAR(20)    NOT NULL,
    `description`      VARCHAR(255)   NULL,
    `unit_type`        VARCHAR(30)    NULL,
    `base_unit_id`     INT UNSIGNED   NULL,
    `base_multiplier`  DECIMAL(15,6)  NULL DEFAULT 1.000000,
    `is_active`        TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       TIMESTAMP      NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5. PRODUCTS
-- =========================================================
CREATE TABLE IF NOT EXISTS `products` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `category_id`     INT UNSIGNED  NULL,
    `unit_id`         INT UNSIGNED  NULL,
    `name`            VARCHAR(200)  NOT NULL,
    `slug`            VARCHAR(210)  NULL UNIQUE,
    `sku`             VARCHAR(60)   NULL UNIQUE,
    `barcode`         VARCHAR(60)   NULL UNIQUE,
    `description`     TEXT          NULL,
    `unit_price`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `cost_price`      DECIMAL(12,2) NULL DEFAULT 0.00,
    `min_stock_level` INT           NOT NULL DEFAULT 0,
    `reorder_point`   INT           NOT NULL DEFAULT 0,
    `is_perishable`   TINYINT(1)    NOT NULL DEFAULT 0,
    `shelf_life_days` INT           NULL,
    `is_featured`     TINYINT(1)    NOT NULL DEFAULT 0,
    `product_status`  ENUM('active','inactive','discontinued','out_of_stock') NOT NULL DEFAULT 'active',
    `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
    `created_by`      INT UNSIGNED  NULL,
    `updated_by`      INT UNSIGNED  NULL,
    `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`      TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_products_unit`     FOREIGN KEY (`unit_id`)     REFERENCES `units_of_measure`(`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_products_category_id` ON `products`(`category_id`);
CREATE INDEX IF NOT EXISTS `idx_products_is_active`   ON `products`(`is_active`);

-- =========================================================
-- 6. PRODUCT IMAGES
-- =========================================================
CREATE TABLE IF NOT EXISTS `product_images` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `filename`   VARCHAR(255) NOT NULL,
    `alt_text`   VARCHAR(200) NULL,
    `is_primary` TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order` SMALLINT     NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_pi_product_id` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 7. PRODUCT PRICE HISTORY
-- =========================================================
CREATE TABLE IF NOT EXISTS `product_price_history` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `product_id`    INT UNSIGNED  NOT NULL,
    `old_price`     DECIMAL(12,2) NOT NULL,
    `new_price`     DECIMAL(12,2) NOT NULL,
    `change_reason` VARCHAR(255)  NULL,
    `changed_by`    INT UNSIGNED  NULL,
    `changed_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_pph_product_id` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 8. FARMS
-- =========================================================
CREATE TABLE IF NOT EXISTS `farms` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `farm_code`     VARCHAR(30)   NULL DEFAULT NULL COMMENT 'Human-readable farm identifier e.g. FARM-001',
    `name`          VARCHAR(150)  NOT NULL,
    `location`      VARCHAR(255)  NULL,
    `description`   TEXT          NULL,
    `farm_type`     VARCHAR(60)   NULL,
    `area_hectares` DECIMAL(10,2) NULL,
    `campus_id`     INT UNSIGNED  NULL,
    `building_id`   INT UNSIGNED  NULL,
    `manager_id`    INT UNSIGNED  NULL,
    `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_farms_farm_code` (`farm_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 9. WAREHOUSES
-- =========================================================
CREATE TABLE IF NOT EXISTS `warehouses` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150)  NOT NULL,
    `location`    VARCHAR(255)  NULL,
    `description` TEXT          NULL,
    `capacity`    DECIMAL(12,2) NULL,
    `campus_id`   INT UNSIGNED  NULL,
    `building_id` INT UNSIGNED  NULL,
    `manager_id`  INT UNSIGNED  NULL,
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 10. SHOPS
-- =========================================================
CREATE TABLE IF NOT EXISTS `shops` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `location`    VARCHAR(255) NULL,
    `description` TEXT         NULL,
    `campus_id`   INT UNSIGNED NULL,
    `manager_id`  INT UNSIGNED NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 11. USER LOCATION ASSIGNMENTS
-- =========================================================
CREATE TABLE IF NOT EXISTS `user_location_assignments` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED NOT NULL,
    `location_type` ENUM('farm','warehouse','shop') NOT NULL,
    `location_id`   INT UNSIGNED NOT NULL,
    `assigned_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `assigned_by`   INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ula_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_ula_user_id`  ON `user_location_assignments`(`user_id`);
CREATE INDEX IF NOT EXISTS `idx_ula_location` ON `user_location_assignments`(`location_type`,`location_id`);

-- =========================================================
-- 12. LOGIN HISTORY
-- =========================================================
CREATE TABLE IF NOT EXISTS `login_history` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`            INT UNSIGNED    NULL,
    `username_attempted` VARCHAR(80)     NOT NULL DEFAULT '',
    `was_successful`     TINYINT(1)      NOT NULL DEFAULT 0,
    `ip_address`         VARCHAR(45)     NULL,
    `user_agent`         VARCHAR(500)    NULL,
    `browser`            VARCHAR(100)    NULL,
    `os_name`            VARCHAR(100)    NULL,
    `device_type`        VARCHAR(50)     NULL,
    `failure_reason`     VARCHAR(255)    NULL,
    `session_id`         VARCHAR(64)     NULL,
    `logout_at`          TIMESTAMP       NULL DEFAULT NULL,
    `attempted_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_lh_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_lh_user_id`      ON `login_history`(`user_id`);
CREATE INDEX IF NOT EXISTS `idx_lh_attempted_at` ON `login_history`(`attempted_at`);

-- =========================================================
-- 13. REMEMBER ME TOKENS
-- =========================================================
CREATE TABLE IF NOT EXISTS `remember_me_tokens` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NOT NULL,
    `selector`       VARCHAR(32)  NOT NULL UNIQUE,
    `hashed_token`   VARCHAR(64)  NOT NULL,
    `user_agent`     VARCHAR(500) NULL,
    `ip_address`     VARCHAR(45)  NULL,
    `expires_at`     TIMESTAMP    NOT NULL,
    `last_used_at`   TIMESTAMP    NULL DEFAULT NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `account_status` VARCHAR(20)  NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_rmt_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_rmt_selector` ON `remember_me_tokens`(`selector`);
CREATE INDEX IF NOT EXISTS `idx_rmt_user_id`  ON `remember_me_tokens`(`user_id`);
CREATE INDEX IF NOT EXISTS `idx_rmt_expires`  ON `remember_me_tokens`(`expires_at`);

-- =========================================================
-- 14. ACTIVITY LOGS
-- =========================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED    NULL,
    `action`       VARCHAR(80)     NOT NULL,
    `description`  VARCHAR(255)    NOT NULL DEFAULT '',
    `subject_type` VARCHAR(80)     NULL,
    `subject_id`   INT UNSIGNED    NULL,
    `ip_address`   VARCHAR(45)     NULL,
    `user_agent`   VARCHAR(255)    NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_al_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_al_user_id` ON `activity_logs`(`user_id`);
CREATE INDEX IF NOT EXISTS `idx_al_action`  ON `activity_logs`(`action`);
CREATE INDEX IF NOT EXISTS `idx_al_created` ON `activity_logs`(`created_at`);

-- =========================================================
-- 15. AUDIT LOGS
-- =========================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED    NULL,
    `action_type`    VARCHAR(50)     NOT NULL,
    `module`         VARCHAR(60)     NOT NULL DEFAULT '',
    `table_affected` VARCHAR(80)     NULL,
    `record_id`      INT UNSIGNED    NULL,
    `old_values`     JSON            NULL,
    `new_values`     JSON            NULL,
    `ip_address`     VARCHAR(45)     NULL,
    `user_agent`     VARCHAR(500)    NULL,
    `action_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_auditl_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_audit_user_id` ON `audit_logs`(`user_id`);
CREATE INDEX IF NOT EXISTS `idx_audit_module`  ON `audit_logs`(`module`);
CREATE INDEX IF NOT EXISTS `idx_audit_at`      ON `audit_logs`(`action_at`);

-- =========================================================
-- 15B. AUDIT LOGS — PHASE 9 BUSINESS AUDIT TRAIL COLUMNS
-- =========================================================
-- Extends audit_logs for Business Operational Audit Trail.
-- Adds location, role, entity, and description columns for
-- full business traceability across Farm / Warehouse / Shop
-- / Transfers / Products / Users.
-- =========================================================
ALTER TABLE `audit_logs`
    ADD COLUMN IF NOT EXISTS `role_name`    VARCHAR(80)   NULL DEFAULT NULL
        COMMENT 'Role name of acting user at time of action',
    ADD COLUMN IF NOT EXISTS `entity_type`  VARCHAR(80)   NULL DEFAULT NULL
        COMMENT 'Business entity type e.g. harvest, sale, transfer',
    ADD COLUMN IF NOT EXISTS `entity_label` VARCHAR(255)  NULL DEFAULT NULL
        COMMENT 'Human-readable entity label e.g. product name',
    ADD COLUMN IF NOT EXISTS `description`  VARCHAR(500)  NULL DEFAULT NULL
        COMMENT 'Human-readable description of the action',
    ADD COLUMN IF NOT EXISTS `location_type`
        ENUM('farm','warehouse','shop','system','') NULL DEFAULT NULL
        COMMENT 'Which location context the action occurred in',
    ADD COLUMN IF NOT EXISTS `farm_id`      INT UNSIGNED  NULL DEFAULT NULL
        COMMENT 'Farm context if applicable',
    ADD COLUMN IF NOT EXISTS `warehouse_id` INT UNSIGNED  NULL DEFAULT NULL
        COMMENT 'Warehouse context if applicable',
    ADD COLUMN IF NOT EXISTS `shop_id`      INT UNSIGNED  NULL DEFAULT NULL
        COMMENT 'Shop context if applicable';

CREATE INDEX IF NOT EXISTS `idx_audit_role`          ON `audit_logs`(`role_name`);
CREATE INDEX IF NOT EXISTS `idx_audit_entity_type`   ON `audit_logs`(`entity_type`);
CREATE INDEX IF NOT EXISTS `idx_audit_farm`          ON `audit_logs`(`farm_id`);
CREATE INDEX IF NOT EXISTS `idx_audit_warehouse`     ON `audit_logs`(`warehouse_id`);
CREATE INDEX IF NOT EXISTS `idx_audit_shop`          ON `audit_logs`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_audit_module_action` ON `audit_logs`(`module`, `action_type`, `action_at`);

-- =========================================================
-- 16. NOTIFICATIONS
-- =========================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recipient_user_id` INT UNSIGNED    NOT NULL,
    `sender_user_id`    INT UNSIGNED    NULL,
    `notification_type` VARCHAR(80)     NOT NULL,
    `title`             VARCHAR(200)    NOT NULL,
    `message`           TEXT            NULL,
    `reference_type`    VARCHAR(60)     NULL,
    `reference_id`      INT UNSIGNED    NULL,
    `is_read`           TINYINT(1)      NOT NULL DEFAULT 0,
    `read_at`           TIMESTAMP       NULL DEFAULT NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_notif_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_notif_recipient` ON `notifications`(`recipient_user_id`,`is_read`);

-- =========================================================
-- 17. SYSTEM SETTINGS
-- =========================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT         NULL,
    `data_type`     ENUM('string','integer','boolean','json','float') NOT NULL DEFAULT 'string',
    `group_name`    VARCHAR(60)  NOT NULL DEFAULT 'general',
    `description`   VARCHAR(255) NULL,
    `is_public`     TINYINT(1)   NOT NULL DEFAULT 0,
    `is_encrypted`  TINYINT(1)   NOT NULL DEFAULT 0,
    `updated_by`    INT UNSIGNED NULL,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ss_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 18. PERMISSIONS
-- =========================================================
CREATE TABLE IF NOT EXISTS `permissions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(80)  NOT NULL UNIQUE,
    `module`      VARCHAR(60)  NOT NULL DEFAULT '',
    `label`       VARCHAR(120) NOT NULL DEFAULT '',
    `description` VARCHAR(255) NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 19. ROLE PERMISSIONS
-- =========================================================
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_role_perm` (`role_id`, `permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- SEED DATA
-- =========================================================

-- Roles
INSERT IGNORE INTO `roles` (`id`, `name`, `display_name`, `description`, `is_active`) VALUES
(1, 'administrator',     'System Administrator',  'Full system access',                                    1),
(2, 'farm_manager',      'Farm Manager',          'Manages farm operations: harvests, dispatches',          1),
(3, 'warehouse_manager', 'Warehouse Manager',     'Manages warehouse: receiving, stock, transfers',         1),
(5, 'salesperson',       'Salesperson',           'Operates the POS terminal and processes cash sales',     1),
(6, 'general_manager',   'General Manager',       'Read-only access to all reports and dashboards',        1);

-- =========================================================
-- DEFAULT ADMIN ACCOUNT
-- Username : admin
-- Password : Admin@123
-- Hash     : bcrypt(cost=12) of 'Admin@123'
-- =========================================================
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`,
    `is_active`, `account_status`,
    `failed_login_attempts`, `is_locked`, `must_change_password`,
    `created_at`
) VALUES (
    1, 1, 'admin',
    '$2y$12$EajGTTRivwLm1gz2LXZXMOZoH58cH.pDJGqblmrlkxHINHv8cUcY6',
    'System', 'Administrator',
    1, 'active', 0, 0, 0, NOW()
);

-- Ensure admin credentials are always correct
UPDATE `users` SET
    `password_hash`         = '$2y$12$EajGTTRivwLm1gz2LXZXMOZoH58cH.pDJGqblmrlkxHINHv8cUcY6',
    `must_change_password`  = 0,
    `is_active`             = 1,
    `account_status`        = 'active',
    `is_locked`             = 0,
    `failed_login_attempts` = 0
WHERE `username` = 'admin';

-- Categories
INSERT IGNORE INTO `categories` (`id`, `name`, `description`, `is_active`) VALUES
(1, 'Grains & Cereals',  'Wheat, Barley, Teff, Maize, Sorghum',  1),
(2, 'Pulses & Legumes',  'Beans, Lentils, Chickpeas, Peas',       1),
(3, 'Vegetables',        'Tomatoes, Onions, Potatoes, Cabbage',   1),
(4, 'Fruits',            'Apples, Bananas, Oranges, Avocados',    1),
(5, 'Oilseeds',          'Sesame, Sunflower, Niger Seed, Flax',   1),
(6, 'Coffee & Tea',      'Coffee Beans, Tea Leaves',              1),
(7, 'Animal Products',   'Milk, Eggs, Honey, Butter',             1),
(8, 'Other Products',    'Miscellaneous agricultural products',   1);

-- Units of Measure
INSERT IGNORE INTO `units_of_measure` (`id`, `name`, `abbreviation`, `description`, `is_active`) VALUES
(1,  'Kilogram',   'kg',  'Standard weight unit',    1),
(2,  'Gram',       'g',   '1/1000 of a kilogram',    1),
(3,  'Metric Ton', 'ton', '1000 kilograms',          1),
(4,  'Quintal',    'qtl', '100 kilograms',           1),
(5,  'Liter',      'l',   'Standard volume unit',    1),
(6,  'Milliliter', 'ml',  '1/1000 of a liter',      1),
(7,  'Piece',      'pc',  'Individual item count',   1),
(8,  'Dozen',      'dzn', '12 pieces',               1),
(9,  'Bag',        'bag', 'Standard bag',            1),
(10, 'Sack',       'sck', 'Large sack',              1);

-- Warehouses
INSERT IGNORE INTO `warehouses` (`id`, `name`, `location`, `description`, `is_active`) VALUES
(1, 'Central Warehouse', 'Main Campus, Asella', 'Main storage facility', 1);

-- Farms
INSERT IGNORE INTO `farms` (`id`, `name`, `location`, `farm_type`, `is_active`) VALUES
(1, 'Arsi University Main Farm', 'Main Campus, Asella',  'Mixed',        1),
(2, 'Cattle & Dairy Farm',       'Main Campus, Asella',  'Dairy',        1),
(3, 'Horticulture Farm',         'Main Campus, Asella',  'Horticulture', 1);

-- Backfill farm_code for seeded farms
UPDATE `farms` SET `farm_code` = CONCAT('FARM-', LPAD(`id`, 3, '0'))
WHERE `farm_code` IS NULL OR `farm_code` = '';

-- Shops
INSERT IGNORE INTO `shops` (`id`, `name`, `location`, `is_active`) VALUES
(1, 'Main Campus Shop',   'Main Campus, Building A, Asella', 1),
(2, 'Health Campus Shop', 'Health Campus, Block 1, Asella',  1),
(3, 'Cafeteria Outlet',   'Main Campus, Cafeteria, Asella',  1);

-- Add Phase 12 shop columns
ALTER TABLE `shops`
    ADD COLUMN IF NOT EXISTS `shop_code` VARCHAR(30)  NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `phone`     VARCHAR(30)  NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `email`     VARCHAR(191) NULL DEFAULT NULL;

CREATE INDEX IF NOT EXISTS `idx_shops_code` ON `shops`(`shop_code`);

-- Backfill shop_code
UPDATE `shops` SET `shop_code` = CONCAT('SHOP-', LPAD(`id`, 3, '0'))
WHERE `shop_code` IS NULL OR `shop_code` = '';

-- System Settings
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `data_type`, `group_name`, `description`, `is_public`) VALUES
('app.name',                  'Arsi Agri-Manage',  'string',  'general',     'Application display name',     1),
('app.version',               '1.0.0',             'string',  'general',     'Application version',          1),
('app.timezone',              'Africa/Addis_Ababa','string',  'general',     'Default application timezone', 0),
('financial.currency_code',   'ETB',               'string',  'financial',   'Currency code',                1),
('financial.currency_symbol', 'Br',                'string',  'financial',   'Currency symbol',              1),
('maintenance.enabled',       '0',                 'boolean', 'maintenance', 'Maintenance mode',             0);

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT 'AAM Database installed successfully!' AS `status`;
SELECT CONCAT('Admin: ', username, ' | Status: ', account_status, ' | Locked: ', is_locked, ' | MustChange: ', must_change_password) AS admin_info
FROM users WHERE username = 'admin';
SELECT COUNT(*) AS total_tables FROM information_schema.tables WHERE table_schema = 'aam_db';

-- =========================================================
-- 20. HARVEST BATCHES  (Phase 5B — Farm Operations)
-- =========================================================
CREATE TABLE IF NOT EXISTS `harvest_batches` (
    `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `farm_id`           INT UNSIGNED     NOT NULL,
    `product_id`        INT UNSIGNED     NOT NULL,
    `batch_number`      VARCHAR(50)      NOT NULL UNIQUE,
    `production_date`   DATE             NOT NULL,
    `harvest_date`      DATE             NOT NULL,
    `expiry_date`       DATE             NULL DEFAULT NULL,
    `initial_quantity`  DECIMAL(12,3)    NOT NULL,
    `remaining_quantity`DECIMAL(12,3)    NOT NULL,
    `unit_id`           INT UNSIGNED     NOT NULL,
    `quality_grade`     ENUM('A','B','C','Ungraded') NOT NULL DEFAULT 'Ungraded',
    `status`            ENUM('active','partially_dispatched','fully_dispatched',
                             'depleted','expired','quarantine')
                                         NOT NULL DEFAULT 'active',
    `notes`             TEXT             NULL DEFAULT NULL,
    `recorded_by`       INT UNSIGNED     NOT NULL,
    `created_at`        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_hb_farm_id`       FOREIGN KEY (`farm_id`)     REFERENCES `farms`(`id`)             ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_hb_product_id`    FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)          ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_hb_unit_id`       FOREIGN KEY (`unit_id`)     REFERENCES `units_of_measure`(`id`)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_hb_recorded_by`   FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`)             ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_hb_initial_qty`  CHECK (`initial_quantity` > 0),
    CONSTRAINT `chk_hb_remaining`    CHECK (`remaining_quantity` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Every harvest event at every farm; origin of all supply chain inventory';

CREATE INDEX IF NOT EXISTS `idx_hb_farm_id`      ON `harvest_batches`(`farm_id`);
CREATE INDEX IF NOT EXISTS `idx_hb_product_id`   ON `harvest_batches`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_hb_status`        ON `harvest_batches`(`status`);
CREATE INDEX IF NOT EXISTS `idx_hb_harvest_date`  ON `harvest_batches`(`harvest_date`);

-- =========================================================
-- 21. FARM DISPATCHES  (Phase 5B — Farm Operations)
-- =========================================================
CREATE TABLE IF NOT EXISTS `farm_dispatches` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `farm_id`               INT UNSIGNED NOT NULL,
    `warehouse_id`          INT UNSIGNED NOT NULL,
    `dispatch_number`       VARCHAR(30)  NOT NULL UNIQUE,
    `dispatch_date`         DATE         NOT NULL,
    `expected_arrival_date` DATE         NULL DEFAULT NULL,
    `status`                ENUM('pending','in_transit','received','partially_received',
                                 'received_with_discrepancy','cancelled')
                                         NOT NULL DEFAULT 'pending',
    `notes`                 TEXT         NULL DEFAULT NULL,
    `initiated_by`          INT UNSIGNED NOT NULL,
    `received_by`           INT UNSIGNED NULL DEFAULT NULL,
    `received_date`         DATE         NULL DEFAULT NULL,
    `created_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_fd_farm_id`       FOREIGN KEY (`farm_id`)      REFERENCES `farms`(`id`)      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fd_warehouse_id`  FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fd_initiated_by`  FOREIGN KEY (`initiated_by`) REFERENCES `users`(`id`)      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fd_received_by`   FOREIGN KEY (`received_by`)  REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Farm-to-warehouse dispatch header records';

CREATE INDEX IF NOT EXISTS `idx_fd_farm_id`         ON `farm_dispatches`(`farm_id`);
CREATE INDEX IF NOT EXISTS `idx_fd_status`          ON `farm_dispatches`(`status`);
CREATE INDEX IF NOT EXISTS `idx_fd_dispatch_date`   ON `farm_dispatches`(`dispatch_date`);

-- =========================================================
-- 22. FARM DISPATCH ITEMS  (Phase 5B)
-- =========================================================
CREATE TABLE IF NOT EXISTS `farm_dispatch_items` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `dispatch_id`         INT UNSIGNED  NOT NULL,
    `harvest_batch_id`    INT UNSIGNED  NOT NULL,
    `product_id`          INT UNSIGNED  NOT NULL,
    `quantity_dispatched` DECIMAL(12,3) NOT NULL,
    `quantity_received`   DECIMAL(12,3) NULL DEFAULT NULL,
    `unit_id`             INT UNSIGNED  NOT NULL,
    `item_status`         ENUM('pending','received','partially_received','not_received')
                                        NOT NULL DEFAULT 'pending',
    `notes`               TEXT          NULL DEFAULT NULL,
    `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_fdi_dispatch_id`       FOREIGN KEY (`dispatch_id`)      REFERENCES `farm_dispatches`(`id`)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fdi_harvest_batch_id`  FOREIGN KEY (`harvest_batch_id`) REFERENCES `harvest_batches`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fdi_product_id`        FOREIGN KEY (`product_id`)       REFERENCES `products`(`id`)         ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fdi_unit_id`           FOREIGN KEY (`unit_id`)          REFERENCES `units_of_measure`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_fdi_qty`              CHECK (`quantity_dispatched` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Line items per farm dispatch';

CREATE INDEX IF NOT EXISTS `idx_fdi_dispatch_id`      ON `farm_dispatch_items`(`dispatch_id`);
CREATE INDEX IF NOT EXISTS `idx_fdi_harvest_batch_id` ON `farm_dispatch_items`(`harvest_batch_id`);
CREATE INDEX IF NOT EXISTS `idx_fdi_product_id`       ON `farm_dispatch_items`(`product_id`);

-- =========================================================
-- 23. ORGANIZATION ASSIGNMENTS  (Phase 8C)
--     Uses users.id (aam_db FK — not arsiagro_db user_id)
-- =========================================================
CREATE TABLE IF NOT EXISTS `organization_assignments` (
    `assignment_id`   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED  NOT NULL,
    `location_type`   ENUM('farm','warehouse','shop') NOT NULL,
    `location_id`     INT UNSIGNED  NOT NULL,
    `assignment_role` VARCHAR(60)   NOT NULL,
    `assigned_by`     INT UNSIGNED  NOT NULL,
    `assigned_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes`           VARCHAR(255)  NULL,
    `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (`assignment_id`),
    CONSTRAINT `fk_oa_user_id`       FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_oa_assigned_by`   FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Current staff location assignments (Phase 8C)';

CREATE INDEX IF NOT EXISTS `idx_oa_user_id`     ON `organization_assignments`(`user_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_oa_location`    ON `organization_assignments`(`location_type`, `location_id`, `is_active`);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- PHASE 9A — FARM MANAGER TEST USER SEED
-- =========================================================
-- Test credentials:
--   Username  : farm_manager_01
--   Password  : FarmMgr@2026
--   Role      : farm_manager (role_id = 2)
--   Farm      : Arsi University Main Farm (id = 1)
-- =========================================================

-- Ensure Farm 01 exists
INSERT IGNORE INTO `farms` (`id`, `name`, `location`, `farm_type`, `is_active`) VALUES
(1, 'Arsi University Main Farm', 'Main Campus, Asella', 'Mixed', 1);

UPDATE `farms` SET `is_active` = 1 WHERE `id` = 1;

-- Create farm_manager_01 user
-- Password hash: bcrypt(cost=12) of 'FarmMgr@2026'
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`, `employee_id`,
    `is_active`, `account_status`,
    `failed_login_attempts`, `is_locked`, `must_change_password`,
    `created_at`
) VALUES (
    100, 2, 'farm_manager_01',
    '$2y$12$uD3b1.p9jGpUKLwHm/sq0efwXE8gPCR7sLh7lEVvMEDfeU5JR4MCy',
    'Abebe', 'Girma', 'FM-001',
    1, 'active', 0, 0, 0, NOW()
);

UPDATE `users` SET
    `role_id`               = 2,
    `password_hash`         = '$2y$12$uD3b1.p9jGpUKLwHm/sq0efwXE8gPCR7sLh7lEVvMEDfeU5JR4MCy',
    `is_active`             = 1,
    `account_status`        = 'active',
    `failed_login_attempts` = 0,
    `is_locked`             = 0,
    `must_change_password`  = 0
WHERE `username` = 'farm_manager_01';

-- Assign farm_manager_01 to Farm 01 (user_location_assignments)
DELETE FROM `user_location_assignments`
WHERE `user_id` = (SELECT `id` FROM `users` WHERE `username` = 'farm_manager_01' LIMIT 1);

INSERT INTO `user_location_assignments` (`user_id`, `location_type`, `location_id`, `assigned_at`, `assigned_by`)
SELECT u.id, 'farm', 1, NOW(), 1
FROM `users` u WHERE u.`username` = 'farm_manager_01' LIMIT 1;

-- Also add to organization_assignments
SET @fm_id = (SELECT `id` FROM `users` WHERE `username` = 'farm_manager_01' LIMIT 1);

INSERT IGNORE INTO `organization_assignments`
    (`user_id`, `location_type`, `location_id`, `assignment_role`, `assigned_by`, `notes`, `is_active`)
VALUES
    (@fm_id, 'farm', 1, 'farm_manager', 1, 'Phase 9A dev seed', 1);

-- =========================================================
-- FINAL VERIFICATION
-- =========================================================
SELECT 'AAM Database installed successfully!' AS `status`;

SELECT CONCAT('Admin      → username: admin | status: ', account_status,
              ' | locked: ', is_locked) AS accounts
FROM `users` WHERE `username` = 'admin'
UNION ALL
SELECT CONCAT('Farm Mgr   → username: farm_manager_01 | status: ', account_status,
              ' | locked: ', is_locked)
FROM `users` WHERE `username` = 'farm_manager_01';

SELECT CONCAT('Farm assignment → farm_manager_01 → farm_id: ',
              ula.location_id, ' → ', f.name) AS assignment
FROM `user_location_assignments` ula
JOIN `users` u ON u.id = ula.user_id
JOIN `farms` f ON f.id = ula.location_id
WHERE u.username = 'farm_manager_01' AND ula.location_type = 'farm';

SELECT COUNT(*) AS total_tables
FROM information_schema.tables
WHERE table_schema = 'aam_db';

-- =========================================================
-- PHASE 9B — MANAGER + ADMIN INTEGRATION
-- Grant General Manager (role_id = 6) full permissions.
-- This implements the unified Manager who controls both
-- Business Management and System Administration.
-- =========================================================

-- Update GM role description
UPDATE `roles`
SET `display_name` = 'General Manager',
    `description`  = 'Full business management and system administration access'
WHERE `name` = 'general_manager';

-- Seed all permission codes (safe — INSERT IGNORE)
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('auth.login',                  'auth',         'Login',                    'Can log into the system',                           1),
('auth.password.change.own',    'auth',         'Change Own Password',      'Can change own password',                           1),
('auth.password.reset.any',     'auth',         'Reset Any Password',       'Can reset any user password',                       1),
('admin.users.view',            'admin',        'View Users',               'Can view list of all users',                        1),
('admin.users.create',          'admin',        'Create Users',             'Can create new user accounts',                      1),
('admin.users.edit',            'admin',        'Edit Users',               'Can edit user details and role',                    1),
('admin.users.deactivate',      'admin',        'Deactivate Users',         'Can deactivate/activate user accounts',             1),
('admin.users.unlock',          'admin',        'Unlock Users',             'Can unlock locked user accounts',                   1),
('admin.products.view',         'admin',        'View Products',            'Can view product catalog',                          1),
('admin.products.create',       'admin',        'Create Products',          'Can add new products to catalog',                   1),
('admin.products.edit',         'admin',        'Edit Products',            'Can edit product details',                          1),
('admin.products.deactivate',   'admin',        'Deactivate Products',      'Can deactivate products',                           1),
('admin.products.price.change', 'admin',        'Change Prices',            'Can change product selling prices',                 1),
('admin.categories.manage',     'admin',        'Manage Categories',        'Can manage product categories',                     1),
('admin.units.manage',          'admin',        'Manage Units',             'Can manage units of measure',                       1),
('admin.organization.view',     'admin',        'View Organization',        'Can view organization structure',                   1),
('admin.organization.manage',   'admin',        'Manage Organization',      'Can manage farms, warehouses, shops, assignments',  1),
('admin.audit.view',            'admin',        'View Audit Trail',         'Can view audit log records',                        1),
('admin.audit.export',          'admin',        'Export Audit Trail',       'Can export audit log records',                      1),
('admin.settings.view',         'admin',        'View Settings',            'Can view system settings',                          1),
('admin.settings.edit',         'admin',        'Edit Settings',            'Can modify system settings',                        1),
('admin.backup.create',         'admin',        'Create Backup',            'Can trigger database backup',                       1),
('admin.backup.restore',        'admin',        'Restore Backup',           'Can restore from backup',                           1),
('admin.maintenance.manage',    'admin',        'Manage Maintenance Mode',  'Can enable/disable maintenance mode',               1),
('farm.list.view',              'farm',         'View Farms List',          'Can view list of farms',                            1),
('farm.own.view',               'farm',         'View Own Farm',            'Can view own assigned farm details',                1),
('farm.harvest.create',         'farm',         'Create Harvest',           'Can register new harvest records',                  1),
('farm.harvest.view',           'farm',         'View Harvests',            'Can view harvest history',                          1),
('farm.dispatch.create',        'farm',         'Create Dispatch',          'Can initiate product dispatches',                   1),
('farm.dispatch.view',          'farm',         'View Dispatches',          'Can view dispatch history',                         1),
('farm.inventory.view',         'farm',         'View Farm Inventory',      'Can view farm inventory levels',                    1),
('warehouse.inventory.view',    'warehouse',    'View WH Inventory',        'Can view warehouse inventory',                      1),
('warehouse.receiving.view',    'warehouse',    'View Receiving',           'Can view receiving records',                        1),
('warehouse.receiving.confirm', 'warehouse',    'Confirm Receiving',        'Can confirm receipt of farm dispatches',            1),
('warehouse.adjustment.request','warehouse',    'Request Adjustment',       'Can request inventory adjustments',                 1),
('warehouse.adjustment.approve','warehouse',    'Approve Adjustment',       'Can approve inventory adjustments',                 1),
('warehouse.discrepancy.view',  'warehouse',    'View Discrepancies',       'Can view discrepancy records',                      1),
('transfer.create',             'transfer',     'Create Transfer',          'Can create transfer orders',                        1),
('transfer.view.all',           'transfer',     'View All Transfers',       'Can view all transfer orders',                      1),
('transfer.view.own.shop',      'transfer',     'View Own Shop Transfers',  'Can view own shop transfers',                       1),
('transfer.confirm',            'transfer',     'Confirm Transfer',         'Can confirm transfer receipt',                      1),
('transfer.discrepancy.view',   'transfer',     'View Transfer Discrepancy','Can view transfer discrepancies',                   1),
('shop.list.view',              'shop',         'View All Shops',           'Can view list of all shops',                        1),
('shop.own.view',               'shop',         'View Own Shop',            'Can view own assigned shop details',                1),
('shop.inventory.view',         'shop',         'View Shop Inventory',      'Can view shop inventory',                           1),
('shop.sales.view.own',         'shop',         'View Own Shop Sales',      'Can view sales at own shop',                        1),
('shop.sales.view.all',         'shop',         'View All Shop Sales',      'Can view sales at all shops',                       1),
('shop.adjustment.request',     'shop',         'Request Shop Adjustment',  'Can request adjustments for own shop',              1),
('pos.sale.create',             'pos',          'Create Sale',              'Can process cash sales at POS',                     1),
('pos.sale.view.own',           'pos',          'View Own Sales',           'Can view own sales history',                        1),
('pos.sale.void',               'pos',          'Void Sale',                'Can void a completed sale',                         1),
('pos.return.create',           'pos',          'Process Return',           'Can process a sale return',                         1),
('pos.receipt.reprint',         'pos',          'Reprint Receipt',          'Can reprint a receipt',                             1),
('reports.farm.view',           'reports',      'Farm Reports',             'Can view farm production reports',                  1),
('reports.warehouse.view',      'reports',      'Warehouse Reports',        'Can view warehouse reports',                        1),
('reports.transfer.view',       'reports',      'Transfer Reports',         'Can view transfer reports',                         1),
('reports.sales.view.own',      'reports',      'Own Shop Sales Reports',   'Can view own shop sales reports',                   1),
('reports.sales.view.all',      'reports',      'All Sales Reports',        'Can view all sales reports',                        1),
('reports.revenue.view',        'reports',      'Revenue Reports',          'Can view revenue reports',                          1),
('reports.discrepancy.view',    'reports',      'Discrepancy Reports',      'Can view discrepancy reports',                      1),
('reports.user.activity.view',  'reports',      'User Activity Reports',    'Can view user activity reports',                    1),
('reports.export',              'reports',      'Export Reports',           'Can export reports',                                1),
('dashboard.admin.view',        'dashboard',    'Admin Dashboard',          'Can view admin dashboard',                          1),
('dashboard.gm.view',           'dashboard',    'GM Dashboard',             'Can view GM dashboard',                             1),
('dashboard.farm.view',         'dashboard',    'Farm Dashboard',           'Can view farm manager dashboard',                   1),
('dashboard.warehouse.view',    'dashboard',    'Warehouse Dashboard',      'Can view warehouse dashboard',                      1),
('dashboard.shop.view',         'dashboard',    'Shop Dashboard',           'Can view shop dashboard',                           1),
('dashboard.cashier.view',      'dashboard',    'Cashier Dashboard',        'Can view cashier dashboard',                        1),
('inventory.view.all',          'inventory',    'View All Inventory',       'Can view inventory across all locations',           1),
('inventory.batch.view',        'inventory',    'View Batches',             'Can view batch records',                            1),
('notifications.view',          'notifications','View Notifications',       'Can view own notifications',                        1);

-- Administrator (id=1): all permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- General Manager (id=6): ALL permissions (Business + System Admin)
DELETE FROM `role_permissions` WHERE `role_id` = 6;
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, `id` FROM `permissions`;

SELECT 'Manager + Admin integration seeded.' AS `status`;
SELECT r.`name`, COUNT(rp.`permission_id`) AS perms
FROM `roles` r
LEFT JOIN `role_permissions` rp ON rp.`role_id` = r.`id`
WHERE r.`id` IN (1, 6)
GROUP BY r.`id`, r.`name`;

-- =========================================================
-- PHASE 10: WAREHOUSE OPERATIONS MODULE
-- Run this block after all previous migrations.
-- All statements are IF NOT EXISTS safe.
-- =========================================================

-- 1. Warehouse Inventory
CREATE TABLE IF NOT EXISTS `warehouse_inventory` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `warehouse_id`       INT UNSIGNED  NOT NULL,
    `product_id`         INT UNSIGNED  NOT NULL,
    `batch_number`       VARCHAR(60)   NULL DEFAULT NULL,
    `current_quantity`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `reserved_quantity`  DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `available_quantity` DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `unit_id`            INT UNSIGNED  NULL,
    `min_stock_level`    DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `production_date`    DATE          NULL,
    `expiry_date`        DATE          NULL,
    `last_movement_at`   TIMESTAMP     NULL DEFAULT NULL,
    `last_movement_type` VARCHAR(40)   NULL DEFAULT NULL,
    `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wi_warehouse_product` (`warehouse_id`, `product_id`),
    CONSTRAINT `fk_wi_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wi_product`   FOREIGN KEY (`product_id`)   REFERENCES `products`(`id`)   ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Current inventory balance per product per warehouse';

CREATE INDEX IF NOT EXISTS `idx_wi_warehouse_id` ON `warehouse_inventory`(`warehouse_id`);
CREATE INDEX IF NOT EXISTS `idx_wi_product_id`   ON `warehouse_inventory`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_wi_expiry`        ON `warehouse_inventory`(`expiry_date`);

-- 2. Warehouse Receiving (header)
CREATE TABLE IF NOT EXISTS `warehouse_receiving` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `receiving_number` VARCHAR(30)   NOT NULL UNIQUE,
    `dispatch_id`      INT UNSIGNED  NOT NULL,
    `warehouse_id`     INT UNSIGNED  NOT NULL,
    `farm_id`          INT UNSIGNED  NULL,
    `received_by`      INT UNSIGNED  NULL,
    `received_date`    DATE          NOT NULL,
    `status`           ENUM('pending','received','partially_received','discrepancy','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `total_expected`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `total_received`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `notes`            TEXT          NULL,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wr_dispatch` (`dispatch_id`),
    CONSTRAINT `fk_wr_dispatch`     FOREIGN KEY (`dispatch_id`)  REFERENCES `farm_dispatches`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wr_warehouse`    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wr_received_by`  FOREIGN KEY (`received_by`)  REFERENCES `users`(`id`)           ON DELETE SET NULL  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Warehouse receiving records';

CREATE INDEX IF NOT EXISTS `idx_wr_dispatch_id`   ON `warehouse_receiving`(`dispatch_id`);
CREATE INDEX IF NOT EXISTS `idx_wr_warehouse_id`  ON `warehouse_receiving`(`warehouse_id`);
CREATE INDEX IF NOT EXISTS `idx_wr_status`        ON `warehouse_receiving`(`status`);
CREATE INDEX IF NOT EXISTS `idx_wr_received_date` ON `warehouse_receiving`(`received_date`);

-- 3. Warehouse Receiving Items (line items)
CREATE TABLE IF NOT EXISTS `warehouse_receiving_items` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `receiving_id`        INT UNSIGNED  NOT NULL,
    `dispatch_item_id`    INT UNSIGNED  NOT NULL,
    `product_id`          INT UNSIGNED  NOT NULL,
    `unit_id`             INT UNSIGNED  NULL,
    `batch_number`        VARCHAR(60)   NULL,
    `production_date`     DATE          NULL,
    `expiry_date`         DATE          NULL,
    `quantity_expected`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_received`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_variance`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `variance_percent`    DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    `item_status`         ENUM('pending','accepted','partial','rejected','discrepancy') NOT NULL DEFAULT 'pending',
    `notes`               VARCHAR(500)  NULL,
    `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_wri_receiving` FOREIGN KEY (`receiving_id`) REFERENCES `warehouse_receiving`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wri_product`   FOREIGN KEY (`product_id`)   REFERENCES `products`(`id`)            ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Line items for each warehouse receiving record';

CREATE INDEX IF NOT EXISTS `idx_wri_receiving_id` ON `warehouse_receiving_items`(`receiving_id`);
CREATE INDEX IF NOT EXISTS `idx_wri_product_id`   ON `warehouse_receiving_items`(`product_id`);

-- 4. Warehouse Discrepancies (immutable)
CREATE TABLE IF NOT EXISTS `warehouse_discrepancies` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `discrepancy_number` VARCHAR(30)   NOT NULL UNIQUE,
    `reference_type`     ENUM('receiving','transfer') NOT NULL DEFAULT 'receiving',
    `reference_id`       INT UNSIGNED  NOT NULL,
    `receiving_item_id`  INT UNSIGNED  NULL,
    `warehouse_id`       INT UNSIGNED  NOT NULL,
    `farm_id`            INT UNSIGNED  NULL,
    `shop_id`            INT UNSIGNED  NULL,
    `product_id`         INT UNSIGNED  NOT NULL,
    `batch_number`       VARCHAR(60)   NULL,
    `expected_quantity`  DECIMAL(14,3) NOT NULL,
    `actual_quantity`    DECIMAL(14,3) NOT NULL,
    `variance_quantity`  DECIMAL(14,3) NOT NULL,
    `variance_percent`   DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    `discrepancy_type`   ENUM('short_delivery','over_delivery','damaged','spoiled','wrong_product','wrong_unit','expired','packaging_damage','other') NOT NULL DEFAULT 'other',
    `reason`             TEXT          NOT NULL,
    `status`             ENUM('open','under_review','resolved','rejected') NOT NULL DEFAULT 'open',
    `reported_by`        INT UNSIGNED  NULL,
    `reported_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_by`        INT UNSIGNED  NULL,
    `resolved_at`        TIMESTAMP     NULL,
    `resolution_notes`   TEXT          NULL,
    `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_wd_warehouse`    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wd_product`      FOREIGN KEY (`product_id`)   REFERENCES `products`(`id`)   ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wd_reported_by`  FOREIGN KEY (`reported_by`)  REFERENCES `users`(`id`)      ON DELETE SET NULL  ON UPDATE CASCADE,
    CONSTRAINT `fk_wd_resolved_by`  FOREIGN KEY (`resolved_by`)  REFERENCES `users`(`id`)      ON DELETE SET NULL  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable discrepancy records';

CREATE INDEX IF NOT EXISTS `idx_wd_reference`    ON `warehouse_discrepancies`(`reference_type`, `reference_id`);
CREATE INDEX IF NOT EXISTS `idx_wd_warehouse_id` ON `warehouse_discrepancies`(`warehouse_id`);
CREATE INDEX IF NOT EXISTS `idx_wd_product_id`   ON `warehouse_discrepancies`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_wd_status`        ON `warehouse_discrepancies`(`status`);
CREATE INDEX IF NOT EXISTS `idx_wd_reported_at`   ON `warehouse_discrepancies`(`reported_at`);

-- 5. Warehouse Transfers (header)
CREATE TABLE IF NOT EXISTS `warehouse_transfers` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `transfer_number`  VARCHAR(30)   NOT NULL UNIQUE,
    `warehouse_id`     INT UNSIGNED  NOT NULL,
    `shop_id`          INT UNSIGNED  NOT NULL,
    `status`           ENUM('draft','pending','approved','in_transit','received','partially_received','discrepancy','cancelled') NOT NULL DEFAULT 'pending',
    `initiated_by`     INT UNSIGNED  NULL,
    `approved_by`      INT UNSIGNED  NULL,
    `received_by`      INT UNSIGNED  NULL,
    `transfer_date`    DATE          NOT NULL,
    `expected_date`    DATE          NULL,
    `received_date`    DATE          NULL,
    `total_items`      INT           NOT NULL DEFAULT 0,
    `total_quantity`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `notes`            TEXT          NULL,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_wt_warehouse`    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wt_shop`         FOREIGN KEY (`shop_id`)      REFERENCES `shops`(`id`)      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wt_initiated_by` FOREIGN KEY (`initiated_by`) REFERENCES `users`(`id`)      ON DELETE SET NULL  ON UPDATE CASCADE,
    CONSTRAINT `fk_wt_approved_by`  FOREIGN KEY (`approved_by`)  REFERENCES `users`(`id`)      ON DELETE SET NULL  ON UPDATE CASCADE,
    CONSTRAINT `fk_wt_received_by`  FOREIGN KEY (`received_by`)  REFERENCES `users`(`id`)      ON DELETE SET NULL  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Warehouse to shop transfer orders';

CREATE INDEX IF NOT EXISTS `idx_wtr_warehouse_id`  ON `warehouse_transfers`(`warehouse_id`);
CREATE INDEX IF NOT EXISTS `idx_wtr_shop_id`       ON `warehouse_transfers`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_wtr_status`        ON `warehouse_transfers`(`status`);
CREATE INDEX IF NOT EXISTS `idx_wtr_transfer_date` ON `warehouse_transfers`(`transfer_date`);

-- 6. Warehouse Transfer Items (line items)
CREATE TABLE IF NOT EXISTS `warehouse_transfer_items` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `transfer_id`         INT UNSIGNED  NOT NULL,
    `product_id`          INT UNSIGNED  NOT NULL,
    `unit_id`             INT UNSIGNED  NULL,
    `batch_number`        VARCHAR(60)   NULL,
    `expiry_date`         DATE          NULL,
    `quantity_requested`  DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_sent`       DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_received`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `item_status`         ENUM('pending','sent','received','partial','discrepancy') NOT NULL DEFAULT 'pending',
    `notes`               VARCHAR(500)  NULL,
    `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_wti_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `warehouse_transfers`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wti_product`  FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)            ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Line items for warehouse transfer orders';

CREATE INDEX IF NOT EXISTS `idx_wti_transfer_id` ON `warehouse_transfer_items`(`transfer_id`);
CREATE INDEX IF NOT EXISTS `idx_wti_product_id`  ON `warehouse_transfer_items`(`product_id`);

-- 7. Shop Inventory
CREATE TABLE IF NOT EXISTS `shop_inventory` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `shop_id`            INT UNSIGNED  NOT NULL,
    `product_id`         INT UNSIGNED  NOT NULL,
    `current_quantity`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `reserved_quantity`  DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `available_quantity` DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `unit_id`            INT UNSIGNED  NULL,
    `batch_number`       VARCHAR(60)   NULL DEFAULT NULL COMMENT 'Primary batch tracking',
    `min_stock_level`    DECIMAL(14,3) NOT NULL DEFAULT 0.000 COMMENT 'Minimum stock threshold for low-stock alerts',
    `expiry_date`        DATE          NULL DEFAULT NULL COMMENT 'Expiry date for perishable tracking',
    `last_movement_at`   TIMESTAMP     NULL DEFAULT NULL,
    `last_movement_type` VARCHAR(40)   NULL DEFAULT NULL COMMENT 'Type of last stock movement',
    `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_si_shop_product` (`shop_id`, `product_id`),
    CONSTRAINT `fk_si_shop`    FOREIGN KEY (`shop_id`)    REFERENCES `shops`(`id`)    ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_si_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Current inventory balance per product per shop';

CREATE INDEX IF NOT EXISTS `idx_si_shop_id`        ON `shop_inventory`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_si_product_id`     ON `shop_inventory`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_si_expiry`         ON `shop_inventory`(`expiry_date`);
CREATE INDEX IF NOT EXISTS `idx_si_min_stock`      ON `shop_inventory`(`min_stock_level`);
CREATE INDEX IF NOT EXISTS `idx_si_avail_quantity` ON `shop_inventory`(`available_quantity`);

-- 8. Stock Movement Log (immutable append-only ledger)
CREATE TABLE IF NOT EXISTS `stock_movement_log` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `movement_type`    ENUM('receiving','transfer_out','transfer_in','adjustment','discrepancy','return','initial','sale','count','damage','expiry') NOT NULL,
    `location_type`    ENUM('warehouse','shop') NOT NULL,
    `location_id`      INT UNSIGNED  NOT NULL,
    `product_id`       INT UNSIGNED  NOT NULL,
    `batch_number`     VARCHAR(60)   NULL,
    `quantity_before`  DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_in`      DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_out`     DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_after`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `reference_type`   VARCHAR(40)   NULL,
    `reference_id`     INT UNSIGNED  NULL,
    `reference_number` VARCHAR(30)   NULL,
    `performed_by`     INT UNSIGNED  NULL,
    `notes`            VARCHAR(500)  NULL,
    `moved_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_sml_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable ledger of every warehouse/shop stock movement';

CREATE INDEX IF NOT EXISTS `idx_sml_location`   ON `stock_movement_log`(`location_type`, `location_id`);
CREATE INDEX IF NOT EXISTS `idx_sml_product_id` ON `stock_movement_log`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_sml_reference`  ON `stock_movement_log`(`reference_type`, `reference_id`);
CREATE INDEX IF NOT EXISTS `idx_sml_moved_at`   ON `stock_movement_log`(`moved_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_sml_type`       ON `stock_movement_log`(`movement_type`);

-- Phase 10 permissions
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('warehouse.overview',          'warehouse', 'Warehouse Overview',    'Can view warehouse overview dashboard',      1),
('warehouse.inventory.view',    'warehouse', 'View WH Inventory',     'Can view warehouse inventory',               1),
('warehouse.receiving.view',    'warehouse', 'View Receiving',        'Can view pending and completed receivings',  1),
('warehouse.receiving.confirm', 'warehouse', 'Confirm Receiving',     'Can confirm receipt of farm dispatches',     1),
('warehouse.discrepancy.view',  'warehouse', 'View Discrepancies',    'Can view receiving discrepancy records',     1),
('warehouse.discrepancy.resolve','warehouse','Resolve Discrepancies', 'Can resolve or reject discrepancy records',  1),
('warehouse.transfer.view',     'warehouse', 'View Transfers',        'Can view transfer orders',                   1),
('warehouse.transfer.create',   'warehouse', 'Create Transfers',      'Can create transfer orders to shops',        1),
('warehouse.transfer.dispatch', 'warehouse', 'Dispatch Transfers',    'Can mark transfers as dispatched',           1),
('warehouse.transfer.confirm',  'warehouse', 'Confirm Transfers',     'Can confirm receipt of transfers',           1),
('warehouse.transfer.cancel',   'warehouse', 'Cancel Transfers',      'Can cancel pending transfer orders',         1),
('shop.inventory.view',         'shop',      'View Shop Inventory',   'Can view shop inventory levels',             1);

-- Grant Phase 10 permissions to relevant roles
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions` WHERE `code` LIKE 'warehouse.%';   -- warehouse_manager (id=3)

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions`
WHERE `code` IN ('warehouse.overview','warehouse.inventory.view','warehouse.receiving.view',
                 'warehouse.receiving.confirm','warehouse.discrepancy.view',
                 'warehouse.transfer.view','warehouse.transfer.confirm',
                 'shop.inventory.view');

-- Refresh admin and GM
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;   -- admin: all
DELETE FROM `role_permissions` WHERE `role_id` = 6;
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions`;   -- GM: all

SELECT 'Phase 10 warehouse operations tables created.' AS status;


-- =========================================================
-- PHASE 11: INVENTORY & TRANSFER MODULE
-- Run after Phase 10 block above.
-- =========================================================

-- 1. Inventory Batches
CREATE TABLE IF NOT EXISTS `inventory_batches` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `location_type`      ENUM('warehouse','shop') NOT NULL,
    `location_id`        INT UNSIGNED  NOT NULL,
    `product_id`         INT UNSIGNED  NOT NULL,
    `batch_number`       VARCHAR(60)   NOT NULL,
    `production_date`    DATE          NULL,
    `expiry_date`        DATE          NULL,
    `quantity`           DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `unit_id`            INT UNSIGNED  NULL,
    `source_type`        VARCHAR(40)   NULL,
    `source_id`          INT UNSIGNED  NULL,
    `notes`              VARCHAR(255)  NULL,
    `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ib_location_product_batch` (`location_type`,`location_id`,`product_id`,`batch_number`),
    CONSTRAINT `fk_ib_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_ib_location`   ON `inventory_batches`(`location_type`,`location_id`);
CREATE INDEX IF NOT EXISTS `idx_ib_product_id` ON `inventory_batches`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_ib_expiry`     ON `inventory_batches`(`expiry_date`);
CREATE INDEX IF NOT EXISTS `idx_ib_batch`      ON `inventory_batches`(`batch_number`);

-- 2. Stock Adjustments
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `adjustment_number` VARCHAR(30)   NOT NULL UNIQUE,
    `location_type`     ENUM('warehouse','shop') NOT NULL,
    `location_id`       INT UNSIGNED  NOT NULL,
    `product_id`        INT UNSIGNED  NOT NULL,
    `batch_number`      VARCHAR(60)   NULL,
    `unit_id`           INT UNSIGNED  NULL,
    `system_quantity`   DECIMAL(14,3) NOT NULL,
    `physical_quantity` DECIMAL(14,3) NOT NULL,
    `variance`          DECIMAL(14,3) NOT NULL,
    `reason`            ENUM('physical_count','damaged','spoilage','expiry',
                             'measurement_error','correction','transfer_error','other')
                        NOT NULL DEFAULT 'physical_count',
    `notes`             TEXT          NULL,
    `status`            ENUM('pending','approved','rejected','applied') NOT NULL DEFAULT 'pending',
    `requested_by`      INT UNSIGNED  NULL,
    `approved_by`       INT UNSIGNED  NULL,
    `applied_at`        TIMESTAMP     NULL,
    `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_sa_product`       FOREIGN KEY (`product_id`)   REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sa_requested_by`  FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sa_approved_by`   FOREIGN KEY (`approved_by`)  REFERENCES `users`(`id`)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_sa_location`   ON `stock_adjustments`(`location_type`,`location_id`);
CREATE INDEX IF NOT EXISTS `idx_sa_product_id` ON `stock_adjustments`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_sa_status`     ON `stock_adjustments`(`status`);

-- 3. Stock Counts
-- =========================================================
-- NOTE: stock_counts and stock_count_items tables have been
-- removed from the AAM system. The Stock Count module was
-- dropped in favour of the Adjustments-only workflow.
-- Run migration 2026_08_05_000023_drop_stock_counts.sql
-- on any existing database to clean up those tables.
-- Fresh installs created from this file do NOT create them.
-- =========================================================

-- 4. Stock Count Items (REMOVED — Stock Count module dropped)
-- stock_count_items referenced stock_counts.id — both tables
-- have been removed. See migration 000023_drop_stock_counts.sql

-- 5. Extend shop_inventory
ALTER TABLE `shop_inventory`
    ADD COLUMN IF NOT EXISTS `batch_number`       VARCHAR(60)   NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `min_stock_level`    DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    ADD COLUMN IF NOT EXISTS `expiry_date`        DATE          NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `last_movement_type` VARCHAR(40)   NULL DEFAULT NULL;

CREATE INDEX IF NOT EXISTS `idx_si_expiry` ON `shop_inventory`(`expiry_date`);

-- 6. Extend stock_movement_log enum
ALTER TABLE `stock_movement_log`
    MODIFY COLUMN `movement_type`
        ENUM('receiving','transfer_out','transfer_in',
             'adjustment','discrepancy','return','initial',
             'sale','count','damage','expiry') NOT NULL;

-- 7. Phase 11 permissions
INSERT IGNORE INTO `permissions` (`code`,`module`,`label`,`description`,`is_active`) VALUES
('inventory.overview',           'inventory','Inventory Overview',       'View inventory overview dashboard',     1),
('inventory.view.warehouse',     'inventory','View WH Inventory',        'View warehouse inventory',              1),
('inventory.view.shop',          'inventory','View Shop Inventory',      'View shop inventory',                   1),
('inventory.view.all',           'inventory','View All Inventory',       'View all location inventory',           1),
('inventory.batch.view',         'inventory','View Batches',             'View batch tracking records',           1),
('inventory.movements.view',     'inventory','View Stock Movements',     'View stock movement ledger',            1),
('inventory.adjustment.request', 'inventory','Request Adjustment',       'Submit stock adjustment requests',      1),
('inventory.adjustment.approve', 'inventory','Approve Adjustment',       'Approve or reject adjustments',         1),
('inventory.adjustment.view',    'inventory','View Adjustments',         'View adjustment history',               1),
('inventory.count.create',       'inventory','Create Stock Count',       'Start a physical stock count',          1),
('inventory.count.enter',        'inventory','Enter Stock Count',        'Enter physical quantities in a count',  1),
('inventory.count.approve',      'inventory','Approve Stock Count',      'Approve a completed stock count',       1),
('inventory.count.view',         'inventory','View Stock Counts',        'View stock count history',              1);

-- Warehouse Manager
INSERT IGNORE INTO `role_permissions`(`role_id`,`permission_id`)
SELECT 3,id FROM `permissions`
WHERE `code` IN ('inventory.overview','inventory.view.warehouse','inventory.view.all',
                 'inventory.batch.view','inventory.movements.view',
                 'inventory.adjustment.request','inventory.adjustment.approve','inventory.adjustment.view',
                 'inventory.count.create','inventory.count.enter','inventory.count.approve','inventory.count.view');

INSERT IGNORE INTO `role_permissions`(`role_id`,`permission_id`)
SELECT 4,id FROM `permissions`
WHERE `code` IN ('inventory.overview','inventory.view.warehouse','inventory.view.shop',
                 'inventory.batch.view','inventory.movements.view',
                 'inventory.adjustment.request','inventory.adjustment.view',
                 'inventory.count.create','inventory.count.enter','inventory.count.view');

-- Farm Manager (read-only)
INSERT IGNORE INTO `role_permissions`(`role_id`,`permission_id`)
SELECT 2,id FROM `permissions`
WHERE `code` IN ('inventory.overview','inventory.movements.view','inventory.batch.view');

-- Refresh admin and GM
INSERT IGNORE INTO `role_permissions`(`role_id`,`permission_id`) SELECT 1,id FROM `permissions`;
DELETE FROM `role_permissions` WHERE `role_id`=6;
INSERT IGNORE INTO `role_permissions`(`role_id`,`permission_id`) SELECT 6,id FROM `permissions`;

SELECT 'Phase 11 inventory & transfer module installed.' AS status;


-- =========================================================
-- PHASE 12 — SHOP & POS MODULE
-- =========================================================

-- 1. Seller Shifts
CREATE TABLE IF NOT EXISTS `seller_shifts` (
    `id`                   INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `shift_number`         VARCHAR(30)    NOT NULL UNIQUE,
    `shop_id`              INT UNSIGNED   NOT NULL,
    `seller_id`            INT UNSIGNED   NOT NULL,
    `status`               ENUM('open','closed','abandoned') NOT NULL DEFAULT 'open',
    `opened_at`            TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at`            TIMESTAMP      NULL DEFAULT NULL,
    `opening_notes`        VARCHAR(500)   NULL,
    `closing_notes`        VARCHAR(500)   NULL,
    `total_transactions`   INT            NOT NULL DEFAULT 0,
    `total_sales_amount`   DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `cash_collected`       DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `created_at`           TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ss_shop`   FOREIGN KEY (`shop_id`)   REFERENCES `shops`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ss_seller` FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_shift_shop`   ON `seller_shifts`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_shift_seller` ON `seller_shifts`(`seller_id`);
CREATE INDEX IF NOT EXISTS `idx_shift_status` ON `seller_shifts`(`status`);

-- 2. Sales (header)
CREATE TABLE IF NOT EXISTS `sales` (
    `id`              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `sale_number`     VARCHAR(30)    NOT NULL UNIQUE,
    `receipt_number`  VARCHAR(30)    NOT NULL UNIQUE,
    `shop_id`         INT UNSIGNED   NOT NULL,
    `seller_id`       INT UNSIGNED   NOT NULL,
    `shift_id`        INT UNSIGNED   NULL,
    `status`          ENUM('completed','voided','returned','partially_returned') NOT NULL DEFAULT 'completed',
    `payment_method`  ENUM('cash','credit','mobile_money','other') NOT NULL DEFAULT 'cash' COMMENT 'Payment method — always cash in current workflow',
    `subtotal`        DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `total_amount`    DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `cash_received`   DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `change_given`    DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `total_items`     INT            NOT NULL DEFAULT 0,
    `notes`           VARCHAR(500)   NULL,
    `void_reason`     VARCHAR(500)   NULL,
    `voided_by`       INT UNSIGNED   NULL,
    `voided_at`       TIMESTAMP      NULL DEFAULT NULL,
    `sale_date`       DATE           NOT NULL,
    `completed_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_sales_shop`      FOREIGN KEY (`shop_id`)   REFERENCES `shops`(`id`)          ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_seller`    FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`)          ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_shift`     FOREIGN KEY (`shift_id`)  REFERENCES `seller_shifts`(`id`)  ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_voided_by` FOREIGN KEY (`voided_by`) REFERENCES `users`(`id`)          ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_sales_shop`      ON `sales`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_sales_seller`    ON `sales`(`seller_id`);
CREATE INDEX IF NOT EXISTS `idx_sales_status`    ON `sales`(`status`);
CREATE INDEX IF NOT EXISTS `idx_sales_date`      ON `sales`(`sale_date`);

-- 3. Sale Items
CREATE TABLE IF NOT EXISTS `sale_items` (
    `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `sale_id`      INT UNSIGNED   NOT NULL,
    `product_id`   INT UNSIGNED   NOT NULL,
    `unit_id`      INT UNSIGNED   NULL,
    `product_name` VARCHAR(200)   NOT NULL,
    `product_sku`  VARCHAR(60)    NULL,
    `quantity`     DECIMAL(14,3)  NOT NULL,
    `unit_price`   DECIMAL(12,2)  NOT NULL,
    `subtotal`     DECIMAL(14,2)  NOT NULL,
    `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_sitm_sale`    FOREIGN KEY (`sale_id`)    REFERENCES `sales`(`id`)    ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sitm_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_sale_items_sale_id`    ON `sale_items`(`sale_id`);
CREATE INDEX IF NOT EXISTS `idx_sale_items_product_id` ON `sale_items`(`product_id`);

-- 4. Phase 12 Permissions
INSERT IGNORE INTO `permissions` (`code`,`module`,`label`,`description`,`is_active`) VALUES
('pos.terminal',        'pos',  'Access POS Terminal',  'Can open and use the POS terminal',             1),
('pos.sale.create',     'pos',  'Create Sale',          'Can process a cash sale',                       1),
('pos.sale.view',       'pos',  'View Sales',           'Can view own sales history',                    1),
('pos.sale.void',       'pos',  'Void Sale',            'Can void a sale (manager only)',                1),
('pos.receipt.view',    'pos',  'View Receipt',         'Can view and print receipts',                   1),
('pos.shift.open',      'pos',  'Open Shift',           'Can open a seller shift',                       1),
('pos.shift.close',     'pos',  'Close Shift',          'Can close own shift',                           1),
('pos.shift.view',      'pos',  'View Shifts',          'Can view own shift history',                    1),
('shop.view',           'shop', 'View Shop',            'Can view own shop details',                     1),
('shop.inventory.view', 'shop', 'View Shop Inventory',  'Can view own shop inventory',                   1),
('shop.sales.view',     'shop', 'View Shop Sales',      'Can view shop sales (all sellers)',             1),
('shop.sales.manage',   'shop', 'Manage Shop Sales',    'Can view/void all shop sales',                  1);

-- Salesperson (role_id=5)
INSERT IGNORE INTO `role_permissions`(`role_id`,`permission_id`)
SELECT 5,id FROM `permissions`
WHERE `code` IN ('pos.terminal','pos.sale.create','pos.sale.view','pos.receipt.view',
                 'pos.shift.open','pos.shift.close','pos.shift.view',
                 'shop.view','shop.inventory.view');

INSERT IGNORE INTO `role_permissions`(`role_id`,`permission_id`)
SELECT 4,id FROM `permissions`
WHERE `code` IN ('pos.terminal','pos.sale.view','pos.sale.void','pos.receipt.view',
                 'pos.shift.view','shop.view','shop.inventory.view',
                 'shop.sales.view','shop.sales.manage');

-- Refresh admin and GM
INSERT IGNORE INTO `role_permissions`(`role_id`,`permission_id`) SELECT 1,id FROM `permissions`;
DELETE FROM `role_permissions` WHERE `role_id`=6;
INSERT IGNORE INTO `role_permissions`(`role_id`,`permission_id`) SELECT 6,id FROM `permissions`;

SELECT 'Phase 12 Shop & POS module installed.' AS status;


-- =========================================================
-- PHASE 12 — DEMO USERS SEED
-- All passwords use bcrypt cost=12. Verified correct.
-- =========================================================

-- ── 1. Warehouse Manager ─────────────────────────────────
-- Username: wh_manager_01  |  Password: WHMgr@2026
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`, `employee_id`,
    `is_active`, `account_status`,
    `failed_login_attempts`, `is_locked`, `must_change_password`, `created_at`
) VALUES (
    10, 3, 'wh_manager_01',
    '$2y$12$ZNE8xjZ3QYgDqXo.4srFDumrh7W1TyEi6K9QjiF9p2P03pQs16jly',
    'Kebede', 'Alemu', 'EMP-010',
    1, 'active', 0, 0, 0, NOW()
);
UPDATE `users` SET
    `role_id`=3,
    `password_hash`='$2y$12$ZNE8xjZ3QYgDqXo.4srFDumrh7W1TyEi6K9QjiF9p2P03pQs16jly',
    `must_change_password`=0, `is_active`=1, `account_status`='active',
    `failed_login_attempts`=0, `is_locked`=0
WHERE `username`='wh_manager_01';

DELETE FROM `user_location_assignments`
WHERE `user_id`=(SELECT `id` FROM `users` WHERE `username`='wh_manager_01' LIMIT 1);
INSERT INTO `user_location_assignments` (`user_id`,`location_type`,`location_id`,`assigned_at`,`assigned_by`)
SELECT u.id,'warehouse',1,NOW(),1 FROM `users` u WHERE u.`username`='wh_manager_01' LIMIT 1;

-- ── 2. Salesperson — Shop A (Main Campus Shop, id=1) ─────
-- Username: seller_01  |  Password: Seller@2026
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`, `employee_id`,
    `is_active`, `account_status`,
    `failed_login_attempts`, `is_locked`, `must_change_password`, `created_at`
) VALUES (
    12, 5, 'seller_01',
    '$2y$12$APcDCaGzSpMMd0SwBNjLm.50mLVPMRUALLXp7gQ64phWKBBl2R8t6',
    'Abebe', 'Girma', 'EMP-012',
    1, 'active', 0, 0, 0, NOW()
);
UPDATE `users` SET
    `role_id`=5,
    `password_hash`='$2y$12$APcDCaGzSpMMd0SwBNjLm.50mLVPMRUALLXp7gQ64phWKBBl2R8t6',
    `must_change_password`=0, `is_active`=1, `account_status`='active',
    `failed_login_attempts`=0, `is_locked`=0
WHERE `username`='seller_01';

DELETE FROM `user_location_assignments`
WHERE `user_id`=(SELECT `id` FROM `users` WHERE `username`='seller_01' LIMIT 1);
INSERT INTO `user_location_assignments` (`user_id`,`location_type`,`location_id`,`assigned_at`,`assigned_by`)
SELECT u.id,'shop',1,NOW(),1 FROM `users` u WHERE u.`username`='seller_01' LIMIT 1;

-- ── 4. Salesperson — Shop B (Health Campus Shop, id=2) ───
-- Username: seller_02  |  Password: Seller@2026
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`, `employee_id`,
    `is_active`, `account_status`,
    `failed_login_attempts`, `is_locked`, `must_change_password`, `created_at`
) VALUES (
    13, 5, 'seller_02',
    '$2y$12$APcDCaGzSpMMd0SwBNjLm.50mLVPMRUALLXp7gQ64phWKBBl2R8t6',
    'Meron', 'Tadesse', 'EMP-013',
    1, 'active', 0, 0, 0, NOW()
);
UPDATE `users` SET
    `role_id`=5,
    `password_hash`='$2y$12$APcDCaGzSpMMd0SwBNjLm.50mLVPMRUALLXp7gQ64phWKBBl2R8t6',
    `must_change_password`=0, `is_active`=1, `account_status`='active',
    `failed_login_attempts`=0, `is_locked`=0
WHERE `username`='seller_02';

DELETE FROM `user_location_assignments`
WHERE `user_id`=(SELECT `id` FROM `users` WHERE `username`='seller_02' LIMIT 1);
INSERT INTO `user_location_assignments` (`user_id`,`location_type`,`location_id`,`assigned_at`,`assigned_by`)
SELECT u.id,'shop',2,NOW(),1 FROM `users` u WHERE u.`username`='seller_02' LIMIT 1;

-- ── 5. General Manager ───────────────────────────────────
-- Username: gm_01  |  Password: GenMgr@2026
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`, `employee_id`,
    `is_active`, `account_status`,
    `failed_login_attempts`, `is_locked`, `must_change_password`, `created_at`
) VALUES (
    14, 6, 'gm_01',
    '$2y$12$Tw9ISov/iQObzwMnEJJnxuXZfUQzmOJAX8qaHd4D40KEWH.rBI/cy',
    'Girma', 'Haile', 'EMP-014',
    1, 'active', 0, 0, 0, NOW()
);
UPDATE `users` SET
    `role_id`=6,
    `password_hash`='$2y$12$Tw9ISov/iQObzwMnEJJnxuXZfUQzmOJAX8qaHd4D40KEWH.rBI/cy',
    `must_change_password`=0, `is_active`=1, `account_status`='active',
    `failed_login_attempts`=0, `is_locked`=0
WHERE `username`='gm_01';

SELECT 'Phase 12 demo users seeded.' AS status;

-- =========================================================
-- PHASE 13 — TRANSFER WORKFLOW & SHOP RECEIVING PERMISSIONS
-- Safe to run multiple times (INSERT IGNORE).
-- =========================================================

-- Transfer permission codes
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('transfer.view',               'transfer', 'View Transfers',            'Can view transfer list and detail',               1),
('transfer.create',             'transfer', 'Create Transfer',           'Can create a warehouse→shop transfer',            1),
('transfer.approve',            'transfer', 'Approve Transfer',          'Can approve a pending transfer (GM/Admin)',        1),
('transfer.dispatch',           'transfer', 'Dispatch Transfer',         'Can dispatch an approved transfer',               1),
('transfer.cancel',             'transfer', 'Cancel Transfer',           'Can cancel a pending or approved transfer',       1),
('transfer.receive',            'transfer', 'Receive Transfer',          'Can confirm receipt of an in-transit transfer',   1),
('transfer.discrepancy.report', 'transfer', 'Report Discrepancy',        'Can record a quantity discrepancy on receipt',    1),
('transfer.view.incoming',      'transfer', 'View Incoming Transfers',   'Can view transfers addressed to own shop',        1),
('transfer.view.all',           'transfer', 'View All Transfers',        'Can view all transfers across all locations',     1);

-- Warehouse Manager (role_id = 3): source side
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions`
WHERE `code` IN ('transfer.view','transfer.create','transfer.approve','transfer.dispatch','transfer.cancel');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions`
WHERE `code` IN ('transfer.view','transfer.view.incoming','transfer.receive','transfer.discrepancy.report');

-- Salesperson (role_id = 5): can view incoming and confirm receipt for their shop
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, id FROM `permissions`
WHERE `code` IN ('transfer.view.incoming','transfer.receive','transfer.discrepancy.report');

-- General Manager (role_id = 6): all transfer permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions` WHERE `code` LIKE 'transfer.%';

-- Admin (role_id = 1): all permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions` WHERE `code` LIKE 'transfer.%';

-- Shop receiving / discrepancy permission codes
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('shop.receiving.view',      'shop', 'View Incoming Transfers',   'Can view transfers in-transit to shop',          1),
('shop.receiving.confirm',   'shop', 'Confirm Transfer Receipt',  'Can confirm receipt of an in-transit transfer',  1),
('shop.discrepancy.view',    'shop', 'View Discrepancies',        'Can view shop discrepancy reports',              1),
('shop.discrepancy.report',  'shop', 'Report Discrepancy',        'Can report quantity discrepancy on receipt',     1),
('shop.movements.view',      'shop', 'View Stock Movements',      'Can view shop stock movement history',           1);

-- Salesperson (role_id = 5)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, id FROM `permissions`
WHERE `code` IN (
    'shop.receiving.view','shop.receiving.confirm',
    'shop.discrepancy.view','shop.discrepancy.report','shop.movements.view'
);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions`
WHERE `code` IN (
    'shop.receiving.view','shop.receiving.confirm',
    'shop.discrepancy.view','shop.discrepancy.report','shop.movements.view'
);

-- Admin (role_id = 1) and GM (role_id = 6) — all shop permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions` WHERE `code` LIKE 'shop.%';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions` WHERE `code` LIKE 'shop.%';

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Phase 13 transfer workflow and shop receiving permissions installed.' AS status;

-- =========================================================
-- PHASE ROLE SEPARATION � Run after initial install
-- =========================================================
-- Source: 2026_08_05_000021_phase_role_separation.sql
-- Separates GENERAL_MANAGER from SYSTEM_ADMINISTRATOR.
-- Updates role permissions JSON and display names.
-- Run this file separately if upgrading an existing install:
--   mysql -u root aam_db < 2026_08_05_000021_phase_role_separation.sql
-- =========================================================

-- Update System Administrator permissions JSON
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
    )
WHERE `name` = 'administrator';

-- Update General Manager permissions JSON
UPDATE `roles`
SET
    `display_name` = 'General Manager',
    `description`  = 'Business and operational management: farm, warehouse, inventory, transfers (approve/reject), shops, reports, analytics, audit trail, organization overview.',
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
    )
WHERE `name` = 'general_manager';

SELECT 'Role separation applied: administrator and general_manager permissions updated.' AS status;

-- =========================================================
-- PHASE 11 COUNT APPROVAL WORKFLOW — Run after initial install
-- =========================================================
-- Source: 2026_08_05_000022_phase11_count_approval_workflow.sql
-- Adds submitted_by / submitted_at columns to stock_counts.
-- Extends status ENUM to include pending_approval and rejected.
-- These columns are already included in the CREATE TABLE above
-- for fresh installs. Run the migration file only when upgrading
-- an existing database:
--   mysql -u root aam_db < 2026_08_05_000022_phase11_count_approval_workflow.sql
-- =========================================================

-- =========================================================
-- GM PRICE MANAGEMENT — Selling Price separated to GM role
-- =========================================================
-- Source: 2026_08_07_000027_gm_price_management.sql
-- Extends product_price_history with price_type, effective_date,
-- price_difference, percentage_change, reason columns.
-- Adds product.price.view and product.price.manage permissions.
-- Grants price.manage to general_manager only.
-- =========================================================

-- Extend product_price_history
ALTER TABLE `product_price_history`
    ADD COLUMN IF NOT EXISTS `price_type`
        ENUM('COST','SELLING') NOT NULL DEFAULT 'SELLING'
        AFTER `product_id`;

ALTER TABLE `product_price_history`
    ADD COLUMN IF NOT EXISTS `effective_date`
        DATE NULL
        AFTER `new_price`;

ALTER TABLE `product_price_history`
    ADD COLUMN IF NOT EXISTS `price_difference`
        DECIMAL(15,2) GENERATED ALWAYS AS (`new_price` - `old_price`) STORED
        AFTER `effective_date`;

ALTER TABLE `product_price_history`
    ADD COLUMN IF NOT EXISTS `percentage_change`
        DECIMAL(8,2) NULL
        AFTER `price_difference`;

ALTER TABLE `product_price_history`
    ADD COLUMN IF NOT EXISTS `reason`
        VARCHAR(255) NULL
        AFTER `percentage_change`;

UPDATE `product_price_history`
    SET `reason` = `change_reason`
    WHERE `reason` IS NULL AND `change_reason` IS NOT NULL;

CREATE INDEX IF NOT EXISTS `idx_pph_product_type` ON `product_price_history`(`product_id`, `price_type`);
CREATE INDEX IF NOT EXISTS `idx_pph_changed_at`   ON `product_price_history`(`changed_at` DESC);

-- Price management permissions
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('product.price.view',   'products', 'View Product Prices',   'Can view current selling price and price history', 1),
('product.price.manage', 'products', 'Manage Selling Prices', 'Can set and update official selling prices (GM only)', 1);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE p.code = 'product.price.view'
  AND r.name IN ('salesperson','warehouse_manager','farm_manager','general_manager','administrator');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE p.code = 'product.price.manage' AND r.name = 'general_manager';

SELECT 'GM Price Management schema applied.' AS status;


-- =========================================================
-- RECEIVING DISCREPANCY WORKFLOW — GM Approval Gate
-- =========================================================
-- Source: 2026_08_07_000028_receiving_discrepancy_workflow.sql
-- Implements proper Farm→Warehouse receiving discrepancy workflow:
--   WM reports discrepancy → GM approves/rejects → WM completes
--   Inventory only updated AFTER GM approval
--   Full audit trail via discrepancy_workflow_log
-- =========================================================

-- Extend warehouse_receiving STATUS ENUM
ALTER TABLE `warehouse_receiving`
    MODIFY COLUMN `status`
        ENUM(
            'pending',
            'received',
            'partially_received',
            'discrepancy',
            'discrepancy_pending',
            'gm_approved',
            'gm_rejected',
            'rejected',
            'cancelled'
        )
        NOT NULL DEFAULT 'pending';

-- Add GM decision columns to warehouse_discrepancies
ALTER TABLE `warehouse_discrepancies`
    ADD COLUMN IF NOT EXISTS `gm_reviewed_by`    INT UNSIGNED  NULL
        COMMENT 'FK → users.id — GM who reviewed this discrepancy',
    ADD COLUMN IF NOT EXISTS `gm_reviewed_at`    TIMESTAMP     NULL
        COMMENT 'When the GM made their decision',
    ADD COLUMN IF NOT EXISTS `gm_action`         ENUM('approved','rejected') NULL
        COMMENT 'GM decision',
    ADD COLUMN IF NOT EXISTS `gm_notes`          TEXT          NULL
        COMMENT 'GM review notes or rejection reason',
    ADD COLUMN IF NOT EXISTS `inventory_applied` TINYINT(1)    NOT NULL DEFAULT 0
        COMMENT '1 = inventory has been updated with actual received qty';

ALTER TABLE `warehouse_discrepancies`
    ADD CONSTRAINT IF NOT EXISTS `fk_wd_gm_reviewed_by`
        FOREIGN KEY (`gm_reviewed_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

CREATE INDEX IF NOT EXISTS `idx_wd_gm_action`   ON `warehouse_discrepancies`(`gm_action`);

-- Discrepancy workflow audit log
CREATE TABLE IF NOT EXISTS `discrepancy_workflow_log` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `discrepancy_id` INT UNSIGNED    NOT NULL,
    `receiving_id`   INT UNSIGNED    NULL,
    `action`         VARCHAR(60)     NOT NULL,
    `actor_user_id`  INT UNSIGNED    NULL,
    `notes`          TEXT            NULL,
    `snapshot`       JSON            NULL,
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_dwl_discrepancy`
        FOREIGN KEY (`discrepancy_id`) REFERENCES `warehouse_discrepancies`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_dwl_actor`
        FOREIGN KEY (`actor_user_id`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable audit log for receiving discrepancy workflow steps';

CREATE INDEX IF NOT EXISTS `idx_dwl_discrepancy_id` ON `discrepancy_workflow_log`(`discrepancy_id`);
CREATE INDEX IF NOT EXISTS `idx_dwl_action`         ON `discrepancy_workflow_log`(`action`);

-- Permissions
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('receiving.discrepancy.submit',   'warehouse', 'Submit Receiving Discrepancy',    'Can submit a discrepancy on farm receiving',          1),
('receiving.discrepancy.complete', 'warehouse', 'Complete Receiving After Approval','Can complete receiving after GM approves discrepancy', 1),
('receiving.discrepancy.gm_review','warehouse', 'GM Review Discrepancy',           'Can review and approve/reject receiving discrepancies', 1);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions` WHERE `code` IN ('receiving.discrepancy.submit','receiving.discrepancy.complete');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions` WHERE `code` = 'receiving.discrepancy.gm_review';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions` WHERE `code` LIKE 'receiving.discrepancy.%';

SELECT 'Migration 000028: Receiving discrepancy workflow applied.' AS status;

-- =========================================================
-- PHASE 5 — CUSTOMER ORDERS (migration 000040)
-- =========================================================

-- customer_orders
CREATE TABLE IF NOT EXISTS `customer_orders` (
    `id`                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `customer_session_id` VARCHAR(128)     NULL DEFAULT NULL,
    `customer_name`       VARCHAR(200)     NOT NULL,
    `customer_phone`      VARCHAR(30)      NULL DEFAULT NULL,
    `customer_email`      VARCHAR(200)     NULL DEFAULT NULL,
    `customer_user_id`    INT UNSIGNED     NULL DEFAULT NULL,
    `order_reference`     VARCHAR(30)      NOT NULL UNIQUE,
    `shop_id`             INT UNSIGNED     NOT NULL,
    `order_status`        ENUM('pending_payment','paid','preparing','ready_for_pickup',
                               'completed','cancelled','payment_failed')
                          NOT NULL DEFAULT 'pending_payment',
    `payment_status`      ENUM('pending','paid','failed','expired','cancelled')
                          NOT NULL DEFAULT 'pending',
    `payment_method_id`   INT UNSIGNED     NULL DEFAULT NULL,
    `payment_reference`   VARCHAR(30)      NULL DEFAULT NULL UNIQUE,
    `mobile_payment_id`   BIGINT UNSIGNED  NULL DEFAULT NULL,
    `subtotal`            DECIMAL(14,2)    NOT NULL DEFAULT 0.00,
    `total_amount`        DECIMAL(14,2)    NOT NULL DEFAULT 0.00,
    `sale_id`             INT UNSIGNED     NULL DEFAULT NULL,
    `notes`               VARCHAR(500)     NULL DEFAULT NULL,
    `pickup_instructions` VARCHAR(500)     NULL DEFAULT NULL,
    `processed_by`        INT UNSIGNED     NULL DEFAULT NULL,
    `paid_at`             TIMESTAMP        NULL DEFAULT NULL,
    `ready_at`            TIMESTAMP        NULL DEFAULT NULL,
    `completed_at`        TIMESTAMP        NULL DEFAULT NULL,
    `cancelled_at`        TIMESTAMP        NULL DEFAULT NULL,
    `cancellation_reason` VARCHAR(300)     NULL DEFAULT NULL,
    `expires_at`          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_co_shop`
        FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_co_payment_method`
        FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_co_session`         ON `customer_orders`(`customer_session_id`);
CREATE INDEX IF NOT EXISTS `idx_co_customer_phone`  ON `customer_orders`(`customer_phone`);
CREATE INDEX IF NOT EXISTS `idx_co_shop_id`         ON `customer_orders`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_co_order_status`    ON `customer_orders`(`order_status`);
CREATE INDEX IF NOT EXISTS `idx_co_payment_status`  ON `customer_orders`(`payment_status`);
CREATE INDEX IF NOT EXISTS `idx_co_expires_at`      ON `customer_orders`(`expires_at`);
CREATE INDEX IF NOT EXISTS `idx_co_created_at`      ON `customer_orders`(`created_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_co_order_reference` ON `customer_orders`(`order_reference`);
CREATE INDEX IF NOT EXISTS `idx_co_customer_name`   ON `customer_orders`(`customer_name`);
CREATE INDEX IF NOT EXISTS `idx_co_shop_status`     ON `customer_orders`(`shop_id`, `order_status`);
CREATE INDEX IF NOT EXISTS `idx_co_shop_paid_at`    ON `customer_orders`(`shop_id`, `paid_at`);

-- customer_order_items
CREATE TABLE IF NOT EXISTS `customer_order_items` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`     BIGINT UNSIGNED NOT NULL,
    `product_id`   INT UNSIGNED    NOT NULL,
    `unit_id`      INT UNSIGNED    NULL DEFAULT NULL,
    `product_name` VARCHAR(255)    NOT NULL,
    `product_sku`  VARCHAR(100)    NULL DEFAULT NULL,
    `quantity`     DECIMAL(12,3)   NOT NULL,
    `unit_price`   DECIMAL(14,2)   NOT NULL,
    `subtotal`     DECIMAL(14,2)   NOT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_coi_order`
        FOREIGN KEY (`order_id`) REFERENCES `customer_orders`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_coi_order_id`   ON `customer_order_items`(`order_id`);
CREATE INDEX IF NOT EXISTS `idx_coi_product_id` ON `customer_order_items`(`product_id`);

-- customer_order_id column on mobile_payments
ALTER TABLE `mobile_payments`
    ADD COLUMN IF NOT EXISTS `customer_order_id`
        BIGINT UNSIGNED NULL DEFAULT NULL
        AFTER `sale_id`;

CREATE INDEX IF NOT EXISTS `idx_mp_customer_order_id`
    ON `mobile_payments`(`customer_order_id`);

-- Phase 5 permissions
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('shop.customer_orders.view',    'shop', 'View Customer Orders',    'Can view customer pickup orders for their assigned shop', 1),
('shop.customer_orders.process', 'shop', 'Process Customer Orders', 'Can mark customer orders as preparing/ready/completed',   1),
('shop.customer_orders.search',  'shop', 'Search Customer Orders',  'Can search customer orders by reference during pickup',   1);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, p.id FROM `permissions` p
WHERE p.`code` IN ('shop.customer_orders.view','shop.customer_orders.process','shop.customer_orders.search');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, p.id FROM `permissions` p
WHERE p.`code` IN ('shop.customer_orders.view','shop.customer_orders.search');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.`code` LIKE 'shop.customer_orders.%';

SELECT 'Phase 5 customer orders tables applied.' AS status;

-- =========================================================
-- PHASE 6 — SHOP ORDER MANAGEMENT ENHANCEMENTS (migration 000041)
-- =========================================================

-- audit_logs index for customer_orders lookups
CREATE INDEX IF NOT EXISTS `idx_audit_table_record`
    ON `audit_logs`(`table_affected`, `record_id`);

-- customer_order_status_changes — append-only status audit
CREATE TABLE IF NOT EXISTS `customer_order_status_changes` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`        BIGINT UNSIGNED NOT NULL,
    `order_reference` VARCHAR(30)     NOT NULL,
    `shop_id`         INT UNSIGNED    NOT NULL,
    `changed_by`      INT UNSIGNED    NOT NULL,
    `old_status`      VARCHAR(30)     NOT NULL,
    `new_status`      VARCHAR(30)     NOT NULL,
    `notes`           VARCHAR(500)    NULL DEFAULT NULL,
    `changed_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_cosc_order`
        FOREIGN KEY (`order_id`)   REFERENCES `customer_orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cosc_shop`
        FOREIGN KEY (`shop_id`)    REFERENCES `shops`(`id`)           ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_cosc_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`)           ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Append-only audit trail of customer order status changes by shop staff — Phase 6';

CREATE INDEX IF NOT EXISTS `idx_cosc_order_id`   ON `customer_order_status_changes`(`order_id`);
CREATE INDEX IF NOT EXISTS `idx_cosc_shop_id`    ON `customer_order_status_changes`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_cosc_changed_by` ON `customer_order_status_changes`(`changed_by`);
CREATE INDEX IF NOT EXISTS `idx_cosc_changed_at` ON `customer_order_status_changes`(`changed_at` DESC);

SELECT 'Phase 6 shop order management tables applied.' AS status;

-- =========================================================
-- PHASE 7 — MOBILE BANKING / DYNAMIC QR PAYMENT (migration 000042)
-- =========================================================

-- Expand payment_reference to handle AUS-PAY-YYYYMMDD-XXXXXX format
ALTER TABLE `customer_orders`
    MODIFY COLUMN `payment_reference`
        VARCHAR(40)
        NULL DEFAULT NULL
        COMMENT 'Payment reference: AUS-PAY-YYYYMMDD-XXXXXX (random suffix, unique, server-generated)';

-- Add customer-facing display_name to payment_methods
ALTER TABLE `payment_methods`
    ADD COLUMN IF NOT EXISTS `display_name`
        VARCHAR(120)
        NULL DEFAULT NULL
        COMMENT 'Customer-facing display name. Falls back to name if NULL.'
        AFTER `name`;

-- Performance indexes for payment polling endpoint
CREATE INDEX IF NOT EXISTS `idx_co_id_session`
    ON `customer_orders`(`id`, `customer_session_id`);

CREATE INDEX IF NOT EXISTS `idx_mp_status_order`
    ON `mobile_payments`(`status`, `customer_order_id`);

-- Friendly display names for the 3 seeded payment methods
UPDATE `payment_methods`
SET `display_name` = 'Cash on Pickup'
WHERE `code` = 'cash' AND (`display_name` IS NULL OR `display_name` = '');

UPDATE `payment_methods`
SET `display_name` = 'CBE Birr Mobile Banking'
WHERE `code` = 'cbe_birr' AND (`display_name` IS NULL OR `display_name` = '');

UPDATE `payment_methods`
SET `display_name` = 'Telebirr Mobile Money'
WHERE `code` = 'telebirr' AND (`display_name` IS NULL OR `display_name` = '');

SELECT 'Phase 7 mobile banking QR payment tables applied.' AS status;
