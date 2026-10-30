-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_14_000036_mobile_payment_support.sql
-- Purpose   : CBE Birr + Telebirr Mobile Payment Support
--
--   1. Extend sales.payment_method ENUM with 'mobile_money'
--      (already present) and add new payment detail columns
--   2. Create mobile_payments table — one row per mobile
--      payment attempt, fully decoupled from sales completion
--   3. Add performance indexes
--   4. Add POS mobile payment permissions
--
-- DESIGN PRINCIPLES
--   - Generating a QR / payment request does NOT complete a sale
--   - Inventory is NEVER deducted until payment is VERIFIED
--   - Sale header is only written after verified payment
--   - mobile_payments is the source of truth for payment state
--   - One pending mobile payment per cart session at a time
--   - Payment reference: AUPOS-YYYYMMDD-NNNNNN (unique, server-side)
--
-- PAYMENT STATES
--   pending   → QR shown, customer has not yet paid
--   paid      → provider confirmed payment received
--   failed    → provider rejected / error
--   cancelled → seller cancelled before verification
--   expired   → payment not completed within TTL window
--
-- SAFE TO RE-RUN — all statements use IF NOT EXISTS / ALTER
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. ENSURE sales.payment_method ENUM INCLUDES mobile_money
--    (already present from migration 020 — ALTER is idempotent
--    if the value already exists in the ENUM)
-- =========================================================

-- Update the ENUM to include all supported values
-- IMPORTANT: This is written conservatively — we only modify
-- the column if 'mobile_money' is not already present.
-- The IF NOT EXISTS pattern for ENUM values requires checking
-- information_schema first; to stay safe we just re-apply the
-- full ENUM definition (idempotent).

ALTER TABLE `sales`
    MODIFY COLUMN `payment_method`
        ENUM('cash','credit','mobile_money','other')
        NOT NULL DEFAULT 'cash'
        COMMENT 'Payment method: cash | mobile_money | credit | other';

-- Add payment_provider column (nullable — only for mobile_money)
ALTER TABLE `sales`
    ADD COLUMN IF NOT EXISTS `payment_provider`
        ENUM('cbe_birr','telebirr','other_mobile')
        NULL DEFAULT NULL
        COMMENT 'Mobile payment provider (cbe_birr, telebirr) — NULL for cash'
        AFTER `payment_method`;

-- Add payment_reference column (unique per sale, NULL for cash)
ALTER TABLE `sales`
    ADD COLUMN IF NOT EXISTS `payment_reference`
        VARCHAR(60)
        NULL DEFAULT NULL
        COMMENT 'Unique payment reference e.g. AUPOS-20260814-000123'
        AFTER `payment_provider`;

-- Add payment_status column
ALTER TABLE `sales`
    ADD COLUMN IF NOT EXISTS `payment_status`
        ENUM('paid','pending','failed','cancelled','expired')
        NOT NULL DEFAULT 'paid'
        COMMENT 'paid = confirmed | pending = awaiting verification. Cash sales default to paid immediately.'
        AFTER `payment_reference`;

-- Backfill: all existing cash sales are already paid
UPDATE `sales`
SET `payment_status` = 'paid'
WHERE `payment_method` = 'cash' AND `payment_status` != 'paid';

-- Add verified_at timestamp
ALTER TABLE `sales`
    ADD COLUMN IF NOT EXISTS `verified_at`
        TIMESTAMP NULL DEFAULT NULL
        COMMENT 'When payment was verified by provider — NULL for cash (completed_at is used)'
        AFTER `payment_status`;

-- Unique constraint on payment_reference (prevents duplicate references)
-- MySQL/MariaDB allows multiple NULLs in a UNIQUE index natively since NULL ≠ NULL
-- Use CREATE INDEX IF NOT EXISTS (MariaDB-compatible syntax)
CREATE UNIQUE INDEX IF NOT EXISTS `uq_sales_payment_reference`
    ON `sales`(`payment_reference`);

-- =========================================================
-- 2. CREATE mobile_payments TABLE
--    One row per mobile payment attempt.
--    Created BEFORE the sale is finalised (at QR generation).
--    The corresponding sale row is only written AFTER the
--    payment status transitions to 'paid'.
--
--    Relationship:
--      mobile_payments.sale_id → sales.id (NULL until sale is created)
--      mobile_payments.status  drives whether sale creation proceeds
-- =========================================================
CREATE TABLE IF NOT EXISTS `mobile_payments` (
    `id`                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,

    -- Identity & reference
    `payment_reference` VARCHAR(60)      NOT NULL UNIQUE
                        COMMENT 'AUPOS-YYYYMMDD-NNNNNN — globally unique server-generated ref',
    `provider`          ENUM('cbe_birr','telebirr','other_mobile')
                        NOT NULL
                        COMMENT 'Payment provider identifier',

    -- Amounts (server-side authoritative — never trusted from client)
    `amount`            DECIMAL(14,2)    NOT NULL
                        COMMENT 'Exact sale total in ETB — calculated server-side',
    `currency`          CHAR(3)          NOT NULL DEFAULT 'ETB',

    -- Payment state machine
    `status`            ENUM('pending','paid','failed','cancelled','expired')
                        NOT NULL DEFAULT 'pending',

    -- Sale linkage (NULL until payment verified and sale created)
    `sale_id`           INT UNSIGNED     NULL DEFAULT NULL
                        COMMENT 'FK → sales.id — populated after sale is created post-verification',
    `shop_id`           INT UNSIGNED     NOT NULL,
    `seller_id`         INT UNSIGNED     NOT NULL
                        COMMENT 'FK → users.id',
    `shift_id`          INT UNSIGNED     NULL DEFAULT NULL
                        COMMENT 'FK → seller_shifts.id',

    -- Cart snapshot (JSON — captured server-side at payment creation time)
    -- This is the authoritative list of items to be sold.
    -- Never re-read from the client after payment is initiated.
    `cart_snapshot`     JSON             NOT NULL
                        COMMENT 'Server-validated cart at payment initiation time',

    -- Provider response data (never exposed to client)
    `provider_request`  JSON             NULL DEFAULT NULL
                        COMMENT 'Raw request sent to payment provider (for debugging)',
    `provider_response` JSON             NULL DEFAULT NULL
                        COMMENT 'Raw response from payment provider (for debugging)',
    `provider_txn_id`   VARCHAR(200)     NULL DEFAULT NULL
                        COMMENT 'Transaction ID returned by the provider',
    `qr_payload`        TEXT             NULL DEFAULT NULL
                        COMMENT 'QR code payload / deeplink / display data for the UI',

    -- Notes
    `notes`             VARCHAR(500)     NULL DEFAULT NULL,
    `failure_reason`    VARCHAR(500)     NULL DEFAULT NULL
                        COMMENT 'Reason for failed/cancelled/expired status',

    -- Timing
    `expires_at`        TIMESTAMP        NOT NULL
                        COMMENT 'Payment expires if not verified by this time',
    `verified_at`       TIMESTAMP        NULL DEFAULT NULL
                        COMMENT 'When provider confirmed payment',
    `cancelled_at`      TIMESTAMP        NULL DEFAULT NULL,
    `created_at`        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_mp_sale`
        FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_mp_shop`
        FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mp_seller`
        FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mobile payment attempts — one per checkout, decoupled from sale creation';

CREATE INDEX IF NOT EXISTS `idx_mp_status`     ON `mobile_payments`(`status`);
CREATE INDEX IF NOT EXISTS `idx_mp_shop_id`    ON `mobile_payments`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_mp_seller_id`  ON `mobile_payments`(`seller_id`);
CREATE INDEX IF NOT EXISTS `idx_mp_expires`    ON `mobile_payments`(`expires_at`);
CREATE INDEX IF NOT EXISTS `idx_mp_sale_id`    ON `mobile_payments`(`sale_id`);
CREATE INDEX IF NOT EXISTS `idx_mp_created`    ON `mobile_payments`(`created_at`);

-- =========================================================
-- 3. ADD INDEXES ON sales FOR PAYMENT QUERIES
-- =========================================================
CREATE INDEX IF NOT EXISTS `idx_sales_payment_method`   ON `sales`(`payment_method`);
CREATE INDEX IF NOT EXISTS `idx_sales_payment_provider` ON `sales`(`payment_provider`);
CREATE INDEX IF NOT EXISTS `idx_sales_payment_status`   ON `sales`(`payment_status`);

-- =========================================================
-- 4. POS MOBILE PAYMENT PERMISSIONS
-- =========================================================
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('pos.payment.mobile',    'pos', 'Mobile Payment',        'Can process mobile payments (CBE Birr, Telebirr)', 1),
('pos.payment.verify',    'pos', 'Verify Mobile Payment', 'Can verify mobile payment status',                 1),
('pos.payment.cancel',    'pos', 'Cancel Mobile Payment', 'Can cancel a pending mobile payment',              1);

-- Grant to salesperson (role_id=5)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, p.id FROM `permissions` p
WHERE p.`code` IN ('pos.payment.mobile','pos.payment.verify','pos.payment.cancel');

-- Grant to GM (role_id=6)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, p.id FROM `permissions` p
WHERE p.`code` IN ('pos.payment.mobile','pos.payment.verify','pos.payment.cancel');

-- Grant to Admin (role_id=1)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.`code` IN ('pos.payment.mobile','pos.payment.verify','pos.payment.cancel');

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT 'Mobile payment migration applied.' AS status;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'mobile_payments table' AS check_item
FROM information_schema.tables
WHERE table_schema = 'aam_db' AND table_name = 'mobile_payments';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'sales.payment_provider' AS check_item
FROM information_schema.columns
WHERE table_schema = 'aam_db' AND table_name = 'sales' AND column_name = 'payment_provider';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'sales.payment_reference' AS check_item
FROM information_schema.columns
WHERE table_schema = 'aam_db' AND table_name = 'sales' AND column_name = 'payment_reference';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'sales.payment_status' AS check_item
FROM information_schema.columns
WHERE table_schema = 'aam_db' AND table_name = 'sales' AND column_name = 'payment_status';
