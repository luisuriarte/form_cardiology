<?php
/**
 * Stress Test card – save.php
 *
 * Handles POST from new.php / view.php.
 * Processes the main study row AND the stage rows.
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

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once(__DIR__ . '/../../lib/CardiologyContainer.php');

// 1. CSRF
if (!CardiologyContainer::verifyCsrfToken()) {
    CsrfUtils::csrfNotVerified();
}

// 2. ACL
if (!AclMain::aclCheckCore('patients', 'med', '', 'write')) {
    die(xlt('Not authorized'));
}

// 3. Container active
$cardiologyId = (int)($_POST['cardiology_id'] ?? 0);
$container    = CardiologyContainer::getContainerById($cardiologyId);
if ($container === null) {
    die(xlt('The cardiology record is not available or has been deleted.'));
}

$pid       = (int)($_POST['pid']       ?? 0);
$encounter = (int)($_POST['encounter'] ?? 0);
$studyId   = (int)($_POST['study_id']  ?? 0);
$action    = trim($_POST['action'] ?? 'save_draft');

// Session consistency check
if ((isset($GLOBALS['pid']) && $pid !== (int)$GLOBALS['pid'])
    || (isset($GLOBALS['encounter']) && $encounter !== (int)$GLOBALS['encounter'])) {
    die(xlt('Session mismatch. Please reload and try again.'));
}

// 4. If updating, verify not finalized
if ($studyId > 0) {
    $existing = sqlQuery(
        'SELECT id, status FROM `form_card_stress_test` WHERE id = ? AND pid = ? AND activity = 1',
        [$studyId, $pid]
    );
    if (!$existing || !isset($existing['id'])) {
        die(xlt('Study not found or has been deleted.'));
    }
    if ($existing['status'] === 'final') {
        die(xlt('This study is finalized and cannot be edited.'));
    }
}

// ---------------------------------------------------------------------------
// Sanitize helpers
// ---------------------------------------------------------------------------
function stNullDecimal(?string $v): ?string
{
    $v = trim($v ?? '');
    return $v !== '' ? (string)(float)$v : null;
}

function stNullInt(?string $v): ?int
{
    $v = trim($v ?? '');
    return $v !== '' ? (int)$v : null;
}

function stNullStr(?string $v): ?string
{
    $v = trim($v ?? '');
    return $v !== '' ? $v : null;
}

// ---------------------------------------------------------------------------
// Collect study fields
// ---------------------------------------------------------------------------
$p = $_POST;

$studyDate  = stNullStr($p['study_date'] ?? null);
if ($studyDate !== null) { $studyDate = str_replace('T', ' ', $studyDate); }

$indication       = stNullStr($p['indication']       ?? null);
$heightCm         = stNullDecimal($p['height_cm']    ?? null);
$weightKg         = stNullDecimal($p['weight_kg']    ?? null);
$onBetaBlocker    = isset($p['on_beta_blocker']) ? 1 : 0;
$medicationsNote  = stNullStr($p['medications_note'] ?? null);
$protocol         = stNullStr($p['protocol']         ?? null);
$restEcg          = stNullStr($p['rest_ecg_findings'] ?? null);

$restHr           = stNullInt($p['rest_hr_bpm']             ?? null);
$restBpSys        = stNullInt($p['rest_bp_systolic_mmhg']   ?? null);
$restBpDia        = stNullInt($p['rest_bp_diastolic_mmhg']  ?? null);

$peakHr           = stNullInt($p['peak_hr_bpm']             ?? null);
$peakBpSys        = stNullInt($p['peak_bp_systolic_mmhg']   ?? null);
$peakBpDia        = stNullInt($p['peak_bp_diastolic_mmhg']  ?? null);
$exerciseDuration = stNullInt($p['exercise_duration_s']     ?? null);
$metsAchieved     = stNullDecimal($p['mets_achieved']       ?? null);
$bpResponse       = stNullStr($p['bp_response']             ?? null);
$hrRecovery1min   = stNullInt($p['hr_recovery_1min_bpm']    ?? null);

$symptom          = stNullStr($p['symptom']           ?? null);
$anginaIndex      = stNullInt($p['angina_index']      ?? null);
$stDepression     = stNullDecimal($p['st_depression_mm'] ?? null);
$stElevation      = stNullDecimal($p['st_elevation_mm'] ?? null);
$stLeads          = stNullStr($p['st_leads']           ?? null);
$stOnsetHr        = stNullInt($p['st_onset_hr_bpm']   ?? null);
$arrhythmias      = stNullStr($p['arrhythmias']        ?? null);

$stopReason       = stNullStr($p['stop_reason'] ?? null);
$result           = stNullStr($p['result']       ?? null);
$conclusion       = stNullStr($p['conclusion']   ?? null);
$comments         = stNullStr($p['comments']     ?? null);

// ---------------------------------------------------------------------------
// Server-side derived calculations
// ---------------------------------------------------------------------------

// Age from patient DOB if not provided
$ageYears = stNullInt($p['age_years'] ?? null);
if ($ageYears === null) {
    $patRow = sqlQuery('SELECT DOB FROM patient_data WHERE pid = ? LIMIT 1', [$pid]);
    if ($patRow && !empty($patRow['DOB'])) {
        $dob      = new DateTime($patRow['DOB']);
        $now      = new DateTime();
        $ageYears = (int)$dob->diff($now)->y;
    }
}

// Max predicted HR
$maxPredictedHr = ($ageYears !== null) ? (220 - $ageYears) : null;
// Target HR (85%)
$targetHr = ($maxPredictedHr !== null) ? (int)round(0.85 * $maxPredictedHr) : null;
// Peak HR % of predicted
$peakHrPct = null;
if ($peakHr !== null && $maxPredictedHr !== null && $maxPredictedHr > 0) {
    $peakHrPct = round($peakHr / $maxPredictedHr * 100, 1);
}
// Double product
$doubleProduct = null;
if ($peakHr !== null && $peakBpSys !== null) {
    $doubleProduct = $peakHr * $peakBpSys;
}
// Duke score: duration_minutes - 5×ST_depression - 4×angina_index
$dukeScore = null;
if ($exerciseDuration !== null && $stDepression !== null && $anginaIndex !== null) {
    $dukeScore = round(
        ($exerciseDuration / 60.0)
        - 5.0 * (float)$stDepression
        - 4.0 * $anginaIndex,
        1
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
// INSERT or UPDATE the main study row
// ---------------------------------------------------------------------------
if ($studyId === 0) {
    $studyId = (int)sqlInsert(
        'INSERT INTO `form_card_stress_test`
            (cardiology_id, pid, encounter, study_date, `user`, status,
             finalized_at, finalized_by, activity,
             indication, age_years, height_cm, weight_kg,
             on_beta_blocker, medications_note, protocol, rest_ecg_findings,
             rest_hr_bpm, rest_bp_systolic_mmhg, rest_bp_diastolic_mmhg,
             max_predicted_hr_bpm, target_hr_bpm,
             peak_hr_bpm, peak_hr_pct_predicted,
             peak_bp_systolic_mmhg, peak_bp_diastolic_mmhg,
             double_product, exercise_duration_s, mets_achieved,
             bp_response, hr_recovery_1min_bpm,
             symptom, angina_index,
             st_depression_mm, st_elevation_mm, st_leads, st_onset_hr_bpm,
             arrhythmias, stop_reason, duke_score, result,
             conclusion, comments)
         VALUES
            (?,?,?,?,?,?,?,?,1,
             ?,?,?,?,
             ?,?,?,?,
             ?,?,?,
             ?,?,
             ?,?,
             ?,?,
             ?,?,?,
             ?,?,
             ?,?,
             ?,?,?,?,
             ?,?,?,?,
             ?,?)',
        [
            $cardiologyId, $pid, $encounter, $studyDate, $authUser, $status,
            $finalizedAt, $finalizedBy,
            $indication, $ageYears, $heightCm, $weightKg,
            $onBetaBlocker, $medicationsNote, $protocol, $restEcg,
            $restHr, $restBpSys, $restBpDia,
            $maxPredictedHr, $targetHr,
            $peakHr, $peakHrPct,
            $peakBpSys, $peakBpDia,
            $doubleProduct, $exerciseDuration, $metsAchieved,
            $bpResponse, $hrRecovery1min,
            $symptom, $anginaIndex,
            $stDepression, $stElevation, $stLeads, $stOnsetHr,
            $arrhythmias, $stopReason, $dukeScore, $result,
            $conclusion, $comments,
        ]
    );
} else {
    sqlStatement(
        'UPDATE `form_card_stress_test` SET
            study_date = ?, status = ?, finalized_at = ?, finalized_by = ?,
            indication = ?, age_years = ?, height_cm = ?, weight_kg = ?,
            on_beta_blocker = ?, medications_note = ?, protocol = ?, rest_ecg_findings = ?,
            rest_hr_bpm = ?, rest_bp_systolic_mmhg = ?, rest_bp_diastolic_mmhg = ?,
            max_predicted_hr_bpm = ?, target_hr_bpm = ?,
            peak_hr_bpm = ?, peak_hr_pct_predicted = ?,
            peak_bp_systolic_mmhg = ?, peak_bp_diastolic_mmhg = ?,
            double_product = ?, exercise_duration_s = ?, mets_achieved = ?,
            bp_response = ?, hr_recovery_1min_bpm = ?,
            symptom = ?, angina_index = ?,
            st_depression_mm = ?, st_elevation_mm = ?, st_leads = ?, st_onset_hr_bpm = ?,
            arrhythmias = ?, stop_reason = ?, duke_score = ?, result = ?,
            conclusion = ?, comments = ?
          WHERE id = ? AND pid = ?',
        [
            $studyDate, $status, $finalizedAt, $finalizedBy,
            $indication, $ageYears, $heightCm, $weightKg,
            $onBetaBlocker, $medicationsNote, $protocol, $restEcg,
            $restHr, $restBpSys, $restBpDia,
            $maxPredictedHr, $targetHr,
            $peakHr, $peakHrPct,
            $peakBpSys, $peakBpDia,
            $doubleProduct, $exerciseDuration, $metsAchieved,
            $bpResponse, $hrRecovery1min,
            $symptom, $anginaIndex,
            $stDepression, $stElevation, $stLeads, $stOnsetHr,
            $arrhythmias, $stopReason, $dukeScore, $result,
            $conclusion, $comments,
            $studyId, $pid,
        ]
    );
}

// ---------------------------------------------------------------------------
// Process stage rows
// ---------------------------------------------------------------------------
$stagesPost = $_POST['stages'] ?? [];
if (is_array($stagesPost)) {
    foreach ($stagesPost as $stageData) {
        if (!is_array($stageData)) { continue; }

        $stageId     = (int)($stageData['id']     ?? 0);
        $deleteFlag  = (int)($stageData['delete']  ?? 0);
        $phase       = trim($stageData['phase']    ?? 'exercise');
        $stageNo     = stNullInt($stageData['stage_no']          ?? null);
        $elapsedS    = stNullInt($stageData['elapsed_s']         ?? null);
        $speedKmh    = stNullDecimal($stageData['speed_kmh']     ?? null);
        $gradePct    = stNullDecimal($stageData['grade_pct']     ?? null);
        $mets        = stNullDecimal($stageData['mets']          ?? null);
        $hrBpm       = stNullInt($stageData['hr_bpm']            ?? null);
        $bpSys       = stNullInt($stageData['bp_systolic_mmhg']  ?? null);
        $bpDia       = stNullInt($stageData['bp_diastolic_mmhg'] ?? null);
        $stMm        = stNullDecimal($stageData['st_mm']         ?? null);
        $symptomNote = stNullStr($stageData['symptom_note']      ?? null);

        if ($stageId > 0 && $deleteFlag === 1) {
            // Physical delete is allowed for stage rows (no activity column)
            sqlStatement(
                'DELETE FROM `form_card_stress_test_stage` WHERE id = ? AND stress_test_id = ?',
                [$stageId, $studyId]
            );
        } elseif ($stageId > 0) {
            sqlStatement(
                'UPDATE `form_card_stress_test_stage` SET
                    phase = ?, stage_no = ?, elapsed_s = ?,
                    speed_kmh = ?, grade_pct = ?, mets = ?,
                    hr_bpm = ?, bp_systolic_mmhg = ?, bp_diastolic_mmhg = ?,
                    st_mm = ?, symptom_note = ?
                  WHERE id = ? AND stress_test_id = ?',
                [
                    $phase, $stageNo, $elapsedS,
                    $speedKmh, $gradePct, $mets,
                    $hrBpm, $bpSys, $bpDia,
                    $stMm, $symptomNote,
                    $stageId, $studyId,
                ]
            );
        } else {
            // New stage row
            sqlInsert(
                'INSERT INTO `form_card_stress_test_stage`
                    (stress_test_id, phase, stage_no, elapsed_s,
                     speed_kmh, grade_pct, mets,
                     hr_bpm, bp_systolic_mmhg, bp_diastolic_mmhg,
                     st_mm, symptom_note)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $studyId, $phase, $stageNo, $elapsedS,
                    $speedKmh, $gradePct, $mets,
                    $hrBpm, $bpSys, $bpDia,
                    $stMm, $symptomNote,
                ]
            );
        }
    }
}

// ---------------------------------------------------------------------------
// Redirect to view.php
// ---------------------------------------------------------------------------
$viewUrl = CardiologyContainer::getFormRootUrl()
    . '/cards/stress_test/view.php'
    . '?pid='           . urlencode((string)$pid)
    . '&encounter='     . urlencode((string)$encounter)
    . '&cardiology_id=' . urlencode((string)$cardiologyId)
    . '&id='            . urlencode((string)$studyId);

formJump($viewUrl);

