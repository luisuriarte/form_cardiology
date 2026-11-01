<?php

/**
 * Stress Test card – save.php
 *
 * Handles POST from cards/stress_test/new.php and cards/stress_test/view.php.
 * Processes the main study row AND the stage rows atomically.
 *
 * Checks:
 *   1. CSRF token (version-compatible via CardiologyContainer)
 *   2. ACL and card availability (via CardRegistry::canWrite)
 *   3. Container active and ownership matching pid and encounter
 *   4. Session pid and encounter consistency
 *   5. Study not finalized (using SELECT ... FOR UPDATE within transaction)
 *   6. Strict input validation with CardInput and RANGES / STAGE_RANGES bounds
 *   7. Finalizing requires a non-empty conclusion
 *   8. Stage rows validation: phase whitelist (rest|exercise|recovery), max 100 rows,
 *      scoped strictly by stress_test_id.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\StressTest
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

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Forms\Cardiology\CardInput;
use OpenEMR\Forms\Cardiology\CardRegistry;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

// ---------------------------------------------------------------------------
// Plausibility bounds for input validation
// ---------------------------------------------------------------------------
const RANGES = [
    'height_cm'              => [50.0, 250.0],
    'weight_kg'              => [2.0, 400.0],
    'rest_hr_bpm'            => [30, 200],
    'rest_bp_systolic_mmhg'  => [40, 300],
    'rest_bp_diastolic_mmhg' => [20, 200],
    'peak_hr_bpm'            => [40, 260],
    'peak_bp_systolic_mmhg'  => [60, 320],
    'peak_bp_diastolic_mmhg' => [20, 220],
    'exercise_duration_s'    => [0, 5400],
    'mets_achieved'          => [1.0, 30.0],
    'hr_recovery_1min_bpm'   => [0, 100],
    'angina_index'           => [0, 2],
    'st_depression_mm'       => [0.0, 10.0],
    'st_elevation_mm'        => [0.0, 10.0],
    'st_onset_hr_bpm'        => [40, 260],
];

const STAGE_RANGES = [
    'elapsed_s'          => [0, 7200],
    'stage_no'           => [0, 50],
    'speed_kmh'          => [0.0, 25.0],
    'grade_pct'          => [0.0, 30.0],
    'mets'               => [0.0, 30.0],
    'hr_bpm'             => [30, 260],
    'bp_systolic_mmhg'   => [40, 320],
    'bp_diastolic_mmhg'  => [20, 220],
    'st_mm'              => [-10.0, 10.0],
];

// Helper to render validation error page
function stRenderError(string $message, string $backUrl): void
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
if (!$registry->canWrite('stress_test', $userId)) {
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
        . '/cards/stress_test/view.php?id=' . urlencode((string)$studyId)
        . '&cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
} else {
    $backUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/stress_test/new.php?cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
}

// ---------------------------------------------------------------------------
// 4. Validate input data with CardInput
// ---------------------------------------------------------------------------
$p = $_POST;

try {
    $studyDate = CardInput::dateTime($p['study_date'] ?? null, 'study_date');

    // Clinical context
    $indication      = CardInput::text($p['indication'] ?? null);
    $heightCm        = CardInput::decimal($p['height_cm'] ?? null, 'height_cm', RANGES['height_cm'][0], RANGES['height_cm'][1]);
    $weightKg        = CardInput::decimal($p['weight_kg'] ?? null, 'weight_kg', RANGES['weight_kg'][0], RANGES['weight_kg'][1]);
    $onBetaBlocker   = CardInput::flag($p['on_beta_blocker'] ?? null);
    $medicationsNote = CardInput::text($p['medications_note'] ?? null);
    $protocol        = CardInput::listOption('card_stress_protocol', $p['protocol'] ?? null, 'protocol');
    $restEcgFindings = CardInput::text($p['rest_ecg_findings'] ?? null);

    // Baseline values
    $restHr    = CardInput::int($p['rest_hr_bpm'] ?? null, 'rest_hr_bpm', RANGES['rest_hr_bpm'][0], RANGES['rest_hr_bpm'][1]);
    $restBpSys = CardInput::int($p['rest_bp_systolic_mmhg'] ?? null, 'rest_bp_systolic_mmhg', RANGES['rest_bp_systolic_mmhg'][0], RANGES['rest_bp_systolic_mmhg'][1]);
    $restBpDia = CardInput::int($p['rest_bp_diastolic_mmhg'] ?? null, 'rest_bp_diastolic_mmhg', RANGES['rest_bp_diastolic_mmhg'][0], RANGES['rest_bp_diastolic_mmhg'][1]);

    // Exercise performance
    $peakHr           = CardInput::int($p['peak_hr_bpm'] ?? null, 'peak_hr_bpm', RANGES['peak_hr_bpm'][0], RANGES['peak_hr_bpm'][1]);
    $peakBpSys        = CardInput::int($p['peak_bp_systolic_mmhg'] ?? null, 'peak_bp_systolic_mmhg', RANGES['peak_bp_systolic_mmhg'][0], RANGES['peak_bp_systolic_mmhg'][1]);
    $peakBpDia        = CardInput::int($p['peak_bp_diastolic_mmhg'] ?? null, 'peak_bp_diastolic_mmhg', RANGES['peak_bp_diastolic_mmhg'][0], RANGES['peak_bp_diastolic_mmhg'][1]);
    $exerciseDuration = CardInput::int($p['exercise_duration_s'] ?? null, 'exercise_duration_s', RANGES['exercise_duration_s'][0], RANGES['exercise_duration_s'][1]);
    $metsAchieved     = CardInput::decimal($p['mets_achieved'] ?? null, 'mets_achieved', RANGES['mets_achieved'][0], RANGES['mets_achieved'][1]);
    $bpResponse       = CardInput::listOption('card_bp_response', $p['bp_response'] ?? null, 'bp_response');
    $hrRecovery1min   = CardInput::int($p['hr_recovery_1min_bpm'] ?? null, 'hr_recovery_1min_bpm', RANGES['hr_recovery_1min_bpm'][0], RANGES['hr_recovery_1min_bpm'][1]);

    // Symptoms, ST segment and arrhythmias
    $symptom      = CardInput::listOption('card_stress_symptom', $p['symptom'] ?? null, 'symptom');
    $anginaIndex  = CardInput::int($p['angina_index'] ?? null, 'angina_index', RANGES['angina_index'][0], RANGES['angina_index'][1]);
    $stDepression = CardInput::decimal($p['st_depression_mm'] ?? null, 'st_depression_mm', RANGES['st_depression_mm'][0], RANGES['st_depression_mm'][1]);
    $stElevation  = CardInput::decimal($p['st_elevation_mm'] ?? null, 'st_elevation_mm', RANGES['st_elevation_mm'][0], RANGES['st_elevation_mm'][1]);
    $stLeads      = CardInput::text($p['st_leads'] ?? null);
    $stOnsetHr    = CardInput::int($p['st_onset_hr_bpm'] ?? null, 'st_onset_hr_bpm', RANGES['st_onset_hr_bpm'][0], RANGES['st_onset_hr_bpm'][1]);
    $arrhythmias  = CardInput::text($p['arrhythmias'] ?? null);

    // Result & Report
    $stopReason = CardInput::listOption('card_stop_reason', $p['stop_reason'] ?? null, 'stop_reason');
    $result     = CardInput::listOption('card_stress_result', $p['result'] ?? null, 'result');
    $conclusion = CardInput::text($p['conclusion'] ?? null);
    $comments   = CardInput::text($p['comments'] ?? null);

    // Finalize requires a non-empty conclusion
    if ($action === 'save_final' && ($conclusion === null || $conclusion === '')) {
        throw new InvalidArgumentException(xlt('A conclusion is required to finalize the study.'));
    }

    // -----------------------------------------------------------------------
    // Validate Stage rows
    // -----------------------------------------------------------------------
    $stagesPost = $p['stages'] ?? [];
    if (!is_array($stagesPost)) {
        $stagesPost = [];
    }

    if (count($stagesPost) > 100) {
        throw new InvalidArgumentException(xlt('Maximum of 100 stage records exceeded.'));
    }

    $stagesValidated = [];
    $allowedPhases   = ['rest', 'exercise', 'recovery'];

    foreach ($stagesPost as $idx => $stageData) {
        if (!is_array($stageData)) {
            continue;
        }

        $stageId    = (int)($stageData['id'] ?? 0);
        $deleteFlag = (int)($stageData['delete'] ?? 0);
        $phase      = trim((string)($stageData['phase'] ?? 'exercise'));

        if (!in_array($phase, $allowedPhases, true)) {
            throw new InvalidArgumentException(xlt('Invalid phase for stage: ') . $phase);
        }

        $fieldPrefix = 'stage[' . $idx . '].';
        $stageNo     = CardInput::int($stageData['stage_no'] ?? null, $fieldPrefix . 'stage_no', STAGE_RANGES['stage_no'][0], STAGE_RANGES['stage_no'][1]);
        $elapsedS    = CardInput::int($stageData['elapsed_s'] ?? null, $fieldPrefix . 'elapsed_s', STAGE_RANGES['elapsed_s'][0], STAGE_RANGES['elapsed_s'][1]);
        $speedKmh    = CardInput::decimal($stageData['speed_kmh'] ?? null, $fieldPrefix . 'speed_kmh', STAGE_RANGES['speed_kmh'][0], STAGE_RANGES['speed_kmh'][1]);
        $gradePct    = CardInput::decimal($stageData['grade_pct'] ?? null, $fieldPrefix . 'grade_pct', STAGE_RANGES['grade_pct'][0], STAGE_RANGES['grade_pct'][1]);
        $mets        = CardInput::decimal($stageData['mets'] ?? null, $fieldPrefix . 'mets', STAGE_RANGES['mets'][0], STAGE_RANGES['mets'][1]);
        $hrBpm       = CardInput::int($stageData['hr_bpm'] ?? null, $fieldPrefix . 'hr_bpm', STAGE_RANGES['hr_bpm'][0], STAGE_RANGES['hr_bpm'][1]);
        $bpSys       = CardInput::int($stageData['bp_systolic_mmhg'] ?? null, $fieldPrefix . 'bp_systolic_mmhg', STAGE_RANGES['bp_systolic_mmhg'][0], STAGE_RANGES['bp_systolic_mmhg'][1]);
        $bpDia       = CardInput::int($stageData['bp_diastolic_mmhg'] ?? null, $fieldPrefix . 'bp_diastolic_mmhg', STAGE_RANGES['bp_diastolic_mmhg'][0], STAGE_RANGES['bp_diastolic_mmhg'][1]);
        $stMm        = CardInput::decimal($stageData['st_mm'] ?? null, $fieldPrefix . 'st_mm', STAGE_RANGES['st_mm'][0], STAGE_RANGES['st_mm'][1]);
        $symptomNote = CardInput::text($stageData['symptom_note'] ?? null);

        $isEmpty = ($stageNo === null && $elapsedS === null && $speedKmh === null
            && $gradePct === null && $mets === null && $hrBpm === null
            && $bpSys === null && $bpDia === null && $stMm === null
            && $symptomNote === null);

        $stagesValidated[] = [
            'stage_id'    => $stageId,
            'delete_flag' => $deleteFlag,
            'phase'       => $phase,
            'stage_no'    => $stageNo,
            'elapsed_s'   => $elapsedS,
            'speed_kmh'   => $speedKmh,
            'grade_pct'   => $gradePct,
            'mets'        => $mets,
            'hr_bpm'      => $hrBpm,
            'bp_systolic_mmhg'  => $bpSys,
            'bp_diastolic_mmhg' => $bpDia,
            'st_mm'       => $stMm,
            'symptom_note'=> $symptomNote,
            'is_empty'    => $isEmpty,
        ];
    }
} catch (InvalidArgumentException $e) {
    stRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// Server-side derived calculations
// ---------------------------------------------------------------------------

// Age in years calculated at study_date from patient_data.DOB
$ageYears = null;
$patRow   = sqlQuery('SELECT DOB FROM patient_data WHERE pid = ? LIMIT 1', [$pid]);
if ($patRow && !empty($patRow['DOB']) && $patRow['DOB'] !== '0000-00-00') {
    try {
        $dobDt   = new DateTime((string)$patRow['DOB']);
        $studyDt = new DateTime($studyDate);
        $diff    = $dobDt->diff($studyDt);
        if (!$diff->invert) {
            $ageYears = (int)$diff->y;
        }
    } catch (Throwable $e) {
        $ageYears = null;
    }
}

// Max predicted HR (220 - age)
$maxPredictedHr = ($ageYears !== null && $ageYears > 0) ? (220 - $ageYears) : null;

// Target HR (85% of max predicted)
$targetHr = ($maxPredictedHr !== null) ? (int)round(0.85 * (float)$maxPredictedHr) : null;

// Peak HR % of predicted
$peakHrPct = null;
if ($peakHr !== null && $maxPredictedHr !== null && $maxPredictedHr > 0) {
    $peakHrPct = number_format(round(((float)$peakHr / (float)$maxPredictedHr) * 100.0, 1), 1, '.', '');
}

// Double product (peak HR * peak systolic BP)
$doubleProduct = null;
if ($peakHr !== null && $peakBpSys !== null) {
    $doubleProduct = $peakHr * $peakBpSys;
}

// Duke treadmill score: (duration_s / 60) - (5 * net_st_deviation) - (4 * angina_index)
// ONLY calculated when protocol is 'bruce' or 'mod_bruce' and exercise_duration_s is not null.
$dukeScore = null;
if (in_array($protocol, ['bruce', 'mod_bruce'], true) && $exerciseDuration !== null) {
    $devDep   = ($stDepression !== null) ? (float)$stDepression : 0.0;
    $devElev  = ($stElevation !== null)  ? (float)$stElevation  : 0.0;
    $netStDev = max($devDep, $devElev);
    $angina   = ($anginaIndex !== null) ? (float)$anginaIndex : 0.0;
    $minutes  = (float)$exerciseDuration / 60.0;
    $rawScore = $minutes - (5.0 * $netStDev) - (4.0 * $angina);
    $dukeScore = number_format(round($rawScore, 1), 1, '.', '');
}

// ---------------------------------------------------------------------------
// Status / workflow
// ---------------------------------------------------------------------------
$status      = ($action === 'save_final') ? 'final' : 'draft';
$finalizedAt = ($status === 'final') ? date('Y-m-d H:i:s') : null;
$finalizedBy = ($status === 'final') ? ($_SESSION['authUser'] ?? '') : null;
$authUser    = $_SESSION['authUser'] ?? '';

// ---------------------------------------------------------------------------
// Construct $data associative array for main study row
// ---------------------------------------------------------------------------
$data = [
    // Identity columns (used on INSERT, stripped on UPDATE)
    'cardiology_id'         => $cardiologyId,
    'pid'                   => $pid,
    'encounter'             => $encounter,
    'user'                  => $authUser,
    'activity'              => 1,

    // Study workflow
    'study_date'            => $studyDate,
    'status'                => $status,
    'finalized_at'          => $finalizedAt,
    'finalized_by'          => $finalizedBy,

    // Clinical context
    'indication'            => $indication,
    'age_years'             => $ageYears,
    'height_cm'             => $heightCm,
    'weight_kg'             => $weightKg,
    'on_beta_blocker'       => $onBetaBlocker,
    'medications_note'      => $medicationsNote,
    'protocol'              => $protocol,
    'rest_ecg_findings'     => $restEcgFindings,

    // Baseline values
    'rest_hr_bpm'           => $restHr,
    'rest_bp_systolic_mmhg' => $restBpSys,
    'rest_bp_diastolic_mmhg'=> $restBpDia,

    // Exercise performance
    'max_predicted_hr_bpm'  => $maxPredictedHr,
    'target_hr_bpm'         => $targetHr,
    'peak_hr_bpm'           => $peakHr,
    'peak_hr_pct_predicted' => $peakHrPct,
    'peak_bp_systolic_mmhg' => $peakBpSys,
    'peak_bp_diastolic_mmhg'=> $peakBpDia,
    'double_product'        => $doubleProduct,
    'exercise_duration_s'   => $exerciseDuration,
    'mets_achieved'         => $metsAchieved,
    'bp_response'           => $bpResponse,
    'hr_recovery_1min_bpm'  => $hrRecovery1min,

    // Symptoms, ST segment and arrhythmias
    'symptom'               => $symptom,
    'angina_index'          => $anginaIndex,
    'st_depression_mm'      => $stDepression,
    'st_elevation_mm'       => $stElevation,
    'st_leads'              => $stLeads,
    'st_onset_hr_bpm'       => $stOnsetHr,
    'arrhythmias'           => $arrhythmias,

    // Result & Report
    'stop_reason'           => $stopReason,
    'duke_score'            => $dukeScore,
    'result'                => $result,
    'conclusion'            => $conclusion,
    'comments'              => $comments,
];

// ---------------------------------------------------------------------------
// 5. Database Transaction (Atomic for study and stage rows)
// ---------------------------------------------------------------------------
CardiologyContainer::startTransaction();

try {
    if ($studyId === 0) {
        // INSERT
        $columns = array_keys($data);
        $sql = 'INSERT INTO `form_card_stress_test` (`' . implode('`, `', $columns) . '`) '
             . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $studyId = (int)sqlInsert($sql, array_values($data));
    } else {
        // SELECT FOR UPDATE and verify
        $existing = sqlQuery(
            'SELECT id, status FROM `form_card_stress_test`
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

        $sql = 'UPDATE `form_card_stress_test` SET ' . implode(', ', $setParts)
             . ' WHERE id = ? AND pid = ? AND cardiology_id = ? AND status <> \'final\'';

        sqlStatement($sql, $params);
    }

    // Process stage rows scoped by $studyId
    foreach ($stagesValidated as $st) {
        if ($st['stage_id'] > 0 && $st['delete_flag'] === 1) {
            sqlStatement(
                'DELETE FROM `form_card_stress_test_stage` WHERE id = ? AND stress_test_id = ?',
                [$st['stage_id'], $studyId]
            );
        } elseif ($st['stage_id'] > 0) {
            sqlStatement(
                'UPDATE `form_card_stress_test_stage` SET
                    phase = ?, stage_no = ?, elapsed_s = ?,
                    speed_kmh = ?, grade_pct = ?, mets = ?,
                    hr_bpm = ?, bp_systolic_mmhg = ?, bp_diastolic_mmhg = ?,
                    st_mm = ?, symptom_note = ?
                  WHERE id = ? AND stress_test_id = ?',
                [
                    $st['phase'],
                    $st['stage_no'] ?? 0,
                    $st['elapsed_s'],
                    $st['speed_kmh'],
                    $st['grade_pct'],
                    $st['mets'],
                    $st['hr_bpm'],
                    $st['bp_systolic_mmhg'],
                    $st['bp_diastolic_mmhg'],
                    $st['st_mm'],
                    $st['symptom_note'],
                    $st['stage_id'],
                    $studyId,
                ]
            );
        } else {
            // New stage row: skip if completely blank
            if ($st['is_empty']) {
                continue;
            }

            sqlInsert(
                'INSERT INTO `form_card_stress_test_stage`
                    (stress_test_id, phase, stage_no, elapsed_s,
                     speed_kmh, grade_pct, mets,
                     hr_bpm, bp_systolic_mmhg, bp_diastolic_mmhg,
                     st_mm, symptom_note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $studyId,
                    $st['phase'],
                    $st['stage_no'] ?? 0,
                    $st['elapsed_s'],
                    $st['speed_kmh'],
                    $st['grade_pct'],
                    $st['mets'],
                    $st['hr_bpm'],
                    $st['bp_systolic_mmhg'],
                    $st['bp_diastolic_mmhg'],
                    $st['st_mm'],
                    $st['symptom_note'],
                ]
            );
        }
    }

    CardiologyContainer::commitTransaction();
} catch (Throwable $e) {
    CardiologyContainer::rollbackTransaction();
    stRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// 6. Redirect to view.php
// ---------------------------------------------------------------------------
$viewUrl = CardiologyContainer::getFormRootUrl()
    . '/cards/stress_test/view.php'
    . '?pid='           . urlencode((string)$pid)
    . '&encounter='     . urlencode((string)$encounter)
    . '&cardiology_id=' . urlencode((string)$cardiologyId)
    . '&id='            . urlencode((string)$studyId);

formJump($viewUrl);
