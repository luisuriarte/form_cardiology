<?php

/**
 * CardLists – safe helpers for the Cardiology card_* list catalogs.
 *
 * Only lists registered in list_options (list_id = 'lists') and matching
 * ^card_[a-z0-9_]+$ are manageable from the settings screen. Option IDs are
 * generated once and never changed. Deletion is intentionally not supported.
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

final class CardLists
{
    public const MAX_OPTIONS = 200;
    public const LIST_PATTERN = '/^card_[a-z0-9_]+$/';

    /** @var array<string,list<array{table:string,column:string}>>|null */
    private static ?array $columnCache = null;

    /**
     * Return every registered Cardiology list as list_id => title.
     *
     * @return array<string,string>
     */
    public static function registeredLists(): array
    {
        $lists = [];
        $res = sqlStatement(
            "SELECT option_id, title
               FROM `list_options`
              WHERE list_id = 'lists'
                AND option_id LIKE 'card_%'
              ORDER BY seq ASC, title ASC",
            []
        );

        while ($row = sqlFetchArray($res)) {
            $listId = (string)$row['option_id'];
            if (!preg_match(self::LIST_PATTERN, $listId)) {
                continue;
            }
            $lists[$listId] = (string)$row['title'];
        }

        if ($lists === []) {
            // Resilient fallback: discover distinct card_* list IDs if registration rows in 'lists' are missing
            $distinct = sqlStatement(
                "SELECT DISTINCT list_id
                   FROM `list_options`
                  WHERE list_id LIKE 'card_%'
                  ORDER BY list_id ASC",
                []
            );
            while ($row = sqlFetchArray($distinct)) {
                $lid = (string)$row['list_id'];
                if (preg_match(self::LIST_PATTERN, $lid)) {
                    $lists[$lid] = ucwords(str_replace(['card_', '_'], ['', ' '], $lid));
                }
            }
        }

        return $lists;
    }

    /**
     * Check that a list is registered and has a safe identifier.
     */
    public static function isRegisteredList(string $listId): bool
    {
        if (!preg_match(self::LIST_PATTERN, $listId)) {
            return false;
        }

        $row = sqlQuery(
            "SELECT option_id FROM `list_options`
              WHERE list_id = 'lists' AND option_id = ?
              LIMIT 1",
            [$listId]
        );

        return $row && (string)$row['option_id'] === $listId;
    }

    /**
     * Assert that a list may be managed.
     */
    public static function assertRegisteredList(string $listId): void
    {
        if (!self::isRegisteredList($listId)) {
            throw new InvalidArgumentException('Unknown Cardiology list.');
        }
    }

    /**
     * Load all options of one list, ordered for display.
     *
     * @return list<array<string,mixed>>
     */
    public static function options(string $listId): array
    {
        self::assertRegisteredList($listId);

        $options = [];
        $res = sqlStatement(
            'SELECT option_id, title, seq, is_default, activity
               FROM `list_options`
              WHERE list_id = ?
              ORDER BY seq ASC, title ASC',
            [$listId]
        );

        while ($row = sqlFetchArray($res)) {
            $options[] = $row;
        }

        return $options;
    }

    /**
     * Count how many list options exist.
     */
    public static function countOptions(string $listId): int
    {
        self::assertRegisteredList($listId);

        $row = sqlQuery(
            'SELECT COUNT(*) AS total FROM `list_options` WHERE list_id = ?',
            [$listId]
        );

        return (int)($row['total'] ?? 0);
    }

    /**
     * Find one option inside a list.
     *
     * @return array<string,mixed>|null
     */
    public static function findOption(string $listId, string $optionId): ?array
    {
        self::assertRegisteredList($listId);

        $row = sqlQuery(
            'SELECT option_id, title, seq, is_default, activity
               FROM `list_options`
              WHERE list_id = ? AND option_id = ?
              LIMIT 1',
            [$listId, $optionId]
        );

        return ($row && isset($row['option_id'])) ? $row : null;
    }

    /**
     * Generate a stable, unique option_id from a human title.
     */
    public static function generateOptionId(string $listId, string $title): string
    {
        self::assertRegisteredList($listId);

        $ascii = $title;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
            if ($converted !== false) {
                $ascii = $converted;
            }
        }

        $base = strtolower($ascii);
        $base = (string)preg_replace('/[^a-z0-9]+/', '_', $base);
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'option';
        }

        $base = substr($base, 0, 24);
        $candidate = $base;
        $suffix = 2;

        while (self::findOption($listId, $candidate) !== null) {
            $suffixText = '_' . $suffix;
            $candidate = substr($base, 0, 31 - strlen($suffixText)) . $suffixText;
            ++$suffix;

            if ($suffix > 9999) {
                throw new InvalidArgumentException('Unable to generate a unique option identifier.');
            }
        }

        return $candidate;
    }

    /**
     * Return the next display sequence for a list.
     */
    public static function nextSeq(string $listId): int
    {
        self::assertRegisteredList($listId);

        $row = sqlQuery(
            'SELECT MAX(seq) AS max_seq FROM `list_options` WHERE list_id = ?',
            [$listId]
        );

        return (int)($row['max_seq'] ?? 0) + 10;
    }

    /**
     * Count records that reference every option of a list.
     *
     * Columns are discovered through information_schema.COLUMNS comments using
     * the pattern "list_id card_example". Identifiers are validated before they
     * are interpolated because MySQL cannot bind table or column names.
     *
     * @return array<string,int>
     */
    public static function usageCounts(string $listId): array
    {
        self::assertRegisteredList($listId);

        $counts = [];
        foreach (self::referencingColumns($listId) as $column) {
            $table = (string)$column['table'];
            $name = (string)$column['column'];

            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)
                || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                continue;
            }

            $res = sqlStatement(
                "SELECT `{$name}` AS option_value, COUNT(*) AS total
                   FROM `{$table}`
                  WHERE `{$name}` IS NOT NULL AND `{$name}` <> ''
                  GROUP BY `{$name}`",
                []
            );

            while ($row = sqlFetchArray($res)) {
                $optionId = (string)($row['option_value'] ?? '');
                if ($optionId === '') {
                    continue;
                }
                $counts[$optionId] = (int)($counts[$optionId] ?? 0) + (int)($row['total'] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * Count records that reference one option of a list.
     */
    public static function optionUsage(string $listId, string $optionId): int
    {
        $counts = self::usageCounts($listId);
        return (int)($counts[$optionId] ?? 0);
    }

    /**
     * Number of columns that reference this list.
     */
    public static function columnUsage(string $listId): int
    {
        return count(self::referencingColumns($listId));
    }

    /**
     * Discover columns whose COMMENT contains "list_id <list_id>".
     *
     * @return list<array{table:string,column:string}>
     */
    public static function referencingColumns(string $listId): array
    {
        self::assertRegisteredList($listId);

        $all = self::columnCache();
        return $all[$listId] ?? [];
    }

    /**
     * @return array<string,list<array{table:string,column:string}>>
     */
    private static function columnCache(): array
    {
        if (self::$columnCache !== null) {
            return self::$columnCache;
        }

        self::$columnCache = [];
        $res = sqlStatement(
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_COMMENT
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND COLUMN_COMMENT LIKE ?',
            ['%list_id%']
        );

        while ($row = sqlFetchArray($res)) {
            $comment = (string)($row['COLUMN_COMMENT'] ?? '');
            if (!preg_match('/list_id\s+(card_[a-z0-9_]+)/', $comment, $match)) {
                continue;
            }

            $listId = $match[1];
            self::$columnCache[$listId][] = [
                'table' => (string)$row['TABLE_NAME'],
                'column' => (string)$row['COLUMN_NAME'],
            ];
        }

        return self::$columnCache;
    }
}
