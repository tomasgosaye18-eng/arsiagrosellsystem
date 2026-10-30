<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : helpers/format.php
 * Purpose  : Procedural formatting helper functions.
 *            Used directly in view templates.
 *            Thin wrappers around class-based helpers.
 * =========================================================
 */

declare(strict_types=1);

if (defined('AAM_FORMAT_LOADED')) return;
define('AAM_FORMAT_LOADED', true);

/**
 * Format ETB currency.
 *
 * @param float|string|null $amount
 * @param bool              $symbol
 */
function fmt_currency(float|string|null $amount, bool $symbol = true): string
{
    return \AAM\Helpers\NumberHelper::currency($amount, $symbol);
}

/**
 * Format a product quantity with unit.
 *
 * @param float|string|null $qty
 * @param string            $unit
 */
function fmt_qty(float|string|null $qty, string $unit = '', int $decimals = 3): string
{
    return \AAM\Helpers\NumberHelper::quantity($qty, $unit, $decimals);
}

/**
 * Format a date for display: 25/07/2026
 */
function fmt_date(?string $date): string
{
    return \AAM\Helpers\DateHelper::display($date);
}

/**
 * Format a datetime for display: 25/07/2026 14:30
 */
function fmt_datetime(?string $datetime): string
{
    return \AAM\Helpers\DateHelper::displayDatetime($datetime);
}

/**
 * Get a time-ago string.
 */
function fmt_ago(string $datetime): string
{
    return \AAM\Helpers\DateHelper::timeAgo($datetime);
}

/**
 * Format a percentage.
 */
function fmt_percent(float|string|null $value, int $decimals = 1): string
{
    return \AAM\Helpers\NumberHelper::percent($value, $decimals);
}

/**
 * Format file size: bytes → "2.5 MB"
 */
function fmt_filesize(int $bytes): string
{
    return \AAM\Helpers\NumberHelper::fileSize($bytes);
}

/**
 * Render a status badge span.
 * Maps DB status values to CSS classes defined in app.css.
 */
function fmt_status(string $status): string
{
    $label = ucfirst(str_replace('_', ' ', $status));
    return '<span class="status-badge status-' . e($status) . '">' . e($label) . '</span>';
}

/**
 * Render a quality grade badge.
 */
function fmt_grade(string $grade): string
{
    $map = [
        'A'        => 'success',
        'B'        => 'primary',
        'C'        => 'warning',
        'Ungraded' => 'secondary',
    ];
    $cls = $map[$grade] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . e($grade) . '</span>';
}

/**
 * Truncate text for table cells.
 */
function fmt_short(string $text, int $len = 60): string
{
    return \AAM\Helpers\StringHelper::truncate($text, $len);
}

/**
 * Render a yes/no icon.
 */
function fmt_bool(mixed $value): string
{
    return $value
        ? '<i class="bi bi-check-circle-fill text-success" aria-label="Yes"></i>'
        : '<i class="bi bi-x-circle-fill text-danger" aria-label="No"></i>';
}

/**
 * Format a number as short: 1500 → "1.5K"
 */
function fmt_short_num(float $value): string
{
    return \AAM\Helpers\NumberHelper::shortFormat($value);
}
