-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_13_000035_final_rejection_workflow.sql
-- Purpose   : Implement two-rejection / final-rejection rule
--             for BOTH Farm→Warehouse (receiving discrepancy)
--             AND Warehouse→Shop (transfer discrepancy).
--
-- BUSINESS RULE:
--   - First GM rejection  → WM/SP may resubmit (rejection_count = 1)
--   - Second GM rejection → FINAL REJECTED (rejection_count = 2)
--     → No resubmit allowed
--     → Stock automatically restored to source
--     → Source and destination notified
--
-- Changes:
--   1. Add rejection_count, final_rejected, stock_restored
--      columns to warehouse_receiving (Farm→WH path)
--   2. Add discrepancy_rejection_count, discrepancy_final_rejected,
--      discrepancy_stock_restored columns to warehouse_transfers (WH→Shop path)
--   3. Extend warehouse_receiving.status ENUM with 'final_rejected'
--   4. Extend warehouse_transfers.status ENUM with 'final_rejected'
--   5. Extend farm_dispatches.status ENUM with 'final_rejected'
--   6. Add permissions for final rejection
--
-- Safe to run multiple times (IF NOT EXISTS / ALTER IGNORE).
-- =========================================================

USE `aam_db`;

-- =========================================================
-- 1. warehouse_receiving — rejection tracking columns
-- =========================================================
ALTER TABLE `warehouse_receiving`
    ADD COLUMN IF NOT EXISTS `rejection_count`   TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Number of times GM has rejected this receiving discrepancy (max 2)',
    ADD COLUMN IF NOT EXISTS `final_rejected`    TINYINT(1)       NOT NULL DEFAULT 0
        COMMENT '1 = final rejection applied (rejection_count reached 2)',
    ADD COLUMN IF NOT EXISTS `stock_restored`    TINYINT(1)       NOT NULL DEFAULT 0
        COMMENT '1 = source farm stock has been restored after final rejection';

-- =========================================================
-- 2. warehouse_receiving — extend status ENUM with final_rejected
-- =========================================================
ALTER TABLE `warehouse_receiving`
    MODIFY COLUMN `status`
        ENUM(
            'pending',
            'received',
            'partially_received',
            'discrepancy',
            'discrepancy_pending',
            'gm_approved',
            'gm_rejected',
            'final_rejected',
            'rejected',
            'cancelled'
        ) NOT NULL DEFAULT 'pending';

-- =========================================================
-- 3. warehouse_transfers — rejection tracking columns (WH→Shop)
-- =========================================================
ALTER TABLE `warehouse_transfers`
    ADD COLUMN IF NOT EXISTS `discrepancy_rejection_count`   TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Number of times GM rejected the receiving discrepancy (max 2)',
    ADD COLUMN IF NOT EXISTS `discrepancy_final_rejected`    TINYINT(1)       NOT NULL DEFAULT 0
        COMMENT '1 = final rejection applied on receiving discrepancy',
    ADD COLUMN IF NOT EXISTS `discrepancy_stock_restored`    TINYINT(1)       NOT NULL DEFAULT 0
        COMMENT '1 = warehouse stock has been restored after final discrepancy rejection';

-- =========================================================
-- 4. warehouse_transfers — extend status ENUM with final_rejected
-- =========================================================
ALTER TABLE `warehouse_transfers`
    MODIFY COLUMN `status`
        ENUM(
            'draft',
            'pending',
            'approved',
            'in_transit',
            'received',
            'partially_received',
            'discrepancy',
            'discrepancy_pending',
            'gm_approved',
            'gm_rejected',
            'received_with_discrepancy',
            'final_rejected',
            'rejected',
            'cancelled'
        ) NOT NULL DEFAULT 'pending';

-- =========================================================
-- 5. farm_dispatches — extend status ENUM with final_rejected
--    (Farm→WH path: dispatch is set to final_rejected when
--     the receiving is finally rejected and stock restored)
-- =========================================================
ALTER TABLE `farm_dispatches`
    MODIFY COLUMN `status`
        ENUM(
            'pending_approval',
            'approved',
            'rejected',
            'pending',
            'in_transit',
            'received',
            'partially_received',
            'received_with_discrepancy',
            'final_rejected',
            'cancelled'
        ) NOT NULL DEFAULT 'pending_approval';

-- =========================================================
-- 6. Permissions
-- =========================================================
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
(
    'receiving.discrepancy.final_reject',
    'warehouse',
    'Final Rejection — Receiving Discrepancy',
    'System-triggered: sets final_rejected after second GM rejection',
    1
);

-- Grant to general_manager and administrator
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name IN ('general_manager', 'administrator', 'admin')
  AND p.code = 'receiving.discrepancy.final_reject';

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT
    CONCAT('warehouse_receiving.status        = ', COLUMN_TYPE) AS schema_check
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db' AND TABLE_NAME = 'warehouse_receiving'   AND COLUMN_NAME = 'status'
UNION ALL
SELECT CONCAT('warehouse_receiving.rejection_count = ',  COLUMN_TYPE)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db' AND TABLE_NAME = 'warehouse_receiving'   AND COLUMN_NAME = 'rejection_count'
UNION ALL
SELECT CONCAT('warehouse_transfers.status      = ', COLUMN_TYPE)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db' AND TABLE_NAME = 'warehouse_transfers'   AND COLUMN_NAME = 'status'
UNION ALL
SELECT CONCAT('warehouse_transfers.discrepancy_rejection_count = ', COLUMN_TYPE)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db' AND TABLE_NAME = 'warehouse_transfers'   AND COLUMN_NAME = 'discrepancy_rejection_count'
UNION ALL
SELECT CONCAT('farm_dispatches.status          = ', COLUMN_TYPE)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db' AND TABLE_NAME = 'farm_dispatches'       AND COLUMN_NAME = 'status';

SELECT 'Migration 2026_08_13_000035 applied — final rejection workflow schema ready.' AS status;
