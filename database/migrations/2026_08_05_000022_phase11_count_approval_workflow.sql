-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_05_000022_phase11_count_approval_workflow.sql
-- Purpose   : Add submitted_by / submitted_at columns to
--             stock_counts to support the GM approval workflow.
--             Warehouse Manager submits → GM approves/rejects.
--             Also add 'rejected' and 'pending_approval' to
--             the status ENUM if not already present.
-- Safe to re-run (uses IF NOT EXISTS / MODIFY IGNORE logic).
-- =========================================================

USE `aam_db`;

-- Add submitted_by column if it doesn't exist
ALTER TABLE `stock_counts`
    ADD COLUMN IF NOT EXISTS `submitted_by` INT NULL DEFAULT NULL
        COMMENT 'User ID of the Warehouse Manager who submitted for approval',
    ADD COLUMN IF NOT EXISTS `submitted_at` DATETIME NULL DEFAULT NULL
        COMMENT 'Timestamp when WM submitted count for GM approval';

-- Extend the status ENUM to include pending_approval and rejected
-- First check existing ENUM values and modify if needed.
-- Using MODIFY COLUMN to safely extend ENUM:
ALTER TABLE `stock_counts`
    MODIFY COLUMN `status` ENUM(
        'draft',
        'in_progress',
        'completed',
        'pending_approval',
        'approved',
        'rejected',
        'cancelled'
    ) NOT NULL DEFAULT 'in_progress'
    COMMENT 'draft|in_progress|completed|pending_approval|approved|rejected|cancelled';

-- Add FK for submitted_by (non-blocking — ignore if already exists)
ALTER TABLE `stock_counts`
    ADD CONSTRAINT IF NOT EXISTS `fk_sc_submitted_by`
    FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- =========================================================
-- VERIFICATION (comment out for production)
-- =========================================================
-- DESCRIBE stock_counts;
-- SELECT DISTINCT status FROM stock_counts;
