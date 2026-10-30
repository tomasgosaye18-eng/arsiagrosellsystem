-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_12_000032_transfer_discrepancy_workflow.sql
-- Purpose   : Implement Warehouse→Shop Transfer Receiving
--             Discrepancy Workflow with GM approval gate.
--
-- WORKFLOW:
--   Warehouse dispatches 100 kg → Shop physically receives 70 kg
--   → Salesperson submits discrepancy (reason required)
--   → Transfer status = 'discrepancy_pending'
--   → GM reviews and APPROVES or REJECTS
--   → If APPROVED: inventory updated with ACTUAL received qty (70 kg)
--                  Transfer status → 'received_with_discrepancy'
--   → If REJECTED: Salesperson must review/correct and re-submit
--   → Notifications sent at each step
--
-- Reuses:
--   warehouse_discrepancies (reference_type = 'transfer')
--   discrepancy_workflow_log (same table, transfer context)
--
-- Adds:
--   1. New statuses to warehouse_transfers
--   2. transfer_discrepancy_workflow_log (transfer-scoped log)
--      OR reuses discrepancy_workflow_log with transfer_id column
--   3. Permissions for shop receiving discrepancy
--
-- Safe to run multiple times (IF NOT EXISTS / ALTER IGNORE).
-- =========================================================

USE `aam_db`;

-- =========================================================
-- 1. EXTEND warehouse_transfers STATUS ENUM
--    Add:
--      discrepancy_pending       — submitted by Salesperson, awaiting GM
--      gm_approved               — GM approved discrepancy
--      gm_rejected               — GM rejected, Salesperson must re-submit
--      received_with_discrepancy — final state after GM approved & inventory applied
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
            'cancelled'
        )
        NOT NULL DEFAULT 'pending';

-- =========================================================
-- 2. ADD GM DECISION COLUMNS TO warehouse_transfers
--    So the GM's name, timestamp, and notes are easy to retrieve
--    without joining warehouse_discrepancies on every query.
-- =========================================================
ALTER TABLE `warehouse_transfers`
    ADD COLUMN IF NOT EXISTS `discrepancy_reviewed_by` INT UNSIGNED NULL
        COMMENT 'FK → users.id — GM who reviewed the transfer discrepancy',
    ADD COLUMN IF NOT EXISTS `discrepancy_reviewed_at` TIMESTAMP   NULL
        COMMENT 'When GM made their decision on transfer discrepancy',
    ADD COLUMN IF NOT EXISTS `discrepancy_gm_action`   ENUM('approved','rejected') NULL
        COMMENT 'GM decision: approved or rejected',
    ADD COLUMN IF NOT EXISTS `discrepancy_gm_notes`    TEXT NULL
        COMMENT 'GM review notes or rejection reason';

-- FK for discrepancy_reviewed_by (added separately to avoid MariaDB IF NOT EXISTS FK limitation)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME        = 'warehouse_transfers'
      AND CONSTRAINT_NAME   = 'fk_wt_discrepancy_reviewed_by'
      AND CONSTRAINT_TYPE   = 'FOREIGN KEY'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `warehouse_transfers`
     ADD CONSTRAINT `fk_wt_discrepancy_reviewed_by`
     FOREIGN KEY (`discrepancy_reviewed_by`) REFERENCES `users`(`id`)
     ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "FK fk_wt_discrepancy_reviewed_by already exists" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 3. EXTEND discrepancy_workflow_log WITH transfer_id COLUMN
--    The existing table uses receiving_id.
--    We add transfer_id to cover Warehouse→Shop workflows.
--    Both columns are nullable — only the relevant one is set.
-- =========================================================
ALTER TABLE `discrepancy_workflow_log`
    ADD COLUMN IF NOT EXISTS `transfer_id` INT UNSIGNED NULL
        COMMENT 'FK → warehouse_transfers.id (for Warehouse→Shop discrepancy workflow)';

-- Index for transfer-scoped log queries
CREATE INDEX IF NOT EXISTS `idx_dwl_transfer_id`
    ON `discrepancy_workflow_log`(`transfer_id`);

-- =========================================================
-- 4. STATUS LABELS — StatusLabel() in TransferModel updated in PHP.
--    Documenting here for reference:
--      discrepancy_pending       → 'Pending GM Approval'
--      gm_approved               → 'GM Approved (Pending Completion)'
--      gm_rejected               → 'Rejected — Awaiting Correction'
--      received_with_discrepancy → 'Completed with Discrepancy'
-- =========================================================

-- =========================================================
-- 5. PERMISSIONS — transfer discrepancy workflow
-- =========================================================
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('transfer.discrepancy.submit',     'transfer', 'Submit Transfer Discrepancy',      'Can submit a receiving discrepancy on a warehouse→shop transfer',    1),
('transfer.discrepancy.resubmit',   'transfer', 'Resubmit Transfer Discrepancy',    'Can re-submit a discrepancy after GM rejection',                    1),
('transfer.discrepancy.gm_review',  'transfer', 'GM Review Transfer Discrepancy',   'Can review and approve/reject transfer receiving discrepancies',     1);

-- Grant to salesperson (role_id = 5 — check your roles table; use name match)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'salesperson'
  AND p.code IN ('transfer.discrepancy.submit', 'transfer.discrepancy.resubmit');

-- Grant to general_manager
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'general_manager'
  AND p.code = 'transfer.discrepancy.gm_review';

-- Grant to administrator (full access)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name IN ('administrator','admin')
  AND p.code LIKE 'transfer.discrepancy.%';

SELECT 'Transfer discrepancy workflow migration applied successfully.' AS status;
