-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_05_000024_remove_storekeeper_role.sql
-- Purpose   : Permanently remove the Storekeeper role from
--             the database.
--
-- SAFE:
--   - Does NOT delete users with other roles
--   - Does NOT drop shared tables
--   - Does NOT delete warehouse/inventory/transfer data
--   - Does NOT delete permissions used by other roles
--
-- Run AFTER deploying the application code changes.
-- Run:  mysql -u root aam_db < 2026_08_05_000024_remove_storekeeper_role.sql
-- =========================================================

USE `aam_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. RE-ASSIGN any existing storekeeper users to salesperson
--    (prevents orphaned users; adjust to preferred role if needed)
-- =========================================================
UPDATE `users`
SET `role_id` = (SELECT `id` FROM `roles` WHERE `name` = 'salesperson' LIMIT 1)
WHERE `role_id` = (SELECT `id` FROM `roles` WHERE `name` = 'storekeeper' LIMIT 1);

-- =========================================================
-- 2. Remove storekeeper role_permissions entries
-- =========================================================
DELETE `rp` FROM `role_permissions` `rp`
JOIN `roles` `r` ON `r`.`id` = `rp`.`role_id`
WHERE `r`.`name` = 'storekeeper';

-- =========================================================
-- 3. Remove storekeeper from roles table
-- =========================================================
DELETE FROM `roles` WHERE `name` = 'storekeeper';

-- =========================================================
-- 4. Remove demo storekeeper user (if still present)
-- =========================================================
DELETE FROM `user_location_assignments`
WHERE `user_id` IN (
    SELECT `id` FROM `users` WHERE `username` = 'storekeeper_01'
);
DELETE FROM `users` WHERE `username` = 'storekeeper_01';

-- =========================================================
-- 5. Update notification_types that reference storekeeper
--    (remove storekeeper from recipient_roles JSON/CSV fields)
-- =========================================================
UPDATE `system_settings`
SET `setting_value` = REPLACE(`setting_value`, ',storekeeper', '')
WHERE `setting_value` LIKE '%storekeeper%';

UPDATE `system_settings`
SET `setting_value` = REPLACE(`setting_value`, 'storekeeper,', '')
WHERE `setting_value` LIKE '%storekeeper%';

UPDATE `system_settings`
SET `setting_value` = REPLACE(`setting_value`, 'storekeeper', '')
WHERE `setting_value` LIKE '%storekeeper%';

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'SUCCESS — Storekeeper role removed.'
        ELSE CONCAT('WARNING — ', COUNT(*), ' storekeeper record(s) still exist.')
    END AS result
FROM `roles` WHERE `name` = 'storekeeper';

SELECT
    CONCAT('Remaining users with storekeeper role_id: ',
        COALESCE((
            SELECT COUNT(*) FROM `users` u
            JOIN `roles` r ON r.id = u.role_id
            WHERE r.name = 'storekeeper'
        ), 0)
    ) AS user_check;
