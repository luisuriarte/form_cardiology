<?php

/**
 * Ambulatory Blood Pressure Monitoring card – save.php
 *
 * Handles POST from cards/abpm/new.php and cards/abpm/view.php.
 *
 * Checks:
 *   1. CSRF token (version-compatible via CardiologyContainer)
 *   2. ACL and card availability (via CardRegistry::canWrite)
 *   3. Container active and ownership matching pid and encounter
 *   4. Session pid and encounter consistency
 *   5. Study not finalized (using SELECT ... FOR UPDATE within transaction)
 *   6. Strict input validation with CardInput and RANGES bounds
 *   7. Cross-field rules and finalization requirements
 *
 * Calculated values (valid_pct, pulse_pressure_24h, dip_sbp_pct and
 * dip_dbp_pct) are recomputed here and never read from POST. No clinical
 * classification is calculated: the dipping pattern and ABPM classification
 * are always selected by the physician.
 *
 * Uses a single associative array for both INSERT and UPDATE statements.
 * Wrapped in an atomic database transaction.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Abpm
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once('../../../../globals.php');
require_once($GLOBALS['srcdir'] . '/api.inc.php');
require_once($GLOBALS['srcdir'] . '/forms.inc.php');
require_once(__DIR__ . '/../../lib/CardiologyContainer.php');
require_once(__DIR__ . '/../../lib/CardRegistry.php');
require_once(__DIR__ . '/../../lib/CardInput.php');
require_once(__DIR__ . '/../../lib/CardAudit.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Forms\Cardiology\CardAudit;
use OpenEMR\Forms\Cardiology\CardInput;
use OpenEMR\Forms\Cardiology\CardRegistry;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

// ---------------------------------------------------------------------------
// Plausibility bounds for input validation
// ---------------------------------------------------------------------------
const RANGES = [
    'recording_hours'  => [6, 72],
    'readings_total'   => [1, 500],
    'readings_valid'   => [0, 500],
    'office_sbp_mmhg'  => [40, 300],
    'office_dbp_mmhg'  => [20, 200],
    'office_hr_bpm'    => [20, 250],
    'sbp_24h'          => [40, 300],
    'dbp_24h'          => [20, 200],
    'hr_24h'           => [20, 250],
    'sbp_day'          => [40, 300],
    'dbp_day'          => [20, 200],
    'hr_day'           => [20, 250],
    'sbp_night'        => [40, 300],
    'dbp_night'        => [20, 200],
    'hr_night'         => [20, 250],
    'sbp_max'          => [40, 300],
    'dbp_max'          => [20, 200],
    'hr_max'           => [20, 250],
    'sbp_min'          => [40, 300],
    'dbp_min'          => [20, 200],
    'hr_min'           => [20, 250],
];

// Helper to render validation error page
function abpmRenderError(string $message, string $backUrl): void
{
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title><?php echo xlt('Cardiology Error'); ?></title>
        <link rel="stylesheet" href="<?php echo attr($GLOBALS['webroot']); ?>/public/assets/bootstrap/dist/css/bootstrap.min.css">
    </head>
    <body class="p-4 bg-light">
        <div class="container" style="max-width: 600px;">
            <div class="alert alert-danger shadow-sm" role="alert">
                <h5 class="alert-heading"><?php echo xlt('Validation Error'); ?></h5>
                <p class="mb-0"><?php echo text($message); ?></p>
            </div>
            <div class="mt-3">
                <a href="<?php echo attr($backUrl); ?>" class="btn btn-secondary" onclick="if (typeof top.restoreSession === 'function') { top.restoreSession(); }">
                    <i class="fa fa-arrow-left me-1"></i><?php echo xlt('Back'); ?>
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Validate and normalize an optional TIME input.
 *
 * Accepts HH:MM and HH:MM:SS and always returns HH:MM:SS.
 */
function abpmTime(mixed $value, string $field): ?string
{
    return \OpenEMR\Forms\Cardiology\CardDate::parseTime($value, $field);
}

// ---------------------------------------------------------------------------
// 1. CSRF
// ---------------------------------------------------------------------------
if (!CardiologyContainer::verifyCsrfToken()) {
    CsrfUtils::csrfNotVerified();
}

// ---------------------------------------------------------------------------
// 2. ACL & card availability for current user
// ---------------------------------------------------------------------------
$userId   = (int)($_SESSION['authUserID'] ?? 0);
$registry = new CardRegistry();
if (!$registry->canWrite('abpm', $userId)) {
    die(xlt('Not authorized'));
}

// ---------------------------------------------------------------------------
// 3. Parent container & ownership checks
// ---------------------------------------------------------------------------
$cardiologyId = (int)($_POST['cardiology_id'] ?? 0);
$container    = CardiologyContainer::getContainerById($cardiologyId);
if ($container === null) {
    die(xlt('The cardiology record is not available or has been deleted.'));
}

$pid       = (int)($_POST['pid']       ?? 0);
$encounter = (int)($_POST['encounter'] ?? 0);
$studyId   = (int)($_POST['study_id']  ?? 0);
$action    = trim((string)($_POST['action'] ?? 'save_draft'));

$sessionPid       = CardiologyContainer::getSessionPid();
$sessionEncounter = CardiologyContainer::getSessionEncounter();

if ($pid <= 0 || $encounter <= 0 || $pid !== $sessionPid || $encounter !== $sessionEncounter) {
    die(xlt('Session mismatch. Please reload and try again.'));
}

if ((int)$container['pid'] !== $pid || (int)$container['encounter'] !== $encounter) {
    die(xlt('The container does not match the patient or encounter.'));
}

// Determine back URL for validation errors
if ($studyId > 0) {
    $backUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/abpm/view.php?id=' . urlencode((string)$studyId)
        . '&cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
} else {
    $backUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/abpm/new.php?cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
}

// ---------------------------------------------------------------------------
// 4. Load stored list values so inactive options already saved stay editable
// ---------------------------------------------------------------------------
$stored = [];
if ($studyId > 0) {
    $storedRow = sqlQuery(
        'SELECT * FROM `form_card_abpm`
          WHERE id = ? AND pid = ? AND cardiology_id = ? AND activity = 1
          LIMIT 1',
        [$studyId, $pid, $cardiologyId]
    );
    $stored = $storedRow ?: [];
}

$storedValue = static function (string $field) use ($stored): ?string {
    return isset($stored[$field]) ? (string)$stored[$field] : null;
};

// ---------------------------------------------------------------------------
// 5. Validate input data with CardInput
// ---------------------------------------------------------------------------
$p = $_POST;

try {
    $studyDate = CardInput::dateTime($p['study_date'] ?? null, 'study_date');

    $indication   = CardInput::text($p['indication'] ?? null);
    $deviceModel  = CardInput::text($p['device_model'] ?? null);
    $recordingHours = CardInput::int($p['recording_hours'] ?? null, 'recording_hours', RANGES['recording_hours'][0], RANGES['recording_hours'][1]);
    $readingsTotal  = CardInput::int($p['readings_total'] ?? null, 'readings_total', RANGES['readings_total'][0], RANGES['readings_total'][1]);
    $readingsValid  = CardInput::int($p['readings_valid'] ?? null, 'readings_valid', RANGES['readings_valid'][0], RANGES['readings_valid'][1]);

    $onAntihypertensives = CardInput::flag($p['on_antihypertensives'] ?? null);
    $medicationsNote     = CardInput::text($p['medications_note'] ?? null);

    $dayStartTime   = abpmTime($p['day_start_time'] ?? null, 'day_start_time');
    $nightStartTime = abpmTime($p['night_start_time'] ?? null, 'night_start_time');

    $officeSbp = CardInput::int($p['office_sbp_mmhg'] ?? null, 'office_sbp_mmhg', RANGES['office_sbp_mmhg'][0], RANGES['office_sbp_mmhg'][1]);
    $officeDbp = CardInput::int($p['office_dbp_mmhg'] ?? null, 'office_dbp_mmhg', RANGES['office_dbp_mmhg'][0], RANGES['office_dbp_mmhg'][1]);
    $officeHr  = CardInput::int($p['office_hr_bpm'] ?? null, 'office_hr_bpm', RANGES['office_hr_bpm'][0], RANGES['office_hr_bpm'][1]);

    $sbp24h = CardInput::int($p['sbp_24h'] ?? null, 'sbp_24h', RANGES['sbp_24h'][0], RANGES['sbp_24h'][1]);
    $dbp24h = CardInput::int($p['dbp_24h'] ?? null, 'dbp_24h', RANGES['dbp_24h'][0], RANGES['dbp_24h'][1]);
    $hr24h  = CardInput::int($p['hr_24h'] ?? null, 'hr_24h', RANGES['hr_24h'][0], RANGES['hr_24h'][1]);

    $sbpDay = CardInput::int($p['sbp_day'] ?? null, 'sbp_day', RANGES['sbp_day'][0], RANGES['sbp_day'][1]);
    $dbpDay = CardInput::int($p['dbp_day'] ?? null, 'dbp_day', RANGES['dbp_day'][0], RANGES['dbp_day'][1]);
    $hrDay  = CardInput::int($p['hr_day'] ?? null, 'hr_day', RANGES['hr_day'][0], RANGES['hr_day'][1]);

    $sbpNight = CardInput::int($p['sbp_night'] ?? null, 'sbp_night', RANGES['sbp_night'][0], RANGES['sbp_night'][1]);
    $dbpNight = CardInput::int($p['dbp_night'] ?? null, 'dbp_night', RANGES['dbp_night'][0], RANGES['dbp_night'][1]);
    $hrNight  = CardInput::int($p['hr_night'] ?? null, 'hr_night', RANGES['hr_night'][0], RANGES['hr_night'][1]);

    $sbpMax = CardInput::int($p['sbp_max'] ?? null, 'sbp_max', RANGES['sbp_max'][0], RANGES['sbp_max'][1]);
    $dbpMax = CardInput::int($p['dbp_max'] ?? null, 'dbp_max', RANGES['dbp_max'][0], RANGES['dbp_max'][1]);
    $hrMax  = CardInput::int($p['hr_max'] ?? null, 'hr_max', RANGES['hr_max'][0], RANGES['hr_max'][1]);

    $sbpMin = CardInput::int($p['sbp_min'] ?? null, 'sbp_min', RANGES['sbp_min'][0], RANGES['sbp_min'][1]);
    $dbpMin = CardInput::int($p['dbp_min'] ?? null, 'dbp_min', RANGES['dbp_min'][0], RANGES['dbp_min'][1]);
    $hrMin  = CardInput::int($p['hr_min'] ?? null, 'hr_min', RANGES['hr_min'][0], RANGES['hr_min'][1]);

    $dippingPattern     = CardInput::listOption('card_dipping_pattern', $p['dipping_pattern'] ?? null, 'dipping_pattern', $storedValue('dipping_pattern'));
    $abpmClassification = CardInput::listOption('card_abpm_classification', $p['abpm_classification'] ?? null, 'abpm_classification', $storedValue('abpm_classification'));

    $findings   = CardInput::text($p['findings'] ?? null);
    $conclusion = CardInput::text($p['conclusion'] ?? null);
    $comments   = CardInput::text($p['comments'] ?? null);

    // Cross-field rules: valid readings cannot exceed total readings.
    if ($readingsTotal !== null && $readingsValid !== null && $readingsValid > $readingsTotal) {
        throw new \InvalidArgumentException(xlt('The number of valid readings cannot exceed the total number of readings.'));
    }

    // Cross-field rules: systolic must be greater than diastolic when both exist.
    $bpPairs = [
        'office' => [$officeSbp, $officeDbp],
        '24h'    => [$sbp24h, $dbp24h],
        'day'    => [$sbpDay, $dbpDay],
        'night'  => [$sbpNight, $dbpNight],
        'max'    => [$sbpMax, $dbpMax],
        'min'    => [$sbpMin, $dbpMin],
    ];
    foreach ($bpPairs as $pair) {
        if ($pair[0] !== null && $pair[1] !== null && $pair[0] <= $pair[1]) {
            throw new \InvalidArgumentException(xlt('Systolic blood pressure must be greater than diastolic blood pressure.'));
        }
    }

    // Cross-field rules: minimum <= mean <= maximum for pressure and heart rate.
    $orderGroups = [
        [$sbpMin, $sbp24h, $sbpMax],
        [$dbpMin, $dbp24h, $dbpMax],
        [$hrMin, $hr24h, $hrMax],
        [$sbpMin, $sbpDay, $sbpMax],
        [$dbpMin, $dbpDay, $dbpMax],
        [$hrMin, $hrDay, $hrMax],
        [$sbpMin, $sbpNight, $sbpMax],
        [$dbpMin, $dbpNight, $dbpMax],
        [$hrMin, $hrNight, $hrMax],
    ];
    foreach ($orderGroups as $group) {
        if ($group[0] !== null && $group[1] !== null && $group[2] !== null
            && !($group[0] <= $group[1] && $group[1] <= $group[2])) {
            throw new \InvalidArgumentException(xlt('Blood pressure and heart rate values must satisfy minimum less than or equal to mean less than or equal to maximum.'));
        }
    }

    // Finalize requirements
    if ($action === 'save_final') {
        if ($recordingHours === null) {
            throw new \InvalidArgumentException(xlt('A recording duration is required to finalize the study.'));
        }
        if ($readingsTotal === null) {
            throw new \InvalidArgumentException(xlt('The total number of readings is required to finalize the study.'));
        }
        if ($readingsValid === null) {
            throw new \InvalidArgumentException(xlt('The number of valid readings is required to finalize the study.'));
        }
        if ($sbp24h === null) {
            throw new \InvalidArgumentException(xlt('The 24-hour mean systolic blood pressure is required to finalize the study.'));
        }
        if ($dbp24h === null) {
            throw new \InvalidArgumentException(xlt('The 24-hour mean diastolic blood pressure is required to finalize the study.'));
        }
        if ($conclusion === null || $conclusion === '') {
            throw new \InvalidArgumentException(xlt('A conclusion is required to finalize the study.'));
        }
    }
} catch (\InvalidArgumentException $e) {
    abpmRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// Server-side calculations (never read from POST)
// ---------------------------------------------------------------------------
$validPct = null;
if ($readingsTotal !== null && $readingsValid !== null && $readingsTotal > 0) {
    $validPct = number_format(
        ((float)$readingsValid / (float)$readingsTotal) * 100.0,
        2,
        '.',
        ''
    );
}

$pulsePressure24h = null;
if ($sbp24h !== null && $dbp24h !== null) {
    $pulsePressure24h = number_format((float)$sbp24h - (float)$dbp24h, 2, '.', '');
}

$dipSbpPct = null;
if ($sbpDay !== null && $sbpNight !== null && $sbpDay > 0) {
    $dipSbpPct = number_format(
        (((float)$sbpDay - (float)$sbpNight) / (float)$sbpDay) * 100.0,
        2,
        '.',
        ''
    );
}

$dipDbpPct = null;
if ($dbpDay !== null && $dbpNight !== null && $dbpDay > 0) {
    $dipDbpPct = number_format(
        (((float)$dbpDay - (float)$dbpNight) / (float)$dbpDay) * 100.0,
        2,
        '.',
        ''
    );
}

// ---------------------------------------------------------------------------
// Status / workflow
// ---------------------------------------------------------------------------
$status      = ($action === 'save_final') ? 'final' : 'draft';
$finalizedAt = ($status === 'final') ? date('Y-m-d H:i:s') : null;
$finalizedBy = ($status === 'final') ? ($_SESSION['authUser'] ?? '') : null;
$authUser    = $_SESSION['authUser'] ?? '';

// ---------------------------------------------------------------------------
// Construct $data associative array
// ---------------------------------------------------------------------------
$data = [
    // Identity columns (used on INSERT, stripped on UPDATE)
    'cardiology_id'        => $cardiologyId,
    'pid'                  => $pid,
    'encounter'            => $encounter,
    'user'                 => $authUser,
    'activity'             => 1,

    // Study workflow
    'study_date'           => $studyDate,
    'status'               => $status,
    'finalized_at'         => $finalizedAt,
    'finalized_by'         => $finalizedBy,

    // Recording and device
    'indication'           => $indication,
    'device_model'         => $deviceModel,
    'recording_hours'      => $recordingHours,
    'readings_total'       => $readingsTotal,
    'readings_valid'       => $readingsValid,
    'valid_pct'            => $validPct,
    'on_antihypertensives' => $onAntihypertensives,
    'medications_note'     => $medicationsNote,
    'day_start_time'       => $dayStartTime,
    'night_start_time'     => $nightStartTime,

    // Office measurements
    'office_sbp_mmhg'      => $officeSbp,
    'office_dbp_mmhg'      => $officeDbp,
    'office_hr_bpm'        => $officeHr,

    // 24-hour means
    'sbp_24h'              => $sbp24h,
    'dbp_24h'              => $dbp24h,
    'hr_24h'               => $hr24h,
    'pulse_pressure_24h'   => $pulsePressure24h,

    // Daytime and nighttime means
    'sbp_day'              => $sbpDay,
    'dbp_day'              => $dbpDay,
    'hr_day'               => $hrDay,
    'sbp_night'            => $sbpNight,
    'dbp_night'            => $dbpNight,
    'hr_night'             => $hrNight,
    'dip_sbp_pct'          => $dipSbpPct,
    'dip_dbp_pct'          => $dipDbpPct,

    // Maximum and minimum readings
    'sbp_max'              => $sbpMax,
    'dbp_max'              => $dbpMax,
    'hr_max'               => $hrMax,
    'sbp_min'              => $sbpMin,
    'dbp_min'              => $dbpMin,
    'hr_min'               => $hrMin,

    // Physician-selected classification, findings and report
    'dipping_pattern'      => $dippingPattern,
    'abpm_classification'  => $abpmClassification,
    'findings'             => $findings,
    'conclusion'           => $conclusion,
    'comments'             => $comments,
];

// ---------------------------------------------------------------------------
// 6. Database Transaction
// ---------------------------------------------------------------------------
CardiologyContainer::startTransaction();

try {
    if ($studyId === 0) {
        // INSERT
        $columns = array_keys($data);
        $sql = 'INSERT INTO `form_card_abpm` (`' . implode('`, `', $columns) . '`) '
             . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $studyId = (int)sqlInsert($sql, array_values($data));
    } else {
        // SELECT FOR UPDATE and verify
        $existing = sqlQuery(
            'SELECT id, status FROM `form_card_abpm`
              WHERE id = ? AND pid = ? AND cardiology_id = ? AND activity = 1
              FOR UPDATE',
            [$studyId, $pid, $cardiologyId]
        );

        if (!$existing || !isset($existing['id'])) {
            CardiologyContainer::rollbackTransaction();
            die(xlt('Study not found or has been deleted.'));
        }

        if ($existing['status'] === 'final') {
            CardiologyContainer::rollbackTransaction();
            die(xlt('This study is finalized and cannot be edited.'));
        }

        // UPDATE (strip identity columns)
        $identityCols = ['cardiology_id', 'pid', 'encounter', 'user', 'activity', 'created_at'];
        $updateData   = array_diff_key($data, array_flip($identityCols));

        $setParts = [];
        foreach (array_keys($updateData) as $col) {
            $setParts[] = '`' . $col . '` = ?';
        }

        $params   = array_values($updateData);
        $params[] = $studyId;
        $params[] = $pid;
        $params[] = $cardiologyId;

        $sql = 'UPDATE `form_card_abpm` SET ' . implode(', ', $setParts)
             . ' WHERE id = ? AND pid = ? AND cardiology_id = ? AND status <> \'final\'';

        sqlStatement($sql, $params);
    }

    CardiologyContainer::commitTransaction();
} catch (\Throwable $e) {
    CardiologyContainer::rollbackTransaction();
    abpmRenderError($e->getMessage(), $backUrl);
}

// Audit after a successful commit. No clinical values are written to the log.
CardAudit::log(
    $status === 'final' ? 'study_finalize' : 'study_save',
    'abpm',
    [
        'study_id'      => $studyId,
        'pid'           => $pid,
        'encounter'     => $encounter,
        'cardiology_id' => $cardiologyId,
    ]
);

// ---------------------------------------------------------------------------
// 7. Redirect to view.php
// ---------------------------------------------------------------------------
$viewUrl = CardiologyContainer::getFormRootUrl()
    . '/cards/abpm/view.php'
    . '?pid='           . urlencode((string)$pid)
    . '&encounter='     . urlencode((string)$encounter)
    . '&cardiology_id=' . urlencode((string)$cardiologyId)
    . '&id='            . urlencode((string)$studyId);

formJump($viewUrl);
