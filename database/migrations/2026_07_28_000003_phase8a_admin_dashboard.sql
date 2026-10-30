-- =============================================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_07_28_000003_phase8a_admin_dashboard.sql
-- Purpose   : Phase 8A enhancements — adds employee_id,
--             middle_name, gender, email to users table;
--             adds activity_logs table for recent activity
--             tracking on the admin dashboard.
-- Run after : 2026_07_28_000002_login_history_enhancements.sql
-- =============================================================================

USE aam_db;

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- 1. EXTEND users TABLE — Phase 8A new profile fields
-- =============================================================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS employee_id   VARCHAR(30)  NULL DEFAULT NULL
        COMMENT 'Institutional employee / staff ID'
        AFTER id;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS middle_name   VARCHAR(80)  NULL DEFAULT NULL
        COMMENT 'Optional middle name'
        AFTER first_name;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS gender
        ENUM('male','female','other','prefer_not_to_say')
        NULL DEFAULT NULL
        COMMENT 'User gender'
        AFTER last_name;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email         VARCHAR(191) NULL DEFAULT NULL
        COMMENT 'User email address (optional)'
        AFTER phone;

-- Unique constraint on email (allow NULLs)
CREATE UNIQUE INDEX IF NOT EXISTS uq_users_email        ON users (email);
CREATE UNIQUE INDEX IF NOT EXISTS uq_users_employee_id  ON users (employee_id);

-- =============================================================================
-- 2. activity_logs TABLE
-- Purpose: Powers the "Recent Activities" panel on the admin dashboard.
--          Tracks user-facing events: login, user creation, password reset,
--          profile update, etc.
-- =============================================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED    NULL      COMMENT 'Actor (NULL for system)',
    action       VARCHAR(80)     NOT NULL  COMMENT 'Machine-readable action code',
    description  VARCHAR(255)    NOT NULL  COMMENT 'Human-readable description',
    subject_type VARCHAR(80)     NULL      COMMENT 'Object type, e.g. users',
    subject_id   INT UNSIGNED    NULL      COMMENT 'Object primary key',
    ip_address   VARCHAR(45)     NULL,
    user_agent   VARCHAR(255)    NULL,
    created_at   TIMESTAMP       NOT NULL  DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_activity_user_id
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Application activity log for dashboard recent-activity panel';

CREATE INDEX idx_activity_user_id   ON activity_logs (user_id);
CREATE INDEX idx_activity_action    ON activity_logs (action);
CREATE INDEX idx_activity_created   ON activity_logs (created_at DESC);
CREATE INDEX idx_activity_subject   ON activity_logs (subject_type, subject_id);

SET FOREIGN_KEY_CHECKS = 1;
