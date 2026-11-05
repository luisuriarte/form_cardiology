<?php

/**
 * CardDate - OpenEMR-native date/datetime handling for cardiology cards
 *
 * Display uses OpenEMR's own formatting (DateFormatterUtils), which respects
 * the user's globals: date_display_format (Y-m-d, m/d/Y, d/m/Y) and
 * time_display_format (24h / 12h). Inputs are rendered as text with the
 * core `datepicker` / `datetimepicker` classes (jquery-datetimepicker,
 * asset key 'datetime-picker'), never as native type="date" /
 * type="datetime-local" which follow the browser locale instead.
 *
 * Parsing accepts the configured display format as well as ISO strings,
 * and always normalizes to Y-m-d / Y-m-d H:i:s for storage.
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Forms\Cardiology;

use OpenEMR\Services\Utils\DateFormatterUtils;

final class CardDate
{
    /**
     * Formats a stored Y-m-d date for display in the user's format.
     */
    public static function displayDate(?string $ymd): string
    {
        if ($ymd === null || trim($ymd) === '') {
            return '';
        }

        $date = trim(substr($ymd, 0, 10));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return '';
        }

        return DateFormatterUtils::oeFormatShortDate($date, true);
    }

    /**
     * Formats a stored Y-m-d H:i:s datetime for display in the user's format.
     */
    public static function displayDateTime(?string $ymdHis): string
    {
        if ($ymdHis === null || trim($ymdHis) === '') {
            return '';
        }

        $value = trim(str_replace('T', ' ', $ymdHis));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value)) {
            return '';
        }

        if (strlen($value) === 10) {
            return DateFormatterUtils::oeFormatShortDate($value, true);
        }

        return DateFormatterUtils::oeFormatDateTime($value . (strlen($value) === 16 ? ':00' : ''));
    }

    /**
     * Formats a stored H:i[:s] time for display in the user's format.
     */
    public static function displayTime(?string $his): string
    {
        if ($his === null || trim($his) === '') {
            return '';
        }

        $value = trim($his);
        if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
            return '';
        }

        return DateFormatterUtils::oeFormatTime($value);
    }

    /**
     * Parses a date input (configured display format or ISO) to Y-m-d.
     * Returns null for empty input; throws on invalid input.
     *
     * @throws \InvalidArgumentException
     */
    public static function parseDate(mixed $value, string $field): ?string
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        $raw = trim((string)$value);
        // Accept a full datetime and keep only the date part.
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $raw)) {
            $raw = substr(str_replace('T', ' ', $raw), 0, 10);
        }

        $iso = self::toIsoDate($raw);
        if ($iso === null) {
            throw new \InvalidArgumentException(
                sprintf(xlt('Invalid date format in field %s. Expected Y-m-d'), $field)
            );
        }

        return $iso;
    }

    /**
     * Parses a datetime input (configured display format or ISO) to Y-m-d H:i:s.
     * Required: throws on empty or invalid input.
     *
     * @throws \InvalidArgumentException
     */
    public static function parseDateTime(mixed $value, string $field): string
    {
        if ($value === null || trim((string)$value) === '') {
            throw new \InvalidArgumentException(sprintf("Field '%s' is required.", $field));
        }

        $raw = trim((string)$value);

        // ISO / datetime-local first.
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $format) {
            $dt = \DateTime::createFromFormat($format, $raw);
            if ($dt !== false) {
                $errors = \DateTime::getLastErrors();
                if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }
        }

        // Configured display format (d/m/Y, m/d/Y, Y-m-d with 12h/24h time).
        $dt = DateFormatterUtils::dateStringToDateTime($raw);
        if ($dt !== false && str_contains($raw, ':')) {
            return $dt->format('Y-m-d H:i:s');
        }

        throw new \InvalidArgumentException(
            sprintf(xlt('Invalid date format in field %s. Expected Y-m-d H:i[:s]'), $field)
        );
    }

    /**
     * Parses a time input (24h or configured 12h format) to H:i:s.
     * Returns null for empty input; throws on invalid input.
     *
     * @throws \InvalidArgumentException
     */
    public static function parseTime(mixed $value, string $field): ?string
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        $raw = trim((string)$value);

        foreach (['H:i:s', 'H:i', 'g:i:s a', 'g:i a', 'h:i:s a', 'h:i a'] as $format) {
            $dt = \DateTime::createFromFormat($format, $raw);
            if ($dt !== false && $dt->format($format) === $raw) {
                return $dt->format('H:i:s');
            }
        }

        throw new \InvalidArgumentException(
            sprintf(xlt('Invalid time format in field %s. Expected HH:MM'), $field)
        );
    }

    /**
     * Converts a date string in the configured display format (or ISO)
     * to Y-m-d, or returns null when it cannot be parsed.
     */
    private static function toIsoDate(string $raw): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $dt = \DateTime::createFromFormat('Y-m-d', $raw);
            return ($dt !== false && $dt->format('Y-m-d') === $raw) ? $raw : null;
        }

        $iso = DateFormatterUtils::DateToYYYYMMDD($raw);
        if ($iso !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso)) {
            $dt = \DateTime::createFromFormat('Y-m-d', $iso);
            return ($dt !== false && $dt->format('Y-m-d') === $iso) ? $iso : null;
        }

        return null;
    }
}
