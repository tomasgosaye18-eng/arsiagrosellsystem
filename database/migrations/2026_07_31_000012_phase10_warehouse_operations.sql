-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_07_31_000012_phase10_warehouse_operations.sql
-- Purpose   : Phase 10 — Warehouse Operations Module
--             Creates tables for:
--               1. warehouse_inventory
--               2. warehouse_receiving (header)
--               3. warehouse_receiving_items (line items)
--               4. warehouse_discrepancies
--               5. warehouse_transfers (header)
--               6. warehouse_transfer_items (line items)
--               7. shop_inventory
--               8. stock_movement_log
--             All safe to run multiple times (IF NOT EXISTS).
-- Run after : 2026_07_31_000011_manager_admin_integration.sql
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. WAREHOUSE INVENTORY
--    One row per (warehouse_id, product_id).
--    current_quantity = total physical stock.
--    reserved_quantity = qty locked for pending transfers.
--    available_quantity = current - reserved.
-- =========================================================
CREATE TABLE IF NOT EXISTS `warehouse_inventory` (
    `id`                   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `warehouse_id`         INT UNSIGNED      NOT NULL,
    `product_id`           INT UNSIGNED      NOT NULL,
    `batch_number`         VARCHAR(60)       NULL DEFAULT NULL COMMENT 'Primary batch for FEFO',
    `current_quantity`     DECIMAL(14,3)     NOT NULL DEFAULT 0.000,
    `reserved_quantity`    DECIMAL(14,3)     NOT NULL DEFAULT 0.000,
    `available_quantity`   DECIMAL(14,3)     NOT NULL DEFAULT 0.000,
    `unit_id`              INT UNSIGNED      NULL COMMENT 'Unit of measure',
    `min_stock_level`      DECIMAL(14,3)     NOT NULL DEFAULT 0.000,
    `production_date`      DATE              NULL,
    `expiry_date`          DATE              NULL,
    `last_movement_at`     TIMESTAMP         NULL DEFAULT NULL,
    `last_movement_type`   VARCHAR(40)       NULL DEFAULT NULL,
    `created_at`           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wi_warehouse_product` (`warehouse_id`, `product_id`),

    CONSTRAINT `fk_wi_warehouse`
        FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wi_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Current inventory balance per product per warehouse';

CREATE INDEX IF NOT EXISTS `idx_wi_warehouse_id`  ON `warehouse_inventory`(`warehouse_id`);
CREATE INDEX IF NOT EXISTS `idx_wi_product_id`    ON `warehouse_inventory`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_wi_expiry`        ON `warehouse_inventory`(`expiry_date`);

-- =========================================================
-- 2. WAREHOUSE RECEIVING (HEADER)
--    One row per dispatch accepted for receiving.
--    References existing farm_dispatches.
-- =========================================================
CREATE TABLE IF NOT EXISTS `warehouse_receiving` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `receiving_number` VARCHAR(30)   NOT NULL UNIQUE COMMENT 'RCV-YYYY-NNNN',
    `dispatch_id`      INT UNSIGNED  NOT NULL COMMENT 'FK → farm_dispatches.id',
    `warehouse_id`     INT UNSIGNED  NOT NULL,
    `farm_id`          INT UNSIGNED  NULL,
    `received_by`      INT UNSIGNED  NULL COMMENT 'FK → users.id',
    `received_date`    DATE          NOT NULL,
    `status`           ENUM('pending','received','partially_received',
                            'discrepancy','rejected','cancelled')
                       NOT NULL DEFAULT 'pending',
    `total_expected`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `total_received`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `notes`            TEXT          NULL,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wr_dispatch` (`dispatch_id`),

    CONSTRAINT `fk_wr_dispatch`
        FOREIGN KEY (`dispatch_id`) REFERENCES `farm_dispatches`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wr_warehouse`
        FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wr_received_by`
        FOREIGN KEY (`received_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Warehouse receiving records — one per farm dispatch';

CREATE INDEX IF NOT EXISTS `idx_wr_dispatch_id`   ON `warehouse_receiving`(`dispatch_id`);
CREATE INDEX IF NOT EXISTS `idx_wr_warehouse_id`  ON `warehouse_receiving`(`warehouse_id`);
CREATE INDEX IF NOT EXISTS `idx_wr_status`        ON `warehouse_receiving`(`status`);
CREATE INDEX IF NOT EXISTS `idx_wr_received_date` ON `warehouse_receiving`(`received_date`);

-- =========================================================
-- 3. WAREHOUSE RECEIVING ITEMS (LINE ITEMS)
--    One row per product/batch line within a receiving.
-- =========================================================
CREATE TABLE IF NOT EXISTS `warehouse_receiving_items` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `receiving_id`        INT UNSIGNED  NOT NULL,
    `dispatch_item_id`    INT UNSIGNED  NOT NULL COMMENT 'FK → farm_dispatch_items.id',
    `product_id`          INT UNSIGNED  NOT NULL,
    `unit_id`             INT UNSIGNED  NULL,
    `batch_number`        VARCHAR(60)   NULL,
    `production_date`     DATE          NULL,
    `expiry_date`         DATE          NULL,
    `quantity_expected`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_received`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_variance`   DECIMAL(14,3) NOT NULL DEFAULT 0.000 COMMENT 'expected - received',
    `variance_percent`    DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    `item_status`         ENUM('pending','accepted','partial','rejected','discrepancy')
                          NOT NULL DEFAULT 'pending',
    `notes`               VARCHAR(500)  NULL,
    `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_wri_receiving`
        FOREIGN KEY (`receiving_id`) REFERENCES `warehouse_receiving`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wri_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Line items for each warehouse receiving record';

CREATE INDEX IF NOT EXISTS `idx_wri_receiving_id` ON `warehouse_receiving_items`(`receiving_id`);
CREATE INDEX IF NOT EXISTS `idx_wri_product_id`   ON `warehouse_receiving_items`(`product_id`);

-- =========================================================
-- 4. WAREHOUSE DISCREPANCIES
--    Records quantity / quality mismatches on receiving.
--    Immutable — never deleted.
-- =========================================================
CREATE TABLE IF NOT EXISTS `warehouse_discrepancies` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `discrepancy_number` VARCHAR(30)   NOT NULL UNIQUE COMMENT 'DIS-YYYY-NNNN',
    `reference_type`     ENUM('receiving','transfer') NOT NULL DEFAULT 'receiving',
    `reference_id`       INT UNSIGNED  NOT NULL COMMENT 'FK → warehouse_receiving.id or transfer_orders.id',
    `receiving_item_id`  INT UNSIGNED  NULL,
    `warehouse_id`       INT UNSIGNED  NOT NULL,
    `farm_id`            INT UNSIGNED  NULL,
    `shop_id`            INT UNSIGNED  NULL,
    `product_id`         INT UNSIGNED  NOT NULL,
    `batch_number`       VARCHAR(60)   NULL,
    `expected_quantity`  DECIMAL(14,3) NOT NULL,
    `actual_quantity`    DECIMAL(14,3) NOT NULL,
    `variance_quantity`  DECIMAL(14,3) NOT NULL,
    `variance_percent`   DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    `discrepancy_type`   ENUM('short_delivery','over_delivery','damaged',
                              'spoiled','wrong_product','wrong_unit',
                              'expired','packaging_damage','other')
                         NOT NULL DEFAULT 'other',
    `reason`             TEXT          NOT NULL,
    `status`             ENUM('open','under_review','resolved','rejected')
                         NOT NULL DEFAULT 'open',
    `reported_by`        INT UNSIGNED  NULL,
    `reported_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_by`        INT UNSIGNED  NULL,
    `resolved_at`        TIMESTAMP     NULL,
    `resolution_notes`   TEXT          NULL,
    `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_wd_warehouse`
        FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wd_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wd_reported_by`
        FOREIGN KEY (`reported_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_wd_resolved_by`
        FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable discrepancy records for receiving and transfer';

CREATE INDEX IF NOT EXISTS `idx_wd_reference`     ON `warehouse_discrepancies`(`reference_type`, `reference_id`);
CREATE INDEX IF NOT EXISTS `idx_wd_warehouse_id`  ON `warehouse_discrepancies`(`warehouse_id`);
CREATE INDEX IF NOT EXISTS `idx_wd_product_id`    ON `warehouse_discrepancies`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_wd_status`        ON `warehouse_discrepancies`(`status`);
CREATE INDEX IF NOT EXISTS `idx_wd_reported_at`   ON `warehouse_discrepancies`(`reported_at`);

-- =========================================================
-- 5. WAREHOUSE TRANSFERS (HEADER)
--    Warehouse → Shop transfer orders.
-- =========================================================
CREATE TABLE IF NOT EXISTS `warehouse_transfers` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `transfer_number`  VARCHAR(30)   NOT NULL UNIQUE COMMENT 'TRF-YYYY-NNNN',
    `warehouse_id`     INT UNSIGNED  NOT NULL,
    `shop_id`          INT UNSIGNED  NOT NULL,
    `status`           ENUM('draft','pending','approved','in_transit',
                            'received','partially_received','discrepancy','cancelled')
                       NOT NULL DEFAULT 'pending',
    `initiated_by`     INT UNSIGNED  NULL,
    `approved_by`      INT UNSIGNED  NULL,
    `received_by`      INT UNSIGNED  NULL,
    `transfer_date`    DATE          NOT NULL,
    `expected_date`    DATE          NULL,
    `received_date`    DATE          NULL,
    `total_items`      INT           NOT NULL DEFAULT 0,
    `total_quantity`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `notes`            TEXT          NULL,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_wt_warehouse`
        FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wt_shop`
        FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wt_initiated_by`
        FOREIGN KEY (`initiated_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_wt_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_wt_received_by`
        FOREIGN KEY (`received_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Warehouse to shop transfer orders';

CREATE INDEX IF NOT EXISTS `idx_wtr_warehouse_id`   ON `warehouse_transfers`(`warehouse_id`);
CREATE INDEX IF NOT EXISTS `idx_wtr_shop_id`        ON `warehouse_transfers`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_wtr_status`         ON `warehouse_transfers`(`status`);
CREATE INDEX IF NOT EXISTS `idx_wtr_transfer_date`  ON `warehouse_transfers`(`transfer_date`);

-- =========================================================
-- 6. WAREHOUSE TRANSFER ITEMS (LINE ITEMS)
-- =========================================================
CREATE TABLE IF NOT EXISTS `warehouse_transfer_items` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `transfer_id`         INT UNSIGNED  NOT NULL,
    `product_id`          INT UNSIGNED  NOT NULL,
    `unit_id`             INT UNSIGNED  NULL,
    `batch_number`        VARCHAR(60)   NULL,
    `expiry_date`         DATE          NULL,
    `quantity_requested`  DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_sent`       DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_received`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `item_status`         ENUM('pending','sent','received','partial','discrepancy')
                          NOT NULL DEFAULT 'pending',
    `notes`               VARCHAR(500)  NULL,
    `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_wti_transfer`
        FOREIGN KEY (`transfer_id`) REFERENCES `warehouse_transfers`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_wti_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Line items for warehouse transfer orders';

CREATE INDEX IF NOT EXISTS `idx_wti_transfer_id` ON `warehouse_transfer_items`(`transfer_id`);
CREATE INDEX IF NOT EXISTS `idx_wti_product_id`  ON `warehouse_transfer_items`(`product_id`);

-- =========================================================
-- 7. SHOP INVENTORY
--    One row per (shop_id, product_id).
-- =========================================================
CREATE TABLE IF NOT EXISTS `shop_inventory` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `shop_id`            INT UNSIGNED  NOT NULL,
    `product_id`         INT UNSIGNED  NOT NULL,
    `current_quantity`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `reserved_quantity`  DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `available_quantity` DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `unit_id`            INT UNSIGNED  NULL,
    `last_movement_at`   TIMESTAMP     NULL DEFAULT NULL,
    `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_si_shop_product` (`shop_id`, `product_id`),

    CONSTRAINT `fk_si_shop`
        FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_si_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Current inventory balance per product per shop';

CREATE INDEX IF NOT EXISTS `idx_si_shop_id`    ON `shop_inventory`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_si_product_id` ON `shop_inventory`(`product_id`);

-- =========================================================
-- 8. STOCK MOVEMENT LOG
--    Immutable append-only ledger. Never updated/deleted.
-- =========================================================
CREATE TABLE IF NOT EXISTS `stock_movement_log` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `movement_type`    ENUM('receiving','transfer_out','transfer_in',
                            'adjustment','discrepancy','return','initial')
                       NOT NULL,
    `location_type`    ENUM('warehouse','shop') NOT NULL,
    `location_id`      INT UNSIGNED  NOT NULL,
    `product_id`       INT UNSIGNED  NOT NULL,
    `batch_number`     VARCHAR(60)   NULL,
    `quantity_before`  DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_in`      DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_out`     DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `quantity_after`   DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `reference_type`   VARCHAR(40)   NULL COMMENT 'warehouse_receiving|warehouse_transfers|adjustment',
    `reference_id`     INT UNSIGNED  NULL,
    `reference_number` VARCHAR(30)   NULL,
    `performed_by`     INT UNSIGNED  NULL,
    `notes`            VARCHAR(500)  NULL,
    `moved_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_sml_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable ledger of every warehouse/shop stock movement';

CREATE INDEX IF NOT EXISTS `idx_sml_location`     ON `stock_movement_log`(`location_type`, `location_id`);
CREATE INDEX IF NOT EXISTS `idx_sml_product_id`   ON `stock_movement_log`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_sml_reference`    ON `stock_movement_log`(`reference_type`, `reference_id`);
CREATE INDEX IF NOT EXISTS `idx_sml_moved_at`     ON `stock_movement_log`(`moved_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_sml_type`         ON `stock_movement_log`(`movement_type`);

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Phase 10 warehouse operations migration applied successfully.' AS status;
