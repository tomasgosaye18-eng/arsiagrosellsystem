-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_07_29_000007_xampp_integration_checkpoint.sql
-- Purpose   : Integration Checkpoint — adds all missing columns,
--             tables, indexes, and seed data needed for the
--             application to run on XAMPP.
--             Idempotent — safe to run multiple times.
-- Run order : Run LAST, after all previous migrations.
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = '';

-- =========================================================
-- 1. ENSURE account_status COLUMN EXISTS on users
-- =========================================================
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `account_status`
        ENUM('active','inactive','suspended','locked','pending','deleted')
        NOT NULL DEFAULT 'active'
        COMMENT 'Detailed account status for login gating'
        AFTER `is_active`;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `profile_photo`
        VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Filename of uploaded profile photo'
        AFTER `phone`;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `middle_name`
        VARCHAR(80) NULL DEFAULT NULL
        AFTER `first_name`;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `gender`
        ENUM('male','female','other','prefer_not_to_say')
        NULL DEFAULT NULL
        AFTER `last_name`;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `email`
        VARCHAR(191) NULL DEFAULT NULL
        AFTER `phone`;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `employee_id`
        VARCHAR(30) NULL DEFAULT NULL
        AFTER `id`;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `password_reset_token`
        VARCHAR(64) NULL DEFAULT NULL
        COMMENT 'SHA-256 hashed reset token';

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `password_reset_expires_at`
        TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `remember_token`
        VARCHAR(64) NULL DEFAULT NULL
        COMMENT 'Hashed remember-me token';

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `suspension_reason`
        VARCHAR(500) NULL DEFAULT NULL;

-- Fix existing users who have no account_status
UPDATE `users`
SET `account_status` = CASE
    WHEN `is_locked` = 1 THEN 'locked'
    WHEN `is_active` = 0 THEN 'inactive'
    ELSE 'active'
END
WHERE `account_status` IS NULL OR `account_status` = '';

-- Indexes
CREATE INDEX IF NOT EXISTS `idx_users_account_status` ON `users` (`account_status`);
CREATE INDEX IF NOT EXISTS `idx_users_email`           ON `users` (`email`);
CREATE UNIQUE INDEX IF NOT EXISTS `uq_users_employee_id` ON `users` (`employee_id`);

-- =========================================================
-- 2. LOGIN HISTORY TABLE — ensure all columns exist
-- =========================================================
CREATE TABLE IF NOT EXISTS `login_history` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED    NULL,
    `username_attempted` VARCHAR(80)  NOT NULL DEFAULT '',
    `was_successful`  TINYINT(1)      NOT NULL DEFAULT 0,
    `ip_address`      VARCHAR(45)     NULL,
    `user_agent`      VARCHAR(500)    NULL,
    `browser`         VARCHAR(100)    NULL,
    `os_name`         VARCHAR(100)    NULL,
    `device_type`     VARCHAR(50)     NULL,
    `failure_reason`  VARCHAR(255)    NULL,
    `session_id`      VARCHAR(64)     NULL,
    `logout_at`       TIMESTAMP       NULL,
    `attempted_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_lh_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Every login attempt — success or failure';

-- Add missing columns if table already exists
ALTER TABLE `login_history`
    ADD COLUMN IF NOT EXISTS `browser`      VARCHAR(100) NULL DEFAULT NULL AFTER `user_agent`,
    ADD COLUMN IF NOT EXISTS `os_name`      VARCHAR(100) NULL DEFAULT NULL AFTER `browser`,
    ADD COLUMN IF NOT EXISTS `device_type`  VARCHAR(50)  NULL DEFAULT NULL AFTER `os_name`,
    ADD COLUMN IF NOT EXISTS `session_id`   VARCHAR(64)  NULL DEFAULT NULL AFTER `failure_reason`,
    ADD COLUMN IF NOT EXISTS `logout_at`    TIMESTAMP    NULL DEFAULT NULL AFTER `session_id`;

CREATE INDEX IF NOT EXISTS `idx_lh_user_id`      ON `login_history` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_lh_attempted_at` ON `login_history` (`attempted_at`);
CREATE INDEX IF NOT EXISTS `idx_lh_ip`           ON `login_history` (`ip_address`);

-- =========================================================
-- 3. REMEMBER ME TOKENS TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `remember_me_tokens` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED    NOT NULL,
    `selector`     VARCHAR(32)     NOT NULL UNIQUE COMMENT '32-char hex selector',
    `hashed_token` VARCHAR(64)     NOT NULL COMMENT 'SHA-256 hash of the validator',
    `user_agent`   VARCHAR(500)    NULL,
    `ip_address`   VARCHAR(45)     NULL,
    `expires_at`   TIMESTAMP       NOT NULL,
    `last_used_at` TIMESTAMP       NULL,
    `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `account_status` VARCHAR(20)   NULL COMMENT 'Denormalized for fast cookie auth check',

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_rmt_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Remember-me tokens for persistent login';

CREATE INDEX IF NOT EXISTS `idx_rmt_user_id`  ON `remember_me_tokens` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_rmt_selector` ON `remember_me_tokens` (`selector`);
CREATE INDEX IF NOT EXISTS `idx_rmt_expires`  ON `remember_me_tokens` (`expires_at`);

-- =========================================================
-- 4. ACTIVITY LOGS TABLE (Phase 8A)
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

CREATE INDEX IF NOT EXISTS `idx_al_user_id`  ON `activity_logs` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_al_action`   ON `activity_logs` (`action`);
CREATE INDEX IF NOT EXISTS `idx_al_created`  ON `activity_logs` (`created_at` DESC);

-- =========================================================
-- 5. AUDIT LOGS TABLE
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
  COMMENT='Immutable audit trail of significant actions';

CREATE INDEX IF NOT EXISTS `idx_audit_user_id`  ON `audit_logs` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_audit_module`   ON `audit_logs` (`module`);
CREATE INDEX IF NOT EXISTS `idx_audit_action`   ON `audit_logs` (`action_type`);
CREATE INDEX IF NOT EXISTS `idx_audit_at`       ON `audit_logs` (`action_at` DESC);

-- =========================================================
-- 6. NOTIFICATIONS TABLE
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
  COMMENT='In-system notification messages';

CREATE INDEX IF NOT EXISTS `idx_notif_recipient` ON `notifications` (`recipient_user_id`, `is_read`);
CREATE INDEX IF NOT EXISTS `idx_notif_created`   ON `notifications` (`created_at` DESC);

-- =========================================================
-- 7. ENSURE ROLES SEED DATA EXISTS
-- =========================================================
INSERT IGNORE INTO `roles` (`id`, `name`, `display_name`, `description`, `is_active`) VALUES
(1, 'administrator',     'System Administrator',  'Full system access — manage users, products, settings, and all data',         1),
(2, 'farm_manager',      'Farm Manager',          'Manages farm operations: harvests, dispatches, farm inventory',                1),
(3, 'warehouse_manager', 'Warehouse Manager',     'Manages warehouse: receiving, stock, and transfer initiation',                 1),
(4, 'storekeeper',       'Storekeeper',           'Manages shop stock: confirms transfers, monitors shop inventory',              1),
(5, 'salesperson',       'Salesperson',           'Operates the POS terminal and processes cash sales',                          1),
(6, 'general_manager',   'General Manager',       'Read-only access to all reports, analytics, and dashboards',                  1);

-- =========================================================
-- 8. DEFAULT ADMINISTRATOR ACCOUNT
-- Username : admin
-- Password : Admin@123   (bcrypt cost 12)
-- =========================================================
-- Hash generated from: password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12])
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`, `is_active`, `account_status`,
    `failed_login_attempts`, `is_locked`, `must_change_password`,
    `created_at`
) VALUES (
    1,
    1,
    'admin',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'System',
    'Administrator',
    1,
    'active',
    0,
    0,
    0,
    NOW()
);

-- Update hash to Admin@123 if admin account already exists with old hash
UPDATE `users`
SET
    `password_hash`       = '$2y$12$k8fB.9Jy3HqNrr8XTW5P3OOFBLBtH5MG8Mc.dG6VzXKT1BmxF6Li',
    `must_change_password` = 0,
    `is_active`           = 1,
    `account_status`      = 'active',
    `is_locked`           = 0,
    `failed_login_attempts` = 0
WHERE `username` = 'admin'
  AND `password_hash` != '$2y$12$k8fB.9Jy3HqNrr8XTW5P3OOFBLBtH5MG8Mc.dG6VzXKT1BmxF6Li';

-- =========================================================
-- 9. SYSTEM SETTINGS — ensure base records exist
-- =========================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100)    NOT NULL UNIQUE,
    `setting_value` TEXT            NULL,
    `data_type`     ENUM('string','integer','boolean','json','float')
                    NOT NULL DEFAULT 'string',
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

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `data_type`, `group_name`, `description`, `is_public`) VALUES
('app.name',             'Arsi Agri-Manage', 'string',  'general',  'Application display name',       1),
('app.version',          '1.0.0',            'string',  'general',  'Application version',            1),
('app.timezone',         'Africa/Addis_Ababa','string', 'general',  'Default application timezone',   0),
('financial.currency_code',   'ETB',          'string',  'financial','Currency code',                  1),
('financial.currency_symbol', 'Br',           'string',  'financial','Currency symbol',                1),
('maintenance.enabled',       '0',            'boolean', 'maintenance','Maintenance mode',             0);

-- =========================================================
-- 10. CATEGORIES — default seed
-- =========================================================
INSERT IGNORE INTO `categories` (`id`, `name`, `description`, `is_active`) VALUES
(1, 'Grains & Cereals',  'Wheat, Barley, Teff, Maize, Sorghum',            1),
(2, 'Pulses & Legumes',  'Beans, Lentils, Chickpeas, Peas',                 1),
(3, 'Vegetables',        'Tomatoes, Onions, Potatoes, Cabbage',             1),
(4, 'Fruits',            'Apples, Bananas, Oranges, Avocados',              1),
(5, 'Oilseeds',          'Sesame, Sunflower, Niger Seed, Flax',             1),
(6, 'Coffee & Tea',      'Coffee Beans, Tea Leaves',                        1),
(7, 'Animal Products',   'Milk, Eggs, Honey, Butter',                       1),
(8, 'Other Products',    'Miscellaneous agricultural products',             1);

-- =========================================================
-- 11. UNITS OF MEASURE — default seed
-- =========================================================
INSERT IGNORE INTO `units_of_measure` (`id`, `name`, `abbreviation`, `description`, `is_active`) VALUES
(1,  'Kilogram',      'kg',  'Standard weight unit',          1),
(2,  'Gram',          'g',   '1/1000 of a kilogram',          1),
(3,  'Metric Ton',    'ton', '1000 kilograms',                1),
(4,  'Quintal',       'qtl', '100 kilograms',                 1),
(5,  'Liter',         'l',   'Standard volume unit',          1),
(6,  'Milliliter',    'ml',  '1/1000 of a liter',             1),
(7,  'Piece',         'pc',  'Individual item count',         1),
(8,  'Dozen',         'dzn', '12 pieces',                     1),
(9,  'Bag',           'bag', 'Standard bag',                  1),
(10, 'Sack',          'sck', 'Large sack',                    1);

-- =========================================================
-- 12. DEFAULT WAREHOUSE
-- =========================================================
INSERT IGNORE INTO `warehouses` (`id`, `name`, `location`, `description`, `is_active`) VALUES
(1, 'Central Warehouse', 'Main Campus, Asella', 'Main storage facility for all agricultural products', 1);

-- =========================================================
-- 13. DEFAULT FARMS
-- =========================================================
INSERT IGNORE INTO `farms` (`id`, `name`, `location`, `farm_type`, `is_active`) VALUES
(1, 'Arsi University Main Farm', 'Main Campus, Asella',  'Mixed',       1),
(2, 'Cattle & Dairy Farm',       'Main Campus, Asella',  'Dairy',       1),
(3, 'Horticulture Farm',         'Main Campus, Asella',  'Horticulture',1);

-- =========================================================
-- 14. DEFAULT SHOPS
-- =========================================================
INSERT IGNORE INTO `shops` (`id`, `name`, `location`, `is_active`) VALUES
(1, 'Main Campus Shop',   'Main Campus, Building A, Asella', 1),
(2, 'Health Campus Shop', 'Health Campus, Block 1, Asella',  1),
(3, 'Cafeteria Outlet',   'Main Campus, Cafeteria, Asella',  1);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT 'Integration checkpoint migration complete.' AS status;
SELECT CONCAT('Admin user: ', username, ' | Status: ', account_status, ' | MustChange: ', must_change_password) AS admin_info
FROM users WHERE username = 'admin';
