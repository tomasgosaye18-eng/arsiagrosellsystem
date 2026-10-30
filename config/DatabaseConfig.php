<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : config/database.php
 * Purpose  : Database configuration accessor.
 *            Returns the resolved database configuration
 *            array used by the Database connection manager.
 *            Actual credentials come from .env via Application.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Config;

class DatabaseConfig
{
    /**
     * Get the full database configuration array.
     *
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        return [
            'default' => 'mysql',

            'connections' => [
                'mysql' => [
                    'driver'    => 'mysql',
                    'host'      => Application::get('database.host', 'localhost'),
                    'port'      => Application::get('database.port', 3306),
                    'database'  => Application::get('database.name', 'aam_db'),
                    'username'  => Application::get('database.username', 'aam_app'),
                    'password'  => Application::get('database.password', ''),
                    'charset'   => Application::get('database.charset', 'utf8mb4'),
                    'collation' => Application::get('database.collation', 'utf8mb4_unicode_ci'),
                    'options'   => Application::get('database.options', []),
                ],
            ],

            // ─── Connection Pool Settings ────────────────────────────
            'pool' => [
                'min_connections' => 1,
                'max_connections' => 10,
                'timeout'         => 30,
            ],

            // ─── Query Log Settings ──────────────────────────────────
            'logging' => [
                'enabled'    => Application::get('debug', false),
                'slow_query' => 1000, // ms threshold for slow query logging
            ],
        ];
    }

    /**
     * Get DSN string for PDO connection.
     */
    public static function getDsn(): string
    {
        $host    = Application::get('database.host', 'localhost');
        $port    = Application::get('database.port', 3306);
        $dbname  = Application::get('database.name', 'aam_db');
        $charset = Application::get('database.charset', 'utf8mb4');

        return "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
    }
}
