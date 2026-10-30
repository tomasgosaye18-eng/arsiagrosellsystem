<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/SessionManager.php
 * Purpose  : Secure session lifecycle manager.
 *            Handles: start, read, write, delete, destroy.
 *            Session fixation protection, fingerprinting,
 *            idle timeout enforcement, regeneration.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

use AAM\Config\Application;

class SessionManager
{
    /** @var bool Whether session has been started */
    private static bool $started = false;

    /**
     * Start the session with secure configuration.
     * Performs session hijacking protection on every request.
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $config = Application::get('session', []);

        $sessionName    = $config['name']     ?? 'aam_session';
        $lifetime       = $config['lifetime'] ?? 1800;
        $secure         = $config['secure']   ?? false;
        $httponly       = $config['httponly']  ?? true;
        $samesite       = $config['samesite']  ?? 'Strict';

        // Configure session before starting
        ini_set('session.name',             $sessionName);
        ini_set('session.gc_maxlifetime',   (string) $lifetime);
        ini_set('session.cookie_lifetime',  '0');     // Browser session cookie
        ini_set('session.cookie_httponly',  $httponly ? '1' : '0');
        ini_set('session.cookie_secure',    $secure   ? '1' : '0');
        ini_set('session.cookie_samesite',  $samesite);
        ini_set('session.use_strict_mode',  '1');     // Reject uninitialized session IDs
        ini_set('session.use_only_cookies', '1');     // Never accept session ID in URL
        ini_set('session.use_trans_sid',    '0');
        ini_set('session.cookie_path',      '/');

        session_start();
        self::$started = true;

        // Enforce session fingerprinting and idle timeout
        self::enforceIntegrity($lifetime);

        // For read-only AJAX/API requests, release the session lock immediately
        // after reading. This prevents concurrent requests from blocking each other
        // (PHP holds a session file lock for the entire request duration by default).
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_contains($uri, '/api/')) {
            session_write_close();
        }
    }

    /**
     * Enforce session security:
     *  1. Idle timeout — destroy if inactive > $lifetime seconds
     *  2. Fingerprint check — destroy if IP/UA changed (anti-hijack)
     */
    private static function enforceIntegrity(int $lifetime): void
    {
        $now = time();

        // ── Idle Timeout ────────────────────────────────────────────────────
        $lastActivity = $_SESSION[SESSION_LAST_ACTIVITY] ?? null;

        if ($lastActivity !== null && ($now - (int) $lastActivity) > $lifetime) {
            self::destroy();
            return;
        }

        $_SESSION[SESSION_LAST_ACTIVITY] = $now;

        // ── Fingerprint Protection ──────────────────────────────────────────
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $currentUa = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $fingerprint = hash('sha256', $currentIp . $currentUa);

        $storedFingerprint = $_SESSION[SESSION_IP] ?? null;

        if ($storedFingerprint === null) {
            // First request — store fingerprint
            $_SESSION[SESSION_IP]         = $fingerprint;
            $_SESSION[SESSION_USER_AGENT] = $currentUa;
        } elseif ($storedFingerprint !== $fingerprint) {
            // Fingerprint mismatch — potential session hijack
            Logger::warning('Session fingerprint mismatch — possible hijack attempt', [
                'stored'  => $storedFingerprint,
                'current' => $fingerprint,
                'ip'      => $currentIp,
            ]);
            self::destroy();
        }
    }

    /**
     * Regenerate session ID (call after login to prevent session fixation).
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Get a value from the session.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a value in the session.
     *
     * @param string $key
     * @param mixed  $value
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Check if a session key exists.
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Delete a key from the session.
     */
    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Clear all session data (but keep the session alive).
     */
    public static function clear(): void
    {
        session_unset();
    }

    /**
     * Fully destroy the session (cookie + data).
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Clear session data
            session_unset();

            // Delete session cookie
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 3600,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );

            session_destroy();
        }

        self::$started = false;
    }

    /**
     * Check if a user is currently authenticated.
     */
    public static function isAuthenticated(): bool
    {
        return self::has(SESSION_USER_ID) && self::get(SESSION_USER_ID) !== null;
    }

    /**
     * Get the authenticated user's ID.
     */
    public static function userId(): ?int
    {
        $id = self::get(SESSION_USER_ID);
        return $id !== null ? (int) $id : null;
    }

    /**
     * Get the authenticated user's role name.
     */
    public static function userRole(): ?string
    {
        return self::get(SESSION_USER_ROLE);
    }

    /**
     * Store full user data in session after login.
     *
     * @param array<string, mixed> $userData
     */
    public static function loginUser(array $userData): void
    {
        // Regenerate session ID to prevent session fixation
        self::regenerate();

        self::set(SESSION_USER_ID,   $userData['id']);
        self::set(SESSION_USER_ROLE, $userData['role_name'] ?? $userData['role']);
        self::set(SESSION_USER_NAME, trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')));
        self::set(SESSION_USER_DATA, $userData);
        self::set(SESSION_LOGIN_TIME, time());
        self::set(SESSION_LAST_ACTIVITY, time());

        // Store location assignment if present
        if (isset($userData['location_type'])) {
            self::set(SESSION_LOCATION_TYPE, $userData['location_type']);
            self::set(SESSION_LOCATION_ID,   $userData['location_id'] ?? null);
        }
    }

    /**
     * Clear all user-related session data on logout.
     */
    public static function logoutUser(): void
    {
        $keysToRemove = [
            SESSION_USER_ID,
            SESSION_USER_ROLE,
            SESSION_USER_NAME,
            SESSION_USER_DATA,
            SESSION_LOGIN_TIME,
            SESSION_LOCATION_TYPE,
            SESSION_LOCATION_ID,
            SESSION_FLASH,
            'validation_errors',
            'old_input',
            'aam_permissions', // clear cached RBAC permissions
        ];

        foreach ($keysToRemove as $key) {
            self::delete($key);
        }

        // Keep fingerprint keys but force regeneration
        self::regenerate();
    }
}
