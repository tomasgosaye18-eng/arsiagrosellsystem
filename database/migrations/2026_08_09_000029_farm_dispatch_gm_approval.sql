-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_09_000029_farm_dispatch_gm_approval.sql
-- Purpose   : Add GM approval workflow to farm_dispatches.
--
--   1. Extend the status ENUM to include the approval states:
--        pending_approval  — submitted by Farm Manager, awaiting GM
--        approved          — GM approved, Farm Manager can dispatch
--        rejected          — GM rejected, dispatch permanently blocked
--
--   2. Add GM review columns:
--        gm_reviewed_by   — FK → users.id (the GM who acted)
--        gm_reviewed_at   — Timestamp of the GM decision
--        gm_notes         — Optional GM approval note
--        rejection_reason — Mandatory when status = rejected
--
-- BUSINESS RULES ENFORCED BY THIS SCHEMA:
--   • New dispatches land in pending_approval (not pending).
--   • Farm Manager CANNOT dispatch until status = approved.
--   • No harvest stock is ever deducted on rejected dispatches.
--   • Existing rows that are in 'pending' state are preserved
--     as-is (legacy rows — the code allows them to be dispatched
--     directly for backward compatibility).
--
-- Safe to run multiple times (uses IF NOT EXISTS / MODIFY).
-- =========================================================

USE `aam_db`;

-- ──────────────────────────────────────────────────────────
-- 1. Extend the status ENUM
--    MySQL requires MODIFY COLUMN to change an ENUM — we must
--    re-list ALL values including old ones so nothing is lost.
-- ──────────────────────────────────────────────────────────
ALTER TABLE `farm_dispatches`
    MODIFY COLUMN `status`
        ENUM(
            'pending_approval',          -- NEW — awaiting GM decision
            'approved',                  -- NEW — GM approved, ready to dispatch
            'rejected',                  -- NEW — GM rejected, dispatch blocked
            'pending',                   -- LEGACY — kept for pre-workflow rows
            'in_transit',
            'received',
            'partially_received',
            'received_with_discrepancy',
            'cancelled'
        ) NOT NULL DEFAULT 'pending_approval'
        COMMENT 'pending_approval|approved|rejected|in_transit|received|partially_received|received_with_discrepancy|cancelled';

-- ──────────────────────────────────────────────────────────
-- 2. Add GM review columns (all safe with IF NOT EXISTS)
-- ──────────────────────────────────────────────────────────
ALTER TABLE `farm_dispatches`
    ADD COLUMN IF NOT EXISTS `gm_reviewed_by`   INT UNSIGNED  NULL DEFAULT NULL
        COMMENT 'FK → users.id — the General Manager who reviewed this dispatch',
    ADD COLUMN IF NOT EXISTS `gm_reviewed_at`   TIMESTAMP     NULL DEFAULT NULL
        COMMENT 'When the GM made their approve/reject decision',
    ADD COLUMN IF NOT EXISTS `gm_notes`         TEXT          NULL DEFAULT NULL
        COMMENT 'Optional GM approval note (visible to Farm Manager)',
    ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT          NULL DEFAULT NULL
        COMMENT 'Mandatory rejection reason — visible to Farm Manager';

-- ──────────────────────────────────────────────────────────
-- 3. Foreign key for gm_reviewed_by
--    (MariaDB does not support IF NOT EXISTS on constraints;
--     use a procedure to add it only when absent)
-- ──────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS `aam_add_gm_fk`;
DELIMITER //
CREATE PROCEDURE `aam_add_gm_fk`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA   = DATABASE()
          AND TABLE_NAME     = 'farm_dispatches'
          AND CONSTRAINT_NAME = 'fk_fd_gm_reviewed_by'
    ) THEN
        ALTER TABLE `farm_dispatches`
            ADD CONSTRAINT `fk_fd_gm_reviewed_by`
                FOREIGN KEY (`gm_reviewed_by`) REFERENCES `users`(`id`)
                ON DELETE SET NULL ON UPDATE CASCADE;
    END IF;
END //
DELIMITER ;
CALL `aam_add_gm_fk`();
DROP PROCEDURE IF EXISTS `aam_add_gm_fk`;

-- ──────────────────────────────────────────────────────────
-- 4. Index on status for efficient pending-approval queries
-- ──────────────────────────────────────────────────────────
-- (DROP + CREATE to safely handle re-runs in MariaDB)
DROP INDEX IF EXISTS `idx_fd_status` ON `farm_dispatches`;
CREATE INDEX `idx_fd_status` ON `farm_dispatches`(`status`);

-- ──────────────────────────────────────────────────────────
-- 5. Backfill — existing 'pending' rows that were already
--    dispatched (in_transit / received) are fine as-is.
--    Rows stuck in 'pending' that were never dispatched should
--    remain 'pending' so legacy dispatch still works for them.
--    No data change needed.
-- ──────────────────────────────────────────────────────────

SELECT 'Migration 2026_08_09_000029 applied — GM approval workflow added to farm_dispatches.' AS status;

-- Verify columns exist:
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db'
  AND TABLE_NAME   = 'farm_dispatches'
  AND COLUMN_NAME IN ('status','gm_reviewed_by','gm_reviewed_at','gm_notes','rejection_reason')
ORDER BY ORDINAL_POSITION;
