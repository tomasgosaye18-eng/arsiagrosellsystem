-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_01_000016_phase12_demo_users.sql
-- Purpose   : Phase 12 — Seed demo/test users for all roles.
--             All users are assigned to their correct
--             location (warehouse or shop).
--
-- DEMO CREDENTIALS SUMMARY
-- ──────────────────────────────────────────────────────────
-- Role               Username            Password
-- ─────────────────  ──────────────────  ─────────────────
-- administrator      admin               Admin@123
-- farm_manager       farm_manager_01     FarmMgr@2026
-- warehouse_manager  wh_manager_01       WHMgr@2026
-- storekeeper        storekeeper_01      StoreK@2026
-- salesperson        seller_01           Seller@2026
-- salesperson        seller_02           Seller@2026
-- general_manager    gm_01               GenMgr@2026
-- ──────────────────────────────────────────────────────────
-- Run after : 2026_08_01_000015_phase12_shop_enhancements.sql
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. WAREHOUSE MANAGER
--    Username : wh_manager_01
--    Password : WHMgr@2026
--    Assigned : Central Warehouse (id = 1)
-- =========================================================
-- Hash: bcrypt(cost=12) of 'WHMgr@2026'
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`, `employee_id`,
    `is_active`, `account_status`,
    `failed_login_attempts`, `is_locked`, `must_change_password`,
    `created_at`
) VALUES (
    10, 3, 'wh_manager_01',
    '$2y$12$ZNE8xjZ3QYgDqXo.4srFDumrh7W1TyEi6K9QjiF9p2P03pQs16jly',
    'Kebede', 'Alemu', 'EMP-010',
    1, 'active', 0, 0, 0, NOW()
);

-- Force correct password hash
UPDATE `users` SET
    `role_id`               = 3,
    `password_hash`         = '$2y$12$ZNE8xjZ3QYgDqXo.4srFDumrh7W1TyEi6K9QjiF9p2P03pQs16jly',
    `must_change_password`  = 0,
    `is_active`             = 1,
    `account_status`        = 'active',
    `failed_login_attempts` = 0,
    `is_locked`             = 0
WHERE `username` = 'wh_manager_01';

-- Assign to Central Warehouse (id = 1)
DELETE FROM `user_location_assignments`
WHERE `user_id` = (SELECT `id` FROM `users` WHERE `username` = 'wh_manager_01' LIMIT 1);

INSERT INTO `user_location_assignments` (`user_id`, `location_type`, `location_id`, `assigned_at`, `assigned_by`)
SELECT u.id, 'warehouse', 1, NOW(), 1
FROM `users` u WHERE u.`username` = 'wh_manager_01' LIMIT 1;

-- =========================================================
-- 2. STOREKEEPER
--    Username : storekeeper_01
--    Password : StoreK@2026
--    Assigned : Main Campus Shop (id = 1)
-- =========================================================
-- Hash: bcrypt(cost=12) of 'StoreK@2026'
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`, `employee_id`,
    `is_active`, `account_status`,
    `failed_login_attempts`, `is_locked`, `must_change_password`,
    `created_at`
) VALUES (
    11, 4, 'storekeeper_01',
    '$2y$12$mT1CM6ewQXMK8JZureArXe4g0K6nkCSs1AVO.vduAt4XrySIUZr0y',
    'Tigist', 'Bekele', 'EMP-011',
    1, 'active', 0, 0, 0, NOW()
);

UPDATE `users` SET
    `role_id`               = 4,
    `password_hash`         = '$2y$12$mT1CM6ewQXMK8JZureArXe4g0K6nkCSs1AVO.vduAt4XrySIUZr0y',
    `must_change_password`  = 0,
    `is_active`             = 1,
    `account_status`        = 'active',
    `failed_login_attempts` = 0,
    `is_locked`             = 0
WHERE `username` = 'storekeeper_01';

DELETE FROM `user_location_assignments`
WHERE `user_id` = (SELECT `id` FROM `users` WHERE `username` = 'storekeeper_01' LIMIT 1);

INSERT INTO `user_location_assignments` (`user_id`, `location_type`, `location_id`, `assigned_at`, `assigned_by`)
SELECT u.id, 'shop', 1, NOW(), 1
FROM `users` u WHERE u.`username` = 'storekeeper_01' LIMIT 1;

-- =========================================================
-- 3. SALESPERSON / SELLER — Shop A (Main Campus Shop, id=1)
--    Username : seller_01
--    Password : Seller@2026
-- =========================================================
-- Hash: bcrypt(cost=12) of 'Seller@2026'
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`, `employee_id`,
    `is_active`, `account_status`,
    `failed_login_attempts`, `is_locked`, `must_change_password`,
    `created_at`
) VALUES (
    12, 5, 'seller_01',
    '$2y$12$APcDCaGzSpMMd0SwBNjLm.50mLVPMRUALLXp7gQ64phWKBBl2R8t6',
    'Abebe', 'Girma', 'EMP-012',
    1, 'active', 0, 0, 0, NOW()
);

UPDATE `users` SET
    `role_id`               = 5,
    `password_hash`         = '$2y$12$APcDCaGzSpMMd0SwBNjLm.50mLVPMRUALLXp7gQ64phWKBBl2R8t6',
    `must_change_password`  = 0,
    `is_active`             = 1,
    `account_status`        = 'active',
    `failed_login_attempts` = 0,
    `is_locked`             = 0
WHERE `username` = 'seller_01';

DELETE FROM `user_location_assignments`
WHERE `user_id` = (SELECT `id` FROM `users` WHERE `username` = 'seller_01' LIMIT 1);

INSERT INTO `user_location_assignments` (`user_id`, `location_type`, `location_id`, `assigned_at`, `assigned_by`)
SELECT u.id, 'shop', 1, NOW(), 1
FROM `users` u WHERE u.`username` = 'seller_01' LIMIT 1;

-- =========================================================
-- 4. SALESPERSON / SELLER — Shop B (Health Campus Shop, id=2)
--    Username : seller_02
--    Password : Seller@2026
-- =========================================================
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`, `employee_id`,
    `is_active`, `account_status`,
    `failed_login_attempts`, `is_locked`, `must_change_password`,
    `created_at`
) VALUES (
    13, 5, 'seller_02',
    '$2y$12$APcDCaGzSpMMd0SwBNjLm.50mLVPMRUALLXp7gQ64phWKBBl2R8t6',
    'Meron', 'Tadesse', 'EMP-013',
    1, 'active', 0, 0, 0, NOW()
);

UPDATE `users` SET
    `role_id`               = 5,
    `password_hash`         = '$2y$12$APcDCaGzSpMMd0SwBNjLm.50mLVPMRUALLXp7gQ64phWKBBl2R8t6',
    `must_change_password`  = 0,
    `is_active`             = 1,
    `account_status`        = 'active',
    `failed_login_attempts` = 0,
    `is_locked`             = 0
WHERE `username` = 'seller_02';

DELETE FROM `user_location_assignments`
WHERE `user_id` = (SELECT `id` FROM `users` WHERE `username` = 'seller_02' LIMIT 1);

INSERT INTO `user_location_assignments` (`user_id`, `location_type`, `location_id`, `assigned_at`, `assigned_by`)
SELECT u.id, 'shop', 2, NOW(), 1
FROM `users` u WHERE u.`username` = 'seller_02' LIMIT 1;

-- =========================================================
-- 5. GENERAL MANAGER
--    Username : gm_01
--    Password : GenMgr@2026
-- =========================================================
-- Hash: bcrypt(cost=12) of 'GenMgr@2026'
INSERT IGNORE INTO `users` (
    `id`, `role_id`, `username`, `password_hash`,
    `first_name`, `last_name`, `employee_id`,
    `is_active`, `account_status`,
    `failed_login_attempts`, `is_locked`, `must_change_password`,
    `created_at`
) VALUES (
    14, 6, 'gm_01',
    '$2y$12$Tw9ISov/iQObzwMnEJJnxuXZfUQzmOJAX8qaHd4D40KEWH.rBI/cy',
    'Girma', 'Haile', 'EMP-014',
    1, 'active', 0, 0, 0, NOW()
);

UPDATE `users` SET
    `role_id`               = 6,
    `password_hash`         = '$2y$12$Tw9ISov/iQObzwMnEJJnxuXZfUQzmOJAX8qaHd4D40KEWH.rBI/cy',
    `must_change_password`  = 0,
    `is_active`             = 1,
    `account_status`        = 'active',
    `failed_login_attempts` = 0,
    `is_locked`             = 0
WHERE `username` = 'gm_01';

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- VERIFICATION — Print all demo user accounts
-- =========================================================
SELECT
    CONCAT(
        RPAD(r.display_name, 22, ' '), ' | ',
        RPAD(u.username, 18, ' '), ' | ',
        RPAD(u.account_status, 8, ' '), ' | ',
        'MustChange=', u.must_change_password
    ) AS account_summary
FROM `users` u
JOIN `roles` r ON r.id = u.role_id
WHERE u.username IN ('admin','farm_manager_01','wh_manager_01','storekeeper_01','seller_01','seller_02','gm_01')
ORDER BY u.role_id, u.username;

SELECT 'Phase 12 demo users seeded successfully.' AS status;
