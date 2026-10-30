-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_01_000013_phase11_inventory_transfer.sql
-- Purpose   : Phase 11 — Inventory & Transfer Module
--             Extends existing Phase 10 schema with:
--               1. inventory_batches   — multi-location batch tracking
--               2. stock_adjustments   — adjustment transactions
--               3. stock_counts        — physical count sessions
--               4. stock_count_items   — per-product count lines
--             And adds columns to existing tables:
--               - warehouse_inventory:  min_stock_level already exists
--               - shop_inventory:       adds batch_number, min_stock_level,
--                                       expiry_date, last_movement_type
--               - stock_movement_log:   adds 'sale','count','damage','expiry'
--                                       to movement_type enum
-- All safe to run multiple times (IF NOT EXISTS / ALTER IGNORE).
-- Run after : 2026_07_31_000012_phase10_warehouse_operations.sql
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. INVENTORY BATCHES
--    Multi-location batch ledger supporting FEFO.
--    One row per (location_type, location_id, product_id, batch_number).
--    Tracks quantity at that batch level within a location.
-- =========================================================
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
    `source_type`        VARCHAR(40)   NULL COMMENT 'receiving|transfer|adjustment',
    `source_id`          INT UNSIGNED  NULL COMMENT 'FK to source record',
    `notes`              VARCHAR(255)  NULL,
    `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ib_location_product_batch` (`location_type`, `location_id`, `product_id`, `batch_number`),

    CONSTRAINT `fk_ib_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ib_unit`
        FOREIGN KEY (`unit_id`) REFERENCES `units_of_measure`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Batch-level inventory per location; supports FEFO';

CREATE INDEX IF NOT EXISTS `idx_ib_location`    ON `inventory_batches`(`location_type`, `location_id`);
CREATE INDEX IF NOT EXISTS `idx_ib_product_id`  ON `inventory_batches`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_ib_expiry`      ON `inventory_batches`(`expiry_date`);
CREATE INDEX IF NOT EXISTS `idx_ib_batch`       ON `inventory_batches`(`batch_number`);

-- =========================================================
-- 2. STOCK ADJUSTMENTS
--    Authorized corrections to physical stock.
--    Never directly modifies inventory — creates a transaction
--    that is then applied to warehouse_inventory or shop_inventory.
-- =========================================================
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `adjustment_number`VARCHAR(30)   NOT NULL UNIQUE COMMENT 'ADJ-YYYY-NNNN',
    `location_type`    ENUM('warehouse','shop') NOT NULL,
    `location_id`      INT UNSIGNED  NOT NULL,
    `product_id`       INT UNSIGNED  NOT NULL,
    `batch_number`     VARCHAR(60)   NULL,
    `unit_id`          INT UNSIGNED  NULL,
    `system_quantity`  DECIMAL(14,3) NOT NULL COMMENT 'Quantity before adjustment',
    `physical_quantity`DECIMAL(14,3) NOT NULL COMMENT 'Actual physical count',
    `variance`         DECIMAL(14,3) NOT NULL COMMENT 'physical - system',
    `reason`           ENUM('physical_count','damaged','spoilage','expiry',
                            'measurement_error','correction','transfer_error','other')
                       NOT NULL DEFAULT 'physical_count',
    `notes`            TEXT          NULL,
    `status`           ENUM('pending','approved','rejected','applied')
                       NOT NULL DEFAULT 'pending',
    `requested_by`     INT UNSIGNED  NULL,
    `approved_by`      INT UNSIGNED  NULL,
    `applied_at`       TIMESTAMP     NULL,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_sa_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sa_requested_by`
        FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sa_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stock adjustment transactions — never directly edits inventory';

CREATE INDEX IF NOT EXISTS `idx_sa_location`   ON `stock_adjustments`(`location_type`, `location_id`);
CREATE INDEX IF NOT EXISTS `idx_sa_product_id` ON `stock_adjustments`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_sa_status`     ON `stock_adjustments`(`status`);
CREATE INDEX IF NOT EXISTS `idx_sa_created`    ON `stock_adjustments`(`created_at`);

-- =========================================================
-- 3. STOCK COUNTS  (physical inventory count sessions)
-- =========================================================
CREATE TABLE IF NOT EXISTS `stock_counts` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `count_number`  VARCHAR(30)   NOT NULL UNIQUE COMMENT 'CNT-YYYY-NNNN',
    `location_type` ENUM('warehouse','shop') NOT NULL,
    `location_id`   INT UNSIGNED  NOT NULL,
    `status`        ENUM('draft','in_progress','completed','approved','cancelled')
                    NOT NULL DEFAULT 'draft',
    `count_date`    DATE          NOT NULL,
    `notes`         TEXT          NULL,
    `started_by`    INT UNSIGNED  NULL,
    `approved_by`   INT UNSIGNED  NULL,
    `started_at`    TIMESTAMP     NULL,
    `completed_at`  TIMESTAMP     NULL,
    `approved_at`   TIMESTAMP     NULL,
    `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_sc_started_by`
        FOREIGN KEY (`started_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Physical stock count sessions';

CREATE INDEX IF NOT EXISTS `idx_sc_location`  ON `stock_counts`(`location_type`, `location_id`);
CREATE INDEX IF NOT EXISTS `idx_sc_status`    ON `stock_counts`(`status`);
CREATE INDEX IF NOT EXISTS `idx_sc_date`      ON `stock_counts`(`count_date`);

-- =========================================================
-- 4. STOCK COUNT ITEMS  (per-product lines within a count)
-- =========================================================
CREATE TABLE IF NOT EXISTS `stock_count_items` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `count_id`          INT UNSIGNED  NOT NULL,
    `product_id`        INT UNSIGNED  NOT NULL,
    `batch_number`      VARCHAR(60)   NULL,
    `unit_id`           INT UNSIGNED  NULL,
    `system_quantity`   DECIMAL(14,3) NOT NULL DEFAULT 0.000 COMMENT 'System balance at count time',
    `counted_quantity`  DECIMAL(14,3) NULL      COMMENT 'Physical count entered by user',
    `variance`          DECIMAL(14,3) NULL      COMMENT 'counted - system',
    `notes`             VARCHAR(255)  NULL,
    `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_sci_count`
        FOREIGN KEY (`count_id`) REFERENCES `stock_counts`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sci_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Line items for physical stock count sessions';

CREATE INDEX IF NOT EXISTS `idx_sci_count_id`   ON `stock_count_items`(`count_id`);
CREATE INDEX IF NOT EXISTS `idx_sci_product_id` ON `stock_count_items`(`product_id`);

-- =========================================================
-- 5. EXTEND shop_inventory with Phase 11 fields
-- =========================================================
ALTER TABLE `shop_inventory`
    ADD COLUMN IF NOT EXISTS `batch_number`       VARCHAR(60)   NULL DEFAULT NULL
        COMMENT 'Primary batch tracking',
    ADD COLUMN IF NOT EXISTS `min_stock_level`    DECIMAL(14,3) NOT NULL DEFAULT 0.000
        COMMENT 'Minimum stock threshold for low-stock alerts',
    ADD COLUMN IF NOT EXISTS `expiry_date`        DATE          NULL DEFAULT NULL
        COMMENT 'Expiry date for perishable tracking',
    ADD COLUMN IF NOT EXISTS `last_movement_type` VARCHAR(40)   NULL DEFAULT NULL
        COMMENT 'Type of last stock movement';

CREATE INDEX IF NOT EXISTS `idx_si_expiry` ON `shop_inventory`(`expiry_date`);

-- =========================================================
-- 6. EXTEND stock_movement_log — add new movement types
--    MySQL does not support adding to ENUM easily; use a
--    full ALTER COLUMN statement.
-- =========================================================
ALTER TABLE `stock_movement_log`
    MODIFY COLUMN `movement_type`
        ENUM('receiving','transfer_out','transfer_in',
             'adjustment','discrepancy','return','initial',
             'sale','count','damage','expiry')
        NOT NULL;

-- =========================================================
-- 7. PERMISSIONS — Phase 11 inventory control
-- =========================================================
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('inventory.overview',           'inventory', 'Inventory Overview',        'Can view inventory overview dashboard',       1),
('inventory.view.warehouse',     'inventory', 'View Warehouse Inventory',  'Can view warehouse location inventory',        1),
('inventory.view.shop',          'inventory', 'View Shop Inventory',       'Can view shop location inventory',            1),
('inventory.view.all',           'inventory', 'View All Inventory',        'Can view inventory across all locations',      1),
('inventory.batch.view',         'inventory', 'View Batches',              'Can view batch tracking records',             1),
('inventory.movements.view',     'inventory', 'View Stock Movements',      'Can view stock movement ledger',              1),
('inventory.adjustment.request', 'inventory', 'Request Adjustment',        'Can submit stock adjustment requests',        1),
('inventory.adjustment.approve', 'inventory', 'Approve Adjustment',        'Can approve or reject stock adjustments',     1),
('inventory.adjustment.view',    'inventory', 'View Adjustments',          'Can view stock adjustment history',           1),
('inventory.count.create',       'inventory', 'Create Stock Count',        'Can start a new physical stock count',        1),
('inventory.count.enter',        'inventory', 'Enter Stock Count',         'Can enter physical quantities in a count',    1),
('inventory.count.approve',      'inventory', 'Approve Stock Count',       'Can approve a completed stock count',         1),
('inventory.count.view',         'inventory', 'View Stock Counts',         'Can view stock count history',                1);

-- Grant to warehouse_manager (role_id = 3)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions`
WHERE `code` IN (
    'inventory.overview','inventory.view.warehouse','inventory.view.all',
    'inventory.batch.view','inventory.movements.view',
    'inventory.adjustment.request','inventory.adjustment.approve','inventory.adjustment.view',
    'inventory.count.create','inventory.count.enter','inventory.count.approve','inventory.count.view'
);

-- Grant to storekeeper (role_id = 4)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions`
WHERE `code` IN (
    'inventory.overview','inventory.view.warehouse','inventory.view.shop',
    'inventory.batch.view','inventory.movements.view',
    'inventory.adjustment.request','inventory.adjustment.view',
    'inventory.count.create','inventory.count.enter','inventory.count.view'
);

-- Grant to farm_manager (role_id = 2) — read-only overview
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions`
WHERE `code` IN (
    'inventory.overview','inventory.movements.view','inventory.batch.view'
);

-- Refresh admin (role_id=1) and GM (role_id=6) — all permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

DELETE FROM `role_permissions` WHERE `role_id` = 6;
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions`;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Phase 11 inventory & transfer migration applied successfully.' AS status;
SELECT COUNT(*) AS phase11_tables
FROM information_schema.tables
WHERE table_schema = 'aam_db'
  AND table_name IN ('inventory_batches','stock_adjustments','stock_counts','stock_count_items');
