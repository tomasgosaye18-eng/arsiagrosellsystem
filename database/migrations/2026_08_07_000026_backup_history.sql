-- =========================================================
-- Migration: Create backup_history table
-- File: 2026_08_07_000026_backup_history.sql
-- =========================================================
USE `aam_db`;

CREATE TABLE IF NOT EXISTS `backup_history` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `filename`         VARCHAR(255)  NOT NULL,
  `file_path`        VARCHAR(500)  NOT NULL,
  `file_size_bytes`  BIGINT UNSIGNED DEFAULT 0,
  `backup_type`      ENUM('manual','scheduled','pre_restore') DEFAULT 'manual',
  `status`           ENUM('pending','running','completed','failed','verified','deleted') DEFAULT 'pending',
  `tables_count`     INT UNSIGNED DEFAULT 0,
  `rows_count`       BIGINT UNSIGNED DEFAULT 0,
  `checksum`         VARCHAR(64)   NULL,
  `error_message`    TEXT          NULL,
  `notes`            TEXT          NULL,
  `created_by`       INT UNSIGNED  NULL,
  `completed_at`     TIMESTAMP     NULL,
  `verified_at`      TIMESTAMP     NULL,
  `deleted_at`       TIMESTAMP     NULL,
  `created_at`       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_bh_status`  (`status`),
  INDEX `idx_bh_type`    (`backup_type`),
  INDEX `idx_bh_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
