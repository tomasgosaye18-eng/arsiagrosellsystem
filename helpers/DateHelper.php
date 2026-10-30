<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : helpers/DateHelper.php
 * Purpose  : Date and time formatting helpers.
 *            Uses Ethiopia timezone (Africa/Addis_Ababa).
 *            Provides human-readable date/time display.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Helpers;

class DateHelper
{
    /**
     * Format a date for display: 25/07/2026
     *
     * @param string|null $date  MySQL date string (Y-m-d) or timestamp
     */
    public static function display(?string $date): string
    {
        if (!$date) {
            return '—';
        }
        $dt = self::parse($date);
        return $dt ? $dt->format(DATE_FORMAT_DISPLAY) : '—';
    }

    /**
     * Format a datetime for display: 25/07/2026 14:30
     */
    public static function displayDatetime(?string $datetime): string
    {
        if (!$datetime) {
            return '—';
        }
        $dt = self::parse($datetime);
        return $dt ? $dt->format(DATETIME_FORMAT) : '—';
    }

    /**
     * Format a time for display: 14:30
     */
    public static function displayTime(?string $time): string
    {
        if (!$time) {
            return '—';
        }
        $dt = self::parse($time);
        return $dt ? $dt->format(TIME_FORMAT) : '—';
    }

    /**
     * Format date for MySQL storage: Y-m-d
     */
    public static function toDb(?string $date): ?string
    {
        if (!$date) {
            return null;
        }
        $dt = self::parse($date);
        return $dt ? $dt->format(DATE_FORMAT_INPUT) : null;
    }

    /**
     * Get today's date as a display string.
     */
    public static function today(): string
    {
        return date(DATE_FORMAT_DISPLAY);
    }

    /**
     * Get today's date in MySQL format.
     */
    public static function todayDb(): string
    {
        return date(DATE_FORMAT_INPUT);
    }

    /**
     * Get current date and time in log format.
     */
    public static function now(): string
    {
        return date(DATE_FORMAT_LOG);
    }

    /**
     * Get a human-readable time-ago string.
     * e.g. "2 hours ago", "3 days ago"
     */
    public static function timeAgo(string $datetime): string
    {
        $dt   = self::parse($datetime);
        if (!$dt) {
            return '—';
        }

        $diff = time() - $dt->getTimestamp();

        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $m = (int) round($diff / 60);
            return "{$m} minute" . ($m === 1 ? '' : 's') . " ago";
        }
        if ($diff < 86400) {
            $h = (int) round($diff / 3600);
            return "{$h} hour" . ($h === 1 ? '' : 's') . " ago";
        }
        if ($diff < 604800) {
            $d = (int) round($diff / 86400);
            return "{$d} day" . ($d === 1 ? '' : 's') . " ago";
        }
        if ($diff < 2592000) {
            $w = (int) round($diff / 604800);
            return "{$w} week" . ($w === 1 ? '' : 's') . " ago";
        }

        return self::display($datetime);
    }

    /**
     * Check if a date is in the past.
     */
    public static function isPast(string $date): bool
    {
        $dt = self::parse($date);
        return $dt ? $dt->getTimestamp() < time() : false;
    }

    /**
     * Check if a date is in the future.
     */
    public static function isFuture(string $date): bool
    {
        $dt = self::parse($date);
        return $dt ? $dt->getTimestamp() > time() : false;
    }

    /**
     * Get the difference in days between two dates.
     * Returns negative value if $date is before $from.
     */
    public static function diffInDays(string $date, string $from = 'now'): int
    {
        $dt1 = self::parse($date);
        $dt2 = $from === 'now' ? new \DateTime() : self::parse($from);

        if (!$dt1 || !$dt2) {
            return 0;
        }

        $interval = $dt1->diff($dt2);
        return $interval->invert ? -$interval->days : $interval->days;
    }

    /**
     * Get start and end of a month.
     *
     * @return array{start: string, end: string}
     */
    public static function monthRange(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));
        return ['start' => $start, 'end' => $end];
    }

    /**
     * Parse a date string into a DateTime object.
     */
    private static function parse(string $datetime): ?\DateTime
    {
        // Try multiple formats
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd/m/Y', 'H:i:s', 'H:i', 'U'];

        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $datetime);
            if ($dt !== false) {
                return $dt;
            }
        }

        // Try strtotime as fallback
        $ts = strtotime($datetime);
        if ($ts !== false) {
            return (new \DateTime())->setTimestamp($ts);
        }

        return null;
    }
}
