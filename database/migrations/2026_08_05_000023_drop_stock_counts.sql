-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_05_000023_drop_stock_counts.sql
-- Purpose   : Permanently remove the Stock Count module.
--             Inventory discrepancies are now handled
--             exclusively through the Adjustments workflow.
--
-- ORDER MATTERS — drop child table before parent:
--   1. stock_count_items  (references stock_counts)
--   2. stock_counts
--
-- WARNING: This is irreversible. Back up before running.
-- Run:  mysql -u root aam_db < 2026_08_05_000023_drop_stock_counts.sql
-- =========================================================

USE `aam_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Drop child table first (foreign key: count_id → stock_counts.id)
DROP TABLE IF EXISTS `stock_count_items`;

-- 2. Drop parent table
DROP TABLE IF EXISTS `stock_counts`;

SET FOREIGN_KEY_CHECKS = 1;

-- Verify tables are gone
SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'SUCCESS — stock_counts and stock_count_items have been removed.'
        ELSE CONCAT('WARNING — ', COUNT(*), ' table(s) still exist.')
    END AS result
FROM information_schema.tables
WHERE table_schema = 'aam_db'
  AND table_name IN ('stock_counts', 'stock_count_items');
