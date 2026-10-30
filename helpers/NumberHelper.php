<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : helpers/NumberHelper.php
 * Purpose  : Number and currency formatting helpers.
 *            Formats ETB currency, quantities, percentages.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Helpers;

class NumberHelper
{
    /**
     * Format a monetary value in Ethiopian Birr.
     * e.g. 1234.56 → "Br 1,234.56"
     *
     * @param float|string|null $amount
     * @param bool              $symbol  Include "Br" prefix
     */
    public static function currency(float|string|null $amount, bool $symbol = true): string
    {
        if ($amount === null || $amount === '') {
            return $symbol ? 'Br 0.00' : '0.00';
        }

        $formatted = number_format((float) $amount, 2, '.', ',');
        return $symbol ? 'Br ' . $formatted : $formatted;
    }

    /**
     * Format a quantity with the appropriate unit abbreviation.
     * e.g. formatQty(15.500, 'kg') → "15.500 kg"
     *
     * @param float|string|null $quantity
     * @param string            $unit      Unit abbreviation
     * @param int               $decimals
     */
    public static function quantity(
        float|string|null $quantity,
        string            $unit     = '',
        int               $decimals = 3
    ): string {
        if ($quantity === null) {
            return '—';
        }

        $formatted = number_format((float) $quantity, $decimals, '.', ',');
        return $unit ? "{$formatted} {$unit}" : $formatted;
    }

    /**
     * Format a percentage value.
     * e.g. 15.5 → "15.5%"
     */
    public static function percent(float|string|null $value, int $decimals = 1): string
    {
        if ($value === null) {
            return '—';
        }
        return number_format((float)$value, $decimals) . '%';
    }

    /**
     * Round a number to a given precision.
     */
    public static function round(float $value, int $precision = 2): float
    {
        return round($value, $precision);
    }

    /**
     * Format a large number with K/M suffix.
     * e.g. 1500 → "1.5K", 2000000 → "2M"
     */
    public static function shortFormat(float $value): string
    {
        if ($value >= 1_000_000) {
            return number_format($value / 1_000_000, 1) . 'M';
        }
        if ($value >= 1_000) {
            return number_format($value / 1_000, 1) . 'K';
        }
        return number_format($value, 0);
    }

    /**
     * Calculate percentage of a value relative to total.
     * Returns 0 if total is 0 (avoids division by zero).
     */
    public static function percentOf(float $value, float $total, int $decimals = 1): float
    {
        if ($total == 0) {
            return 0.0;
        }
        return round(($value / $total) * 100, $decimals);
    }

    /**
     * Format file size in human-readable form.
     * e.g. 1048576 → "1.00 MB"
     */
    public static function fileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = 0;
        $size  = (float) $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return number_format($size, 2) . ' ' . $units[$i];
    }
}
