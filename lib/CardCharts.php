<?php

/**
 * CardCharts – Longitudinal and study-level charting engine for Cardiology cards.
 *
 * Provides reusable, config-driven data extraction for:
 *   (a) Longitudinal evolution charts across historical patient encounters.
 *   (b) Within-study stage-by-stage trends (e.g. stress test exercise metrics).
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

require_once __DIR__ . '/CardiologyContainer.php';
require_once __DIR__ . '/CardRegistry.php';
require_once __DIR__ . '/CardSettings.php';

final class CardCharts
{
    /**
     * Maximum number of historical studies to plot in longitudinal charts.
     * The clinic can change this between 10 and 200 from the settings screen.
     */
    private static function maxStudies(): int
    {
        return CardSettings::getClinicInt('chart_max_studies');
    }

    /**
     * Load chart definitions from cards/<code>/charts.php.
     *
     * @param string $code Card identifier (e.g. 'echo', 'stress_test', 'ecg')
     * @return array
     */
    public static function getDefinitions(string $code): array
    {
        if (!preg_match('/^[a-z0-9_]+$/', $code)) {
            return [];
        }

        // Verify card is registered in card_modules
        $module = sqlQuery(
            "SELECT code, table_name FROM card_modules
              WHERE code = ? AND card_type = 'study'
              LIMIT 1",
            [$code]
        );
        if (!$module || empty($module['table_name'])) {
            return [];
        }

        $configFile = dirname(__DIR__) . '/cards/' . $code . '/charts.php';
        if (!is_file($configFile)) {
            return [];
        }

        $defs = require $configFile;
        return is_array($defs) ? $defs : [];
    }

    /**
     * Extract longitudinal time-series data for a card across a patient's studies.
     *
     * @param string   $code           Card identifier
     * @param int      $pid            Patient ID (must match current session PID)
     * @param int|null $currentStudyId Open study ID to include even if status is 'draft'
     * @return array
     */
    public static function getLongitudinal(string $code, int $pid, ?int $currentStudyId = null): array
    {
        if ($pid <= 0 || $pid !== CardiologyContainer::getSessionPid()) {
            return [];
        }

        if (!preg_match('/^[a-z0-9_]+$/', $code)) {
            return [];
        }

        $defs = self::getDefinitions($code);
        $chartList = $defs['longitudinal'] ?? [];
        if (empty($chartList) || !is_array($chartList)) {
            return [];
        }

        // Validate table name from card_modules
        $module = sqlQuery(
            "SELECT table_name FROM card_modules
              WHERE code = ? AND card_type = 'study'
              LIMIT 1",
            [$code]
        );
        $tableName = $module['table_name'] ?? '';
        if (!preg_match('/^form_card_[a-z0-9_]+$/', $tableName)) {
            return [];
        }

        // Whitelist all metric fields declared in the chart configuration
        $fieldMap = [];
        foreach ($chartList as $c) {
            foreach ($c['series'] ?? [] as $s) {
                $f = (string)($s['field'] ?? '');
                if (preg_match('/^[a-z0-9_]+$/', $f)) {
                    $fieldMap[$f] = true;
                }
            }
        }
        if (empty($fieldMap)) {
            return [];
        }

        $escapedColumns = array_map(static fn(string $col): string => '`' . $col . '`', array_keys($fieldMap));
        $columnSql = 'id, study_date, status, ' . implode(', ', $escapedColumns);

        $escapedTable = escape_table_name($tableName);

        // Fetch a bounded number of studies: final ones plus the current
        // study when it is still open. The clinic controls the bound.
        $maxStudies = self::maxStudies();
        if ($currentStudyId !== null && $currentStudyId > 0) {
            $sql = "SELECT {$columnSql} FROM {$escapedTable}
                    WHERE pid = ? AND activity = 1 AND (status = 'final' OR id = ?)
                    ORDER BY study_date DESC, id DESC
                    LIMIT " . $maxStudies;
            $res = sqlStatement($sql, [$pid, $currentStudyId]);
        } else {
            $sql = "SELECT {$columnSql} FROM {$escapedTable}
                    WHERE pid = ? AND activity = 1 AND status = 'final'
                    ORDER BY study_date DESC, id DESC
                    LIMIT " . $maxStudies;
            $res = sqlStatement($sql, [$pid]);
        }

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }

        // Reverse so chronological order is ASC (oldest to newest)
        $rows = array_reverse($rows);

        // Extract metadata per study point
        $labels = [];
        $xRaw = [];
        $isDraftList = [];
        $isCurrentList = [];
        $studyIds = [];

        foreach ($rows as $row) {
            $rawDate = (string)($row['study_date'] ?? '');
            $labels[] = self::formatDate($rawDate);
            $xRaw[] = $rawDate;
            $isDraftList[] = (($row['status'] ?? '') !== 'final');
            $isCurrentList[] = ($currentStudyId !== null && (int)($row['id'] ?? 0) === $currentStudyId);
            $studyIds[] = (int)($row['id'] ?? 0);
        }

        // Build data structure for each configured chart
        $result = [];
        foreach ($chartList as $chart) {
            $chartId = (string)($chart['id'] ?? '');
            $title = xl((string)($chart['title'] ?? ''));
            $unit = xl((string)($chart['unit'] ?? ''));
            $datasets = [];
            $hasAnyValue = false;

            foreach ($chart['series'] ?? [] as $series) {
                $field = (string)($series['field'] ?? '');
                if (!isset($fieldMap[$field])) {
                    continue;
                }

                $scale = isset($series['scale']) ? (float)$series['scale'] : 1.0;
                $decimals = isset($series['decimals']) ? (int)$series['decimals'] : 2;
                $seriesData = [];

                foreach ($rows as $row) {
                    $rawVal = $row[$field] ?? null;
                    if ($rawVal === null || $rawVal === '') {
                        $seriesData[] = null;
                    } elseif (is_numeric($rawVal)) {
                        $val = round(((float)$rawVal) * $scale, $decimals);
                        $seriesData[] = $val;
                        $hasAnyValue = true;
                    } else {
                        $seriesData[] = null;
                    }
                }

                $datasets[] = [
                    'field' => $field,
                    'label' => xl((string)($series['label'] ?? $field)),
                    'data' => $seriesData,
                    'unit' => $unit,
                ];
            }

            $result[] = [
                'id' => $chartId,
                'title' => $title,
                'unit' => $unit,
                'type' => 'longitudinal',
                'labels' => $labels,
                'x_raw' => $xRaw,
                'is_draft' => $isDraftList,
                'is_current' => $isCurrentList,
                'study_ids' => $studyIds,
                'datasets' => $datasets,
                'has_data' => $hasAnyValue,
                'y_min' => $chart['y_min'] ?? null,
                'y_max' => $chart['y_max'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Extract within-study continuous stage series (e.g. stress test exercise stages).
     *
     * @param string $code    Card identifier
     * @param int    $studyId Study ID
     * @param int    $pid     Patient ID (must match session)
     * @return array
     */
    public static function getStudySeries(string $code, int $studyId, int $pid): array
    {
        if ($studyId <= 0 || $pid <= 0 || $pid !== CardiologyContainer::getSessionPid()) {
            return [];
        }

        if (!preg_match('/^[a-z0-9_]+$/', $code)) {
            return [];
        }

        $defs = self::getDefinitions($code);
        $studyConfig = $defs['study'] ?? null;
        if (!is_array($studyConfig) || empty($studyConfig['charts'])) {
            return [];
        }

        // Validate card table and ensure study belongs to patient and is active
        $module = sqlQuery(
            "SELECT table_name FROM card_modules
              WHERE code = ? AND card_type = 'study'
              LIMIT 1",
            [$code]
        );
        $tableName = $module['table_name'] ?? '';
        if (!preg_match('/^form_card_[a-z0-9_]+$/', $tableName)) {
            return [];
        }

        $study = sqlQuery(
            'SELECT id, pid FROM ' . escape_table_name($tableName) . ' WHERE id = ? AND pid = ? AND activity = 1',
            [$studyId, $pid]
        );
        if (!$study || !isset($study['id'])) {
            return [];
        }

        // Validate stage table configuration
        $stageTable = (string)($studyConfig['stage_table'] ?? '');
        if (!preg_match('/^form_card_[a-z0-9_]+$/', $stageTable)) {
            return [];
        }

        $foreignKey = (string)($studyConfig['foreign_key'] ?? 'stress_test_id');
        if (!preg_match('/^[a-z0-9_]+$/', $foreignKey)) {
            return [];
        }

        $xField = (string)($studyConfig['x_field'] ?? 'elapsed_s');
        if (!preg_match('/^[a-z0-9_]+$/', $xField)) {
            return [];
        }

        // Whitelist series fields
        $fieldMap = [];
        foreach ($studyConfig['charts'] as $c) {
            foreach ($c['series'] ?? [] as $s) {
                $f = (string)($s['field'] ?? '');
                if (preg_match('/^[a-z0-9_]+$/', $f)) {
                    $fieldMap[$f] = true;
                }
            }
        }
        if (empty($fieldMap)) {
            return [];
        }

        $escapedColumns = array_map(static fn(string $col): string => '`' . $col . '`', array_keys($fieldMap));
        $columnSql = 'id, phase, stage_no, `' . $xField . '`, ' . implode(', ', $escapedColumns);

        $escapedStageTable = escape_table_name($stageTable);
        $res = sqlStatement(
            "SELECT {$columnSql} FROM {$escapedStageTable}
              WHERE `{$foreignKey}` = ?
              ORDER BY `{$xField}` ASC, id ASC",
            [$studyId]
        );

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }

        // Phase display translations
        $phaseNames = [
            'rest' => xl('Rest'),
            'exercise' => xl('Exercise'),
            'recovery' => xl('Recovery'),
        ];

        $labels = [];
        $xRaw = [];
        $metaList = [];

        foreach ($rows as $row) {
            $sec = (int)($row[$xField] ?? 0);
            $labels[] = self::formatElapsed($sec);
            $xRaw[] = $sec;

            $phaseKey = (string)($row['phase'] ?? '');
            $phaseLabel = $phaseNames[$phaseKey] ?? xl($phaseKey);
            $stageNo = (int)($row['stage_no'] ?? 0);

            $metaList[] = [
                'phase' => $phaseLabel,
                'stage_no' => $stageNo,
                'elapsed_s' => $sec,
            ];
        }

        $result = [];
        foreach ($studyConfig['charts'] as $chart) {
            $chartId = (string)($chart['id'] ?? '');
            $title = xl((string)($chart['title'] ?? ''));
            $unit = xl((string)($chart['unit'] ?? ''));
            $datasets = [];
            $hasAnyValue = false;

            foreach ($chart['series'] ?? [] as $series) {
                $field = (string)($series['field'] ?? '');
                if (!isset($fieldMap[$field])) {
                    continue;
                }

                $scale = isset($series['scale']) ? (float)$series['scale'] : 1.0;
                $decimals = isset($series['decimals']) ? (int)$series['decimals'] : 2;
                $seriesData = [];

                foreach ($rows as $row) {
                    $rawVal = $row[$field] ?? null;
                    if ($rawVal === null || $rawVal === '') {
                        $seriesData[] = null;
                    } elseif (is_numeric($rawVal)) {
                        $val = round(((float)$rawVal) * $scale, $decimals);
                        $seriesData[] = $val;
                        $hasAnyValue = true;
                    } else {
                        $seriesData[] = null;
                    }
                }

                $datasets[] = [
                    'field' => $field,
                    'label' => xl((string)($series['label'] ?? $field)),
                    'data' => $seriesData,
                    'unit' => $unit,
                ];
            }

            $result[] = [
                'id' => $chartId,
                'title' => $title,
                'unit' => $unit,
                'type' => 'study',
                'labels' => $labels,
                'x_raw' => $xRaw,
                'meta' => $metaList,
                'datasets' => $datasets,
                'has_data' => $hasAnyValue,
                'y_min' => $chart['y_min'] ?? null,
                'y_max' => $chart['y_max'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Format a MySQL DATETIME / DATE into localized user short date.
     *
     * @param string $dateTimeStr
     * @return string
     */
    private static function formatDate(string $dateTimeStr): string
    {
        if (trim($dateTimeStr) === '') {
            return '';
        }
        $dateOnly = substr($dateTimeStr, 0, 10);
        if (function_exists('oeFormatShortDate')) {
            return (string)oeFormatShortDate($dateOnly);
        }
        return $dateOnly;
    }

    /**
     * Format seconds as mm:ss.
     *
     * @param int $seconds
     * @return string
     */
    private static function formatElapsed(int $seconds): string
    {
        $minutes = (int)floor($seconds / 60);
        $remSeconds = (int)abs($seconds % 60);
        return sprintf('%02d:%02d', $minutes, $remSeconds);
    }
}

