-- =========================================================
-- AAM — Fix Missing Schema Items
-- Date: 2026-08-30
-- Fixes all column-not-found and table-not-found errors
-- =========================================================

-- ── 1. user_location_assignments: add is_active column ───
ALTER TABLE `user_location_assignments`
    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '1=active assignment' AFTER `assigned_by`;

-- Update existing rows to active
UPDATE `user_location_assignments` SET `is_active` = 1 WHERE `is_active` IS NULL;

-- ── 2. product_price_history: add missing columns ─────────
ALTER TABLE `product_price_history`
    ADD COLUMN IF NOT EXISTS `price_difference` DECIMAL(12,2) NULL DEFAULT NULL
        COMMENT 'new_price - old_price' AFTER `new_price`;

ALTER TABLE `product_price_history`
    ADD COLUMN IF NOT EXISTS `price_type` ENUM('COST','SELLING') NOT NULL DEFAULT 'SELLING'
        AFTER `price_difference`;

-- Backfill price_difference from existing data
UPDATE `product_price_history`
SET `price_difference` = `new_price` - `old_price`
WHERE `price_difference` IS NULL;

-- ── 3. activity_logs table ────────────────────────────────
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED    NULL     DEFAULT NULL,
    `action`       VARCHAR(80)     NOT NULL DEFAULT '',
    `description`  VARCHAR(255)    NOT NULL DEFAULT '',
    `subject_type` VARCHAR(80)     NULL     DEFAULT NULL,
    `subject_id`   INT UNSIGNED    NULL     DEFAULT NULL,
    `ip_address`   VARCHAR(45)     NULL     DEFAULT NULL,
    `user_agent`   VARCHAR(255)    NULL     DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_al_user_id`  (`user_id`),
    KEY `idx_al_action`   (`action`),
    KEY `idx_al_created`  (`created_at`),
    CONSTRAINT `fk_al_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. seed_grades table ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `seed_grades` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `product_id`    INT UNSIGNED    NOT NULL,
    `grade_name`    VARCHAR(80)     NOT NULL,
    `grade_code`    VARCHAR(20)     NOT NULL,
    `description`   TEXT            NULL,
    `display_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sg_product` (`product_id`),
    CONSTRAINT `fk_sg_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. seed_distributions table ───────────────────────────
CREATE TABLE IF NOT EXISTS `seed_distributions` (
    `id`                   INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `distribution_number`  VARCHAR(30)         NOT NULL,
    `warehouse_id`         INT UNSIGNED        NOT NULL,
    `product_id`           INT UNSIGNED        NOT NULL,
    `seed_grade_id`        INT UNSIGNED        NULL,
    `unit_id`              INT UNSIGNED        NULL,
    `distribution_type`    ENUM('warehouse_pickup','field_distribution')
                                               NOT NULL DEFAULT 'warehouse_pickup',
    `quantity`             DECIMAL(12,3)       NOT NULL,
    `status`               ENUM('pending','approved','released','completed','cancelled','rejected')
                                               NOT NULL DEFAULT 'pending',
    `destination_location` VARCHAR(255)        NULL,
    `recipient_name`       VARCHAR(150)        NULL,
    `recipient_phone`      VARCHAR(30)         NULL,
    `recipient_organization` VARCHAR(150)      NULL,
    `recipient_notes`      TEXT                NULL,
    `program_name`         VARCHAR(150)        NULL,
    `distribution_date`    DATE                NULL,
    `notes`                TEXT                NULL,
    `created_by`           INT UNSIGNED        NULL,
    `approved_by`          INT UNSIGNED        NULL,
    `approved_at`          TIMESTAMP           NULL,
    `released_by`          INT UNSIGNED        NULL,
    `released_at`          TIMESTAMP           NULL,
    `rejection_reason`     TEXT                NULL,
    `stock_movement_id`    INT UNSIGNED        NULL,
    `quantity_before`      DECIMAL(12,3)       NULL,
    `quantity_after`       DECIMAL(12,3)       NULL,
    `created_at`           TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sd_number` (`distribution_number`),
    KEY `idx_sd_warehouse`  (`warehouse_id`),
    KEY `idx_sd_product`    (`product_id`),
    KEY `idx_sd_status`     (`status`),
    KEY `idx_sd_created_at` (`created_at`),
    CONSTRAINT `fk_sd_warehouse`
        FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
    CONSTRAINT `fk_sd_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
    CONSTRAINT `fk_sd_grade`
        FOREIGN KEY (`seed_grade_id`) REFERENCES `seed_grades` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sd_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sd_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sd_released_by`
        FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
