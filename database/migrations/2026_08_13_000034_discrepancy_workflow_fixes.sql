-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_13_000034_discrepancy_workflow_fixes.sql
-- Purpose   : Patch any remaining schema gaps for the
--             unified receiving-discrepancy workflow that
--             covers BOTH Farm→Warehouse and Warehouse→Shop.
--
-- Changes:
--   1. Ensure warehouse_receiving has all required columns
--      for the gm_rejected resubmit workflow
--   2. Ensure warehouse_discrepancies has all GM columns
--      (idempotent — safe re-run)
--   3. Add 'resubmitted' and 'resubmitted_quantities' action
--      values to discrepancy_workflow_log if needed
--   4. Ensure stock_movement_log.movement_type has 'reversal'
--   5. Ensure warehouse_transfers has all rejection columns
--   6. Permissions for new resubmit capability
--   7. Index on warehouse_receiving.status for fast queue queries
-- =========================================================

USE `aam_db`;

-- =========================================================
-- 1. WAREHOUSE_RECEIVING — patch any missing columns
-- =========================================================
ALTER TABLE `warehouse_receiving`
    ADD COLUMN IF NOT EXISTS `notes` TEXT NULL
        COMMENT 'Optional WM receiving notes';

-- Ensure the status ENUM includes all required values
-- (safe re-run — MySQL preserves existing data)
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
            'rejected',
            'cancelled'
        ) NOT NULL DEFAULT 'pending';

-- =========================================================
-- 2. WAREHOUSE_DISCREPANCIES — ensure all GM columns exist
-- =========================================================
ALTER TABLE `warehouse_discrepancies`
    ADD COLUMN IF NOT EXISTS `gm_reviewed_by`    INT UNSIGNED  NULL,
    ADD COLUMN IF NOT EXISTS `gm_reviewed_at`    TIMESTAMP     NULL,
    ADD COLUMN IF NOT EXISTS `gm_action`         ENUM('approved','rejected') NULL,
    ADD COLUMN IF NOT EXISTS `gm_notes`          TEXT          NULL,
    ADD COLUMN IF NOT EXISTS `inventory_applied` TINYINT(1)    NOT NULL DEFAULT 0;

-- =========================================================
-- 3. DISCREPANCY_WORKFLOW_LOG — ensure transfer_id exists
--    and action column is wide enough
-- =========================================================
ALTER TABLE `discrepancy_workflow_log`
    ADD COLUMN IF NOT EXISTS `transfer_id` INT UNSIGNED NULL
        COMMENT 'FK → warehouse_transfers.id (Warehouse→Shop workflow)';

-- Widen the action column to accommodate all step names
ALTER TABLE `discrepancy_workflow_log`
    MODIFY COLUMN `action` VARCHAR(80) NOT NULL
        COMMENT 'submitted|gm_approved|gm_rejected|inventory_applied|farm_notified|resubmitted|resubmitted_quantities';

-- =========================================================
-- 4. STOCK_MOVEMENT_LOG — ensure 'reversal' type exists
-- =========================================================
ALTER TABLE `stock_movement_log`
    MODIFY COLUMN `movement_type`
        ENUM(
            'receiving',
            'transfer_out',
            'transfer_in',
            'adjustment',
            'discrepancy',
            'return',
            'initial',
            'sale',
            'count',
            'damage',
            'expiry',
            'reversal'
        ) NOT NULL;

-- =========================================================
-- 5. WAREHOUSE_TRANSFERS — ensure all columns exist
-- =========================================================
ALTER TABLE `warehouse_transfers`
    ADD COLUMN IF NOT EXISTS `rejection_reason`        TEXT          NULL,
    ADD COLUMN IF NOT EXISTS `rejected_by`             INT UNSIGNED  NULL,
    ADD COLUMN IF NOT EXISTS `rejected_at`             TIMESTAMP     NULL,
    ADD COLUMN IF NOT EXISTS `discrepancy_reviewed_by` INT UNSIGNED  NULL,
    ADD COLUMN IF NOT EXISTS `discrepancy_reviewed_at` TIMESTAMP     NULL,
    ADD COLUMN IF NOT EXISTS `discrepancy_gm_action`   ENUM('approved','rejected') NULL,
    ADD COLUMN IF NOT EXISTS `discrepancy_gm_notes`    TEXT          NULL;

-- Ensure the status ENUM covers all required values
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
            'rejected',
            'cancelled'
        ) NOT NULL DEFAULT 'pending';

-- =========================================================
-- 6. PERMISSIONS — resubmit receiving discrepancy
-- =========================================================
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
(
    'receiving.discrepancy.resubmit',
    'warehouse',
    'Resubmit Receiving Discrepancy',
    'WM can resubmit a receiving discrepancy after GM rejection',
    1
);

-- Grant to warehouse_manager
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'warehouse_manager'
  AND p.code = 'receiving.discrepancy.resubmit';

-- Grant to administrator
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name IN ('administrator', 'admin')
  AND p.code = 'receiving.discrepancy.resubmit';

-- =========================================================
-- 7. INDEXES for performance
-- =========================================================
CREATE INDEX IF NOT EXISTS `idx_wr_status`
    ON `warehouse_receiving`(`status`);

CREATE INDEX IF NOT EXISTS `idx_wt_status`
    ON `warehouse_transfers`(`status`);

CREATE INDEX IF NOT EXISTS `idx_wd_ref`
    ON `warehouse_discrepancies`(`reference_type`, `reference_id`);

CREATE INDEX IF NOT EXISTS `idx_wd_gm_action`
    ON `warehouse_discrepancies`(`gm_action`);

SELECT 'Migration 2026_08_13_000034 applied — discrepancy workflow fixes complete.' AS status;

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT
    CONCAT('warehouse_receiving.status  → ', COLUMN_TYPE) AS schema_check
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db'
  AND TABLE_NAME   = 'warehouse_receiving'
  AND COLUMN_NAME  = 'status'
UNION ALL
SELECT
    CONCAT('warehouse_transfers.status  → ', COLUMN_TYPE)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db'
  AND TABLE_NAME   = 'warehouse_transfers'
  AND COLUMN_NAME  = 'status'
UNION ALL
SELECT
    CONCAT('stock_movement_log.movement_type → ', COLUMN_TYPE)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db'
  AND TABLE_NAME   = 'stock_movement_log'
  AND COLUMN_NAME  = 'movement_type'
UNION ALL
SELECT
    CONCAT('discrepancy_workflow_log.action  → ', COLUMN_TYPE)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db'
  AND TABLE_NAME   = 'discrepancy_workflow_log'
  AND COLUMN_NAME  = 'action';
