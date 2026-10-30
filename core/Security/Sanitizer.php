<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Security/Sanitizer.php
 * Purpose  : Input sanitizer.
 *            Strips HTML, normalizes whitespace, removes
 *            null bytes and control characters from all
 *            user-supplied input before use.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Security;

class Sanitizer
{
    /**
     * Clean a single value.
     * Handles scalars, arrays (recursively), and nulls.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::cleanArray($value);
        }

        if (!is_string($value)) {
            return $value;
        }

        return self::cleanString($value);
    }

    /**
     * Clean a string:
     *  1. Remove null bytes (prevent null byte injection)
     *  2. Strip control characters
     *  3. Strip HTML/PHP tags
     *  4. Normalize whitespace
     *  5. Trim leading/trailing whitespace
     */
    public static function cleanString(string $value): string
    {
        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Remove control characters except newline (\n), carriage return (\r), tab (\t)
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // Strip HTML and PHP tags
        $value = strip_tags($value);

        // Normalize whitespace (multiple spaces → single space)
        $value = preg_replace('/\s+/', ' ', $value);

        // Trim
        return trim($value);
    }

    /**
     * Clean an array of values recursively.
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public static function cleanArray(array $data): array
    {
        $cleaned = [];
        foreach ($data as $key => $value) {
            $cleanKey = is_string($key) ? self::cleanString($key) : $key;
            $cleaned[$cleanKey] = self::clean($value);
        }
        return $cleaned;
    }

    /**
     * Escape output for HTML context (use for all displayed values).
     *
     * @param mixed $value
     */
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape a value for use inside a JavaScript string.
     */
    public static function escapeJs(string $value): string
    {
        return addslashes(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Sanitize a filename — keep only alphanumeric, dash, underscore, dot.
     */
    public static function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/\.{2,}/', '.', $filename); // No double dots
        return trim($filename, '._');
    }

    /**
     * Sanitize an integer input.
     *
     * @param mixed $value
     */
    public static function int(mixed $value, int $default = 0): int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        return $filtered !== false ? (int) $filtered : $default;
    }

    /**
     * Sanitize a float input.
     *
     * @param mixed $value
     */
    public static function float(mixed $value, float $default = 0.0): float
    {
        $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
        return $filtered !== false ? (float) $filtered : $default;
    }

    /**
     * Sanitize an email address.
     */
    public static function email(string $value): string
    {
        return (string) filter_var(trim($value), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitize a URL.
     */
    public static function url(string $value): string
    {
        return (string) filter_var(trim($value), FILTER_SANITIZE_URL);
    }

    /**
     * Allow only alphanumeric characters and specified extras.
     */
    public static function alphanumeric(string $value, string $extra = ''): string
    {
        $allowed = 'a-zA-Z0-9' . preg_quote($extra, '/');
        return preg_replace('/[^' . $allowed . ']/', '', $value);
    }
}
