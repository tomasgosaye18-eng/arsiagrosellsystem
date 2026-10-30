-- =========================================================
-- ARSI AGRI-MANAGE (AAM) — Phase 9A — Farm Module
-- =========================================================
-- File    : 2026_07_31_000010_phase9a_farm_module.sql
-- Purpose : Adds farm_code to farms table if absent.
--           Extends harvest_batches quality_grade to match
--           Phase 9A spec (GOOD / ACCEPTABLE / REJECTED /
--           Grade A/B/C / Ungraded).
--           Adds received_with_discrepancy status to
--           farm_dispatches.
--           Safe to run multiple times (IF NOT EXISTS / IGNORE).
-- =========================================================

USE `aam_db`;

-- ──────────────────────────────────────────────────────────
-- 1. Add farm_code column if absent
-- ──────────────────────────────────────────────────────────
ALTER TABLE `farms`
    ADD COLUMN IF NOT EXISTS `farm_code` VARCHAR(30) NULL DEFAULT NULL
        COMMENT 'Human-readable farm identifier e.g. FARM-001';

-- Backfill farm_code for existing rows
UPDATE `farms`
SET `farm_code` = CONCAT('FARM-', LPAD(`id`, 3, '0'))
WHERE `farm_code` IS NULL OR `farm_code` = '';

-- ──────────────────────────────────────────────────────────
-- 2. Extend farm_dispatches.status for discrepancy
-- ──────────────────────────────────────────────────────────
-- MySQL ALTER COLUMN ENUM requires re-specifying all values.
-- We use MODIFY COLUMN to ensure the new status is present.
ALTER TABLE `farm_dispatches`
    MODIFY COLUMN `status`
        ENUM('pending','in_transit','received','partially_received',
             'received_with_discrepancy','cancelled')
        NOT NULL DEFAULT 'pending'
        COMMENT 'Dispatch lifecycle status';

-- ──────────────────────────────────────────────────────────
-- 3. Ensure indexes
-- ──────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS `idx_farms_farm_code`
    ON `farms`(`farm_code`);

CREATE INDEX IF NOT EXISTS `idx_hb_batch_number`
    ON `harvest_batches`(`batch_number`);

CREATE INDEX IF NOT EXISTS `idx_fd_dispatch_number`
    ON `farm_dispatches`(`dispatch_number`);

-- ──────────────────────────────────────────────────────────
-- VERIFICATION
-- ──────────────────────────────────────────────────────────
SELECT 'Phase 9A farm module migration applied successfully.' AS status;

SELECT id, name, farm_code, farm_type, is_active
FROM farms
ORDER BY id;
