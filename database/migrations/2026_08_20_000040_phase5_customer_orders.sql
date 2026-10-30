-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_20_000040_phase5_customer_orders.sql
-- Purpose   : Customer Pickup Order System — Phase 5
--
-- DESIGN DECISION
-- ───────────────
-- Customer orders are distinct from POS sales:
--   - A customer places an order BEFORE payment is confirmed
--   - Payment must be verified before inventory is deducted
--   - The pickup workflow adds states not present in POS sales
--     (PREPARING, READY_FOR_PICKUP)
--   - After payment is verified, a normal sale record is
--     created (reusing existing sales architecture) so all
--     reporting and inventory logic is unchanged.
--   - customer_orders links to mobile_payments (payment tracking)
--     and eventually to sales (after completion).
--
-- ORDER STATUS WORKFLOW
-- ─────────────────────
--   PENDING_PAYMENT  → customer submitted order, awaiting payment
--   PAID             → payment verified, shop notified to prepare
--   PREPARING        → shop acknowledged, preparing items
--   READY_FOR_PICKUP → shop marked order ready, customer notified
--   COMPLETED        → customer picked up, sale record created
--   CANCELLED        → order cancelled (payment failed/expired/manual)
--   PAYMENT_FAILED   → payment attempt failed
--
-- PAYMENT STATUS (in customer_orders)
-- ────────────────────────────────────
--   PENDING  → waiting for payment
--   PAID     → payment verified
--   FAILED   → payment failed
--   EXPIRED  → payment window expired
--   CANCELLED→ order cancelled before payment
--
-- NEW TABLES
--   customer_orders      — order header with pickup workflow state
--   customer_order_items — order line items (snapshot at order time)
--
-- REUSED TABLES (no structural changes)
--   mobile_payments     — payment tracking (already exists)
--   sales               — created after pickup completion
--   sale_items          — created after pickup completion
--   shop_inventory      — deducted after payment verification
--   notifications       — customer + shop notifications
--
-- SAFE TO RE-RUN — all statements use IF NOT EXISTS
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. CUSTOMER ORDERS TABLE
--    Header record for each customer pickup order.
--    Created at checkout submission (before payment).
--    Holds the order reference, status lifecycle, and links
--    to both the payment record and the eventual sale record.
-- =========================================================
CREATE TABLE IF NOT EXISTS `customer_orders` (
    `id`                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,

    -- Customer identity
    -- We store customer_user_id if the customer is a registered user,
    -- OR customer_name/phone for guest/session-based ordering.
    -- Phase 5 uses session-based customers (no separate customer table).
    `customer_session_id` VARCHAR(128)   NULL DEFAULT NULL
                          COMMENT 'PHP session ID at order creation — links this order to the customer session',
    `customer_name`       VARCHAR(200)   NOT NULL
                          COMMENT 'Customer display name (from session or manually entered)',
    `customer_phone`      VARCHAR(30)    NULL DEFAULT NULL
                          COMMENT 'Customer phone number for pickup notification',
    `customer_email`      VARCHAR(200)   NULL DEFAULT NULL
                          COMMENT 'Customer email (optional)',
    `customer_user_id`    INT UNSIGNED   NULL DEFAULT NULL
                          COMMENT 'FK → users.id — if customer is a registered system user (future auth)',

    -- Order reference (customer-facing, unique)
    `order_reference`     VARCHAR(30)    NOT NULL UNIQUE
                          COMMENT 'Human-readable order reference: ORD-YYYY-NNNNNN',

    -- Shop
    `shop_id`             INT UNSIGNED   NOT NULL
                          COMMENT 'FK → shops.id — the pickup shop',

    -- Order lifecycle
    `order_status`        ENUM(
                              'pending_payment',
                              'paid',
                              'preparing',
                              'ready_for_pickup',
                              'completed',
                              'cancelled',
                              'payment_failed'
                          ) NOT NULL DEFAULT 'pending_payment'
                          COMMENT 'Pickup order workflow state',

    -- Payment details
    `payment_status`      ENUM('pending','paid','failed','expired','cancelled')
                          NOT NULL DEFAULT 'pending',
    `payment_method_id`   INT UNSIGNED   NULL DEFAULT NULL
                          COMMENT 'FK → payment_methods.id',
    `payment_reference`   VARCHAR(30)    NULL DEFAULT NULL UNIQUE
                          COMMENT 'Payment reference: PAY-YYYY-NNNNNN',
    `mobile_payment_id`   BIGINT UNSIGNED NULL DEFAULT NULL
                          COMMENT 'FK → mobile_payments.id — the payment attempt record',

    -- Amounts (server-calculated, never trusted from client)
    `subtotal`            DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `total_amount`        DECIMAL(14,2)  NOT NULL DEFAULT 0.00,

    -- Sale linkage (populated after pickup completion)
    `sale_id`             INT UNSIGNED   NULL DEFAULT NULL
                          COMMENT 'FK → sales.id — populated after order is completed and sale is created',

    -- Pickup instructions / notes
    `notes`               VARCHAR(500)   NULL DEFAULT NULL,
    `pickup_instructions` VARCHAR(500)   NULL DEFAULT NULL
                          COMMENT 'Instructions displayed to customer for pickup',

    -- Staff who processed the order (salesperson who marks ready/complete)
    `processed_by`        INT UNSIGNED   NULL DEFAULT NULL
                          COMMENT 'FK → users.id — salesperson who handled the pickup',

    -- Key timestamps
    `paid_at`             TIMESTAMP      NULL DEFAULT NULL COMMENT 'When payment was confirmed',
    `ready_at`            TIMESTAMP      NULL DEFAULT NULL COMMENT 'When shop marked order ready for pickup',
    `completed_at`        TIMESTAMP      NULL DEFAULT NULL COMMENT 'When customer picked up order',
    `cancelled_at`        TIMESTAMP      NULL DEFAULT NULL,
    `cancellation_reason` VARCHAR(300)   NULL DEFAULT NULL,

    -- Expiry (auto-cancel if payment not made within TTL)
    `expires_at`          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
                          COMMENT 'Order/payment expires if not paid by this time',

    `created_at`          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_co_shop`
        FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_co_payment_method`
        FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_co_mobile_payment`
        FOREIGN KEY (`mobile_payment_id`) REFERENCES `mobile_payments`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Customer pickup order headers — Phase 5 customer storefront';

CREATE INDEX IF NOT EXISTS `idx_co_session`          ON `customer_orders`(`customer_session_id`);
CREATE INDEX IF NOT EXISTS `idx_co_customer_phone`   ON `customer_orders`(`customer_phone`);
CREATE INDEX IF NOT EXISTS `idx_co_shop_id`          ON `customer_orders`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_co_order_status`     ON `customer_orders`(`order_status`);
CREATE INDEX IF NOT EXISTS `idx_co_payment_status`   ON `customer_orders`(`payment_status`);
CREATE INDEX IF NOT EXISTS `idx_co_mobile_payment`   ON `customer_orders`(`mobile_payment_id`);
CREATE INDEX IF NOT EXISTS `idx_co_sale_id`          ON `customer_orders`(`sale_id`);
CREATE INDEX IF NOT EXISTS `idx_co_expires_at`       ON `customer_orders`(`expires_at`);
CREATE INDEX IF NOT EXISTS `idx_co_created_at`       ON `customer_orders`(`created_at` DESC);

-- =========================================================
-- 2. CUSTOMER ORDER ITEMS TABLE
--    Line items for each customer order.
--    Captured server-side at order creation time.
--    Prices are authoritative DB prices — never client values.
-- =========================================================
CREATE TABLE IF NOT EXISTS `customer_order_items` (
    `id`            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`      BIGINT UNSIGNED  NOT NULL COMMENT 'FK → customer_orders.id',
    `product_id`    INT UNSIGNED     NOT NULL COMMENT 'FK → products.id',
    `unit_id`       INT UNSIGNED     NULL DEFAULT NULL COMMENT 'FK → units_of_measure.id',
    `product_name`  VARCHAR(255)     NOT NULL COMMENT 'Snapshot of product name at order time',
    `product_sku`   VARCHAR(100)     NULL DEFAULT NULL,
    `quantity`      DECIMAL(12,3)    NOT NULL,
    `unit_price`    DECIMAL(14,2)    NOT NULL COMMENT 'Authoritative price from DB at order time',
    `subtotal`      DECIMAL(14,2)    NOT NULL,
    `created_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_coi_order`
        FOREIGN KEY (`order_id`) REFERENCES `customer_orders`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Customer order line items — price/name snapshot at order creation';

CREATE INDEX IF NOT EXISTS `idx_coi_order_id`    ON `customer_order_items`(`order_id`);
CREATE INDEX IF NOT EXISTS `idx_coi_product_id`  ON `customer_order_items`(`product_id`);

-- =========================================================
-- 3. ADD customer_order_id TO mobile_payments
--    Reverse link: mobile_payments row knows which customer
--    order it belongs to. (Forward link is in customer_orders.)
-- =========================================================
ALTER TABLE `mobile_payments`
    ADD COLUMN IF NOT EXISTS `customer_order_id`
        BIGINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'FK → customer_orders.id — set when initiated via customer checkout'
        AFTER `sale_id`;

CREATE INDEX IF NOT EXISTS `idx_mp_customer_order_id`
    ON `mobile_payments`(`customer_order_id`);

-- =========================================================
-- 4. NOTIFICATION TYPE CONSTANTS
--    New notification types for customer order workflow.
--    These are inserted into the notification_types table
--    if that table exists (graceful — some installs may not
--    have a dedicated notification_types table).
-- =========================================================

-- Attempt to insert notification type records if table exists
SET @has_notif_types = (
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'notification_types'
);

-- Note: The notifications system uses free-text type codes,
-- so no structural changes to notifications table are needed.

-- =========================================================
-- 5. PERMISSIONS FOR CUSTOMER ORDER MANAGEMENT
--    Salesperson (shop staff) needs to see + process orders.
-- =========================================================
INSERT IGNORE INTO `permissions`
    (`code`, `module`, `label`, `description`, `is_active`)
VALUES
('shop.customer_orders.view',     'shop', 'View Customer Orders',
 'Can view customer pickup orders for their assigned shop', 1),
('shop.customer_orders.process',  'shop', 'Process Customer Orders',
 'Can mark customer orders as preparing/ready/completed', 1);

-- Grant to salesperson (role_id = 5)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, p.id FROM `permissions` p
WHERE p.`code` IN (
    'shop.customer_orders.view',
    'shop.customer_orders.process'
);

-- Grant to GM (role_id = 6) — visibility
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, p.id FROM `permissions` p
WHERE p.`code` = 'shop.customer_orders.view';

-- Grant to Admin (role_id = 1) — full visibility
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.`code` IN (
    'shop.customer_orders.view',
    'shop.customer_orders.process'
);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT 'Phase 5 customer orders migration applied.' AS status;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'customer_orders table' AS check_item
FROM information_schema.tables
WHERE table_schema = 'aam_db' AND table_name = 'customer_orders';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'customer_order_items table' AS check_item
FROM information_schema.tables
WHERE table_schema = 'aam_db' AND table_name = 'customer_order_items';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'mobile_payments.customer_order_id column' AS check_item
FROM information_schema.columns
WHERE table_schema = 'aam_db'
  AND table_name   = 'mobile_payments'
  AND column_name  = 'customer_order_id';
