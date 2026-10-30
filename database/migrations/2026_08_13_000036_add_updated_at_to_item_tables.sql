-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_13_000036_add_updated_at_to_item_tables.sql
-- Purpose   : Add missing updated_at column to line-item and
--             workflow tables that the resubmit discrepancy
--             service writes to.
--
-- Affected tables:
--   1. warehouse_receiving_items   — UPDATE in resubmitReceivingDiscrepancy()
--   2. warehouse_transfer_items    — UPDATE in confirmTransferReceived() resubmit
--
-- Safe to run multiple times (ALTER IGNORE / IF NOT EXISTS).
-- =========================================================

USE `aam_db`;

-- ── 1. warehouse_receiving_items ──────────────────────────────────────────
ALTER TABLE `warehouse_receiving_items`
    ADD COLUMN IF NOT EXISTS `updated_at`
        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP
        COMMENT 'Last modification timestamp';

-- ── 2. warehouse_transfer_items ───────────────────────────────────────────
ALTER TABLE `warehouse_transfer_items`
    ADD COLUMN IF NOT EXISTS `updated_at`
        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP
        COMMENT 'Last modification timestamp';

-- ── VERIFICATION ──────────────────────────────────────────────────────────
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'aam_db'
  AND TABLE_NAME   IN ('warehouse_receiving_items', 'warehouse_transfer_items')
  AND COLUMN_NAME  = 'updated_at'
ORDER BY TABLE_NAME;

SELECT 'Migration 2026_08_13_000036 applied — updated_at added to item tables.' AS status;
