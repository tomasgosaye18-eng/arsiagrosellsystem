<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Cookie.php
 * Purpose  : Cookie utility class.
 *            Wraps PHP cookie functions with security
 *            defaults: HttpOnly, SameSite=Strict, Secure
 *            in production. All values are encrypted.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

use AAM\Config\Application;
use AAM\Config\Environment;
use AAM\Security\Encryption;

class Cookie
{
    /** Default cookie lifetime in seconds (1 day) */
    private const DEFAULT_LIFETIME = 86400;

    /**
     * Set a cookie.
     * The value is encrypted automatically using Encryption::encrypt().
     *
     * @param string   $name     Cookie name
     * @param string   $value    Raw value (will be encrypted)
     * @param int      $lifetime Lifetime in seconds from now (0 = session cookie)
     * @param string   $path     Cookie path
     * @param bool     $encrypt  Encrypt the value (default: true)
     */
    public static function set(
        string $name,
        string $value,
        int    $lifetime = self::DEFAULT_LIFETIME,
        string $path     = '/',
        bool   $encrypt  = true
    ): bool {
        $secure   = Environment::isProduction();
        $httponly = true;
        $samesite = 'Strict';

        $cookieValue = $encrypt ? self::encrypt($value) : $value;
        $expires     = $lifetime > 0 ? time() + $lifetime : 0;

        return setcookie($name, $cookieValue, [
            'expires'  => $expires,
            'path'     => $path,
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);
    }

    /**
     * Get a cookie value.
     * Decrypts automatically if $decrypt = true.
     *
     * @param string $name    Cookie name
     * @param mixed  $default Default if cookie not found or decryption fails
     * @param bool   $decrypt Decrypt the value (default: true)
     * @return mixed
     */
    public static function get(string $name, mixed $default = null, bool $decrypt = true): mixed
    {
        if (!isset($_COOKIE[$name])) {
            return $default;
        }

        $raw = $_COOKIE[$name];

        if ($decrypt) {
            $decrypted = self::decrypt($raw);
            return $decrypted ?? $default;
        }

        return $raw;
    }

    /**
     * Check if a cookie exists.
     */
    public static function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Delete (expire) a cookie.
     */
    public static function delete(string $name, string $path = '/'): bool
    {
        unset($_COOKIE[$name]);

        return setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => $path,
            'domain'   => '',
            'secure'   => Environment::isProduction(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Delete all cookies set by this application.
     */
    public static function deleteAll(): void
    {
        foreach (array_keys($_COOKIE) as $name) {
            self::delete($name);
        }
    }

    /**
     * Set a cookie that lives for 30 days (remember-me style).
     */
    public static function setLongLived(string $name, string $value): bool
    {
        return self::set($name, $value, 30 * 86400);
    }

    // ─── Internal Helpers ─────────────────────────────────────────────────────

    /**
     * Encrypt a cookie value.
     * Falls back to raw value if encryption key is not configured.
     */
    private static function encrypt(string $value): string
    {
        try {
            return Encryption::encrypt($value);
        } catch (\RuntimeException $e) {
            // No encryption key configured — store raw (log warning)
            Logger::warning('Cookie encryption skipped: encryption key not configured.');
            return $value;
        }
    }

    /**
     * Decrypt a cookie value.
     * Returns null if decryption fails (tampered / no key).
     */
    private static function decrypt(string $value): ?string
    {
        try {
            return Encryption::decrypt($value);
        } catch (\RuntimeException $e) {
            return null;
        }
    }
}
