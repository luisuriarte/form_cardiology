<?php

/**
 * CardSettings – validated clinic and per-user preferences for Cardiology.
 *
 * All setting keys and their validation rules live in DEFINITIONS. Unknown
 * keys are rejected instead of being stored blindly. Clinic settings use
 * user_id = 0, while user settings use the current OpenEMR users.id.
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

use InvalidArgumentException;
use RuntimeException;

final class CardSettings
{
    public const SCOPE_CLINIC = 'clinic';
    public const SCOPE_USER = 'user';

    /**
     * Single source of truth for every supported setting.
     *
     * type: int, text or option
     * scope: clinic or user
     * default: value used when no row exists
     * list: list_options list_id for option settings
     */
    private const DEFINITIONS = [
        'chart_max_studies' => [
            'scope' => self::SCOPE_CLINIC,
            'type' => 'int',
            'min' => 10,
            'max' => 200,
            'default' => 60,
        ],
        'fitness_validity_months' => [
            'scope' => self::SCOPE_CLINIC,
            'type' => 'int',
            'min' => 1,
            'max' => 36,
            'default' => 12,
        ],
        'pdf_footer_text' => [
            'scope' => self::SCOPE_CLINIC,
            'type' => 'text',
            'max_length' => 500,
            'default' => '',
        ],
        'default_stress_protocol' => [
            'scope' => self::SCOPE_USER,
            'type' => 'option',
            'list' => 'card_stress_protocol',
            'default' => '',
        ],
        'default_lvef_method' => [
            'scope' => self::SCOPE_USER,
            'type' => 'option',
            'list' => 'card_ef_method',
            'default' => '',
        ],
    ];

    /**
     * Cached table-existence probe for installations that have not run the
     * upgrade script yet. The settings screens must still render defaults.
     */
    private static ?bool $tableExists = null;

    /**
     * Return the raw definition map (read-only).
     *
     * @return array<string,array<string,mixed>>
     */
    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * Whether the card_settings table exists. The upgrade script creates it.
     */
    public static function isInstalled(): bool
    {
        return self::hasTable();
    }

    /**
     * Read a setting from the correct scope automatically.
     *
     * @param string   $key    Setting identifier.
     * @param int|null $userId User id for user-scoped settings.
     */
    public static function get(string $key, ?int $userId = null): string
    {
        $definition = self::definition($key);
        $scope = (string)$definition['scope'];

        $storedUserId = $scope === self::SCOPE_CLINIC
            ? 0
            : self::resolveUserId($userId);

        if (!self::hasTable() || $storedUserId < 0) {
            return self::defaultFor($key);
        }

        $row = sqlQuery(
            'SELECT setting_value
               FROM `card_settings`
              WHERE scope = ? AND user_id = ? AND setting_key = ?
              LIMIT 1',
            [$scope, $storedUserId, $key]
        );

        if (!$row || !array_key_exists('setting_value', $row)) {
            return self::defaultFor($key);
        }

        return (string)($row['setting_value'] ?? '');
    }

    /**
     * Read a clinic-scoped setting.
     */
    public static function getClinic(string $key): string
    {
        self::assertScope($key, self::SCOPE_CLINIC);
        return self::get($key, 0);
    }

    /**
     * Read a user-scoped setting.
     */
    public static function getPersonal(string $key, ?int $userId = null): string
    {
        self::assertScope($key, self::SCOPE_USER);
        return self::get($key, $userId);
    }

    /**
     * Read an integer setting, clamped to its declared bounds.
     */
    public static function getInt(string $key, ?int $userId = null): int
    {
        $definition = self::definition($key);
        if (($definition['type'] ?? '') !== 'int') {
            throw new InvalidArgumentException(sprintf("Setting '%s' is not an integer.", $key));
        }

        $value = (int)self::get($key, $userId);
        $min = (int)($definition['min'] ?? PHP_INT_MIN);
        $max = (int)($definition['max'] ?? PHP_INT_MAX);

        return max($min, min($max, $value));
    }

    /**
     * Read a clinic integer setting.
     */
    public static function getClinicInt(string $key): int
    {
        self::assertScope($key, self::SCOPE_CLINIC);
        return self::getInt($key, 0);
    }

    /**
     * Validate and persist one setting.
     *
     * @return string The normalized value that was stored.
     */
    public static function set(
        string $key,
        mixed $value,
        ?int $userId = null,
        ?string $updatedBy = null
    ): string {
        $definition = self::definition($key);
        $scope = (string)$definition['scope'];
        $storedUserId = $scope === self::SCOPE_CLINIC
            ? 0
            : self::resolveUserId($userId);

        if ($scope === self::SCOPE_USER && $storedUserId <= 0) {
            throw new InvalidArgumentException('A valid user is required for this setting.');
        }

        if (!self::hasTable()) {
            throw new RuntimeException('The card_settings table is not installed.');
        }

        $oldValue = self::get($key, $storedUserId);
        $newValue = self::normalize($key, $value, $oldValue);

        $existing = sqlQuery(
            'SELECT id FROM `card_settings`
              WHERE scope = ? AND user_id = ? AND setting_key = ?
              LIMIT 1',
            [$scope, $storedUserId, $key]
        );

        if ($existing && isset($existing['id'])) {
            sqlStatement(
                'UPDATE `card_settings`
                    SET setting_value = ?, updated_by = ?
                  WHERE id = ?',
                [$newValue, $updatedBy, (int)$existing['id']]
            );
        } else {
            sqlStatement(
                'INSERT INTO `card_settings`
                    (scope, user_id, setting_key, setting_value, updated_by)
                 VALUES (?, ?, ?, ?, ?)',
                [$scope, $storedUserId, $key, $newValue, $updatedBy]
            );
        }

        return $newValue;
    }

    /**
     * Return every setting for one scope as key => value.
     *
     * @return array<string,string>
     */
    public static function allForScope(string $scope, ?int $userId = null): array
    {
        if (!in_array($scope, [self::SCOPE_CLINIC, self::SCOPE_USER], true)) {
            throw new InvalidArgumentException('Unknown settings scope.');
        }

        $values = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            if ((string)$definition['scope'] !== $scope) {
                continue;
            }
            $values[$key] = self::get($key, $userId);
        }

        return $values;
    }

    /**
     * Load active list options plus one optional stored value.
     *
     * @param  string      $listId       list_options list_id.
     * @param  string|null $currentValue Stored value that must remain visible.
     * @return list<array{option_id:string,title:string,activity:int}>
     */
    public static function listOptions(string $listId, ?string $currentValue = null): array
    {
        if (!preg_match('/^card_[a-z0-9_]+$/', $listId)) {
            throw new InvalidArgumentException('Invalid list identifier.');
        }

        $currentValue = $currentValue !== null ? trim($currentValue) : null;
        $options = [];
        $res = sqlStatement(
            'SELECT option_id, title, activity
               FROM `list_options`
              WHERE list_id = ?
              ORDER BY seq ASC, title ASC',
            [$listId]
        );

        while ($row = sqlFetchArray($res)) {
            $optionId = (string)$row['option_id'];
            if ((int)$row['activity'] === 1 || $optionId === $currentValue) {
                $options[] = [
                    'option_id' => $optionId,
                    'title' => (string)$row['title'],
                    'activity' => (int)$row['activity'],
                ];
            }
        }

        return $options;
    }

    /**
     * Normalize a value according to its definition.
     */
    private static function normalize(string $key, mixed $value, string $currentValue): string
    {
        $definition = self::definition($key);
        $type = (string)$definition['type'];

        if ($type === 'int') {
            $str = trim((string)$value);
            if ($str === '' || filter_var($str, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException(sprintf("Setting '%s' must be an integer.", $key));
            }

            $intValue = (int)$str;
            $min = (int)$definition['min'];
            $max = (int)$definition['max'];
            if ($intValue < $min || $intValue > $max) {
                throw new InvalidArgumentException(sprintf(
                    "Setting '%s' must be between %d and %d.",
                    $key,
                    $min,
                    $max
                ));
            }

            return (string)$intValue;
        }

        if ($type === 'text') {
            $str = trim((string)$value);
            $maxLength = (int)($definition['max_length'] ?? 0);
            if ($maxLength > 0 && mb_strlen($str, 'UTF-8') > $maxLength) {
                throw new InvalidArgumentException(sprintf(
                    "Setting '%s' must not exceed %d characters.",
                    $key,
                    $maxLength
                ));
            }

            return $str;
        }

        if ($type === 'option') {
            $str = trim((string)$value);
            if ($str === '') {
                return '';
            }

            $listId = (string)$definition['list'];
            $row = sqlQuery(
                'SELECT option_id, activity
                   FROM `list_options`
                  WHERE list_id = ? AND option_id = ?
                  LIMIT 1',
                [$listId, $str]
            );

            $isActive = $row && (int)($row['activity'] ?? 0) === 1;
            $isCurrent = $currentValue !== '' && $str === $currentValue && $row;

            if (!$isActive && !$isCurrent) {
                throw new InvalidArgumentException(sprintf(
                    "Setting '%s' contains an invalid option for list '%s'.",
                    $key,
                    $listId
                ));
            }

            return (string)$row['option_id'];
        }

        throw new InvalidArgumentException(sprintf("Setting '%s' has an unsupported type.", $key));
    }

    /**
     * Return and validate one definition or throw for an unknown key.
     *
     * @return array<string,mixed>
     */
    private static function definition(string $key): array
    {
        if (!isset(self::DEFINITIONS[$key])) {
            throw new InvalidArgumentException(sprintf("Unknown Cardiology setting '%s'.", $key));
        }

        return self::DEFINITIONS[$key];
    }

    private static function assertScope(string $key, string $expected): void
    {
        $definition = self::definition($key);
        if ((string)$definition['scope'] !== $expected) {
            throw new InvalidArgumentException(sprintf(
                "Setting '%s' does not belong to the %s scope.",
                $key,
                $expected
            ));
        }
    }

    private static function defaultFor(string $key): string
    {
        return (string)self::definition($key)['default'];
    }

    private static function resolveUserId(?int $userId): int
    {
        if ($userId !== null) {
            return $userId;
        }

        return (int)($_SESSION['authUserID'] ?? 0);
    }

    private static function hasTable(): bool
    {
        if (self::$tableExists === null) {
            $row = sqlQuery("SHOW TABLES LIKE 'card_settings'");
            self::$tableExists = !empty($row);
        }

        return self::$tableExists;
    }
}
