<?php

/**
 * CardInput – input sanitization and validation helper for cardiology cards.
 *
 * All validation methods throw \InvalidArgumentException with the offending field name
 * on invalid input.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Forms\Cardiology;

use DateTime;
use InvalidArgumentException;

final class CardInput
{
    /**
     * Sanitize and validate a decimal input.
     *
     * - Replaces decimal commas with dots (Argentine keyboard convention).
     * - Checks numeric validity.
     * - Enforces optional min/max bounds.
     * - Formats output with 3 decimal places.
     *
     * @param  mixed       $v     Raw input value.
     * @param  string      $field Field name for error reporting.
     * @param  float|null  $min   Optional minimum bound.
     * @param  float|null  $max   Optional maximum bound.
     * @return string|null Formatted decimal string or null if empty.
     * @throws InvalidArgumentException When input is non-numeric or out of bounds.
     */
    public static function decimal(mixed $v, string $field, ?float $min = null, ?float $max = null): ?string
    {
        if ($v === null) {
            return null;
        }

        $str = trim((string)$v);
        if ($str === '') {
            return null;
        }

        // Support Argentine comma decimals
        $str = str_replace(',', '.', $str);

        if (!is_numeric($str)) {
            throw new InvalidArgumentException(sprintf("Field '%s' must be a valid number.", $field));
        }

        $f = (float)$str;

        if ($min !== null && $f < $min) {
            throw new InvalidArgumentException(sprintf("Field '%s' must be greater than or equal to %s.", $field, $min));
        }

        if ($max !== null && $f > $max) {
            throw new InvalidArgumentException(sprintf("Field '%s' must be less than or equal to %s.", $field, $max));
        }

        return number_format($f, 3, '.', '');
    }

    /**
     * Sanitize and validate an integer input.
     *
     * @param  mixed     $v     Raw input value.
     * @param  string    $field Field name for error reporting.
     * @param  int|null  $min   Optional minimum bound.
     * @param  int|null  $max   Optional maximum bound.
     * @return int|null  Validated integer or null if empty.
     * @throws InvalidArgumentException When input is non-integer or out of bounds.
     */
    public static function int(mixed $v, string $field, ?int $min = null, ?int $max = null): ?int
    {
        if ($v === null) {
            return null;
        }

        $str = trim((string)$v);
        if ($str === '') {
            return null;
        }

        $val = filter_var($str, FILTER_VALIDATE_INT);
        if ($val === false) {
            throw new InvalidArgumentException(sprintf("Field '%s' must be an integer.", $field));
        }

        if ($min !== null && $val < $min) {
            throw new InvalidArgumentException(sprintf("Field '%s' must be greater than or equal to %d.", $field, $min));
        }

        if ($max !== null && $val > $max) {
            throw new InvalidArgumentException(sprintf("Field '%s' must be less than or equal to %d.", $field, $max));
        }

        return $val;
    }

    /**
     * Sanitize a free-text input.
     *
     * @param  mixed       $v Raw input value.
     * @return string|null Trimmed text or null if empty.
     */
    public static function text(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        $trimmed = trim((string)$v);
        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Sanitize and validate a date/time input.
     *
     * Accepts 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'.
     *
     * @param  mixed  $v     Raw input value.
     * @param  string $field Field name for error reporting.
     * @return string 'Y-m-d H:i:s' formatted string.
     * @throws InvalidArgumentException If empty or invalid format.
     */
    public static function dateTime(mixed $v, string $field): string
    {
        if ($v === null) {
            throw new InvalidArgumentException(sprintf("Field '%s' is required.", $field));
        }

        $str = trim((string)$v);
        if ($str === '') {
            throw new InvalidArgumentException(sprintf("Field '%s' is required.", $field));
        }

        $formats = ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $str);
            if ($dt !== false) {
                $err = DateTime::getLastErrors();
                if ($err === false || ($err['warning_count'] === 0 && $err['error_count'] === 0)) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }
        }

        throw new InvalidArgumentException(sprintf("Field '%s' must be a valid date and time.", $field));
    }

    /**
     * Validate that a value exists in OpenEMR list_options with active = 1.
     *
     * @param  string      $listId OpenEMR list identifier (e.g. 'card_valve_grade').
     * @param  mixed       $v      Option ID to validate.
     * @param  string      $field  Field name for error reporting.
     * @return string|null Validated option ID or null if empty.
     * @throws InvalidArgumentException If non-empty value does not exist in list_options.
     */
    public static function listOption(string $listId, mixed $v, string $field): ?string
    {
        if ($v === null) {
            return null;
        }

        $str = trim((string)$v);
        if ($str === '') {
            return null;
        }

        $row = sqlQuery(
            'SELECT option_id FROM `list_options` WHERE list_id = ? AND option_id = ? AND activity = 1 LIMIT 1',
            [$listId, $str]
        );

        if (!$row || empty($row['option_id'])) {
            throw new InvalidArgumentException(sprintf("Field '%s' contains an invalid option for list '%s'.", $field, $listId));
        }

        return (string)$row['option_id'];
    }

    /**
     * Sanitize a boolean flag (e.g. checkbox).
     *
     * @param  mixed $v Raw input value.
     * @return int   1 if checked/truthy, 0 otherwise.
     */
    public static function flag(mixed $v): int
    {
        return !empty($v) ? 1 : 0;
    }
}

