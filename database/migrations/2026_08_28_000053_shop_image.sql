-- =========================================================
-- AAM Migration: 2026_08_28_000053_shop_image.sql
-- Purpose  : Add shop_image column to the shops table.
--            Stores the generated filename of the uploaded
--            shop image (matches the UploadHelper convention
--            for products and profiles).
--            NULL = no image → customer side shows default
--            placeholder.
-- Safe     : Uses ADD COLUMN IF NOT EXISTS — re-runnable.
-- =========================================================

USE `aam_db`;

ALTER TABLE `shops`
    ADD COLUMN IF NOT EXISTS `shop_image` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Stored filename of the shop image (public/uploads/shops/). NULL = no image.';

CREATE INDEX IF NOT EXISTS `idx_shops_image` ON `shops`(`shop_image`(10));

SELECT 'Shop image column added.' AS status;
