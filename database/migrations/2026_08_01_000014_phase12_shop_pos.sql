-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_01_000014_phase12_shop_pos.sql
-- Purpose   : Phase 12 — Shop & POS Module
--             Creates tables for:
--               1. seller_shifts       — shift open/close tracking
--               2. sales               — sale transaction header
--               3. sale_items          — sale line items
--             Adds movement type 'sale' already exists (Phase 11)
--             Adds Phase 12 permissions.
-- Run after : 2026_08_01_000013_phase11_inventory_transfer.sql
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. SELLER SHIFTS
--    One row per shift opened by a seller.
--    Immutable records — never deleted.
-- =========================================================
CREATE TABLE IF NOT EXISTS `seller_shifts` (
    `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `shift_number`     VARCHAR(30)    NOT NULL UNIQUE  COMMENT 'SHF-YYYY-NNNN',
    `shop_id`          INT UNSIGNED   NOT NULL,
    `seller_id`        INT UNSIGNED   NOT NULL         COMMENT 'FK → users.id',
    `status`           ENUM('open','closed','abandoned')
                       NOT NULL DEFAULT 'open',
    `opened_at`        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at`        TIMESTAMP      NULL DEFAULT NULL,
    `opening_notes`    VARCHAR(500)   NULL,
    `closing_notes`    VARCHAR(500)   NULL,
    `total_transactions` INT          NOT NULL DEFAULT 0,
    `total_sales_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `cash_collected`   DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_ss_shop`
        FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ss_seller`
        FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Seller shift open/close records per shop';

CREATE INDEX IF NOT EXISTS `idx_shift_shop`      ON `seller_shifts`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_shift_seller`    ON `seller_shifts`(`seller_id`);
CREATE INDEX IF NOT EXISTS `idx_shift_status`    ON `seller_shifts`(`status`);
CREATE INDEX IF NOT EXISTS `idx_shift_opened`    ON `seller_shifts`(`opened_at`);

-- =========================================================
-- 2. SALES (HEADER)
--    One row per completed sale transaction.
--    Immutable after completion — use status 'voided' to cancel.
--    DIRECT CASH SALES ONLY.
-- =========================================================
CREATE TABLE IF NOT EXISTS `sales` (
    `id`              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `sale_number`     VARCHAR(30)    NOT NULL UNIQUE  COMMENT 'SAL-YYYY-NNNN',
    `receipt_number`  VARCHAR(30)    NOT NULL UNIQUE  COMMENT 'RCPT-YYYY-NNNN',
    `shop_id`         INT UNSIGNED   NOT NULL,
    `seller_id`       INT UNSIGNED   NOT NULL         COMMENT 'FK → users.id',
    `shift_id`        INT UNSIGNED   NULL             COMMENT 'FK → seller_shifts.id',
    `status`          ENUM('completed','voided','returned','partially_returned')
                      NOT NULL DEFAULT 'completed',
    `subtotal`        DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `total_amount`    DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `cash_received`   DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `change_given`    DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `total_items`     INT            NOT NULL DEFAULT 0,
    `notes`           VARCHAR(500)   NULL,
    `void_reason`     VARCHAR(500)   NULL,
    `voided_by`       INT UNSIGNED   NULL             COMMENT 'FK → users.id',
    `voided_at`       TIMESTAMP      NULL DEFAULT NULL,
    `sale_date`       DATE           NOT NULL,
    `completed_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_sales_shop`
        FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_seller`
        FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_shift`
        FOREIGN KEY (`shift_id`) REFERENCES `seller_shifts`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_voided_by`
        FOREIGN KEY (`voided_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cash sale transactions — immutable, direct cash only';

CREATE INDEX IF NOT EXISTS `idx_sales_shop`       ON `sales`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_sales_seller`     ON `sales`(`seller_id`);
CREATE INDEX IF NOT EXISTS `idx_sales_shift`      ON `sales`(`shift_id`);
CREATE INDEX IF NOT EXISTS `idx_sales_status`     ON `sales`(`status`);
CREATE INDEX IF NOT EXISTS `idx_sales_date`       ON `sales`(`sale_date`);
CREATE INDEX IF NOT EXISTS `idx_sales_completed`  ON `sales`(`completed_at`);

-- =========================================================
-- 3. SALE ITEMS (LINE ITEMS)
--    One row per product per sale.
-- =========================================================
CREATE TABLE IF NOT EXISTS `sale_items` (
    `id`            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `sale_id`       INT UNSIGNED   NOT NULL,
    `product_id`    INT UNSIGNED   NOT NULL,
    `unit_id`       INT UNSIGNED   NULL,
    `product_name`  VARCHAR(200)   NOT NULL   COMMENT 'Snapshot at time of sale',
    `product_sku`   VARCHAR(60)    NULL       COMMENT 'Snapshot at time of sale',
    `quantity`      DECIMAL(14,3)  NOT NULL,
    `unit_price`    DECIMAL(12,2)  NOT NULL,
    `subtotal`      DECIMAL(14,2)  NOT NULL,
    `created_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_si2_sale`
        FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_si2_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Line items for each sale transaction';

CREATE INDEX IF NOT EXISTS `idx_sale_items_sale_id`    ON `sale_items`(`sale_id`);
CREATE INDEX IF NOT EXISTS `idx_sale_items_product_id` ON `sale_items`(`product_id`);

-- =========================================================
-- 4. PERMISSIONS — Phase 12 POS & Shop
-- =========================================================
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
-- POS Operations
('pos.terminal',         'pos',  'Access POS Terminal',   'Can open and use the POS terminal',                1),
('pos.sale.create',      'pos',  'Create Sale',           'Can process a cash sale transaction',              1),
('pos.sale.view',        'pos',  'View Sales',            'Can view own sales history',                       1),
('pos.sale.void',        'pos',  'Void Sale',             'Can request a sale void (manager approval)',       1),
('pos.receipt.view',     'pos',  'View Receipt',          'Can view and print sale receipts',                 1),
-- Shift Management
('pos.shift.open',       'pos',  'Open Shift',            'Can open a seller shift',                          1),
('pos.shift.close',      'pos',  'Close Shift',           'Can close own shift',                              1),
('pos.shift.view',       'pos',  'View Shifts',           'Can view own shift history',                       1),
-- Shop Visibility
('shop.view',            'shop', 'View Shop',             'Can view own shop details and overview',           1),
('shop.inventory.view',  'shop', 'View Shop Inventory',   'Can view own shop inventory',                      1),
('shop.sales.view',      'shop', 'View Shop Sales',       'Can view shop sales (all sellers)',                1),
('shop.sales.manage',    'shop', 'Manage Shop Sales',     'Can view/void all shop sales (manager role)',      1);

-- Grant to salesperson (role_id = 5) — operational POS access
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, id FROM `permissions`
WHERE `code` IN (
    'pos.terminal','pos.sale.create','pos.sale.view','pos.receipt.view',
    'pos.shift.open','pos.shift.close','pos.shift.view',
    'shop.view','shop.inventory.view'
);

-- Grant to storekeeper (role_id = 4) — shop oversight + POS read
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions`
WHERE `code` IN (
    'pos.terminal','pos.sale.view','pos.sale.void','pos.receipt.view',
    'pos.shift.view','shop.view','shop.inventory.view','shop.sales.view','shop.sales.manage'
);

-- Refresh admin (role_id=1) and GM (role_id=6) — all permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

DELETE FROM `role_permissions` WHERE `role_id` = 6;
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions`;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Phase 12 Shop & POS migration applied successfully.' AS status;
SELECT COUNT(*) AS phase12_tables
FROM information_schema.tables
WHERE table_schema = 'aam_db'
  AND table_name IN ('seller_shifts','sales','sale_items');
