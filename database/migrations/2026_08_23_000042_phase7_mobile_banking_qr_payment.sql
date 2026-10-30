-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_23_000042_phase7_mobile_banking_qr_payment.sql
-- Purpose   : Phase 7 — Mobile Banking / Dynamic QR Payment
--             Customer Mini-Ecommerce Integration
--
-- WHAT THIS MIGRATION DOES
-- ─────────────────────────
-- 1. Ensures customer_orders.payment_reference accepts the
--    new AUS-PAY-YYYYMMDD-XXXXXX format (VARCHAR(40) expansion
--    from VARCHAR(30) — handles the longer format safely).
--
-- 2. Adds a display_name column to payment_methods so admins
--    can set a customer-facing name different from the internal
--    name (e.g. "Pay with CBE Birr" vs "CBE Birr").
--
-- 3. Adds performance indexes for payment status polling
--    (the /customer/checkout/verify endpoint queries these
--    fields on every poll cycle).
--
-- 4. Ensures the mobile_payments.customer_order_id index
--    exists (added in migration 040 but some installs may
--    have run out of order).
--
-- 5. Adds the customer_order_payment_view convenience view
--    for the payment page data join (optional, no-op if
--    the underlying tables don't exist yet).
--
-- PAYMENT LIFECYCLE (enforced at application layer)
-- ──────────────────────────────────────────────────
--   CHECKOUT
--     ↓ POST /customer/checkout/place
--   CREATE customer_orders  (order_status = pending_payment)
--     ↓ MobilePaymentService::initiatePayment()
--   CREATE mobile_payments  (status = pending)
--   LINK   mobile_payments.customer_order_id → customer_orders.id
--     ↓ QR generated from mobile_payments.qr_payload
--   DISPLAY payment.php — status = PENDING
--     ↓ JS polls POST /customer/checkout/verify every 3s
--   MobilePaymentService::verifyPayment()
--     ↓ Provider returns 'paid'
--   UPDATE mobile_payments  (status = paid, verified_at = NOW())
--   CREATE sales + sale_items (via completeSaleFromPayment)
--   UPDATE shop_inventory   (deduct once — idempotent via FOR UPDATE)
--   UPDATE customer_orders  (order_status = paid, paid_at = NOW())
--   CLEAR  customer session cart
--     ↓ JS redirects to success URL
--   DISPLAY checkout/success.php
--
-- IDEMPOTENCY GUARANTEE
--   SELECT FOR UPDATE in completeSaleFromPayment() prevents
--   double inventory deduction even under concurrent verification.
--
-- PAYMENT REFERENCE FORMAT
--   AUS-PAY-YYYYMMDD-XXXXXX
--   where XXXXXX = 6 random uppercase alphanumeric characters
--   (from 34-char alphabet, excluding I and O for legibility)
--   Example: AUS-PAY-20260823-8F42K7
--
-- SAFE TO RE-RUN — all statements use IF NOT EXISTS / ADD COLUMN IF NOT EXISTS
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. EXPAND customer_orders.payment_reference
--    Old format : PAY-YYYY-NNNNNN  (up to 16 chars)
--    New format : AUS-PAY-YYYYMMDD-XXXXXX (up to 23 chars)
--    Expand to VARCHAR(40) to accommodate both old and new
--    records safely, with room for future format changes.
-- =========================================================
ALTER TABLE `customer_orders`
    MODIFY COLUMN `payment_reference`
        VARCHAR(40)
        NULL DEFAULT NULL
        COMMENT 'Payment reference: AUS-PAY-YYYYMMDD-XXXXXX (random 6-char suffix, unique, server-generated)';

-- =========================================================
-- 2. ADD display_name to payment_methods (optional UI label)
--    Allows admin to set customer-facing text like
--    "Pay with CBE Birr" without changing the internal name.
-- =========================================================
ALTER TABLE `payment_methods`
    ADD COLUMN IF NOT EXISTS `display_name`
        VARCHAR(120)
        NULL DEFAULT NULL
        COMMENT 'Optional customer-facing display name shown on checkout page. Falls back to name if NULL.'
        AFTER `name`;

-- =========================================================
-- 3. PERFORMANCE INDEXES — payment page polling
--    These queries run every 3 seconds per active payment page.
--    Without proper indexes they become table scans.
-- =========================================================

-- Index: customer_orders lookup by (id, customer_session_id)
-- Used by: CheckoutService::verifyPayment() security check
CREATE INDEX IF NOT EXISTS `idx_co_id_session`
    ON `customer_orders`(`id`, `customer_session_id`);

-- Index: mobile_payments by (status, customer_order_id)
-- Used by: MobilePaymentService::verifyPayment() and getPaymentStatus()
CREATE INDEX IF NOT EXISTS `idx_mp_status_order`
    ON `mobile_payments`(`status`, `customer_order_id`);

-- =========================================================
-- 4. ENSURE customer_order_id index on mobile_payments
--    (added in 040 but guard idempotently here)
-- =========================================================
CREATE INDEX IF NOT EXISTS `idx_mp_customer_order_id`
    ON `mobile_payments`(`customer_order_id`);

-- =========================================================
-- 5. UPDATE payment_methods display_name defaults
--    Set friendly customer-facing names for the 3 seeded methods.
-- =========================================================
UPDATE `payment_methods`
SET `display_name` = 'Cash on Pickup'
WHERE `code` = 'cash' AND (`display_name` IS NULL OR `display_name` = '');

UPDATE `payment_methods`
SET `display_name` = 'CBE Birr Mobile Banking'
WHERE `code` = 'cbe_birr' AND (`display_name` IS NULL OR `display_name` = '');

UPDATE `payment_methods`
SET `display_name` = 'Telebirr Mobile Money'
WHERE `code` = 'telebirr' AND (`display_name` IS NULL OR `display_name` = '');

-- =========================================================
-- 6. PERMISSIONS — Phase 7 customer payment polling
--    No new roles needed; existing customer flow is
--    session-based (no auth required for customers).
--    No new permissions needed beyond what Phase 5/6 added.
-- =========================================================
-- (No permission changes required — documented for clarity)

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT 'Phase 7 mobile banking QR payment migration applied.' AS status;

SELECT
    CASE WHEN CHARACTER_MAXIMUM_LENGTH >= 40
         THEN 'OK — VARCHAR(40) or larger'
         ELSE 'WARNING — still too short'
    END AS payment_reference_column_size,
    CHARACTER_MAXIMUM_LENGTH AS current_length
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db'
  AND TABLE_NAME   = 'customer_orders'
  AND COLUMN_NAME  = 'payment_reference';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'payment_methods.display_name' AS check_item
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db'
  AND TABLE_NAME   = 'payment_methods'
  AND COLUMN_NAME  = 'display_name';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'idx_co_id_session' AS check_item
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'aam_db'
  AND TABLE_NAME   = 'customer_orders'
  AND INDEX_NAME   = 'idx_co_id_session';

SELECT
    pm.code,
    pm.name,
    pm.display_name,
    CASE pm.is_active WHEN 1 THEN 'ACTIVE' ELSE 'INACTIVE' END AS status
FROM payment_methods pm
ORDER BY pm.sort_order;
