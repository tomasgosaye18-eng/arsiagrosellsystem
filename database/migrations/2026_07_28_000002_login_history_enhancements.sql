-- =============================================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_07_28_000002_login_history_enhancements.sql
-- Purpose   : Extends the login_history table with browser
--             detection columns, session tracking, and
--             logout recording — required by Phase 7
--             LoginHistoryModel and ProfileController.
-- Run after : aam_core_schema.sql AND
--             2026_07_28_000001_auth_enhancements.sql
-- =============================================================================

USE aam_db;

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- 1. CREATE login_history TABLE (if not already created in core schema)
-- =============================================================================
CREATE TABLE IF NOT EXISTS login_history (
    id                       INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id                  INT UNSIGNED     NULL DEFAULT NULL
        COMMENT 'NULL for failed attempts where user was not found',
    username_attempted       VARCHAR(80)      NOT NULL
        COMMENT 'The credential string submitted (username or phone)',
    was_successful           TINYINT(1)       NOT NULL DEFAULT 0
        COMMENT '1 = successful login, 0 = failed attempt',
    ip_address               VARCHAR(45)      NULL DEFAULT NULL
        COMMENT 'IPv4 or IPv6 address',
    user_agent               VARCHAR(255)     NULL DEFAULT NULL
        COMMENT 'Raw HTTP User-Agent string (truncated to 255)',
    failure_reason           VARCHAR(255)     NULL DEFAULT NULL
        COMMENT 'Human-readable reason for failure',
    session_id               VARCHAR(128)     NULL DEFAULT NULL
        COMMENT 'PHP session ID on successful login',
    browser                  VARCHAR(50)      NULL DEFAULT NULL
        COMMENT 'Detected browser: Chrome, Firefox, Edge, etc.',
    os_name                  VARCHAR(50)      NULL DEFAULT NULL
        COMMENT 'Detected OS: Windows, Linux, Android, etc.',
    device_type              VARCHAR(20)      NULL DEFAULT NULL
        COMMENT 'desktop | mobile | tablet',
    logout_at                TIMESTAMP        NULL DEFAULT NULL
        COMMENT 'When the session was terminated via logout',
    session_duration_seconds INT UNSIGNED     NULL DEFAULT NULL
        COMMENT 'Duration in seconds from login to logout',
    attempted_at             TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Exact timestamp of the login attempt',

    CONSTRAINT pk_login_history PRIMARY KEY (id),
    CONSTRAINT fk_lh_user_id
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Complete record of all login and logout events';

-- =============================================================================
-- 2. ADD COLUMNS to existing login_history (safe IF NOT EXISTS pattern)
-- =============================================================================

-- Browser detection column
ALTER TABLE login_history
    ADD COLUMN IF NOT EXISTS browser
        VARCHAR(50) NULL DEFAULT NULL
        COMMENT 'Detected browser name'
        AFTER session_id;

-- OS detection column
ALTER TABLE login_history
    ADD COLUMN IF NOT EXISTS os_name
        VARCHAR(50) NULL DEFAULT NULL
        COMMENT 'Detected operating system'
        AFTER browser;

-- Device type column
ALTER TABLE login_history
    ADD COLUMN IF NOT EXISTS device_type
        VARCHAR(20) NULL DEFAULT NULL
        COMMENT 'desktop | mobile | tablet'
        AFTER os_name;

-- Logout timestamp column
ALTER TABLE login_history
    ADD COLUMN IF NOT EXISTS logout_at
        TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Timestamp when session ended via logout'
        AFTER device_type;

-- Session duration column
ALTER TABLE login_history
    ADD COLUMN IF NOT EXISTS session_duration_seconds
        INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Session duration from login to logout in seconds'
        AFTER logout_at;

-- =============================================================================
-- 3. INDEXES for performance
-- =============================================================================
CREATE INDEX IF NOT EXISTS idx_lh_user_id      ON login_history (user_id);
CREATE INDEX IF NOT EXISTS idx_lh_attempted_at ON login_history (attempted_at);
CREATE INDEX IF NOT EXISTS idx_lh_ip_address   ON login_history (ip_address);
CREATE INDEX IF NOT EXISTS idx_lh_session_id   ON login_history (session_id);
CREATE INDEX IF NOT EXISTS idx_lh_successful   ON login_history (was_successful);

-- =============================================================================
-- 4. COMPOSITE INDEX for IP brute-force detection query
-- =============================================================================
CREATE INDEX IF NOT EXISTS idx_lh_ip_fail_time
    ON login_history (ip_address, was_successful, attempted_at);

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- VERIFICATION QUERY (run after migration to confirm columns exist):
-- DESCRIBE login_history;
-- =============================================================================
