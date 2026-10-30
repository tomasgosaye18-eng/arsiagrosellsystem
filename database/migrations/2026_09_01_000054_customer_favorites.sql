-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_09_01_000054_customer_favorites.sql
-- Purpose   : Customer Favorites / Wishlist — Phase 6
--
-- Design:
--   - customer_favorites links users ↔ products
--   - user_id references users.id (ROLE_CUSTOMER users only)
--   - product_id references products.id
--   - ON DELETE CASCADE: removing a user or product cleans
--     up favorites automatically
--   - Unique constraint prevents duplicate favorites
--   - Safe to re-run: CREATE TABLE IF NOT EXISTS
-- =========================================================

USE `aam_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. CUSTOMER FAVORITES TABLE
-- =========================================================
CREATE TABLE IF NOT EXISTS `customer_favorites` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    NOT NULL
                 COMMENT 'FK → users.id — the authenticated customer (ROLE_CUSTOMER)',
    `product_id` INT UNSIGNED    NOT NULL
                 COMMENT 'FK → products.id — the saved product',
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- One favorite per user per product — prevents duplicates
    UNIQUE KEY `uq_cf_user_product` (`user_id`, `product_id`),

    CONSTRAINT `fk_cf_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_cf_product`
        FOREIGN KEY (`product_id`)
        REFERENCES `products`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Customer saved/favorited products — Phase 6 Favorites feature';

CREATE INDEX IF NOT EXISTS `idx_cf_user_id`    ON `customer_favorites` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_cf_product_id` ON `customer_favorites` (`product_id`);
CREATE INDEX IF NOT EXISTS `idx_cf_created_at` ON `customer_favorites` (`created_at` DESC);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- VERIFICATION
-- =========================================================
SELECT 'Phase 6 customer_favorites migration applied.' AS status;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS result,
    'customer_favorites table' AS check_item
FROM information_schema.tables
WHERE table_schema = 'aam_db'
  AND table_name   = 'customer_favorites';
