<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Logger.php
 * Purpose  : Application logging framework.
 *            Writes to separate log files by channel:
 *              app.log, error.log, security.log, db.log
 *            Supports PSR-3 log levels.
 *            Log rotation when files exceed size limit.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

use AAM\Config\Application;

class Logger
{
    /** Log levels in ascending severity order */
    private const LEVELS = [
        'debug'     => 0,
        'info'      => 1,
        'notice'    => 2,
        'warning'   => 3,
        'error'     => 4,
        'critical'  => 5,
        'alert'     => 6,
        'emergency' => 7,
    ];

    /** Maximum log file size before rotation (10 MB) */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /** @var string Minimum level to log (from config) */
    private static string $minLevel = 'debug';

    /** @var bool Initialized flag */
    private static bool $initialized = false;

    /**
     * Initialize the logger — create log directory if needed.
     */
    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        if (!is_dir(LOGS_PATH)) {
            mkdir(LOGS_PATH, 0755, true);
        }

        self::$minLevel  = (string) Application::get('logging.level', 'debug');
        self::$initialized = true;
    }

    // ─── PSR-3 Level Methods ──────────────────────────────────────────────────

    /** @param array<string,mixed> $context */
    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function notice(string $message, array $context = []): void
    {
        self::log('notice', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context, 'error');
    }

    /** @param array<string,mixed> $context */
    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context, 'error');
    }

    /** @param array<string,mixed> $context */
    public static function alert(string $message, array $context = []): void
    {
        self::log('alert', $message, $context, 'error');
    }

    /** @param array<string,mixed> $context */
    public static function emergency(string $message, array $context = []): void
    {
        self::log('emergency', $message, $context, 'error');
    }

    /**
     * Log a security event (login, CSRF violation, account lock, etc.)
     *
     * @param array<string,mixed> $context
     */
    public static function security(string $message, array $context = []): void
    {
        self::log('warning', $message, $context, 'security');
    }

    /**
     * Log a database query (debug mode only).
     *
     * @param array<string,mixed> $context
     */
    public static function database(string $message, array $context = []): void
    {
        if (Application::get('debug', false)) {
            self::log('debug', $message, $context, 'db');
        }
    }

    // ─── Core Write ──────────────────────────────────────────────────────────

    /**
     * Write a log entry.
     * Public so ErrorHandler can call Logger::log() directly with a level string.
     *
     * @param string              $level    Log level constant
     * @param string              $message
     * @param array<string,mixed> $context  Additional data
     * @param string              $channel  Log file channel: app, error, security, db
     */
    public static function log(
        string $level,
        string $message,
        array  $context = [],
        string $channel = 'app'
    ): void {
        // Initialize if not done yet (edge case during very early bootstrap)
        if (!self::$initialized) {
            self::initialize();
        }

        // Check minimum level
        $levelNum    = self::LEVELS[$level]          ?? 0;
        $minLevelNum = self::LEVELS[self::$minLevel] ?? 0;

        if ($levelNum < $minLevelNum) {
            return;
        }

        // Build log entry
        $timestamp = date(DATE_FORMAT_LOG);
        $levelUpper = strtoupper($level);
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

        // Include request info for app/error logs
        $requestInfo = '';
        if (in_array($channel, ['app', 'error'], true)) {
            $ip  = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $requestInfo = " [{$ip}]{$uri}";
        }

        $entry = "[{$timestamp}] [{$levelUpper}]{$requestInfo} {$message}{$contextStr}" . PHP_EOL;

        // Determine log file
        $logFile = LOGS_PATH . "/{$channel}.log";

        // Rotate if file is too large
        self::rotateIfNeeded($logFile);

        // Write (suppress errors — logging must never crash the app)
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Rotate a log file if it exceeds the size limit.
     */
    private static function rotateIfNeeded(string $logFile): void
    {
        if (!file_exists($logFile)) {
            return;
        }

        if (filesize($logFile) >= self::MAX_FILE_SIZE) {
            $rotated = $logFile . '.' . date('Y-m-d-His') . '.bak';
            @rename($logFile, $rotated);
        }
    }

    /**
     * Read the last N lines from a log file (for admin log viewer).
     *
     * @param string $channel  Log channel: app, error, security, db
     * @param int    $lines    Number of lines to return
     * @return string[]
     */
    public static function tail(string $channel = 'app', int $lines = 100): array
    {
        $logFile = LOGS_PATH . "/{$channel}.log";

        if (!file_exists($logFile)) {
            return [];
        }

        $content = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($content === false) {
            return [];
        }

        return array_slice($content, -$lines);
    }
}
