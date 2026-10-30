-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_11_000031_farm_product_assignments.sql
-- Purpose   : Creates the farm_products pivot table that
--             links which products belong to which farm.
--
--             This is the FARM PRODUCT ASSIGNMENT layer:
--               - System Administrator maintains the global
--                 products catalog (unchanged).
--               - A product must be explicitly assigned to
--                 a farm before Farm Manager can see it.
--               - Farm Manager sees ONLY products assigned
--                 to their farm(s).
--
-- Safe to run multiple times (IF NOT EXISTS / INSERT IGNORE).
-- =========================================================

USE `aam_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- TABLE: farm_products
-- Purpose: Many-to-many link between farms and products.
--          Controls which products a Farm Manager sees.
-- =========================================================
CREATE TABLE IF NOT EXISTS `farm_products` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `farm_id`    INT UNSIGNED  NOT NULL COMMENT 'References farms.id',
    `product_id` INT UNSIGNED  NOT NULL COMMENT 'References products.id',
    `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
    `assigned_by` INT UNSIGNED NULL COMMENT 'User who made the assignment',
    `assigned_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_farm_product` (`farm_id`, `product_id`),

    CONSTRAINT `fk_fp_farm_id`
        FOREIGN KEY (`farm_id`)    REFERENCES `farms`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_fp_product_id`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_fp_assigned_by`
        FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Farm-to-product assignment: controls product visibility per farm';

CREATE INDEX IF NOT EXISTS `idx_fp_farm_id`    ON `farm_products`(`farm_id`);
CREATE INDEX IF NOT EXISTS `idx_fp_product_id` ON `farm_products`(`product_id`);
CREATE INDEX IF NOT EXISTS `idx_fp_active`     ON `farm_products`(`farm_id`, `is_active`);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- TEST DATA — FINAL TEST SCENARIO
-- =========================================================
-- Creates two test farms (Milk Farm, Crop Farm) and their
-- products, then assigns those products to their respective
-- farms.  Safe to run multiple times (INSERT IGNORE).
-- =========================================================

-- ── 1. Ensure test farms exist ────────────────────────────
INSERT IGNORE INTO `farms`
    (`id`, `name`, `location`, `farm_type`, `is_active`, `created_at`)
VALUES
    (10, 'Milk Farm',  'Main Campus, Asella', 'Dairy',  1, NOW()),
    (11, 'Crop Farm',  'Main Campus, Asella', 'Crop',   1, NOW());

-- Update names in case rows already exist with different values
UPDATE `farms` SET `name` = 'Milk Farm', `farm_type` = 'Dairy',  `is_active` = 1 WHERE `id` = 10;
UPDATE `farms` SET `name` = 'Crop Farm', `farm_type` = 'Crop',   `is_active` = 1 WHERE `id` = 11;

-- Backfill farm_code if column exists and is NULL
UPDATE `farms` SET `farm_code` = CONCAT('FARM-', LPAD(`id`, 3, '0'))
WHERE (`farm_code` IS NULL OR `farm_code` = '') AND `id` IN (10, 11);

-- ── 2. Ensure test products exist ─────────────────────────
-- Milk Farm products
INSERT IGNORE INTO `products`
    (`id`, `name`, `sku`, `category_id`, `unit_id`,
     `unit_price`, `product_status`, `is_active`, `created_by`, `created_at`)
SELECT
    200, 'Milk', 'PRD-MILK-001',
    (SELECT id FROM categories WHERE name LIKE '%Animal%' LIMIT 1),
    (SELECT id FROM units_of_measure WHERE abbreviation = 'l' LIMIT 1),
    25.00, 'active', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'PRD-MILK-001');

INSERT IGNORE INTO `products`
    (`id`, `name`, `sku`, `category_id`, `unit_id`,
     `unit_price`, `product_status`, `is_active`, `created_by`, `created_at`)
SELECT
    201, 'Butter', 'PRD-BUTR-001',
    (SELECT id FROM categories WHERE name LIKE '%Animal%' LIMIT 1),
    (SELECT id FROM units_of_measure WHERE abbreviation = 'kg' LIMIT 1),
    80.00, 'active', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'PRD-BUTR-001');

INSERT IGNORE INTO `products`
    (`id`, `name`, `sku`, `category_id`, `unit_id`,
     `unit_price`, `product_status`, `is_active`, `created_by`, `created_at`)
SELECT
    202, 'Yogurt', 'PRD-YGRT-001',
    (SELECT id FROM categories WHERE name LIKE '%Animal%' LIMIT 1),
    (SELECT id FROM units_of_measure WHERE abbreviation = 'l' LIMIT 1),
    35.00, 'active', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'PRD-YGRT-001');

-- Crop Farm products (Teff / Wheat / Barley)
INSERT IGNORE INTO `products`
    (`id`, `name`, `sku`, `category_id`, `unit_id`,
     `unit_price`, `product_status`, `is_active`, `created_by`, `created_at`)
SELECT
    203, 'Teff', 'PRD-TEFF-001',
    (SELECT id FROM categories WHERE name LIKE '%Grain%' ORDER BY id LIMIT 1),
    (SELECT id FROM units_of_measure WHERE abbreviation = 'kg' LIMIT 1),
    65.00, 'active', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'PRD-TEFF-001');

INSERT IGNORE INTO `products`
    (`id`, `name`, `sku`, `category_id`, `unit_id`,
     `unit_price`, `product_status`, `is_active`, `created_by`, `created_at`)
SELECT
    204, 'Wheat', 'PRD-WHET-001',
    (SELECT id FROM categories WHERE name LIKE '%Grain%' ORDER BY id LIMIT 1),
    (SELECT id FROM units_of_measure WHERE abbreviation = 'kg' LIMIT 1),
    45.00, 'active', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'PRD-WHET-001');

INSERT IGNORE INTO `products`
    (`id`, `name`, `sku`, `category_id`, `unit_id`,
     `unit_price`, `product_status`, `is_active`, `created_by`, `created_at`)
SELECT
    205, 'Barley', 'PRD-BRLY-001',
    (SELECT id FROM categories WHERE name LIKE '%Grain%' ORDER BY id LIMIT 1),
    (SELECT id FROM units_of_measure WHERE abbreviation = 'kg' LIMIT 1),
    40.00, 'active', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'PRD-BRLY-001');

-- ── 3. Assign Milk Farm products ──────────────────────────
INSERT IGNORE INTO `farm_products` (`farm_id`, `product_id`, `is_active`, `assigned_by`)
SELECT 10, p.id, 1, 1 FROM `products` p WHERE p.sku IN ('PRD-MILK-001','PRD-BUTR-001','PRD-YGRT-001');

-- ── 4. Assign Crop Farm products ──────────────────────────
INSERT IGNORE INTO `farm_products` (`farm_id`, `product_id`, `is_active`, `assigned_by`)
SELECT 11, p.id, 1, 1 FROM `products` p WHERE p.sku IN ('PRD-TEFF-001','PRD-WHET-001','PRD-BRLY-001');

-- ── 5. Create test Farm Manager user (Manager A) ──────────
-- Password: FarmMgr@2026 (same bcrypt hash as farm_manager_01 seed)
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`, `employee_id`,
    `is_active`, `account_status`, `failed_login_attempts`,
    `is_locked`, `must_change_password`, `created_at`
) VALUES (
    200, 2,
    'manager_a',
    '$2y$12$uD3b1.p9jGpUKLwHm/sq0efwXE8gPCR7sLh7lEVvMEDfeU5JR4MCy',
    'Manager', 'A',
    'FM-TEST-A',
    1, 'active', 0, 0, 0, NOW()
);

UPDATE `users`
SET
    `role_id`               = 2,
    `password_hash`         = '$2y$12$uD3b1.p9jGpUKLwHm/sq0efwXE8gPCR7sLh7lEVvMEDfeU5JR4MCy',
    `first_name`            = 'Manager',
    `last_name`             = 'A',
    `employee_id`           = 'FM-TEST-A',
    `is_active`             = 1,
    `account_status`        = 'active',
    `failed_login_attempts` = 0,
    `is_locked`             = 0,
    `must_change_password`  = 0
WHERE `username` = 'manager_a';

-- ── 6. Assign Manager A to Milk Farm only (initial test) ──
-- Remove any existing assignment for manager_a
DELETE FROM `user_location_assignments`
WHERE `user_id` = (SELECT `id` FROM `users` WHERE `username` = 'manager_a' LIMIT 1);

DELETE FROM `organization_assignments`
WHERE `user_id` = (SELECT `id` FROM `users` WHERE `username` = 'manager_a' LIMIT 1);

-- Insert: Milk Farm only
INSERT INTO `user_location_assignments`
    (`user_id`, `location_type`, `location_id`, `assigned_at`, `assigned_by`)
SELECT u.id, 'farm', 10, NOW(), 1
FROM `users` u WHERE u.`username` = 'manager_a' LIMIT 1;

INSERT IGNORE INTO `organization_assignments`
    (`user_id`, `location_type`, `location_id`, `assignment_role`,
     `assigned_by`, `notes`, `is_active`, `assigned_at`)
SELECT u.id, 'farm', 10, 'farm_manager', 1,
    'Test seed: Manager A → Milk Farm only', 1, NOW()
FROM `users` u WHERE u.`username` = 'manager_a' LIMIT 1;

-- =========================================================
-- VERIFICATION QUERIES
-- =========================================================
SELECT
    CONCAT('Farm Products assigned → farm_id: ', fp.farm_id,
           ' | farm: ', f.name,
           ' | product: ', p.name,
           ' | active: ', fp.is_active) AS assignment_info
FROM `farm_products` fp
JOIN `farms`    f ON f.id = fp.farm_id
JOIN `products` p ON p.id = fp.product_id
ORDER BY fp.farm_id, p.name;

SELECT
    CONCAT('Manager A assigned to farm_id: ', ula.location_id,
           ' | farm: ', f.name) AS manager_assignment
FROM `user_location_assignments` ula
JOIN `users` u ON u.id = ula.user_id
JOIN `farms` f ON f.id = ula.location_id
WHERE u.`username` = 'manager_a' AND ula.`location_type` = 'farm';
