<?php

/**
 * CardAnticoag – anticoagulation helpers for the Cardiology module.
 *
 * Implements the time in therapeutic range (TTR) calculation using the
 * Rosendaal linear interpolation method over final anticoagulation studies.
 *
 * Method summary:
 *   - Only final studies with a valid INR and a valid target range are used.
 *   - Studies are considered inside a window ending on the reference study date.
 *   - Each consecutive pair of tests forms one interval.
 *   - Intervals longer than 56 days are skipped because linear interpolation is
 *     not considered reliable across such a gap.
 *   - The earlier test of each interval supplies the target range.
 *   - TTR is the time-weighted percentage of days inside that target range.
 *
 * This class never suggests doses or interprets results. It only reports the
 * calculated time in range and the number of days evaluated.
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

final class CardAnticoag
{
    /** Maximum interval in days accepted for linear interpolation. */
    public const MAX_INTERVAL_DAYS = 56.0;

    /** Default TTR lookback window in days. */
    public const DEFAULT_WINDOW_DAYS = 180;

    /**
     * Calculate time in therapeutic range for a patient.
     *
     * @param int    $pid        Patient id.
     * @param string $studyDate  Reference study date (Y-m-d or Y-m-d H:i:s).
     * @param int    $windowDays Lookback window in days.
     * @return array{ttr_pct: ?string, days_evaluated: float, tests: int, message: ?string}
     */
    public static function ttr(int $pid, string $studyDate, int $windowDays = self::DEFAULT_WINDOW_DAYS): array
    {
        $empty = [
            'ttr_pct' => null,
            'days_evaluated' => 0.0,
            'tests' => 0,
            'message' => xlt('Not enough data'),
        ];

        $endDate = substr(trim($studyDate), 0, 10);
        if ($pid <= 0 || $windowDays <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            return $empty;
        }

        $startTimestamp = strtotime($endDate . ' -' . $windowDays . ' days');
        if ($startTimestamp === false) {
            return $empty;
        }
        $startDate = date('Y-m-d', $startTimestamp);

        $res = sqlStatement(
            "SELECT study_date, inr_value, target_inr_low, target_inr_high
               FROM `form_card_anticoag`
              WHERE pid = ?
                AND activity = 1
                AND status = 'final'
                AND inr_value IS NOT NULL
                AND target_inr_low IS NOT NULL
                AND target_inr_high IS NOT NULL
                AND target_inr_high > target_inr_low
                AND DATE(study_date) BETWEEN ? AND ?
              ORDER BY study_date ASC, id ASC",
            [$pid, $startDate, $endDate]
        );

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }

        $tests = count($rows);
        $empty['tests'] = $tests;
        if ($tests < 2) {
            return $empty;
        }

        $weightedDays = 0.0;
        $evaluatedDays = 0.0;

        for ($i = 0; $i < $tests - 1; $i++) {
            $start = strtotime((string)$rows[$i]['study_date']);
            $next = strtotime((string)$rows[$i + 1]['study_date']);
            if ($start === false || $next === false) {
                continue;
            }

            $intervalDays = ($next - $start) / 86400.0;
            if ($intervalDays <= 0.0 || $intervalDays > self::MAX_INTERVAL_DAYS) {
                continue;
            }

            $inrStart = (float)$rows[$i]['inr_value'];
            $inrEnd = (float)$rows[$i + 1]['inr_value'];
            $low = (float)$rows[$i]['target_inr_low'];
            $high = (float)$rows[$i]['target_inr_high'];

            $fraction = self::fractionInRange($inrStart, $inrEnd, $low, $high);
            $weightedDays += $intervalDays * $fraction;
            $evaluatedDays += $intervalDays;
        }

        if ($evaluatedDays <= 0.0) {
            $empty['tests'] = $tests;
            return $empty;
        }

        $pct = ($weightedDays / $evaluatedDays) * 100.0;

        return [
            'ttr_pct' => number_format($pct, 1, '.', ''),
            'days_evaluated' => round($evaluatedDays, 1),
            'tests' => $tests,
            'message' => null,
        ];
    }

    /**
     * Fraction of the interval [0, 1] where a linear INR change stays inside
     * the target range supplied by the earlier test.
     *
     * @param float $startInr INR at the start of the interval.
     * @param float $endInr   INR at the end of the interval.
     * @param float $low      Lower target bound.
     * @param float $high     Upper target bound.
     * @return float Fraction between 0.0 and 1.0.
     */
    private static function fractionInRange(float $startInr, float $endInr, float $low, float $high): float
    {
        if ($high < $low) {
            [$low, $high] = [$high, $low];
        }

        if ($startInr === $endInr) {
            return ($startInr >= $low && $startInr <= $high) ? 1.0 : 0.0;
        }

        $delta = $endInr - $startInr;
        $tLow = ($low - $startInr) / $delta;
        $tHigh = ($high - $startInr) / $delta;

        $from = max(0.0, min($tLow, $tHigh));
        $to = min(1.0, max($tLow, $tHigh));

        return max(0.0, $to - $from);
    }
}
