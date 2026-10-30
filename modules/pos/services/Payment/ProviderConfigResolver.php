<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — AU-AgroSale POS
 * =========================================================
 * File    : modules/pos/services/Payment/ProviderConfigResolver.php
 * Purpose : Resolves provider configuration from two sources,
 *           with DB (payment_methods.provider_config) taking
 *           priority over .env when both are present.
 *
 * PRIORITY ORDER (highest to lowest):
 *   1. payment_methods.provider_config  (set via Admin UI)
 *   2. .env / getenv()                  (legacy / fallback)
 *
 * SECURITY:
 *   - Config is NEVER passed to the browser.
 *   - provider_config is only read here (server-side service layer).
 *   - Credentials are never logged at INFO level — only key names.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\POS\Services\Payment;

use AAM\Core\Database;
use AAM\Core\Logger;

class ProviderConfigResolver
{
    /** In-memory cache: providerCode → config array */
    private static array $cache = [];

    /**
     * Get a configuration value for a provider.
     *
     * Checks DB first, falls back to env var.
     *
     * @param string $providerCode  e.g. 'cbe_birr', 'telebirr'
     * @param string $key           Config key e.g. 'api_key', 'merchant_id'
     * @param string $envVar        Fallback env variable name e.g. 'CBE_BIRR_API_KEY'
     * @param string $default       Default value if neither source has a value
     */
    public static function get(
        string $providerCode,
        string $key,
        string $envVar  = '',
        string $default = ''
    ): string {
        $dbConfig = self::loadDbConfig($providerCode);

        // DB config takes priority if the key exists and is non-empty
        if (!empty($dbConfig[$key])) {
            return (string)$dbConfig[$key];
        }

        // Fallback to .env / environment variable
        if ($envVar !== '') {
            $val = getenv($envVar);
            if ($val !== false && $val !== '') {
                return (string)$val;
            }
        }

        return $default;
    }

    /**
     * Check if a provider has a complete configuration
     * (all required keys present and non-empty).
     *
     * @param string   $providerCode  e.g. 'cbe_birr'
     * @param string[] $requiredKeys  List of keys that must all be non-empty
     */
    public static function isConfigured(string $providerCode, array $requiredKeys): bool
    {
        foreach ($requiredKeys as $key) {
            // Check DB config
            $dbConfig = self::loadDbConfig($providerCode);
            if (!empty($dbConfig[$key])) {
                continue;
            }

            // Check env var mapping
            $envVar = self::envVarName($providerCode, $key);
            $val    = $envVar !== '' ? getenv($envVar) : false;
            if ($val === false || $val === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Clear the in-memory cache (call after config updates in the same request).
     */
    public static function clearCache(string $providerCode = ''): void
    {
        if ($providerCode !== '') {
            unset(self::$cache[$providerCode]);
        } else {
            self::$cache = [];
        }
    }

    // ── Private helpers ───────────────────────────────────

    /**
     * Load and cache provider_config from the payment_methods table.
     * Returns empty array on failure (table not yet created, etc.).
     */
    private static function loadDbConfig(string $providerCode): array
    {
        if (isset(self::$cache[$providerCode])) {
            return self::$cache[$providerCode];
        }

        try {
            $db  = Database::getInstance();
            $row = $db->fetchOne(
                "SELECT provider_config
                 FROM payment_methods
                 WHERE code = :code AND is_active = 1
                 LIMIT 1",
                ['code' => $providerCode]
            );

            if ($row && !empty($row['provider_config'])) {
                $decoded = json_decode($row['provider_config'], true);
                if (is_array($decoded)) {
                    self::$cache[$providerCode] = $decoded;
                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
            // Table may not exist yet — fail silently, fall back to .env
            Logger::debug('ProviderConfigResolver: DB config load failed', [
                'provider' => $providerCode,
                'error'    => $e->getMessage(),
            ]);
        }

        self::$cache[$providerCode] = [];
        return [];
    }

    /**
     * Map a provider+key to its conventional .env variable name.
     */
    private static function envVarName(string $providerCode, string $key): string
    {
        $map = [
            'cbe_birr' => [
                'merchant_id'    => 'CBE_BIRR_MERCHANT_ID',
                'account_number' => 'CBE_BIRR_ACCOUNT_NUMBER',
                'api_key'        => 'CBE_BIRR_API_KEY',
                'shortcode'      => 'CBE_BIRR_SHORTCODE',
                'callback_url'   => 'CBE_BIRR_CALLBACK_URL',
                'environment'    => 'CBE_BIRR_ENVIRONMENT',
            ],
            'telebirr' => [
                'app_id'         => 'TELEBIRR_APP_ID',
                'app_key'        => 'TELEBIRR_APP_KEY',
                'merchant_code'  => 'TELEBIRR_MERCHANT_CODE',
                'merchant_name'  => 'TELEBIRR_MERCHANT_NAME',
                'shortcode'      => 'TELEBIRR_SHORTCODE',
                'notify_url'     => 'TELEBIRR_NOTIFY_URL',
                'return_url'     => 'TELEBIRR_RETURN_URL',
                'environment'    => 'TELEBIRR_ENVIRONMENT',
            ],
        ];
        return $map[$providerCode][$key] ?? '';
    }
}
