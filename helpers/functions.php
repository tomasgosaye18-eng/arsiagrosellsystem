<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : helpers/functions.php
 * Purpose  : Global procedural helper functions.
 *            These are available everywhere without
 *            class instantiation. Kept minimal.
 * =========================================================
 */

declare(strict_types=1);

// ─── Prevent double-loading ───────────────────────────────────────────────────
if (defined('AAM_HELPERS_LOADED')) {
    return;
}
define('AAM_HELPERS_LOADED', true);

// ─── Output Escaping ──────────────────────────────────────────────────────────

/**
 * Escape a value for safe HTML output.
 * The most-used function in every view template.
 *
 * @param mixed $value
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Output an escaped value directly (echo shortcut).
 */
function esc(mixed $value): void
{
    echo e($value);
}

// ─── URL Helpers ─────────────────────────────────────────────────────────────

/**
 * Generate an application URL.
 *
 * @param string $path  Relative path
 */
function url(string $path = ''): string
{
    $base = rtrim((string) \AAM\Config\Application::get('url', 'http://localhost/aam/app'), '/');
    
    // Always return full absolute URL
    $path = ltrim($path, '/');
    return $base . ($path !== '' ? '/' . $path : '');
}

/**
 * Generate a URL to a public asset.
 *
 * @param string $path  Path relative to public/assets/
 */
function asset(string $path): string
{
    return url('public/assets/' . ltrim($path, '/'));
}

/**
 * Generate a URL to an uploaded file.
 *
 * @param string $path  Path relative to public/uploads/
 */
function upload_url(string $path): string
{
    return url('public/uploads/' . ltrim($path, '/'));
}

// ─── CSRF Helper ─────────────────────────────────────────────────────────────

/**
 * Get the current CSRF token.
 */
function csrf_token(): string
{
    return \AAM\Security\CSRF::getToken();
}

/**
 * Output the CSRF hidden input field.
 */
function csrf_field(): void
{
    echo \AAM\Security\CSRF::field();
}

/**
 * Output the CSRF hidden input field (alias used in views).
 * Usage in forms: <?php csrf_input(); ?>
 */
function csrf_input(): void
{
    echo \AAM\Security\CSRF::field();
}

// ─── Flash / Session Helpers ─────────────────────────────────────────────────

/**
 * Get a session value.
 *
 * @param mixed $default
 * @return mixed
 */
function session(string $key, mixed $default = null): mixed
{
    return \AAM\Core\SessionManager::get($key, $default);
}

/**
 * Check if a flash message exists.
 */
function has_flash(): bool
{
    return \AAM\Core\SessionManager::has(SESSION_FLASH);
}

// ─── Authentication Helpers ───────────────────────────────────────────────────

/**
 * Check if a user is currently logged in.
 */
function auth(): bool
{
    return \AAM\Core\SessionManager::isAuthenticated();
}

/**
 * Get the current user's ID.
 */
function auth_id(): ?int
{
    return \AAM\Core\SessionManager::userId();
}

/**
 * Get the current user's role.
 */
function auth_role(): ?string
{
    return \AAM\Core\SessionManager::userRole();
}

/**
 * Get the current user's full name.
 */
function auth_name(): string
{
    return (string) \AAM\Core\SessionManager::get(SESSION_USER_NAME, 'Unknown User');
}

/**
 * Check if the current user has a specific role.
 */
function has_role(string ...$roles): bool
{
    return in_array(auth_role(), $roles, true);
}

// ─── Currency / Number Helpers ────────────────────────────────────────────────

/**
 * Format a number as Ethiopian Birr currency.
 *
 * @param float|string|null $amount
 */
function currency(float|string|null $amount, bool $symbol = true): string
{
    return \AAM\Helpers\NumberHelper::currency($amount, $symbol);
}

/**
 * Format a quantity with unit.
 */
function qty(float|string|null $quantity, string $unit = '', int $decimals = 3): string
{
    return \AAM\Helpers\NumberHelper::quantity($quantity, $unit, $decimals);
}

// ─── Date Helpers ─────────────────────────────────────────────────────────────

/**
 * Format a date for display.
 */
function date_display(?string $date): string
{
    return \AAM\Helpers\DateHelper::display($date);
}

/**
 * Format a datetime for display.
 */
function datetime_display(?string $datetime): string
{
    return \AAM\Helpers\DateHelper::displayDatetime($datetime);
}

/**
 * Get a time-ago string.
 */
function time_ago(string $datetime): string
{
    return \AAM\Helpers\DateHelper::timeAgo($datetime);
}

// ─── String Helpers ───────────────────────────────────────────────────────────

/**
 * Truncate a string to a given length.
 */
function str_truncate(string $text, int $length = 100): string
{
    return \AAM\Helpers\StringHelper::truncate($text, $length);
}

/**
 * Convert snake_case to Title Case.
 */
function title_case(string $text): string
{
    return \AAM\Helpers\StringHelper::titleCase($text);
}

// ─── Debug Helper (dev only) ──────────────────────────────────────────────────

/**
 * Dump a variable and die — for debugging only.
 * Should never reach production.
 *
 * @param mixed ...$vars
 */
function dd(mixed ...$vars): never
{
    echo '<pre style="background:#1e1e1e;color:#dcdcdc;padding:1rem;border-radius:6px;overflow:auto;font-size:13px;">';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    exit;
}
