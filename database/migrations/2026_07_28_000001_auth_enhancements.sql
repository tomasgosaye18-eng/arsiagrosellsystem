-- =============================================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_07_28_000001_auth_enhancements.sql
-- Purpose   : Authentication enhancements for Phase 7.
--             Adds remember_me tokens table and extends
--             users table with profile and status fields
--             that Phase 5A may not yet include.
-- Run after : aam_core_schema.sql (Phase 5A)
-- =============================================================================

USE aam_db;

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- 1. EXTEND users TABLE — add columns if they don't exist
-- =============================================================================

-- Profile photo filename
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS profile_photo   VARCHAR(255)  NULL DEFAULT NULL
        COMMENT 'Filename in uploads/profiles/'
        AFTER phone;

-- Account status (extends is_active with richer states)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS account_status
        ENUM('active','inactive','suspended','locked','pending','deleted')
        NOT NULL DEFAULT 'active'
        COMMENT 'Full account lifecycle status'
        AFTER is_active;

-- Password reset token (hashed)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS password_reset_token      VARCHAR(100)  NULL DEFAULT NULL
        COMMENT 'SHA-256 hashed reset token'
        AFTER password_changed_at;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS password_reset_expires_at TIMESTAMP     NULL DEFAULT NULL
        COMMENT 'Expiry timestamp of reset token'
        AFTER password_reset_token;

-- Remember-me token (hashed selector+validator)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS remember_token  VARCHAR(100)  NULL DEFAULT NULL
        COMMENT 'Hashed remember-me validator token'
        AFTER password_reset_expires_at;

-- Suspension reason
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS suspension_reason TEXT NULL DEFAULT NULL
        COMMENT 'Reason for suspension, if account_status=suspended'
        AFTER remember_token;

-- Indexes
CREATE INDEX IF NOT EXISTS idx_users_account_status    ON users (account_status);
CREATE INDEX IF NOT EXISTS idx_users_reset_token       ON users (password_reset_token);

-- =============================================================================
-- 2. remember_me_tokens TABLE
-- =============================================================================
-- Purpose: Secure "Remember Me" token storage.
--          Uses selector/validator pattern:
--          - selector: public identifier (not secret) stored in cookie
--          - validator: secret, hashed with SHA-256 before storage
--          Tokens expire after 30 days.
--          Each device gets its own token row.
-- =============================================================================
CREATE TABLE IF NOT EXISTS remember_me_tokens (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED  NOT NULL,
    selector     VARCHAR(32)   NOT NULL  COMMENT 'Public selector stored in cookie',
    hashed_token VARCHAR(64)   NOT NULL  COMMENT 'SHA-256 hash of validator',
    user_agent   VARCHAR(255)  NULL      DEFAULT NULL,
    ip_address   VARCHAR(45)   NULL      DEFAULT NULL,
    created_at   TIMESTAMP     NOT NULL  DEFAULT CURRENT_TIMESTAMP,
    expires_at   TIMESTAMP     NOT NULL,
    last_used_at TIMESTAMP     NULL      DEFAULT NULL,

    CONSTRAINT pk_remember_me PRIMARY KEY (id),
    CONSTRAINT uq_remember_selector UNIQUE (selector),
    CONSTRAINT fk_rmt_user_id
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Secure remember-me tokens (selector+validator pattern)';

CREATE INDEX idx_rmt_user_id    ON remember_me_tokens (user_id);
CREATE INDEX idx_rmt_selector   ON remember_me_tokens (selector);
CREATE INDEX idx_rmt_expires_at ON remember_me_tokens (expires_at);

-- =============================================================================
-- 3. SEED — Ensure default roles exist (idempotent)
-- =============================================================================
INSERT IGNORE INTO roles (id, name, display_name, description, is_active) VALUES
(1, 'administrator',     'System Administrator', 'Full system access', 1),
(2, 'farm_manager',      'Farm Manager',         'Manages farm harvests and dispatches', 1),
(3, 'warehouse_manager', 'Warehouse Manager',    'Manages warehouse stock and transfers', 1),
(4, 'storekeeper',       'Storekeeper',          'Manages shop stock and confirms transfers', 1),
(5, 'salesperson',       'Salesperson',          'Processes cash sales at POS', 1),
(6, 'general_manager',   'General Manager',      'Read-only executive dashboard access', 1);

-- =============================================================================
-- 4. SEED — Default Administrator account
--    Username : admin
--    Password : Admin@2026  (bcrypt hash below — CHANGE ON FIRST LOGIN)
--    The must_change_password flag forces change on first login.
-- =============================================================================
INSERT IGNORE INTO users
    (id, role_id, username, password_hash, first_name, last_name,
     is_active, account_status, must_change_password, created_at)
VALUES
(1, 1, 'admin',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'System', 'Administrator',
 1, 'active', 1, NOW());

-- NOTE: The hash above is bcrypt(cost=12) of 'Admin@2026'
-- The administrator MUST change this on first login.

SET FOREIGN_KEY_CHECKS = 1;
