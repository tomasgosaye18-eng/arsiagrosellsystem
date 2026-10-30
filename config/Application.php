<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : config/Application.php
 * Purpose  : Master application configuration.
 *            Reads from environment and exposes typed
 *            configuration values to the entire application.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Config;

class Application
{
    /** @var array<string, mixed> Resolved configuration values */
    private static array $config = [];

    /** @var bool Initialization guard */
    private static bool $initialized = false;

    /**
     * Initialize and cache all application configuration.
     * Called once during bootstrap.
     */
    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$config = [
            // ── App Identity ───────────────────────────────────────────
            'name'         => Environment::get('APP_NAME', 'Arsi Agri-Manage'),
            'version'      => Environment::get('APP_VERSION', '1.0.0'),
            'url'          => rtrim((string) Environment::get('APP_URL', 'http://localhost/aam/app'), '/'),
            'env'          => Environment::get('APP_ENV', 'production'),
            'debug'        => Environment::get('APP_DEBUG', 'false') === 'true',
            'timezone'     => Environment::get('APP_TIMEZONE', 'Africa/Addis_Ababa'),
            'locale'       => Environment::get('APP_LOCALE', 'en'),

            // ── Database ───────────────────────────────────────────────
            'database' => [
                'host'      => Environment::get('DB_HOST', 'localhost'),
                'port'      => (int) Environment::get('DB_PORT', '3306'),
                'name'      => Environment::get('DB_DATABASE', 'aam_db'),
                'username'  => Environment::get('DB_USERNAME', 'aam_app'),
                'password'  => Environment::get('DB_PASSWORD', ''),
                'charset'   => Environment::get('DB_CHARSET', 'utf8mb4'),
                'collation' => Environment::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
                'options'   => [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES   => false,
                    \PDO::ATTR_STRINGIFY_FETCHES  => false,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ],
            ],

            // ── Session ────────────────────────────────────────────────
            'session' => [
                'lifetime'  => (int) Environment::get('SESSION_LIFETIME', '1800'),
                'name'      => Environment::get('SESSION_NAME', 'aam_session'),
                'secure'    => Environment::get('SESSION_SECURE', 'false') === 'true',
                'httponly'  => Environment::get('SESSION_HTTPONLY', 'true') === 'true',
                'samesite'  => Environment::get('SESSION_SAMESITE', 'Strict'),
                'path'      => '/',
            ],

            // ── Security ───────────────────────────────────────────────
            // Login/password policy values now come from config/security.php
            // (PHP constants). The keys below remain for anything that still
            // calls Application::get('security.*') so existing code does not
            // break while it is being migrated to constants.
            'security' => [
                'encryption_key'     => Environment::get('ENCRYPTION_KEY', ''),
                'max_login_attempts' => \MAX_FAILED_LOGIN_ATTEMPTS,
                'lockout_duration'   => \LOCKOUT_DURATION_MINUTES * 60, // seconds
                'bcrypt_cost'        => \BCRYPT_COST,
                'csrf_token_length'  => 32,
            ],

            // ── Uploads ────────────────────────────────────────────────
            'uploads' => [
                'max_size'      => (int) Environment::get('UPLOAD_MAX_SIZE', '10485760'),
                'allowed_types' => explode(',', (string) Environment::get(
                    'UPLOAD_ALLOWED_TYPES',
                    'jpg,jpeg,png,gif,webp,pdf'
                )),
                'products_path' => \UPLOADS_PATH . '/products',
                'profiles_path' => \UPLOADS_PATH . '/profiles',
                'receipts_path' => \UPLOADS_PATH . '/receipts',
            ],

            // ── Logging ────────────────────────────────────────────────
            'logging' => [
                'level'   => Environment::get('LOG_LEVEL', 'error'),
                'channel' => Environment::get('LOG_CHANNEL', 'file'),
                'path'    => \LOGS_PATH,
            ],

            // ── Cache ──────────────────────────────────────────────────
            'cache' => [
                'driver' => Environment::get('CACHE_DRIVER', 'file'),
                'ttl'    => (int) Environment::get('CACHE_TTL', '3600'),
                'path'   => \CACHE_PATH,
            ],

            // ── Pagination ─────────────────────────────────────────────
            'pagination' => [
                'per_page'    => 20,
                'page_range'  => 5,
            ],

            // ── Ethiopia-Specific ─────────────────────────────────────
            'currency' => [
                'code'     => 'ETB',
                'symbol'   => 'Br',
                'decimals' => 2,
                'thousands_sep' => ',',
                'decimal_sep'   => '.',
            ],
        ];

        self::$initialized = true;
    }

    /**
     * Get a configuration value using dot notation.
     *
     * Examples:
     *   Application::get('database.host')
     *   Application::get('session.lifetime')
     *   Application::get('name')
     *
     * @param string $key     Dot-notation key
     * @param mixed  $default Default value if key not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $keys  = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a configuration value at runtime.
     * Useful for test overrides.
     */
    public static function set(string $key, mixed $value): void
    {
        $keys    = explode('.', $key);
        $config  = &self::$config;
        $lastKey = array_pop($keys);

        foreach ($keys as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config[$lastKey] = $value;
    }

    /**
     * Get entire configuration array (for debugging).
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$config;
    }
}
