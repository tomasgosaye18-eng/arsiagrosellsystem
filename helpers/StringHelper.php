<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : helpers/StringHelper.php
 * Purpose  : String manipulation helpers.
 *            Slug generation, truncation, case conversion,
 *            reference number generation.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Helpers;

class StringHelper
{
    /**
     * Truncate a string to a maximum length with ellipsis.
     *
     * @param string $text
     * @param int    $length  Maximum characters
     * @param string $suffix  Appended when truncated
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
    }

    /**
     * Generate a URL-safe slug from a string.
     * e.g. "North Campus Farm #2" → "north-campus-farm-2"
     */
    public static function slug(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * Convert snake_case to Title Case.
     * e.g. "farm_manager" → "Farm Manager"
     */
    public static function titleCase(string $text): string
    {
        return ucwords(str_replace('_', ' ', $text));
    }

    /**
     * Convert camelCase to snake_case.
     * e.g. "farmManager" → "farm_manager"
     */
    public static function snakeCase(string $text): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($text)));
    }

    /**
     * Generate a system reference number.
     * e.g. generateRef('DSP', 1, 42) → "DSP-2026-000042"
     *
     * @param string $prefix    Document prefix: DSP, TRF, INV, RCP
     * @param int    $year      Year (uses current year if 0)
     * @param int    $sequence  Sequential number
     * @param int    $padLength Zero-padding length
     */
    public static function generateRef(
        string $prefix,
        int    $sequence,
        int    $year      = 0,
        int    $padLength = 6
    ): string {
        $year = $year ?: (int) date('Y');
        $seq  = str_pad((string) $sequence, $padLength, '0', STR_PAD_LEFT);
        return "{$prefix}-{$year}-{$seq}";
    }

    /**
     * Generate a unique batch number.
     * e.g. generateBatchNumber(1, 42) → "BATCH-2026-FRM1-000042"
     */
    public static function generateBatchNumber(int $farmId, int $sequence): string
    {
        return 'BATCH-' . date('Y') . '-FRM' . $farmId . '-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Mask a sensitive value, showing only first/last characters.
     * e.g. maskString('password123') → 'pa*******23'
     */
    public static function mask(string $value, int $visible = 2): string
    {
        $len = strlen($value);
        if ($len <= $visible * 2) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, $visible)
            . str_repeat('*', $len - ($visible * 2))
            . substr($value, -$visible);
    }

    /**
     * Convert bytes to readable file size.
     */
    public static function fileSize(int $bytes): string
    {
        return NumberHelper::fileSize($bytes);
    }

    /**
     * Generate a random alphanumeric token.
     */
    public static function randomToken(int $length = 32): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    /**
     * Highlight search terms in a string with <mark> tags.
     */
    public static function highlight(string $text, string $term): string
    {
        if ($term === '') {
            return htmlspecialchars($text);
        }
        $safe = htmlspecialchars($text);
        $term = preg_quote(htmlspecialchars($term), '/');
        return preg_replace("/({$term})/i", '<mark>$1</mark>', $safe);
    }
}
