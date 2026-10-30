-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_10_07_000056_dispatch_notifications.sql
-- Purpose   : Add dispatch notification types so the system can
--             automatically notify:
--               • General Manager when a Farm Manager submits a
--                 dispatch request (dispatch_pending)
--               • Farm Manager when GM approves (dispatch_approved)
--               • Farm Manager when GM rejects (dispatch_rejected)
--
--   1. Extend notifications.notification_type ENUM to include:
--        dispatch_pending   — new dispatch awaiting GM approval
--        dispatch_approved  — GM approved a dispatch
--        dispatch_rejected  — GM rejected a dispatch
--
--   2. Insert matching rows into notification_types catalog
--      (INSERT IGNORE — safe to re-run).
--
-- Safe to run multiple times.
-- Run after: 2026_09_01_000055_seed_distribution_price_receipt.sql
-- =========================================================

USE `aam_db`;

-- ──────────────────────────────────────────────────────────
-- 1. Extend notifications.notification_type ENUM
--    MySQL/MariaDB requires re-listing ALL values.
--    We add the three dispatch types to the existing ENUM.
-- ──────────────────────────────────────────────────────────
ALTER TABLE `notifications`
    MODIFY COLUMN `notification_type` VARCHAR(80) NOT NULL
        COMMENT 'Notification category — matches notification_types.type_code';

-- ──────────────────────────────────────────────────────────
-- 2. Insert notification_types catalog rows (if table exists)
-- ──────────────────────────────────────────────────────────
INSERT IGNORE INTO `notification_types`
    (type_code, display_name, description, priority, icon_class, color_class, target_roles)
VALUES
    ('dispatch_pending',
     'Dispatch Pending Approval',
     'Notify General Manager when Farm Manager submits a dispatch request',
     'high',
     'bi-hourglass-split',
     'text-warning',
     '["general_manager"]'),

    ('dispatch_approved',
     'Dispatch Approved',
     'Notify Farm Manager when General Manager approves their dispatch request',
     'high',
     'bi-check-circle-fill',
     'text-success',
     '["farm_manager"]'),

    ('dispatch_rejected',
     'Dispatch Rejected',
     'Notify Farm Manager when General Manager rejects their dispatch request',
     'high',
     'bi-x-circle-fill',
     'text-danger',
     '["farm_manager"]');

SELECT 'Migration 2026_10_07_000056 applied — dispatch notification types added.' AS status;
