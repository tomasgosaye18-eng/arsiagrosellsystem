-- =========================================================
-- Migration: 2026_08_10_000030_notification_send_feature.sql
-- Purpose  : Extend the existing notifications table with a
--            priority column and a related_url column.
--            Adds no new tables — reuses the existing schema.
-- =========================================================

-- Add priority column (low / medium / high / critical)
ALTER TABLE `notifications`
    ADD COLUMN IF NOT EXISTS `priority` ENUM('low','medium','high','critical')
        NOT NULL DEFAULT 'medium'
        AFTER `message`;

-- Add related_url for deep-linking from a notification click
ALTER TABLE `notifications`
    ADD COLUMN IF NOT EXISTS `related_url` VARCHAR(500) NULL
        AFTER `reference_id`;

-- Index to help queries that filter by sender (GM / Admin sent items)
CREATE INDEX IF NOT EXISTS `idx_notif_sender`
    ON `notifications` (`sender_user_id`);
