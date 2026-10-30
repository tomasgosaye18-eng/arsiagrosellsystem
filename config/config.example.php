<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : config/config.example.php
 * Purpose  : Example configuration file.
 *            Copy to config.php and fill in real values.
 *            config.php is in .gitignore — never committed.
 *            This example file IS committed.
 *
 * NOTE: This file is only used as a reference.
 *       All actual configuration is loaded from .env
 *       via config/Environment.php and config/Application.php.
 *       This file shows what the .env values mean.
 * =========================================================
 */

declare(strict_types=1);

/*
 * ─── HOW CONFIGURATION WORKS IN AAM ────────────────────────────────────────
 *
 * 1. Copy .env.example → .env
 * 2. Fill in your actual credentials in .env
 * 3. The Environment class reads .env on every request bootstrap
 * 4. Application class exposes values with dot-notation:
 *      Application::get('database.host')  → 'localhost'
 *      Application::get('session.lifetime') → 1800
 *
 * You do NOT need to edit this file or create config.php.
 * Everything is driven by .env.
 * ──────────────────────────────────────────────────────────────────────────
 */

return [

    // ─── Application ──────────────────────────────────────────────────────
    'app' => [
        'name'     => 'Arsi Agri-Manage',
        'version'  => '1.0.0',
        'url'      => 'http://localhost/aam/app',
        'env'      => 'development',       // development | testing | production
        'debug'    => true,                // true in dev, false in prod
        'timezone' => 'Africa/Addis_Ababa',
        'locale'   => 'en',
    ],

    // ─── Database ─────────────────────────────────────────────────────────
    'database' => [
        'host'      => 'localhost',
        'port'      => 3306,
        'name'      => 'aam_db',
        'username'  => 'aam_app',
        'password'  => 'YOUR_STRONG_PASSWORD_HERE',
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    // ─── Session ──────────────────────────────────────────────────────────
    'session' => [
        'lifetime' => 1800,           // 30 minutes in seconds
        'name'     => 'aam_session',
        'secure'   => false,          // true on HTTPS production
        'httponly' => true,
        'samesite' => 'Strict',
    ],

    // ─── Security ─────────────────────────────────────────────────────────
    'security' => [
        'encryption_key'     => 'GENERATE_A_32_CHAR_RANDOM_KEY_HERE',
        'max_login_attempts' => 5,
        'lockout_duration'   => 900,  // 15 minutes in seconds
        'bcrypt_cost'        => 12,
    ],

    // ─── File Uploads ─────────────────────────────────────────────────────
    'uploads' => [
        'max_size'      => 10485760,  // 10 MB in bytes
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'],
    ],

    // ─── Logging ──────────────────────────────────────────────────────────
    'logging' => [
        'level'   => 'debug',         // debug | info | warning | error
        'channel' => 'file',
    ],

    // ─── Cache ────────────────────────────────────────────────────────────
    'cache' => [
        'driver' => 'file',
        'ttl'    => 3600,             // 1 hour in seconds
    ],

    // ─── Ethiopian Business Settings ──────────────────────────────────────
    'business' => [
        'currency'         => 'ETB',
        'currency_symbol'  => 'Br',
        'tax_rate'         => 0,      // Percentage (0 = no tax in v1)
        'org_name'         => 'Arsi University',
        'org_address'      => 'Asella, Oromia, Ethiopia',
        'org_phone'        => '',
        'low_stock_days'   => 7,      // Alert if stock lasts < 7 days
    ],

    // ─── Maintenance ──────────────────────────────────────────────────────
    // (Maintenance mode has been removed)

];
