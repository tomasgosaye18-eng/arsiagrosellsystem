<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Security/CSRF.php
 * Purpose  : CSRF token generation and verification.
 *            Generates cryptographically secure tokens.
 *            Tokens stored in session, verified on POST.
 *            Provides HTML hidden field helper.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Security;

use AAM\Core\SessionManager;
use AAM\Config\Application;

class CSRF
{
    /** Token length in bytes (32 bytes = 64 hex chars) */
    private const TOKEN_BYTES = 32;

    /**
     * Initialize — generate a token if one doesn't exist in session.
     */
    public static function initialize(): void
    {
        if (!SessionManager::has(SESSION_CSRF_TOKEN)) {
            self::regenerate();
        }
    }

    /**
     * Generate a new CSRF token and store it in the session.
     */
    public static function regenerate(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        SessionManager::set(SESSION_CSRF_TOKEN, $token);
        return $token;
    }

    /**
     * Get the current CSRF token from the session.
     * Generates one if it doesn't exist.
     */
    public static function getToken(): string
    {
        if (!SessionManager::has(SESSION_CSRF_TOKEN)) {
            return self::regenerate();
        }
        return (string) SessionManager::get(SESSION_CSRF_TOKEN);
    }

    /**
     * Verify a submitted CSRF token against the session token.
     * Uses constant-time comparison to prevent timing attacks.
     *
     * @param string $token The submitted token to verify
     */
    public static function verify(string $token): bool
    {
        $sessionToken = SessionManager::get(SESSION_CSRF_TOKEN, '');

        if (empty($token) || empty($sessionToken)) {
            return false;
        }

        return hash_equals((string) $sessionToken, $token);
    }

    /**
     * Render an HTML hidden input field with the CSRF token.
     * Usage in forms: <?= CSRF::field() ?>
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }

    /**
     * Get the meta tag HTML for AJAX requests.
     * Usage in <head>: <?= CSRF::meta() ?>
     */
    public static function meta(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<meta name="csrf-token" content="' . $token . '">';
    }
}
