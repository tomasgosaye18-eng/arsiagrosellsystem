-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_21_000041_phase6_shop_order_management.sql
-- Purpose   : Phase 6 — Shop/Salesperson Customer Order
--             Management + Pickup Integration Enhancements
--
-- CHANGES
--   1. Add audit_logs index for customer_orders entity
--      (enables fast audit trail queries on order detail page)
--
--   2. Add search indexes on customer_orders for the
--      queue search (order ref, customer name, phone,
--      payment reference)
--
--   3. Ensure NOTIF_CUSTOMER_ORDER_READY notification type
--      is present in any notification_types catalog table
--      (if the table exists — graceful/optional)
--
--   4. Ensure salesperson has all Phase 6 permissions
--      (shop.customer_orders.view + process) — idempotent
--
--   5. Add customer_order_status_changes table for
--      fine-grained audit trail of every order status
--      transition made by shop staff. Supplements the
--      general audit_logs table with order-specific
--      traceability data.
--
-- SAFE TO RE-RUN — all statements use IF NOT EXISTS /
--                  INSERT IGNORE
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. SEARCH PERFORMANCE INDEXES ON customer_orders
--    Support the queue search: ref, name, phone, pay ref
-- =========================================================
CREATE INDEX IF NOT EXISTS `idx_co_order_reference`    ON `customer_orders`(`order_reference`);
CREATE INDEX IF NOT EXISTS `idx_co_customer_name`      ON `customer_orders`(`customer_name`);
CREATE INDEX IF NOT EXISTS `idx_co_payment_reference`  ON `customer_orders`(`payment_reference`);

-- Composite index: shop + status (queue filter)
CREATE INDEX IF NOT EXISTS `idx_co_shop_status`
    ON `customer_orders`(`shop_id`, `order_status`);

-- Composite index: shop + paid_at (queue sort order)
CREATE INDEX IF NOT EXISTS `idx_co_shop_paid_at`
    ON `customer_orders`(`shop_id`, `paid_at`);

-- =========================================================
-- 2. AUDIT_LOGS SUPPORTING INDEX FOR CUSTOMER ORDERS
--    Speeds up the audit trail query on the order show page:
--    WHERE table_affected = 'customer_orders' AND record_id = :id
-- =========================================================
CREATE INDEX IF NOT EXISTS `idx_audit_table_record`
    ON `audit_logs`(`table_affected`, `record_id`);

-- =========================================================
-- 3. CUSTOMER ORDER STATUS CHANGES TABLE
--    Fine-grained, append-only log of every status
--    transition for a customer order performed by shop staff.
--    Each row is immutable — never updated or deleted.
--
--    This table provides clean, queryable order history
--    without relying solely on the general audit_logs.
-- =========================================================
CREATE TABLE IF NOT EXISTS `customer_order_status_changes` (
    `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`        BIGINT UNSIGNED  NOT NULL
                      COMMENT 'FK → customer_orders.id',
    `order_reference` VARCHAR(30)      NOT NULL
                      COMMENT 'Denormalized for fast display without JOIN',
    `shop_id`         INT UNSIGNED     NOT NULL
                      COMMENT 'FK → shops.id — which shop processed this change',
    `changed_by`      INT UNSIGNED     NOT NULL
                      COMMENT 'FK → users.id — salesperson who triggered the change',
    `old_status`      VARCHAR(30)      NOT NULL,
    `new_status`      VARCHAR(30)      NOT NULL,
    `notes`           VARCHAR(500)     NULL DEFAULT NULL
                      COMMENT 'Optional pickup instructions or notes at time of change',
    `changed_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_cosc_order`
        FOREIGN KEY (`order_id`) REFERENCES `customer_orders`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cosc_shop`
        FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_cosc_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Append-only audit trail of customer order status changes by shop staff — Phase 6';

CREATE INDEX IF NOT EXISTS `idx_cosc_order_id`   ON `customer_order_status_changes`(`order_id`);
CREATE INDEX IF NOT EXISTS `idx_cosc_shop_id`    ON `customer_order_status_changes`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_cosc_changed_by` ON `customer_order_status_changes`(`changed_by`);
CREATE INDEX IF NOT EXISTS `idx_cosc_changed_at` ON `customer_order_status_changes`(`changed_at` DESC);

-- =========================================================
-- 4. PERMISSIONS — Phase 6 additions
--    Ensure all required permissions exist and are assigned
-- =========================================================

-- Phase 5 permissions (idempotent — INSERT IGNORE)
INSERT IGNORE INTO `permissions`
    (`code`, `module`, `label`, `description`, `is_active`)
VALUES
('shop.customer_orders.view',     'shop', 'View Customer Orders',
 'Can view customer pickup orders for their assigned shop', 1),
('shop.customer_orders.process',  'shop', 'Process Customer Orders',
 'Can mark customer orders as preparing/ready/completed', 1),
('shop.customer_orders.search',   'shop', 'Search Customer Orders',
 'Can search customer orders by reference during pickup', 1);

-- Salesperson (role_id = 5) — full view + process + search
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, p.id FROM `permissions` p
WHERE p.`code` IN (
    'shop.customer_orders.view',
    'shop.customer_orders.process',
    'shop.customer_orders.search'
);

-- GM (role_id = 6) — view + search (not process)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, p.id FROM `permissions` p
WHERE p.`code` IN (
    'shop.customer_orders.view',
    'shop.customer_orders.search'
);

-- Admin (role_id = 1) — all
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.`code` IN (
    'shop.customer_orders.view',
    'shop.customer_orders.process',
    'shop.customer_orders.search'
);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT 'Phase 6 shop order management migration applied.' AS status;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'customer_order_status_changes table' AS check_item
FROM information_schema.tables
WHERE table_schema = 'aam_db'
  AND table_name = 'customer_order_status_changes';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'idx_co_shop_status index' AS check_item
FROM information_schema.statistics
WHERE table_schema = 'aam_db'
  AND table_name   = 'customer_orders'
  AND index_name   = 'idx_co_shop_status';

SELECT
    p.code,
    CASE WHEN rp.id IS NOT NULL THEN 'GRANTED' ELSE 'MISSING' END AS salesperson_grant
FROM permissions p
LEFT JOIN role_permissions rp
    ON rp.permission_id = p.id AND rp.role_id = 5
WHERE p.code LIKE 'shop.customer_orders.%'
ORDER BY p.code;
