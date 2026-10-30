-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_07_29_000006_phase8d_system_config.sql
-- Purpose   : Phase 8D — System Configuration & Administration
--             Center. Creates all tables required for:
--             company profile, system settings, SMTP config,
--             notification settings, backup history,
--             import/export history, error logs, system health,
--             maintenance mode, and security settings.
-- Run after : 2026_07_29_000005_phase8c_organization_management.sql
-- =========================================================

USE `arsiagro_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- TABLE: company_profile
-- Purpose: Stores the university/company branding and
--          contact information. Single-row table.
-- =========================================================
CREATE TABLE IF NOT EXISTS `company_profile` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `university_name`  VARCHAR(200)    NOT NULL DEFAULT 'Arsi University',
    `short_name`       VARCHAR(50)     NOT NULL DEFAULT 'AAM',
    `tagline`          VARCHAR(255)    NULL,
    `tin_number`       VARCHAR(50)     NULL     COMMENT 'Tax Identification Number (optional)',
    `address`          VARCHAR(500)    NULL,
    `city`             VARCHAR(100)    NULL,
    `region`           VARCHAR(100)    NULL     DEFAULT 'Oromia',
    `country`          VARCHAR(100)    NULL     DEFAULT 'Ethiopia',
    `phone`            VARCHAR(30)     NULL,
    `email`            VARCHAR(191)    NULL,
    `website`          VARCHAR(255)    NULL,
    `support_email`    VARCHAR(191)    NULL,
    `support_phone`    VARCHAR(30)     NULL,
    `logo_path`        VARCHAR(500)    NULL     COMMENT 'Relative path to logo file',
    `favicon_path`     VARCHAR(500)    NULL     COMMENT 'Relative path to favicon file',
    `description`      TEXT            NULL,
    `updated_by`       INT UNSIGNED    NULL,
    `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_company_updated_by` FOREIGN KEY (`updated_by`)
        REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Company / university profile (single row)';

-- =========================================================
-- TABLE: system_settings
-- Purpose: Key-value store for all application settings.
--          Replaces hard-coded config values at runtime.
-- =========================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100)    NOT NULL UNIQUE COMMENT 'Dot-notation key e.g. app.timezone',
    `setting_value` TEXT            NULL,
    `data_type`     ENUM('string','integer','boolean','json','float')
                    NOT NULL DEFAULT 'string',
    `group_name`    VARCHAR(60)     NOT NULL DEFAULT 'general'
                    COMMENT 'Grouping: general, financial, inventory, security, email, notification',
    `description`   VARCHAR(255)    NULL,
    `is_public`     TINYINT(1)      NOT NULL DEFAULT 0
                    COMMENT '1 = exposed to frontend JS',
    `is_encrypted`  TINYINT(1)      NOT NULL DEFAULT 0
                    COMMENT '1 = value is encrypted at rest',
    `updated_by`    INT UNSIGNED    NULL,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_setting_updated_by` FOREIGN KEY (`updated_by`)
        REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_setting_group` (`group_name`),
    INDEX `idx_setting_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Application-wide configuration key-value store';

-- =========================================================
-- TABLE: notification_settings
-- Purpose: Per-type notification on/off + threshold config
-- =========================================================
CREATE TABLE IF NOT EXISTS `notification_settings` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `notification_type`VARCHAR(80)     NOT NULL UNIQUE,
    `label`            VARCHAR(100)    NOT NULL,
    `description`      VARCHAR(255)    NULL,
    `is_enabled`       TINYINT(1)      NOT NULL DEFAULT 1,
    `threshold_value`  INT             NULL     COMMENT 'e.g. days-to-expiry or stock qty',
    `channel_system`   TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'In-app notification',
    `channel_email`    TINYINT(1)      NOT NULL DEFAULT 0,
    `recipients`       VARCHAR(255)    NULL     COMMENT 'Comma-separated role names',
    `updated_by`       INT UNSIGNED    NULL,
    `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_notif_setting_updated_by` FOREIGN KEY (`updated_by`)
        REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Notification type configuration';

-- =========================================================
-- TABLE: backup_history
-- Purpose: Track all manual and scheduled backups
-- =========================================================
CREATE TABLE IF NOT EXISTS `backup_history` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `filename`        VARCHAR(255)    NOT NULL,
    `file_path`       VARCHAR(500)    NULL,
    `file_size_bytes` BIGINT          NULL,
    `backup_type`     ENUM('manual','scheduled','pre_restore') NOT NULL DEFAULT 'manual',
    `status`          ENUM('pending','running','completed','failed','verified','deleted')
                      NOT NULL DEFAULT 'pending',
    `tables_count`    INT             NULL,
    `rows_count`      BIGINT          NULL,
    `checksum`        VARCHAR(64)     NULL COMMENT 'SHA-256 of backup file',
    `error_message`   TEXT            NULL,
    `notes`           VARCHAR(500)    NULL,
    `created_by`      INT UNSIGNED    NULL  COMMENT 'NULL = scheduled/system',
    `completed_at`    TIMESTAMP       NULL,
    `verified_at`     TIMESTAMP       NULL,
    `deleted_at`      TIMESTAMP       NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_backup_created_by` FOREIGN KEY (`created_by`)
        REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_backup_status`  (`status`),
    INDEX `idx_backup_type`    (`backup_type`),
    INDEX `idx_backup_created` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Database backup history and verification log';

-- =========================================================
-- TABLE: import_export_history
-- Purpose: Track all import/export operations
-- =========================================================
CREATE TABLE IF NOT EXISTS `import_export_history` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `operation`     ENUM('import','export') NOT NULL,
    `module`        VARCHAR(60)     NOT NULL COMMENT 'products, users, farms, etc.',
    `file_format`   ENUM('csv','excel','json','pdf') NOT NULL,
    `filename`      VARCHAR(255)    NOT NULL,
    `file_path`     VARCHAR(500)    NULL,
    `file_size_bytes` BIGINT        NULL,
    `status`        ENUM('pending','processing','completed','failed','partial') NOT NULL DEFAULT 'pending',
    `rows_total`    INT             NULL,
    `rows_success`  INT             NULL,
    `rows_failed`   INT             NULL,
    `error_summary` TEXT            NULL,
    `notes`         VARCHAR(500)    NULL,
    `performed_by`  INT UNSIGNED    NOT NULL,
    `completed_at`  TIMESTAMP       NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ie_performed_by` FOREIGN KEY (`performed_by`)
        REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_ie_operation`   (`operation`),
    INDEX `idx_ie_module`      (`module`),
    INDEX `idx_ie_status`      (`status`),
    INDEX `idx_ie_performed_by`(`performed_by`),
    INDEX `idx_ie_created`     (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Import and export operation history';

-- =========================================================
-- TABLE: error_logs
-- Purpose: Structured application / PHP error log for UI
-- =========================================================
CREATE TABLE IF NOT EXISTS `error_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `level`       ENUM('debug','info','notice','warning','error','critical','alert','emergency')
                  NOT NULL DEFAULT 'error',
    `message`     TEXT            NOT NULL,
    `context`     JSON            NULL,
    `channel`     VARCHAR(40)     NOT NULL DEFAULT 'app'
                  COMMENT 'app, security, database, http',
    `url`         VARCHAR(500)    NULL,
    `ip_address`  VARCHAR(45)     NULL,
    `user_id`     INT UNSIGNED    NULL,
    `user_agent`  VARCHAR(500)    NULL,
    `stack_trace` TEXT            NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_error_level`   (`level`),
    INDEX `idx_error_channel` (`channel`),
    INDEX `idx_error_user`    (`user_id`),
    INDEX `idx_error_created` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Structured error log for admin viewer';

-- =========================================================
-- TABLE: company_profile_history
-- Purpose: Track every change to the company profile
-- =========================================================
CREATE TABLE IF NOT EXISTS `company_profile_history` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `changed_field` VARCHAR(60)     NOT NULL,
    `old_value`     TEXT            NULL,
    `new_value`     TEXT            NULL,
    `changed_by`    INT UNSIGNED    NOT NULL,
    `changed_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_cp_history_user` FOREIGN KEY (`changed_by`)
        REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_cp_history_field`   (`changed_field`),
    INDEX `idx_cp_history_changed` (`changed_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit trail for company profile changes';

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- SEED: Default company profile (one row)
-- =========================================================
INSERT IGNORE INTO `company_profile` (
    `id`, `university_name`, `short_name`, `tagline`,
    `address`, `city`, `region`, `country`,
    `phone`, `email`, `website`, `support_email`,
    `description`
) VALUES (
    1,
    'Arsi University',
    'AAM',
    'Enterprise Agricultural Product Management',
    'Asella, Arsi Zone, Oromia',
    'Asella',
    'Oromia',
    'Ethiopia',
    '+251-XXX-XXX-XXX',
    'info@arsi.edu.et',
    'https://www.arsi.edu.et',
    'support@arsi.edu.et',
    'Arsi University Agricultural Enterprise — managing farms, warehouses, and retail shops.'
);

-- =========================================================
-- SEED: Core system settings
-- =========================================================
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `data_type`, `group_name`, `description`, `is_public`) VALUES
-- General
('app.name',               'Arsi Agri-Manage',          'string',  'general',      'Application display name',                    1),
('app.short_name',         'AAM',                        'string',  'general',      'Application abbreviation',                    1),
('app.version',            '1.0.0',                      'string',  'general',      'Current application version',                 1),
('app.timezone',           'Africa/Addis_Ababa',         'string',  'general',      'Default application timezone',                0),
('app.language',           'en',                         'string',  'general',      'Default language code',                       1),
('app.date_format',        'd/m/Y',                      'string',  'general',      'Date display format',                         1),
('app.time_format',        'H:i',                        'string',  'general',      'Time display format',                         1),
('app.pagination_per_page','20',                         'integer', 'general',      'Default records per page',                    0),
('app.session_timeout',    '1800',                       'integer', 'general',      'Session idle timeout in seconds',             0),
-- Financial
('financial.currency_code',  'ETB',                      'string',  'financial',    'ISO currency code',                           1),
('financial.currency_symbol','Br',                       'string',  'financial',    'Currency symbol for display',                 1),
('financial.decimal_places', '2',                        'integer', 'financial',    'Decimal places for monetary values',          1),
('financial.tax_rate',       '0',                        'float',   'financial',    'Default VAT/tax rate percentage',             0),
('financial.receipt_prefix', 'RCP',                      'string',  'financial',    'Receipt number prefix',                       0),
('financial.sale_prefix',    'SL',                       'string',  'financial',    'Sale number prefix',                          0),
-- Inventory
('inventory.barcode_prefix',      'AAM',                 'string',  'inventory',    'Default barcode prefix',                      0),
('inventory.low_stock_threshold', '20',                  'integer', 'inventory',    'Default low-stock alert level',               0),
('inventory.near_expiry_days',    '7',                   'integer', 'inventory',    'Days before expiry to send alert',            0),
('inventory.auto_reorder',        '0',                   'boolean', 'inventory',    'Enable automatic reorder suggestions',        0),
-- Uploads
('upload.max_size_mb',       '10',                       'integer', 'upload',       'Maximum upload file size in MB',              0),
('upload.allowed_image_types','jpg,jpeg,png,gif,webp',   'string',  'upload',       'Allowed image file extensions',               0),
-- Maintenance
('maintenance.enabled',      '0',                        'boolean', 'maintenance',  'Enable maintenance mode',                     0),
('maintenance.message',      'System is under scheduled maintenance. Please check back later.',
                                                         'string',  'maintenance',  'Message shown during maintenance',            0),
('maintenance.allowed_ips',  '',                         'string',  'maintenance',  'Comma-separated IPs allowed during maintenance', 0),
-- Security
('security.max_login_attempts', '5',                     'integer', 'security',     'Max failed logins before lockout',            0),
('security.lockout_minutes',    '15',                    'integer', 'security',     'Account lockout duration in minutes',         0),
('security.password_min_length','8',                     'integer', 'security',     'Minimum password length',                     0),
('security.require_uppercase',  '1',                     'boolean', 'security',     'Require at least one uppercase letter',       0),
('security.require_number',     '1',                     'boolean', 'security',     'Require at least one digit',                  0),
('security.require_special',    '0',                     'boolean', 'security',     'Require at least one special character',      0),
('security.csrf_enabled',       '1',                     'boolean', 'security',     'CSRF protection enabled',                     0),
('security.secure_cookies',     '0',                     'boolean', 'security',     'HTTPS-only session cookies',                  0),
-- Email / SMTP
('email.smtp_host',        '',                           'string',  'email',        'SMTP server hostname',                        0),
('email.smtp_port',        '587',                        'integer', 'email',        'SMTP server port',                            0),
('email.smtp_encryption',  'tls',                        'string',  'email',        'SMTP encryption: tls or ssl',                 0),
('email.smtp_username',    '',                           'string',  'email',        'SMTP authentication username',                0),
('email.smtp_password',    '',                           'string',  'email',        'SMTP authentication password (encrypted)',    0),
('email.sender_name',      'Arsi Agri-Manage',           'string',  'email',        'From name in outgoing emails',                0),
('email.sender_email',     'noreply@arsi.edu.et',        'string',  'email',        'From address in outgoing emails',             0),
-- Backup
('backup.auto_enabled',    '0',                          'boolean', 'backup',       'Enable scheduled automatic backups',          0),
('backup.frequency',       'daily',                      'string',  'backup',       'Backup frequency: daily, weekly, monthly',    0),
('backup.retention_days',  '30',                         'integer', 'backup',       'Days to retain backup files',                 0),
('backup.time',            '02:00',                      'string',  'backup',       'Scheduled backup time (24h format)',          0),
-- Dashboard / UI
('ui.theme',               'light',                      'string',  'ui',           'Default UI theme: light or dark',             1),
('ui.sidebar_collapsed',   '0',                          'boolean', 'ui',           'Default sidebar collapsed state',             1),
('ui.landing_page',        '/dashboard',                 'string',  'ui',           'Default landing page after login',            0);

-- =========================================================
-- SEED: Default notification settings
-- =========================================================
INSERT IGNORE INTO `notification_settings`
    (`notification_type`, `label`, `description`, `is_enabled`, `threshold_value`, `channel_system`, `channel_email`, `recipients`)
VALUES
('low_stock',           'Low Stock Alert',             'Alert when product stock falls below threshold',      1, 20,  1, 0, 'administrator,warehouse_manager,storekeeper'),
('out_of_stock',        'Out of Stock Alert',          'Alert when product is completely out of stock',       1, 0,   1, 0, 'administrator,warehouse_manager,storekeeper'),
('near_expiry',         'Near Expiry Alert',           'Alert when product is close to expiry date',          1, 7,   1, 0, 'administrator,warehouse_manager,storekeeper'),
('expired_product',     'Expired Product Alert',       'Alert when product has passed its expiry date',       1, 0,   1, 0, 'administrator,warehouse_manager'),
('transfer_pending',    'Transfer Pending',            'Notify storekeeper of incoming transfer',             1, NULL,1, 0, 'storekeeper'),
('transfer_received',   'Transfer Received',           'Notify warehouse manager of confirmed transfer',      1, NULL,1, 0, 'warehouse_manager,administrator'),
('dispatch_received',   'Dispatch Received',           'Notify when farm dispatch is received at warehouse',  1, NULL,1, 0, 'warehouse_manager,farm_manager'),
('discrepancy',         'Discrepancy Detected',        'Alert when quantity discrepancy is recorded',         1, NULL,1, 0, 'administrator,warehouse_manager'),
('system_announcement', 'System Announcement',         'Broadcast message to all users',                     1, NULL,1, 0, 'all'),
('backup_completed',    'Backup Completed',            'Notify admin when backup succeeds',                   1, NULL,1, 0, 'administrator'),
('backup_failed',       'Backup Failed',               'Notify admin when backup fails',                     1, NULL,1, 0, 'administrator'),
('account_locked',      'Account Locked',              'Notify admin when a user account is locked',          1, NULL,1, 0, 'administrator');
