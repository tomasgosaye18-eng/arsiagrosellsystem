<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Autoloader.php
 * Purpose  : PSR-4 style class autoloader.
 *            Maps AAM namespace to directory structure.
 *            Also autoloads helpers and standalone files.
 *            No Composer required — pure PHP autoloading.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

class Autoloader
{
    /**
     * Namespace-to-directory prefix map.
     * Key   = namespace prefix (with trailing backslash)
     * Value = base directory (with trailing slash)
     *
     * @var array<string, string>
     */
    private static array $prefixes = [];

    /**
     * Register the autoloader with PHP's SPL stack.
     * Also registers all AAM namespace prefixes.
     */
    public static function register(): void
    {
        // Register namespace → directory mappings
        self::addNamespace('AAM\\Config',      CONFIG_PATH . '/');
        self::addNamespace('AAM\\Core',        CORE_PATH . '/');
        self::addNamespace('AAM\\Helpers',     HELPERS_PATH . '/');
        self::addNamespace('AAM\\Security',    CORE_PATH . '/Security/');
        self::addNamespace('AAM\\Middleware',  CORE_PATH . '/Middleware/');
        self::addNamespace('AAM\\Exceptions',  CORE_PATH . '/Exceptions/');
        self::addNamespace('AAM\\Models',      MODULES_PATH . '/');
        self::addNamespace('AAM\\Controllers', MODULES_PATH . '/');

        // Module-specific namespaces
        self::addNamespace('AAM\\Auth',                    MODULES_PATH . '/auth/');
        self::addNamespace('AAM\\Auth\\Controllers',       MODULES_PATH . '/auth/controllers/');
        self::addNamespace('AAM\\Auth\\Models',            MODULES_PATH . '/auth/models/');
        self::addNamespace('AAM\\Auth\\Services',          MODULES_PATH . '/auth/services/');
        self::addNamespace('AAM\\Admin',                   MODULES_PATH . '/admin/');
        self::addNamespace('AAM\\Admin\\Controllers',      MODULES_PATH . '/admin/controllers/');
        self::addNamespace('AAM\\Admin\\Models',           MODULES_PATH . '/admin/models/');
        self::addNamespace('AAM\\Admin\\Services',         MODULES_PATH . '/admin/services/');
        self::addNamespace('AAM\\Dashboard',               MODULES_PATH . '/dashboard/');
        self::addNamespace('AAM\\Dashboard\\Controllers',  MODULES_PATH . '/dashboard/controllers/');
        self::addNamespace('AAM\\Dashboard\\Services',     MODULES_PATH . '/dashboard/services/');
        self::addNamespace('AAM\\Farm',                    MODULES_PATH . '/farm/');
        self::addNamespace('AAM\\Farm\\Controllers',       MODULES_PATH . '/farm/controllers/');
        self::addNamespace('AAM\\Farm\\Models',            MODULES_PATH . '/farm/models/');
        self::addNamespace('AAM\\Warehouse',               MODULES_PATH . '/warehouse/');
        self::addNamespace('AAM\\Warehouse\\Controllers',  MODULES_PATH . '/warehouse/controllers/');
        self::addNamespace('AAM\\Warehouse\\Models',       MODULES_PATH . '/warehouse/models/');
        self::addNamespace('AAM\\Warehouse\\Services',     MODULES_PATH . '/warehouse/services/');
        self::addNamespace('AAM\\Transfer',                MODULES_PATH . '/transfer/');
        self::addNamespace('AAM\\Transfer\\Controllers',   MODULES_PATH . '/transfer/controllers/');
        self::addNamespace('AAM\\Transfer\\Models',        MODULES_PATH . '/transfer/models/');
        self::addNamespace('AAM\\Inventory',               MODULES_PATH . '/inventory/');
        self::addNamespace('AAM\\Inventory\\Controllers', MODULES_PATH . '/inventory/controllers/');
        self::addNamespace('AAM\\Inventory\\Models',      MODULES_PATH . '/inventory/models/');
        self::addNamespace('AAM\\Inventory\\Services',    MODULES_PATH . '/inventory/services/');
        self::addNamespace('AAM\\Shop',                    MODULES_PATH . '/shop/');
        self::addNamespace('AAM\\Shop\\Controllers',       MODULES_PATH . '/shop/controllers/');
        self::addNamespace('AAM\\Shop\\Models',            MODULES_PATH . '/shop/models/');
        self::addNamespace('AAM\\POS',                          MODULES_PATH . '/pos/');
        self::addNamespace('AAM\\POS\\Controllers',            MODULES_PATH . '/pos/controllers/');
        self::addNamespace('AAM\\POS\\Models',                 MODULES_PATH . '/pos/models/');
        self::addNamespace('AAM\\POS\\Services',               MODULES_PATH . '/pos/services/');
        self::addNamespace('AAM\\POS\\Services\\Payment',      MODULES_PATH . '/pos/services/Payment/');
        self::addNamespace('AAM\\Reports',                 MODULES_PATH . '/reports/');
        self::addNamespace('AAM\\Reports\\Controllers',    MODULES_PATH . '/reports/controllers/');
        self::addNamespace('AAM\\Reports\\Models',         MODULES_PATH . '/reports/models/');
        self::addNamespace('AAM\\Notifications',           MODULES_PATH . '/notifications/');
        self::addNamespace('AAM\\Notifications\\Controllers', MODULES_PATH . '/notifications/controllers/');
        self::addNamespace('AAM\\Notifications\\Models',   MODULES_PATH . '/notifications/models/');
        self::addNamespace('AAM\\Settings',                    MODULES_PATH . '/settings/');
        self::addNamespace('AAM\\Settings\\Controllers',       MODULES_PATH . '/settings/controllers/');
        self::addNamespace('AAM\\Settings\\Models',            MODULES_PATH . '/settings/models/');
        self::addNamespace('AAM\\Settings\\Services',          MODULES_PATH . '/settings/services/');

        // ── Phase 8C: Organization Management Module ───────────────────────────
        self::addNamespace('AAM\\Organization',                    MODULES_PATH . '/organization/');
        self::addNamespace('AAM\\Organization\\Controllers',       MODULES_PATH . '/organization/controllers/');
        self::addNamespace('AAM\\Organization\\Models',            MODULES_PATH . '/organization/models/');
        self::addNamespace('AAM\\Organization\\Services',          MODULES_PATH . '/organization/services/');

        // ── General Manager Module (dedicated /gm/* namespace) ────────────────
        self::addNamespace('AAM\\GeneralManager',                     MODULES_PATH . '/general_manager/');
        self::addNamespace('AAM\\GeneralManager\\Controllers',        MODULES_PATH . '/general_manager/controllers/');
        self::addNamespace('AAM\\GeneralManager\\Services',           MODULES_PATH . '/general_manager/services/');

        // ── Customer Module — Mini E-Commerce Storefront ──────────────────────
        self::addNamespace('AAM\\Customer',                    MODULES_PATH . '/customer/');
        self::addNamespace('AAM\\Customer\\Controllers',       MODULES_PATH . '/customer/controllers/');
        self::addNamespace('AAM\\Customer\\Services',          MODULES_PATH . '/customer/services/');

        // ── Seed Distribution Module ───────────────────────────────────────────
        self::addNamespace('AAM\\SeedDistribution',                   MODULES_PATH . '/seed_distribution/');
        self::addNamespace('AAM\\SeedDistribution\\Controllers',      MODULES_PATH . '/seed_distribution/controllers/');
        self::addNamespace('AAM\\SeedDistribution\\Models',           MODULES_PATH . '/seed_distribution/models/');
        self::addNamespace('AAM\\SeedDistribution\\Services',         MODULES_PATH . '/seed_distribution/services/');

        // ── Phase 8B: App\ namespace aliases (same physical paths) ────────────
        self::addNamespace('App\\Core',                         CORE_PATH . '/');
        self::addNamespace('App\\Modules\\Admin\\Controllers',  MODULES_PATH . '/admin/controllers/');
        self::addNamespace('App\\Modules\\Admin\\Models',       MODULES_PATH . '/admin/models/');
        self::addNamespace('App\\Modules\\Admin\\Services',     MODULES_PATH . '/admin/services/');
        self::addNamespace('App\\Modules\\Auth\\Controllers',   MODULES_PATH . '/auth/controllers/');
        self::addNamespace('App\\Modules\\Auth\\Models',        MODULES_PATH . '/auth/models/');
        self::addNamespace('App\\Modules\\Auth\\Services',      MODULES_PATH . '/auth/services/');

        // Register SPL autoload function
        spl_autoload_register([self::class, 'loadClass'], true, true);

        // Load helper files (not classes — procedural helpers)
        self::loadHelpers();

        // Load constants
        if (file_exists(CONFIG_PATH . '/constants.php')) {
            require_once CONFIG_PATH . '/constants.php';
        }

        // Load security policy constants (must come after constants.php)
        if (file_exists(CONFIG_PATH . '/security.php')) {
            require_once CONFIG_PATH . '/security.php';
        }
    }

    /**
     * Add a namespace-to-directory mapping.
     *
     * @param string $prefix    Namespace prefix (no trailing backslash)
     * @param string $directory Absolute directory path (with trailing slash)
     */
    public static function addNamespace(string $prefix, string $directory): void
    {
        $prefix    = trim($prefix, '\\') . '\\';
        $directory = rtrim($directory, '/\\') . '/';

        self::$prefixes[$prefix] = $directory;
    }

    /**
     * SPL autoload callback.
     * Resolves a fully-qualified class name to a file path and requires it.
     *
     * @param string $class Fully-qualified class name
     */
    public static function loadClass(string $class): void
    {
        // Try registered namespace prefixes (longest prefix first)
        foreach (self::$prefixes as $prefix => $directory) {
            $len = strlen($prefix);

            if (strncmp($prefix, $class, $len) !== 0) {
                continue;
            }

            // Get the relative class name
            $relativeClass = substr($class, $len);

            // Build the file path
            $file = $directory . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }

        // Fallback: try flat file in core/
        $classBaseName = basename(str_replace('\\', '/', $class));
        $fallback = CORE_PATH . '/' . $classBaseName . '.php';
        if (file_exists($fallback)) {
            require_once $fallback;
        }
    }

    /**
     * Load all procedural helper files from the helpers directory.
     */
    private static function loadHelpers(): void
    {
        $helperFiles = [
            HELPERS_PATH . '/functions.php',
            HELPERS_PATH . '/format.php',
            HELPERS_PATH . '/security_helpers.php',
            HELPERS_PATH . '/response_helpers.php',
        ];

        foreach ($helperFiles as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    /**
     * Get all registered namespace prefixes.
     *
     * @return array<string, string>
     */
    public static function getPrefixes(): array
    {
        return self::$prefixes;
    }
}
