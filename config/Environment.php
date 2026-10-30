<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : config/Environment.php
 * Purpose  : Environment loader — reads .env file and
 *            populates $_ENV / getenv().
 *            Supports development, testing, production.
 *            Auto-switches configuration based on APP_ENV.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Config;

class Environment
{
    /** @var bool Whether environment has been loaded */
    private static bool $loaded = false;

    /** @var array<string, string> Loaded environment variables */
    private static array $variables = [];

    /**
     * Load the .env file from the application root.
     * Safe to call multiple times — loads only once.
     */
    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = ROOT_PATH . '/.env';

        // Fall back to example file if .env doesn't exist (dev convenience)
        if (!file_exists($envFile)) {
            $envFile = ROOT_PATH . '/.env.example';
        }

        if (file_exists($envFile)) {
            self::parseFile($envFile);
        }

        // Set timezone immediately after loading environment
        $timezone = self::get('APP_TIMEZONE', 'Africa/Addis_Ababa');
        date_default_timezone_set($timezone);

        // Configure PHP error reporting based on environment
        self::configureErrorReporting();

        self::$loaded = true;
    }

    /**
     * Parse a .env file and populate $_ENV and putenv().
     */
    private static function parseFile(string $filePath): void
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            $line = trim($line);
            if (str_starts_with($line, '#') || $line === '') {
                continue;
            }

            // Must contain an = sign
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Remove surrounding quotes
            $value = self::stripQuotes($value);

            // Store and expose to environment
            self::$variables[$key] = $value;
            $_ENV[$key]            = $value;
            $_SERVER[$key]         = $value;

            if (!getenv($key)) {
                putenv("{$key}={$value}");
            }
        }
    }

    /**
     * Strip surrounding single or double quotes from a value.
     */
    private static function stripQuotes(string $value): string
    {
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return substr($value, 1, -1);
        }
        return $value;
    }

    /**
     * Configure PHP error reporting based on environment.
     */
    private static function configureErrorReporting(): void
    {
        $env   = self::get('APP_ENV', 'production');
        $debug = self::get('APP_DEBUG', 'false');

        if ($env === 'development' || $debug === 'true') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }

        ini_set('log_errors', '1');
        ini_set('error_log', LOGS_PATH . '/php_errors.log');
    }

    /**
     * Get an environment variable value.
     *
     * @param string $key     Variable name
     * @param mixed  $default Default if not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $_ENV[$key] ?? $default;
    }

    /**
     * Check if a variable is defined and non-empty.
     */
    public static function has(string $key): bool
    {
        return isset(self::$variables[$key]) || getenv($key) !== false;
    }

    /**
     * Get current application environment name.
     */
    public static function current(): string
    {
        return (string) self::get('APP_ENV', 'production');
    }

    /**
     * Check if running in development environment.
     */
    public static function isDevelopment(): bool
    {
        return self::current() === 'development';
    }

    /**
     * Check if running in testing environment.
     */
    public static function isTesting(): bool
    {
        return self::current() === 'testing';
    }

    /**
     * Check if running in production environment.
     */
    public static function isProduction(): bool
    {
        return self::current() === 'production';
    }

    /**
     * Check if debug mode is enabled.
     */
    public static function isDebug(): bool
    {
        return self::get('APP_DEBUG', 'false') === 'true';
    }

    /**
     * Get all loaded environment variables (keys only, no secrets).
     *
     * @return array<string>
     */
    public static function keys(): array
    {
        return array_keys(self::$variables);
    }
}
