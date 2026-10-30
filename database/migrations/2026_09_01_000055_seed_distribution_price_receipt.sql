-- =========================================================
-- AAM — Seed Distribution: Price Snapshot + Receipt
-- Date    : 2026-09-01
-- Purpose : Add unit_price and total_amount columns to
--           seed_distributions table.
--           Price snapshot: the unit price at the time of
--           distribution is stored permanently so that
--           historical receipts are never affected by
--           later GM price changes.
-- =========================================================

USE `aam_db`;

-- Add unit_price (snapshot of GM-configured price at distribution time)
ALTER TABLE `seed_distributions`
    ADD COLUMN IF NOT EXISTS `unit_price`
        DECIMAL(12,2) NULL DEFAULT NULL
        COMMENT 'Unit price snapshot from products.unit_price at time of distribution'
        AFTER `quantity`;

-- Add total_amount (unit_price × quantity, stored for immutability)
ALTER TABLE `seed_distributions`
    ADD COLUMN IF NOT EXISTS `total_amount`
        DECIMAL(15,2) NULL DEFAULT NULL
        COMMENT 'Computed: quantity × unit_price — stored at distribution time'
        AFTER `unit_price`;

-- Index for financial reporting queries
CREATE INDEX IF NOT EXISTS `idx_sd_total_amount`
    ON `seed_distributions` (`total_amount`);

-- Back-fill existing rows: compute from products.unit_price if possible
-- (These rows were created before pricing was enforced — set what we can)
UPDATE `seed_distributions` sd
JOIN `products` p ON p.id = sd.product_id
SET sd.unit_price   = p.unit_price,
    sd.total_amount = ROUND(sd.quantity * p.unit_price, 2)
WHERE sd.unit_price IS NULL
  AND p.unit_price  IS NOT NULL
  AND p.unit_price  > 0;

SELECT 'Seed Distribution price/receipt migration applied.' AS status;
