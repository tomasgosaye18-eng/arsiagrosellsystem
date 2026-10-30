-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_07_000028_receiving_discrepancy_workflow.sql
-- Purpose   : Implement proper Farm→Warehouse Receiving
--             Discrepancy Workflow with GM approval gate.
--
-- WORKFLOW:
--   Farm dispatches 100 kg → Warehouse physically receives 50 kg
--   → WM submits discrepancy (reason required)
--   → Transfer status = 'discrepancy_pending'
--   → GM reviews and APPROVES or REJECTS
--   → If APPROVED: WM can complete receiving (50 kg added to inventory)
--   → If REJECTED: WM must review/correct
--   → After completion: Farm Manager notified
--
-- Changes:
--   1. Extend warehouse_receiving.status ENUM with new statuses
--   2. Extend warehouse_discrepancies with GM decision columns
--   3. Add discrepancy_workflow_log table for full audit trail
--
-- Safe to run multiple times (IF NOT EXISTS / ALTER IGNORE).
-- =========================================================

USE `aam_db`;

-- =========================================================
-- 1. EXTEND warehouse_receiving STATUS ENUM
--    Add: discrepancy_pending (submitted by WM, awaiting GM)
--         gm_approved (GM approved the discrepancy)
--         gm_rejected (GM rejected — WM must re-review)
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
            'rejected',
            'cancelled'
        )
        NOT NULL DEFAULT 'pending';

-- =========================================================
-- 2. EXTEND warehouse_discrepancies WITH GM DECISION COLUMNS
--    gm_reviewed_by    : FK → users.id (the GM who acted)
--    gm_reviewed_at    : TIMESTAMP of GM decision
--    gm_action         : 'approved' | 'rejected'
--    gm_notes          : GM review notes
--    inventory_applied : whether inventory has been updated
-- =========================================================
ALTER TABLE `warehouse_discrepancies`
    ADD COLUMN IF NOT EXISTS `gm_reviewed_by`    INT UNSIGNED  NULL
        COMMENT 'FK → users.id — GM who reviewed this discrepancy',
    ADD COLUMN IF NOT EXISTS `gm_reviewed_at`    TIMESTAMP     NULL
        COMMENT 'When the GM made their decision',
    ADD COLUMN IF NOT EXISTS `gm_action`         ENUM('approved','rejected') NULL
        COMMENT 'GM decision: approved (allow reduced inventory) or rejected (return for review)',
    ADD COLUMN IF NOT EXISTS `gm_notes`          TEXT          NULL
        COMMENT 'GM review notes or rejection reason',
    ADD COLUMN IF NOT EXISTS `inventory_applied` TINYINT(1)    NOT NULL DEFAULT 0
        COMMENT '1 = inventory has been updated with actual received qty';

-- Add FK for gm_reviewed_by
ALTER TABLE `warehouse_discrepancies`
    ADD CONSTRAINT IF NOT EXISTS `fk_wd_gm_reviewed_by`
        FOREIGN KEY (`gm_reviewed_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Index for pending GM review queries
CREATE INDEX IF NOT EXISTS `idx_wd_gm_action`   ON `warehouse_discrepancies`(`gm_action`);
CREATE INDEX IF NOT EXISTS `idx_wd_gm_reviewed` ON `warehouse_discrepancies`(`gm_reviewed_at`);

-- =========================================================
-- 3. DISCREPANCY WORKFLOW LOG
--    Immutable audit trail of every step in the discrepancy
--    lifecycle. Never updated or deleted.
-- =========================================================
CREATE TABLE IF NOT EXISTS `discrepancy_workflow_log` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `discrepancy_id` INT UNSIGNED    NOT NULL COMMENT 'FK → warehouse_discrepancies.id',
    `receiving_id`   INT UNSIGNED    NULL     COMMENT 'FK → warehouse_receiving.id',
    `action`         VARCHAR(60)     NOT NULL COMMENT 'submitted|gm_approved|gm_rejected|inventory_applied|farm_notified',
    `actor_user_id`  INT UNSIGNED    NULL     COMMENT 'FK → users.id',
    `notes`          TEXT            NULL,
    `snapshot`       JSON            NULL     COMMENT 'State snapshot at time of action',
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_dwl_discrepancy`
        FOREIGN KEY (`discrepancy_id`) REFERENCES `warehouse_discrepancies`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_dwl_actor`
        FOREIGN KEY (`actor_user_id`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable audit log for receiving discrepancy workflow steps';

CREATE INDEX IF NOT EXISTS `idx_dwl_discrepancy_id` ON `discrepancy_workflow_log`(`discrepancy_id`);
CREATE INDEX IF NOT EXISTS `idx_dwl_action`         ON `discrepancy_workflow_log`(`action`);
CREATE INDEX IF NOT EXISTS `idx_dwl_created`        ON `discrepancy_workflow_log`(`created_at` DESC);

-- =========================================================
-- 4. EXTEND NOTIFICATION TYPES
--    Ensure the GM receives a typed notification for pending
--    discrepancy reviews. No schema changes needed — just
--    documented here for reference.
--
--    Notification types used by this workflow:
--      'discrepancy_gm_review'     → sent to GM
--      'discrepancy_gm_approved'   → sent to WM
--      'discrepancy_gm_rejected'   → sent to WM
--      'receiving_completed'       → sent to Farm Manager
-- =========================================================

-- =========================================================
-- 5. PERMISSIONS — discrepancy workflow
-- =========================================================
INSERT IGNORE INTO `permissions` (`code`, `module`, `label`, `description`, `is_active`) VALUES
('receiving.discrepancy.submit',  'warehouse', 'Submit Receiving Discrepancy',   'Can submit a discrepancy on farm receiving',       1),
('receiving.discrepancy.complete','warehouse', 'Complete Receiving After Approval','Can complete receiving after GM approves discrepancy', 1),
('receiving.discrepancy.gm_review','warehouse','GM Review Discrepancy',          'Can review and approve/reject receiving discrepancies', 1);

-- Grant to warehouse_manager (role_id = 3)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions`
WHERE `code` IN ('receiving.discrepancy.submit', 'receiving.discrepancy.complete');

-- Grant to general_manager (role_id = 6)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions`
WHERE `code` IN ('receiving.discrepancy.gm_review');

-- Grant to administrator (role_id = 1)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`
WHERE `code` LIKE 'receiving.discrepancy.%';

SELECT 'Receiving discrepancy workflow migration applied successfully.' AS status;
