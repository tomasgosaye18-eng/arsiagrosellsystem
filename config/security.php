<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : config/security.php
 * Purpose  : Centralized, hard-coded security configuration.
 *            These values are NOT stored in the database and
 *            cannot be changed through the UI. Edit this file
 *            directly when security policy needs to change.
 *
 * PRODUCTION CHECKLIST:
 *   - Set SECURE_COOKIES to true when deploying behind HTTPS.
 *   - Review MAX_FAILED_LOGIN_ATTEMPTS and LOCKOUT_DURATION_MINUTES
 *     against your institution's password policy.
 * =========================================================
 */

declare(strict_types=1);

// ─── Login & Account Protection ──────────────────────────────────────────────

/**
 * Number of consecutive failed login attempts allowed before the
 * account is locked. The lockout is lifted by an administrator
 * or automatically after LOCKOUT_DURATION_MINUTES.
 */
define('MAX_FAILED_LOGIN_ATTEMPTS', 5);

/**
 * How long (in minutes) a locked account remains locked before it
 * is automatically unlocked. Set to 0 to require admin intervention.
 */
define('LOCKOUT_DURATION_MINUTES', 15);

// ─── Password Policy ─────────────────────────────────────────────────────────

/**
 * Minimum number of characters required in any user password.
 * Must be 6 or greater.
 */
define('MIN_PASSWORD_LENGTH', 8);

/**
 * Require at least one uppercase letter (A–Z) in passwords.
 */
define('REQUIRE_UPPERCASE', true);

/**
 * Require at least one digit (0–9) in passwords.
 */
define('REQUIRE_NUMBER', true);

/**
 * Require at least one special character (!@#$...) in passwords.
 * Currently disabled to match legacy passwords already in the system.
 */
define('REQUIRE_SPECIAL_CHAR', false);

// ─── Protection Features ──────────────────────────────────────────────────────

/**
 * CSRF protection is always enabled. This constant exists to make the
 * policy explicit and to allow conditional checks in tests / tooling.
 * Do NOT set this to false in any environment.
 */
define('CSRF_PROTECTION', true);

/**
 * Restrict session cookies to HTTPS connections only.
 * Keep false for local HTTP development (XAMPP).
 * MUST be set to true before deploying to a production HTTPS host.
 */
define('SECURE_COOKIES', false);

// ─── Bcrypt Settings ──────────────────────────────────────────────────────────

/**
 * Bcrypt cost factor for password hashing.
 * Higher = stronger but slower. 12 is a safe default as of 2026.
 * Increase to 13–14 on production hardware if response time allows.
 */
define('BCRYPT_COST', 12);
