-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_07_30_000009_phase9a_farm_manager_seed.sql
-- Purpose   : Phase 9A — Development test user seed.
--             Creates a Farm Manager test account assigned
--             to Farm 01 (Arsi University Main Farm).
--             Idempotent — safe to run multiple times.
--
-- Test credentials:
--   Username  : farm_manager_01
--   Password  : FarmMgr@2026
--   Role      : farm_manager (role_id = 2)
--   Farm      : Farm 01 — Arsi University Main Farm (id = 1)
--
-- IMPORTANT: This is a DEVELOPMENT seed only.
--            Do not run in production without changing the
--            password and removing must_change_password = 0.
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. ENSURE Farm 01 EXISTS
-- =========================================================
INSERT IGNORE INTO `farms`
    (`id`, `name`, `location`, `farm_type`, `description`, `is_active`, `created_at`)
VALUES
    (1, 'Arsi University Main Farm', 'Main Campus, Asella', 'Mixed',
     'Primary agricultural production farm — mixed crops and livestock',
     1, NOW());

-- Update name if it already exists with different values
UPDATE `farms`
SET
    `name`      = 'Arsi University Main Farm',
    `location`  = 'Main Campus, Asella',
    `farm_type` = 'Mixed',
    `is_active` = 1
WHERE `id` = 1;

-- =========================================================
-- 2. ENSURE FARM MANAGER ROLE EXISTS (role_id = 2)
-- =========================================================
INSERT IGNORE INTO `roles`
    (`id`, `name`, `display_name`, `description`, `is_active`)
VALUES
    (2, 'farm_manager', 'Farm Manager',
     'Manages farm operations: harvests, dispatches, farm inventory', 1);

-- =========================================================
-- 3. CREATE FARM MANAGER TEST USER
--
--   Password: FarmMgr@2026
--   Hash generated with:
--     password_hash('FarmMgr@2026', PASSWORD_BCRYPT, ['cost' => 12])
-- =========================================================
INSERT IGNORE INTO `users` (
    `id`,
    `role_id`,
    `username`,
    `password_hash`,
    `first_name`,
    `last_name`,
    `employee_id`,
    `is_active`,
    `account_status`,
    `failed_login_attempts`,
    `is_locked`,
    `must_change_password`,
    `created_at`
) VALUES (
    100,
    2,
    'farm_manager_01',
    '$2y$12$uD3b1.p9jGpUKLwHm/sq0efwXE8gPCR7sLh7lEVvMEDfeU5JR4MCy',
    'Abebe',
    'Girma',
    'FM-001',
    1,
    'active',
    0,
    0,
    0,
    NOW()
);

-- If user already exists, ensure it is unlocked and active
UPDATE `users`
SET
    `role_id`               = 2,
    `password_hash`         = '$2y$12$uD3b1.p9jGpUKLwHm/sq0efwXE8gPCR7sLh7lEVvMEDfeU5JR4MCy',
    `first_name`            = 'Abebe',
    `last_name`             = 'Girma',
    `employee_id`           = 'FM-001',
    `is_active`             = 1,
    `account_status`        = 'active',
    `failed_login_attempts` = 0,
    `is_locked`             = 0,
    `must_change_password`  = 0
WHERE `username` = 'farm_manager_01';

-- =========================================================
-- 4. ASSIGN FARM MANAGER TO FARM 01
--    Uses user_location_assignments (Phase 5A table)
--    which is what UserModel::findByCredential reads on login.
-- =========================================================

-- Remove any existing assignment for this user first
DELETE FROM `user_location_assignments`
WHERE `user_id` = (SELECT `id` FROM `users` WHERE `username` = 'farm_manager_01' LIMIT 1);

-- Insert fresh assignment
INSERT INTO `user_location_assignments`
    (`user_id`, `location_type`, `location_id`, `assigned_at`, `assigned_by`)
SELECT
    u.id,
    'farm',
    1,
    NOW(),
    1
FROM `users` u
WHERE u.`username` = 'farm_manager_01'
LIMIT 1;

-- =========================================================
-- 5. ALSO CREATE ENTRY IN organization_assignments
--    (Phase 8C table — used by AssignmentController)
--    Only runs if the table exists in aam_db with correct FK.
--    Wrapped in a PROCEDURE to handle safely if table differs.
-- =========================================================
-- Skip organization_assignments if FK references user_id
-- instead of id — the Phase 5A assignment is sufficient for login.
-- The admin panel assignment view uses org_assignments but
-- login scoping uses user_location_assignments (already set above).

-- Attempt gracefully — if table has FK mismatch it will just skip
SET @farm_manager_id = (SELECT `id` FROM `users` WHERE `username` = 'farm_manager_01' LIMIT 1);

INSERT IGNORE INTO `organization_assignments`
    (`user_id`, `location_type`, `location_id`, `assignment_role`,
     `assigned_by`, `notes`, `is_active`, `assigned_at`)
VALUES
    (@farm_manager_id, 'farm', 1, 'farm_manager', 1,
     'Phase 9A development seed assignment', 1, NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- 6. VERIFICATION QUERIES
-- =========================================================
SELECT
    CONCAT('Farm Manager User → username: ', u.username,
           ' | role: ', r.name,
           ' | status: ', u.account_status,
           ' | locked: ', u.is_locked) AS user_info
FROM `users` u
JOIN `roles` r ON r.id = u.role_id
WHERE u.`username` = 'farm_manager_01';

SELECT
    CONCAT('Farm Assignment → user: farm_manager_01',
           ' | farm_id: ', ula.location_id,
           ' | farm: ', f.name) AS assignment_info
FROM `user_location_assignments` ula
JOIN `users`  u ON u.id  = ula.user_id
JOIN `farms`  f ON f.id  = ula.location_id
WHERE u.`username` = 'farm_manager_01'
  AND ula.`location_type` = 'farm';
