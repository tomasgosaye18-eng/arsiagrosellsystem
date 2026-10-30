-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_25_000046_seed_distribution_module.sql
-- Purpose   : Seed Distribution Module
--             1. Add seed_distribution_enabled column to warehouses
--             2. Create seed_grades table (configurable per product)
--             3. Create seed_distributions table (distribution records)
--             4. Extend stock_movement_log movement_type ENUM
--             5. Seed current Wheat and Teff grades
-- Run after : 2026_08_24_000045_customer_orders_user_id.sql
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. ADD SEED DISTRIBUTION CAPABILITY TO WAREHOUSES
--    New column: seed_distribution_enabled TINYINT(1)
--    Default: 0 (OFF) — must be explicitly turned ON per warehouse
-- =========================================================
ALTER TABLE `warehouses`
    ADD COLUMN IF NOT EXISTS `seed_distribution_enabled`
        TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1=Seed Distribution enabled for this warehouse, 0=disabled'
        AFTER `is_active`;

CREATE INDEX IF NOT EXISTS `idx_wh_seed_dist`
    ON `warehouses` (`seed_distribution_enabled`, `is_active`);

-- =========================================================
-- 2. SEED GRADES / CLASSES TABLE
--    Configurable per product — extensible without code change.
--    A seed "grade" is simply a classification linked to a product.
--    Example: Product "Wheat Seed" → grades Pre-basic, Certified
-- =========================================================
CREATE TABLE IF NOT EXISTS `seed_grades` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `product_id`        INT UNSIGNED  NOT NULL COMMENT 'FK → products.id',
    `grade_name`        VARCHAR(100)  NOT NULL COMMENT 'e.g. Pre-basic, Certified, 15 KG Package',
    `grade_code`        VARCHAR(30)   NOT NULL COMMENT 'Short code, e.g. PRE_BASIC, CERTIFIED, PKG_15KG',
    `description`       VARCHAR(500)  NULL,
    `display_order`     TINYINT       NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1)    NOT NULL DEFAULT 1,
    `created_by`        INT UNSIGNED  NULL,
    `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sg_product_code` (`product_id`, `grade_code`),

    CONSTRAINT `fk_sg_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configurable seed grades/classes per product';

CREATE INDEX IF NOT EXISTS `idx_sg_product_id`  ON `seed_grades`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_sg_active`       ON `seed_grades`(`is_active`);

-- =========================================================
-- 3. SEED DISTRIBUTIONS TABLE
--    Core distribution record.
--    References existing warehouse_inventory for stock.
--    Uses existing approval pattern (status lifecycle).
--    Does NOT create a new approval system — reuses status names
--    consistent with existing transfer/adjustment workflow.
-- =========================================================
CREATE TABLE IF NOT EXISTS `seed_distributions` (
    `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `distribution_number`   VARCHAR(30)   NOT NULL UNIQUE
                                          COMMENT 'SEED-YYYY-NNNN',
    `warehouse_id`          INT UNSIGNED  NOT NULL,
    `product_id`            INT UNSIGNED  NOT NULL,
    `seed_grade_id`         INT UNSIGNED  NULL COMMENT 'FK → seed_grades.id (nullable for backward compat)',
    `unit_id`               INT UNSIGNED  NULL,
    `distribution_type`     ENUM('warehouse_pickup','field_distribution')
                            NOT NULL DEFAULT 'warehouse_pickup',
    `quantity`              DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    `status`                ENUM('pending','approved','released','completed','cancelled','rejected')
                            NOT NULL DEFAULT 'pending'
                            COMMENT 'pending=awaiting GM approval, approved=GM approved, released=seed handed out, completed=confirmed done, cancelled, rejected',

    -- Destination / Recipient Info (no system account needed)
    `destination_location`  VARCHAR(500)  NULL COMMENT 'Location/town/region where seed goes',
    `recipient_name`        VARCHAR(200)  NULL COMMENT 'Farmer or organization name',
    `recipient_phone`       VARCHAR(30)   NULL,
    `recipient_organization`VARCHAR(200)  NULL COMMENT 'Group/org if bulk distribution',
    `recipient_notes`       TEXT          NULL,

    -- Program / Purpose
    `program_name`          VARCHAR(200)  NULL COMMENT 'University program or project name',
    `distribution_date`     DATE          NOT NULL,
    `notes`                 TEXT          NULL,

    -- Workflow actors
    `created_by`            INT UNSIGNED  NULL COMMENT 'Warehouse user who created',
    `approved_by`           INT UNSIGNED  NULL COMMENT 'GM who approved',
    `approved_at`           TIMESTAMP     NULL,
    `released_by`           INT UNSIGNED  NULL COMMENT 'WM who released/dispatched seed',
    `released_at`           TIMESTAMP     NULL,
    `rejection_reason`      VARCHAR(500)  NULL,

    -- Inventory reference (set when stock is deducted)
    `stock_movement_id`     BIGINT UNSIGNED NULL COMMENT 'FK → stock_movement_log.id',
    `quantity_before`       DECIMAL(14,3)   NULL,
    `quantity_after`        DECIMAL(14,3)   NULL,

    `created_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_sd_warehouse`
        FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sd_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sd_grade`
        FOREIGN KEY (`seed_grade_id`) REFERENCES `seed_grades`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sd_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sd_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sd_released_by`
        FOREIGN KEY (`released_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Seed distribution records — one per distribution event';

CREATE INDEX IF NOT EXISTS `idx_sd_warehouse_id`  ON `seed_distributions`(`warehouse_id`);
CREATE INDEX IF NOT EXISTS `idx_sd_product_id`    ON `seed_distributions`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_sd_status`         ON `seed_distributions`(`status`);
CREATE INDEX IF NOT EXISTS `idx_sd_date`           ON `seed_distributions`(`distribution_date`);
CREATE INDEX IF NOT EXISTS `idx_sd_created_by`    ON `seed_distributions`(`created_by`);
CREATE INDEX IF NOT EXISTS `idx_sd_number`         ON `seed_distributions`(`distribution_number`);

-- =========================================================
-- 4. EXTEND stock_movement_log.movement_type ENUM
--    Add 'seed_distribution' type alongside existing types.
--    Safe: ALTER MODIFY keeps existing data intact.
-- =========================================================
ALTER TABLE `stock_movement_log`
    MODIFY COLUMN `movement_type`
        ENUM('receiving','transfer_out','transfer_in',
             'adjustment','discrepancy','return','initial',
             'reversal','seed_distribution')
        NOT NULL
        COMMENT 'Extended to include seed_distribution';

-- =========================================================
-- 5. SEED PRODUCT IDENTIFICATION — ADD is_seed FLAG TO products
--    This allows the system to identify seed products without
--    hard-coding product IDs. Simple boolean flag on products.
-- =========================================================
ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `is_seed`
        TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1=This is an improved/improved seed product for distribution'
        AFTER `is_perishable`;

CREATE INDEX IF NOT EXISTS `idx_p_is_seed`
    ON `products`(`is_seed`, `is_active`);

-- =========================================================
-- 6. SEED DATA: Wheat and Teff product seed grades
--    NOTE: We use product IDs by looking them up dynamically.
--    This INSERT uses SELECT to avoid hard-coded IDs.
--    Admins should have already created or will create
--    Wheat Seed and Teff Seed as products.
--    We insert sample grades — if the products don't exist yet,
--    these inserts will be skipped (FK constraint).
--
--    TO ACTIVATE: After creating "Wheat Seed" and "Teff Seed"
--    products in Admin → Products, run:
--      UPDATE products SET is_seed=1 WHERE name IN ('Wheat Seed','Teff Seed');
--    Then grades will auto-link via product_id.
-- =========================================================

-- No auto-seed data here — grades are inserted via the application
-- Admin panel or via direct SQL after products are created.
-- This keeps the migration clean and product-ID-independent.

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Seed Distribution module migration applied successfully.' AS status;
