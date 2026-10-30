-- =========================================================
-- Migration: Add user_id to customer_orders
-- =========================================================
-- Adds an authenticated user ID column to customer_orders.
-- When a customer places an order while authenticated,
-- their user_id (from users.id) is stored here.
-- The customer_session_id column is preserved for backward
-- compatibility with anonymous/session-based orders.
--
-- ORDER OWNERSHIP:
--   Primary: user_id     (authenticated — persistent across sessions)
--   Fallback: customer_session_id (session-based — same browser)
--
-- Both are populated for authenticated orders.
-- =========================================================

ALTER TABLE `customer_orders`
    ADD COLUMN IF NOT EXISTS `user_id` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Authenticated user ID (from users.id). NULL for anonymous/session orders.'
        AFTER `customer_session_id`,
    ADD INDEX IF NOT EXISTS `idx_co_user_id` (`user_id`);

-- Add FK separately (safe to skip if already exists)
ALTER TABLE `customer_orders`
    ADD CONSTRAINT IF NOT EXISTS `fk_co_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Verify
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'customer_orders'
  AND COLUMN_NAME  = 'user_id';
