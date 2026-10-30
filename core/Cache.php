<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Cache.php
 * Purpose  : Simple file-based cache utility.
 *            Stores serialized PHP values in cache files.
 *            Supports TTL expiry and cache invalidation.
 *            Used for dashboard KPIs and report aggregates.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

use AAM\Config\Application;

class Cache
{
    /** @var string Cache directory path */
    private static string $cachePath = '';

    /** @var int Default TTL in seconds */
    private static int $defaultTtl = 3600;

    /**
     * Initialize cache storage path.
     */
    private static function init(): void
    {
        if (self::$cachePath !== '') {
            return;
        }
        self::$cachePath   = (string) Application::get('cache.path', CACHE_PATH);
        self::$defaultTtl  = (int)   Application::get('cache.ttl', 3600);

        if (!is_dir(self::$cachePath)) {
            mkdir(self::$cachePath, 0755, true);
        }
    }

    /**
     * Store a value in the cache.
     *
     * @param string $key   Cache key
     * @param mixed  $value Value to cache (must be serializable)
     * @param int    $ttl   Time-to-live in seconds (0 = use default)
     */
    public static function set(string $key, mixed $value, int $ttl = 0): void
    {
        self::init();
        $ttl      = $ttl ?: self::$defaultTtl;
        $expires  = time() + $ttl;
        $payload  = serialize(['expires' => $expires, 'value' => $value]);
        $file     = self::path($key);

        file_put_contents($file, $payload, LOCK_EX);
    }

    /**
     * Get a cached value.
     * Returns null if key does not exist or has expired.
     *
     * @return mixed
     */
    public static function get(string $key): mixed
    {
        self::init();
        $file = self::path($key);

        if (!file_exists($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $data = unserialize($raw);
        if (!is_array($data) || !isset($data['expires'], $data['value'])) {
            return null;
        }

        if (time() > $data['expires']) {
            @unlink($file);
            return null;
        }

        return $data['value'];
    }

    /**
     * Check if a valid (non-expired) cache entry exists.
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Get a cached value or execute the callback and cache the result.
     *
     * @param string   $key
     * @param callable $callback  Produces the value if cache miss
     * @param int      $ttl
     * @return mixed
     */
    public static function remember(string $key, callable $callback, int $ttl = 0): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    /**
     * Delete a cache entry.
     */
    public static function delete(string $key): void
    {
        self::init();
        $file = self::path($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Clear all cache entries.
     */
    public static function flush(): void
    {
        self::init();
        $files = glob(self::$cachePath . '/*.cache');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Delete all expired cache entries (garbage collection).
     */
    public static function gc(): int
    {
        self::init();
        $deleted = 0;
        $files   = glob(self::$cachePath . '/*.cache') ?: [];

        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $data = unserialize($raw);
            if (!is_array($data) || time() > ($data['expires'] ?? 0)) {
                @unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Build the cache file path for a key.
     */
    private static function path(string $key): string
    {
        // Hash the key to get a safe filename
        $hashed = md5($key);
        return self::$cachePath . '/' . $hashed . '.cache';
    }
}
