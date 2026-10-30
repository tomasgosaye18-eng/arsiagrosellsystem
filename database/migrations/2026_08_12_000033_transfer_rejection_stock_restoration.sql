-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_12_000033_transfer_rejection_stock_restoration.sql
-- Purpose   : Transfer Rejection & Automatic Stock Restoration
--
-- Changes:
--   1. Add 'rejected' status to warehouse_transfers enum
--   2. Add 'reversal' movement type to stock_movement_log enum
--   3. Add rejection_reviewed_by + rejection_reviewed_at to warehouse_transfers
--      (rejection_reason column already exists from prior migration)
--
-- Safe to run multiple times (ALTER IGNORE / IF NOT EXISTS).
-- =========================================================

USE `aam_db`;

-- =========================================================
-- 1. ADD 'rejected' TO warehouse_transfers.status ENUM
--    Keep all existing values. Add 'rejected' as distinct
--    from 'cancelled' so we can tell the difference between
--    a GM-rejected transfer and a WM-cancelled one.
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
            'rejected',
            'cancelled'
        )
        NOT NULL DEFAULT 'pending';

-- =========================================================
-- 2. ADD REJECTION AUDIT COLUMNS TO warehouse_transfers
--    rejection_reason already exists on farm_dispatches.
--    Add it to warehouse_transfers + rejected_by/at.
-- =========================================================
ALTER TABLE `warehouse_transfers`
    ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT NULL
        COMMENT 'GM rejection reason — stored permanently for audit trail',
    ADD COLUMN IF NOT EXISTS `rejected_by`  INT UNSIGNED NULL
        COMMENT 'FK → users.id — GM who rejected this transfer',
    ADD COLUMN IF NOT EXISTS `rejected_at`  TIMESTAMP   NULL
        COMMENT 'Timestamp when GM rejected this transfer';

-- =========================================================
-- 3. ADD 'reversal' TO stock_movement_log.movement_type ENUM
--    Used to record stock restoration when a transfer is rejected.
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
        )
        NOT NULL;

-- =========================================================
-- 4. PERMISSIONS
-- =========================================================
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('transfer.reject', 'transfer', 'Reject Transfer', 'Can reject a warehouse transfer and trigger stock restoration', 1);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'general_manager'
  AND p.code = 'transfer.reject';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name IN ('administrator','admin')
  AND p.code = 'transfer.reject';

SELECT 'Transfer rejection & stock restoration migration applied.' AS status;
