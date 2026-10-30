-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_16_000037_payment_methods.sql
-- Purpose   : Centralized Payment Method Management
--
--   1. Create payment_methods table — one row per system
--      payment method (Cash, CBE Birr, Telebirr).
--      Managed exclusively by System Administrator.
--      Referenced by POS to determine available methods.
--
--   2. Create payment_method_audit table — immutable log of
--      every activation / deactivation / config change.
--
--   3. Seed the three initial payment methods.
--
--   4. Add payment_method_id FK column to mobile_payments
--      so every mobile payment transaction is linked to
--      the specific active method record.
--
--   5. Add permissions for payment method management.
--
-- DESIGN PRINCIPLES
--   - Payment METHODS are centrally configured by System Admin.
--   - Payment TRANSACTIONS are processed by salesperson via POS.
--   - A method can be deactivated but NEVER deleted if it has
--     historical transactions (enforced at application layer).
--   - POS reads only ACTIVE methods at runtime.
--   - Cash workflow is NEVER affected by this table; it is
--     always selectable when the Cash row is_active = 1.
--
-- SAFE TO RE-RUN — all statements use IF NOT EXISTS.
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. PAYMENT METHODS TABLE
--    Centralized registry of all payment methods.
--    Managed exclusively by System Administrator.
-- =========================================================
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id`                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,

    -- Identity
    `code`                VARCHAR(40)      NOT NULL UNIQUE
                          COMMENT 'Machine-readable code: cash | cbe_birr | telebirr',
    `name`                VARCHAR(100)     NOT NULL
                          COMMENT 'Display name shown in POS: Cash | CBE Birr | Telebirr',
    `type`                ENUM('cash','mobile_banking')
                          NOT NULL DEFAULT 'cash'
                          COMMENT 'cash = direct payment; mobile_banking = requires QR/provider flow',
    `provider_code`       VARCHAR(40)      NULL DEFAULT NULL
                          COMMENT 'Provider identifier for mobile_payments.provider FK (NULL for cash)',
    `description`         VARCHAR(255)     NULL DEFAULT NULL,

    -- Status
    `is_active`           TINYINT(1)       NOT NULL DEFAULT 1
                          COMMENT '1 = selectable in POS; 0 = hidden from POS (historical transactions preserved)',
    `sort_order`          TINYINT UNSIGNED NOT NULL DEFAULT 0
                          COMMENT 'Display order in POS payment selector',

    -- Provider configuration (server-side only — NEVER exposed to JS/HTML)
    -- Stored as JSON to accommodate different provider requirements.
    -- is_encrypted flags that the JSON value is encrypted at rest.
    `provider_config`     JSON             NULL DEFAULT NULL
                          COMMENT 'Provider-specific config (merchant_id, shortcode, etc.) — server-side only',
    `is_config_encrypted` TINYINT(1)       NOT NULL DEFAULT 0
                          COMMENT '1 = provider_config JSON is encrypted at rest',

    -- Metadata
    `created_by`          INT UNSIGNED     NULL DEFAULT NULL COMMENT 'FK → users.id',
    `updated_by`          INT UNSIGNED     NULL DEFAULT NULL COMMENT 'FK → users.id',
    `created_at`          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_pm_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_pm_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Centralized payment method registry — managed by System Administrator';

CREATE INDEX IF NOT EXISTS `idx_pm_is_active`  ON `payment_methods`(`is_active`);
CREATE INDEX IF NOT EXISTS `idx_pm_type`       ON `payment_methods`(`type`);

-- =========================================================
-- 2. PAYMENT METHOD AUDIT TABLE
--    Immutable log of every configuration change.
--    Captures who changed what, from what, to what, when.
--    Never updated or deleted — append-only.
-- =========================================================
CREATE TABLE IF NOT EXISTS `payment_method_audit` (
    `id`                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `payment_method_id` INT UNSIGNED     NOT NULL,
    `changed_by`        INT UNSIGNED     NULL     COMMENT 'FK → users.id',
    `action`            VARCHAR(40)      NOT NULL COMMENT 'activated|deactivated|config_updated|created',
    `field_changed`     VARCHAR(80)      NULL     COMMENT 'Which field was changed',
    `old_value`         TEXT             NULL     COMMENT 'Previous value (sensitive fields redacted)',
    `new_value`         TEXT             NULL     COMMENT 'New value (sensitive fields redacted)',
    `changed_at`        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address`        VARCHAR(45)      NULL,
    `notes`             VARCHAR(255)     NULL,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_pma_method`
        FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pma_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable audit trail for payment method configuration changes';

CREATE INDEX IF NOT EXISTS `idx_pma_method_id`  ON `payment_method_audit`(`payment_method_id`);
CREATE INDEX IF NOT EXISTS `idx_pma_changed_by` ON `payment_method_audit`(`changed_by`);
CREATE INDEX IF NOT EXISTS `idx_pma_changed_at` ON `payment_method_audit`(`changed_at` DESC);

-- =========================================================
-- 3. SEED INITIAL PAYMENT METHODS
--    Three methods are pre-seeded and cannot be deleted.
--    Salesperson NEVER creates these — they are system data.
--    INSERT IGNORE — safe to re-run (will not overwrite).
-- =========================================================

-- Cash (always active, no provider)
INSERT IGNORE INTO `payment_methods`
    (`id`, `code`, `name`, `type`, `provider_code`, `description`, `is_active`, `sort_order`)
VALUES
    (1, 'cash', 'Cash', 'cash', NULL,
     'Direct cash payment — no provider integration required.', 1, 1);

-- CBE Birr (mobile banking)
INSERT IGNORE INTO `payment_methods`
    (`id`, `code`, `name`, `type`, `provider_code`, `description`, `is_active`, `sort_order`)
VALUES
    (2, 'cbe_birr', 'CBE Birr', 'mobile_banking', 'cbe_birr',
     'Commercial Bank of Ethiopia mobile banking payment (CBE Birr app).', 1, 2);

-- Telebirr (mobile banking)
INSERT IGNORE INTO `payment_methods`
    (`id`, `code`, `name`, `type`, `provider_code`, `description`, `is_active`, `sort_order`)
VALUES
    (3, 'telebirr', 'Telebirr', 'mobile_banking', 'telebirr',
     'Ethio Telecom mobile money payment (Telebirr app).', 1, 3);

-- =========================================================
-- 4. ADD payment_method_id TO mobile_payments (optional FK)
--    Links each payment transaction to the method record.
--    NULL-able — existing rows remain valid.
-- =========================================================
ALTER TABLE `mobile_payments`
    ADD COLUMN IF NOT EXISTS `payment_method_id`
        INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'FK → payment_methods.id — the active method used for this transaction'
        AFTER `provider`;

-- Backfill existing rows using provider code matching
UPDATE `mobile_payments` mp
JOIN `payment_methods` pm ON pm.provider_code = mp.provider
SET mp.payment_method_id = pm.id
WHERE mp.payment_method_id IS NULL;

-- Add FK constraint (non-blocking — SET NULL on delete to preserve history)
-- DROP the constraint first if it already exists (MariaDB-compatible idempotency)
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'mobile_payments'
      AND CONSTRAINT_NAME = 'fk_mp_payment_method'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `mobile_payments`
     ADD CONSTRAINT `fk_mp_payment_method`
         FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`)
         ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT ''FK fk_mp_payment_method already exists — skipped'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE INDEX IF NOT EXISTS `idx_mp_payment_method_id` ON `mobile_payments`(`payment_method_id`);

-- =========================================================
-- 5. PERMISSIONS — Payment Method Management
--    Only System Administrator should manage payment methods.
--    Salesperson can only VIEW active methods (via POS API).
-- =========================================================
INSERT IGNORE INTO `permissions`
    (`code`, `module`, `label`, `description`, `is_active`)
VALUES
('settings.payment_methods.view',   'settings', 'View Payment Methods',
 'Can view the payment methods management page', 1),
('settings.payment_methods.manage', 'settings', 'Manage Payment Methods',
 'Can activate/deactivate and configure payment methods', 1);

-- Grant view + manage to System Administrator (role_id = 1)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.`code` IN (
    'settings.payment_methods.view',
    'settings.payment_methods.manage'
);

-- Grant view-only to General Manager (role_id = 6)
-- GM can see payment method availability status.
-- GM cannot change provider configuration or toggle active status.
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, p.id FROM `permissions` p
WHERE p.`code` = 'settings.payment_methods.view';

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT 'Payment methods migration applied.' AS status;

SELECT
    pm.id,
    pm.code,
    pm.name,
    pm.type,
    pm.provider_code,
    CASE pm.is_active WHEN 1 THEN 'ACTIVE' ELSE 'INACTIVE' END AS status,
    pm.sort_order
FROM `payment_methods` pm
ORDER BY pm.sort_order;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'payment_methods table' AS check_item
FROM information_schema.tables
WHERE table_schema = 'aam_db' AND table_name = 'payment_methods';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'payment_method_audit table' AS check_item
FROM information_schema.tables
WHERE table_schema = 'aam_db' AND table_name = 'payment_method_audit';
