-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_07_29_000000_phase5a_core_schema.sql
-- Purpose   : Core database schema — base tables required
--             by all subsequent migrations.
--             Run this FIRST before all other migrations.
-- =========================================================

CREATE DATABASE IF NOT EXISTS `aam_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `aam_db`;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = '';

-- =========================================================
-- 1. ROLES TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `roles` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(60)     NOT NULL UNIQUE COMMENT 'Machine-readable role name',
    `display_name` VARCHAR(100)    NOT NULL,
    `description`  VARCHAR(255)    NULL,
    `permissions`  JSON            NULL,
    `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='User roles for RBAC';

-- =========================================================
-- 2. USERS TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`                       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `employee_id`              VARCHAR(30)   NULL DEFAULT NULL,
    `role_id`                  INT UNSIGNED  NOT NULL DEFAULT 1,
    `username`                 VARCHAR(80)   NOT NULL UNIQUE,
    `password_hash`            VARCHAR(255)  NOT NULL,
    `first_name`               VARCHAR(80)   NOT NULL DEFAULT '',
    `middle_name`              VARCHAR(80)   NULL DEFAULT NULL,
    `last_name`                VARCHAR(80)   NOT NULL DEFAULT '',
    `gender`                   ENUM('male','female','other','prefer_not_to_say') NULL,
    `phone`                    VARCHAR(30)   NULL DEFAULT NULL,
    `email`                    VARCHAR(191)  NULL DEFAULT NULL,
    `profile_photo`            VARCHAR(255)  NULL DEFAULT NULL,
    `is_active`                TINYINT(1)    NOT NULL DEFAULT 1,
    `account_status`           ENUM('active','inactive','suspended','locked','pending','deleted')
                               NOT NULL DEFAULT 'active',
    `failed_login_attempts`    TINYINT       NOT NULL DEFAULT 0,
    `is_locked`                TINYINT(1)    NOT NULL DEFAULT 0,
    `must_change_password`     TINYINT(1)    NOT NULL DEFAULT 0,
    `last_login_at`            TIMESTAMP     NULL DEFAULT NULL,
    `password_changed_at`      TIMESTAMP     NULL DEFAULT NULL,
    `password_reset_token`     VARCHAR(64)   NULL DEFAULT NULL,
    `password_reset_expires_at` TIMESTAMP    NULL DEFAULT NULL,
    `remember_token`           VARCHAR(64)   NULL DEFAULT NULL,
    `suspension_reason`        VARCHAR(500)  NULL DEFAULT NULL,
    `created_by`               INT UNSIGNED  NULL DEFAULT NULL,
    `created_at`               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_users_role_id`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='System users with role-based access';

CREATE INDEX IF NOT EXISTS `idx_users_username`        ON `users` (`username`);
CREATE INDEX IF NOT EXISTS `idx_users_phone`           ON `users` (`phone`);
CREATE INDEX IF NOT EXISTS `idx_users_account_status`  ON `users` (`account_status`);
CREATE INDEX IF NOT EXISTS `idx_users_email`           ON `users` (`email`);
CREATE INDEX IF NOT EXISTS `idx_users_role_id`         ON `users` (`role_id`);

-- =========================================================
-- 3. CATEGORIES TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `categories` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(100)  NOT NULL,
    `slug`         VARCHAR(110)  NULL DEFAULT NULL UNIQUE,
    `description`  TEXT          NULL,
    `parent_id`    INT UNSIGNED  NULL DEFAULT NULL,
    `sort_order`   SMALLINT      NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   TIMESTAMP     NULL DEFAULT NULL,

    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Product categories';

-- =========================================================
-- 4. UNITS OF MEASURE TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `units_of_measure` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(60)   NOT NULL UNIQUE,
    `abbreviation` VARCHAR(20)   NOT NULL,
    `description`  VARCHAR(255)  NULL,
    `unit_type`    VARCHAR(30)   NULL COMMENT 'weight, volume, count, area',
    `base_unit_id` INT UNSIGNED  NULL,
    `base_multiplier` DECIMAL(15,6) NULL DEFAULT 1.000000,
    `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   TIMESTAMP     NULL DEFAULT NULL,

    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Units of measure for products';

-- =========================================================
-- 5. PRODUCTS TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `products` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `category_id`      INT UNSIGNED    NULL,
    `unit_id`          INT UNSIGNED    NULL,
    `name`             VARCHAR(200)    NOT NULL,
    `slug`             VARCHAR(210)    NULL UNIQUE,
    `sku`              VARCHAR(60)     NULL UNIQUE COMMENT 'Stock Keeping Unit',
    `barcode`          VARCHAR(60)     NULL UNIQUE,
    `description`      TEXT            NULL,
    `unit_price`       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `cost_price`       DECIMAL(12,2)   NULL DEFAULT 0.00,
    `min_stock_level`  INT             NOT NULL DEFAULT 0,
    `reorder_point`    INT             NOT NULL DEFAULT 0,
    `is_perishable`    TINYINT(1)      NOT NULL DEFAULT 0,
    `shelf_life_days`  INT             NULL,
    `is_featured`      TINYINT(1)      NOT NULL DEFAULT 0,
    `product_status`   ENUM('active','inactive','discontinued','out_of_stock')
                       NOT NULL DEFAULT 'active',
    `is_active`        TINYINT(1)      NOT NULL DEFAULT 1,
    `created_by`       INT UNSIGNED    NULL,
    `updated_by`       INT UNSIGNED    NULL,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       TIMESTAMP       NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_products_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_products_unit`
        FOREIGN KEY (`unit_id`) REFERENCES `units_of_measure` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Master product catalog';

CREATE INDEX IF NOT EXISTS `idx_products_category_id` ON `products` (`category_id`);
CREATE INDEX IF NOT EXISTS `idx_products_is_active`   ON `products` (`is_active`);
CREATE INDEX IF NOT EXISTS `idx_products_name`        ON `products` (`name`);

-- =========================================================
-- 6. PRODUCT IMAGES TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `product_images` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `product_id`   INT UNSIGNED  NOT NULL,
    `filename`     VARCHAR(255)  NOT NULL,
    `alt_text`     VARCHAR(200)  NULL,
    `is_primary`   TINYINT(1)    NOT NULL DEFAULT 0,
    `sort_order`   SMALLINT      NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_pi_product_id`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Product images';

-- =========================================================
-- 7. PRODUCT PRICE HISTORY TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `product_price_history` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `product_id`     INT UNSIGNED  NOT NULL,
    `old_price`      DECIMAL(12,2) NOT NULL,
    `new_price`      DECIMAL(12,2) NOT NULL,
    `change_reason`  VARCHAR(255)  NULL,
    `changed_by`     INT UNSIGNED  NULL,
    `changed_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_pph_product_id`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Product price change history';

-- =========================================================
-- 8. FARMS TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `farms` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(150)  NOT NULL,
    `location`     VARCHAR(255)  NULL,
    `description`  TEXT          NULL,
    `farm_type`    VARCHAR(60)   NULL COMMENT 'Mixed, Crop, Dairy, Poultry, Horticulture',
    `area_hectares` DECIMAL(10,2) NULL,
    `manager_id`   INT UNSIGNED  NULL,
    `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   TIMESTAMP     NULL DEFAULT NULL,

    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Farms managed by the university';

-- =========================================================
-- 9. WAREHOUSES TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `warehouses` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(150)  NOT NULL,
    `location`     VARCHAR(255)  NULL,
    `description`  TEXT          NULL,
    `capacity`     DECIMAL(12,2) NULL,
    `manager_id`   INT UNSIGNED  NULL,
    `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   TIMESTAMP     NULL DEFAULT NULL,

    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Storage warehouses';

-- =========================================================
-- 10. SHOPS TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `shops` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(150)  NOT NULL,
    `location`     VARCHAR(255)  NULL,
    `description`  TEXT          NULL,
    `manager_id`   INT UNSIGNED  NULL,
    `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   TIMESTAMP     NULL DEFAULT NULL,

    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Retail shops';

-- =========================================================
-- 11. USER LOCATION ASSIGNMENTS
-- =========================================================
CREATE TABLE IF NOT EXISTS `user_location_assignments` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED  NOT NULL,
    `location_type` ENUM('farm','warehouse','shop') NOT NULL,
    `location_id`   INT UNSIGNED  NOT NULL,
    `assigned_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `assigned_by`   INT UNSIGNED  NULL,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ula_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Assigns users to operational locations';

CREATE INDEX IF NOT EXISTS `idx_ula_user_id`  ON `user_location_assignments` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_ula_location` ON `user_location_assignments` (`location_type`, `location_id`);

-- =========================================================
-- 12. LOGIN HISTORY TABLE
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
    `logout_at`          TIMESTAMP       NULL,
    `attempted_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_lh_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Login attempt history';

CREATE INDEX IF NOT EXISTS `idx_lh_user_id`      ON `login_history` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_lh_attempted_at` ON `login_history` (`attempted_at`);
CREATE INDEX IF NOT EXISTS `idx_lh_ip`           ON `login_history` (`ip_address`);

-- =========================================================
-- 13. REMEMBER ME TOKENS TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `remember_me_tokens` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED  NOT NULL,
    `selector`       VARCHAR(32)   NOT NULL UNIQUE,
    `hashed_token`   VARCHAR(64)   NOT NULL,
    `user_agent`     VARCHAR(500)  NULL,
    `ip_address`     VARCHAR(45)   NULL,
    `expires_at`     TIMESTAMP     NOT NULL,
    `last_used_at`   TIMESTAMP     NULL,
    `is_active`      TINYINT(1)    NOT NULL DEFAULT 1,
    `account_status` VARCHAR(20)   NULL,
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_rmt_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Remember-me tokens';

CREATE INDEX IF NOT EXISTS `idx_rmt_user_id`  ON `remember_me_tokens` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_rmt_selector` ON `remember_me_tokens` (`selector`);
CREATE INDEX IF NOT EXISTS `idx_rmt_expires`  ON `remember_me_tokens` (`expires_at`);

-- =========================================================
-- 14. ACTIVITY LOGS TABLE
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
    CONSTRAINT `fk_al_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Application activity log';

CREATE INDEX IF NOT EXISTS `idx_al_user_id` ON `activity_logs` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_al_action`  ON `activity_logs` (`action`);
CREATE INDEX IF NOT EXISTS `idx_al_created` ON `activity_logs` (`created_at` DESC);

-- =========================================================
-- 15. AUDIT LOGS TABLE
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
    CONSTRAINT `fk_auditl_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable audit trail';

CREATE INDEX IF NOT EXISTS `idx_audit_user_id` ON `audit_logs` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_audit_module`  ON `audit_logs` (`module`);
CREATE INDEX IF NOT EXISTS `idx_audit_action`  ON `audit_logs` (`action_type`);
CREATE INDEX IF NOT EXISTS `idx_audit_at`      ON `audit_logs` (`action_at` DESC);

-- =========================================================
-- 16. NOTIFICATIONS TABLE
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
    `read_at`           TIMESTAMP       NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_notif_recipient`
        FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='In-system notifications';

CREATE INDEX IF NOT EXISTS `idx_notif_recipient` ON `notifications` (`recipient_user_id`, `is_read`);
CREATE INDEX IF NOT EXISTS `idx_notif_created`   ON `notifications` (`created_at` DESC);

-- =========================================================
-- 17. SYSTEM SETTINGS TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100)    NOT NULL UNIQUE,
    `setting_value` TEXT            NULL,
    `data_type`     ENUM('string','integer','boolean','json','float') NOT NULL DEFAULT 'string',
    `group_name`    VARCHAR(60)     NOT NULL DEFAULT 'general',
    `description`   VARCHAR(255)    NULL,
    `is_public`     TINYINT(1)      NOT NULL DEFAULT 0,
    `is_encrypted`  TINYINT(1)      NOT NULL DEFAULT 0,
    `updated_by`    INT UNSIGNED    NULL,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ss_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Application-wide configuration key-value store';

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Phase 5A core schema created successfully.' AS status;
