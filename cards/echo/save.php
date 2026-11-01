<?php

/**
 * Echo card – save.php
 *
 * Handles POST from cards/echo/new.php and cards/echo/view.php.
 *
 * Checks:
 *   1. CSRF token
 *   2. ACL (patients / med – write)
 *   3. Parent container active
 *   4. Study not finalized (if updating)
 *
 * Sanitizes all measurement fields, calculates derived values (BSA, E/A,
 * E/e', LV mass index) that the JS may have already computed, then
 * INSERTs or UPDATEs form_card_echo and redirects to view.php.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Echo
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

// ---------------------------------------------------------------------------
// 1. CSRF
// ---------------------------------------------------------------------------
if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
    CsrfUtils::csrfNotVerified();
}

// ---------------------------------------------------------------------------
// 2. ACL
// ---------------------------------------------------------------------------
if (!AclMain::aclCheckCore('patients', 'med', '', 'write')) {
    die(xlt('Not authorized'));
}

// ---------------------------------------------------------------------------
// 3. Parent container must be active
// ---------------------------------------------------------------------------
$cardiologyId = (int)($_POST['cardiology_id'] ?? 0);
$container    = CardiologyContainer::getContainerById($cardiologyId);
if ($container === null) {
    die(xlt('The cardiology record is not available or has been deleted.'));
}

// ---------------------------------------------------------------------------
// Collect scalars
// ---------------------------------------------------------------------------
$pid       = (int)($_POST['pid']       ?? 0);
$encounter = (int)($_POST['encounter'] ?? 0);
$studyId   = (int)($_POST['study_id']  ?? 0);
$action    = trim($_POST['action'] ?? 'save_draft');

// Validate session consistency
/** @var int $pid */
/** @var int $encounter */
if ((isset($GLOBALS['pid']) && $pid !== (int)$GLOBALS['pid'])
    || (isset($GLOBALS['encounter']) && $encounter !== (int)$GLOBALS['encounter'])) {
    die(xlt('Session mismatch. Please reload and try again.'));
}

// ---------------------------------------------------------------------------
// 4. If updating, verify the study exists, belongs to same pid, not finalized
// ---------------------------------------------------------------------------
if ($studyId > 0) {
    $existing = sqlQuery(
        'SELECT id, status FROM `form_card_echo`
          WHERE id = ? AND pid = ? AND activity = 1',
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
// Helper functions for sanitizing POST values
// ---------------------------------------------------------------------------
function echoNullableDecimal(?string $val): ?string
{
    $val = trim($val ?? '');
    return $val !== '' ? (string)(float)$val : null;
}

function echoNullableInt(?string $val): ?int
{
    $val = trim($val ?? '');
    return $val !== '' ? (int)$val : null;
}

function echoNullableStr(?string $val): ?string
{
    $val = trim($val ?? '');
    return $val !== '' ? $val : null;
}

// ---------------------------------------------------------------------------
// Collect and sanitize all fields
// ---------------------------------------------------------------------------
$studyDate   = echoNullableStr($_POST['study_date'] ?? '');
// datetime-local sends "YYYY-MM-DDTHH:MM"; convert to MySQL datetime
if ($studyDate !== null) {
    $studyDate = str_replace('T', ' ', $studyDate);
}

$p = $_POST;

// Clinical context
$indication        = echoNullableStr($p['indication']        ?? null);
$heightCm          = echoNullableDecimal($p['height_cm']     ?? null);
$weightKg          = echoNullableDecimal($p['weight_kg']     ?? null);
$heartRateBpm      = echoNullableInt($p['heart_rate_bpm']    ?? null);
$bpSystolic        = echoNullableInt($p['bp_systolic_mmhg']  ?? null);
$bpDiastolic       = echoNullableInt($p['bp_diastolic_mmhg'] ?? null);

// LV dimensions
$lvedd             = echoNullableDecimal($p['lvedd_mm']      ?? null);
$lvesd             = echoNullableDecimal($p['lvesd_mm']      ?? null);
$ivs               = echoNullableDecimal($p['ivs_mm']        ?? null);
$pw                = echoNullableDecimal($p['pw_mm']         ?? null);
$lvedv             = echoNullableDecimal($p['lvedv_ml']      ?? null);
$lvesv             = echoNullableDecimal($p['lvesv_ml']      ?? null);
$lvef              = echoNullableDecimal($p['lvef_pct']      ?? null);
$lvefMethod        = echoNullableStr($p['lvef_method']       ?? null);
$lvMass            = echoNullableDecimal($p['lv_mass_g']     ?? null);
$wallMotion        = echoNullableStr($p['wall_motion']       ?? null);
$wallMotionDetail  = echoNullableStr($p['wall_motion_detail'] ?? null);

// Diastolic
$mitralE           = echoNullableDecimal($p['mitral_e_cm_s']       ?? null);
$mitralA           = echoNullableDecimal($p['mitral_a_cm_s']       ?? null);
$decTime           = echoNullableInt($p['dec_time_ms']             ?? null);
$ePrimeSeptal      = echoNullableDecimal($p['e_prime_septal_cm_s'] ?? null);
$ePrimeLateral     = echoNullableDecimal($p['e_prime_lateral_cm_s'] ?? null);

// Atria
$laDiameter        = echoNullableDecimal($p['la_diameter_mm']          ?? null);
$laVolumeIndex     = echoNullableDecimal($p['la_volume_index_ml_m2']   ?? null);
$raArea            = echoNullableDecimal($p['ra_area_cm2']             ?? null);

// RV
$rvBasal           = echoNullableDecimal($p['rv_basal_diameter_mm']    ?? null);
$tapse             = echoNullableDecimal($p['tapse_mm']                ?? null);
$rvSPrime          = echoNullableDecimal($p['rv_s_prime_cm_s']         ?? null);
$ivcDiameter       = echoNullableDecimal($p['ivc_diameter_mm']         ?? null);
$rap               = echoNullableInt($p['rap_mmhg']                    ?? null);
$trPeakVel         = echoNullableDecimal($p['tr_peak_velocity_m_s']    ?? null);
$pasp              = echoNullableInt($p['pasp_mmhg']                   ?? null);

// Aorta
$aoRoot            = echoNullableDecimal($p['ao_root_mm']              ?? null);
$ascAorta          = echoNullableDecimal($p['asc_aorta_mm']            ?? null);

// Valves
$aoRegurg          = echoNullableStr($p['ao_regurg_grade']             ?? null);
$aoStenosis        = echoNullableStr($p['ao_stenosis_grade']           ?? null);
$aoPeakVel         = echoNullableDecimal($p['ao_peak_velocity_m_s']   ?? null);
$aoMeanGrad        = echoNullableDecimal($p['ao_mean_gradient_mmhg']   ?? null);
$aoArea            = echoNullableDecimal($p['ao_valve_area_cm2']       ?? null);
$mvRegurg          = echoNullableStr($p['mv_regurg_grade']             ?? null);
$mvStenosis        = echoNullableStr($p['mv_stenosis_grade']           ?? null);
$mvMeanGrad        = echoNullableDecimal($p['mv_mean_gradient_mmhg']   ?? null);
$mvArea            = echoNullableDecimal($p['mv_valve_area_cm2']       ?? null);
$tvRegurg          = echoNullableStr($p['tv_regurg_grade']             ?? null);
$pvRegurg          = echoNullableStr($p['pv_regurg_grade']             ?? null);
$valveFindings     = echoNullableStr($p['valve_findings']              ?? null);

// Pericardium
$pericardialEff    = echoNullableStr($p['pericardial_effusion']        ?? null);

// Report
$conclusion        = echoNullableStr($p['conclusion']                  ?? null);
$comments          = echoNullableStr($p['comments']                    ?? null);

// ---------------------------------------------------------------------------
// Server-side calculation of derived values
// (The JS calculates these live; we recalculate here to be safe)
// ---------------------------------------------------------------------------

// BSA (Du Bois): 0.007184 * H^0.725 * W^0.425
$bsa = null;
if ($heightCm !== null && $weightKg !== null) {
    $h   = (float)$heightCm;
    $w   = (float)$weightKg;
    if ($h > 0 && $w > 0) {
        $bsa = round(0.007184 * pow($h, 0.725) * pow($w, 0.425), 2);
    }
}

// E/A ratio
$eaRatio = null;
if ($mitralE !== null && $mitralA !== null && (float)$mitralA > 0) {
    $eaRatio = round((float)$mitralE / (float)$mitralA, 2);
}

// E/e' ratio (average of septal and lateral)
$eePrimeRatio = null;
if ($mitralE !== null && $ePrimeSeptal !== null && $ePrimeLateral !== null) {
    $avgEPrime = ((float)$ePrimeSeptal + (float)$ePrimeLateral) / 2.0;
    if ($avgEPrime > 0) {
        $eePrimeRatio = round((float)$mitralE / $avgEPrime, 1);
    }
} elseif ($mitralE !== null && $ePrimeSeptal !== null && (float)$ePrimeSeptal > 0) {
    $eePrimeRatio = round((float)$mitralE / (float)$ePrimeSeptal, 1);
}

// LV mass index
$lvMassIndex = null;
if ($lvMass !== null && $bsa !== null && $bsa > 0) {
    $lvMassIndex = round((float)$lvMass / $bsa, 1);
}

// ---------------------------------------------------------------------------
// Status
// ---------------------------------------------------------------------------
$status      = ($action === 'save_final') ? 'final' : 'draft';
$finalizedAt = ($status === 'final') ? date('Y-m-d H:i:s') : null;
$finalizedBy = ($status === 'final') ? ($_SESSION['authUser'] ?? '') : null;
$authUser    = $_SESSION['authUser'] ?? '';

// ---------------------------------------------------------------------------
// INSERT or UPDATE
// ---------------------------------------------------------------------------
if ($studyId === 0) {
    // INSERT new study
    $studyId = (int)sqlInsert(
        'INSERT INTO `form_card_echo`
            (cardiology_id, pid, encounter, study_date, `user`, status,
             finalized_at, finalized_by, activity,
             indication, height_cm, weight_kg, bsa_m2,
             heart_rate_bpm, bp_systolic_mmhg, bp_diastolic_mmhg,
             lvedd_mm, lvesd_mm, ivs_mm, pw_mm,
             lvedv_ml, lvesv_ml, lvef_pct, lvef_method,
             lv_mass_g, lv_mass_index_g_m2, wall_motion, wall_motion_detail,
             mitral_e_cm_s, mitral_a_cm_s, mitral_e_a_ratio, dec_time_ms,
             e_prime_septal_cm_s, e_prime_lateral_cm_s, e_e_prime_ratio,
             la_diameter_mm, la_volume_index_ml_m2, ra_area_cm2,
             rv_basal_diameter_mm, tapse_mm, rv_s_prime_cm_s,
             ivc_diameter_mm, rap_mmhg, tr_peak_velocity_m_s, pasp_mmhg,
             ao_root_mm, asc_aorta_mm,
             ao_regurg_grade, ao_stenosis_grade, ao_peak_velocity_m_s,
             ao_mean_gradient_mmhg, ao_valve_area_cm2,
             mv_regurg_grade, mv_stenosis_grade,
             mv_mean_gradient_mmhg, mv_valve_area_cm2,
             tv_regurg_grade, pv_regurg_grade, valve_findings,
             pericardial_effusion, conclusion, comments)
         VALUES
            (?,?,?,?,?,?,?,?,1,
             ?,?,?,?,
             ?,?,?,
             ?,?,?,?,
             ?,?,?,?,
             ?,?,?,?,
             ?,?,?,?,
             ?,?,?,
             ?,?,?,
             ?,?,?,
             ?,?,?,?,
             ?,?,
             ?,?,?,?,?,
             ?,?,?,?,?,
             ?,?,?,?,
             ?,?)',
        [
            $cardiologyId, $pid, $encounter, $studyDate, $authUser, $status,
            $finalizedAt, $finalizedBy,
            $indication, $heightCm, $weightKg, $bsa,
            $heartRateBpm, $bpSystolic, $bpDiastolic,
            $lvedd, $lvesd, $ivs, $pw,
            $lvedv, $lvesv, $lvef, $lvefMethod,
            $lvMass, $lvMassIndex, $wallMotion, $wallMotionDetail,
            $mitralE, $mitralA, $eaRatio, $decTime,
            $ePrimeSeptal, $ePrimeLateral, $eePrimeRatio,
            $laDiameter, $laVolumeIndex, $raArea,
            $rvBasal, $tapse, $rvSPrime,
            $ivcDiameter, $rap, $trPeakVel, $pasp,
            $aoRoot, $ascAorta,
            $aoRegurg, $aoStenosis, $aoPeakVel, $aoMeanGrad, $aoArea,
            $mvRegurg, $mvStenosis, $mvMeanGrad, $mvArea,
            $tvRegurg, $pvRegurg, $valveFindings,
            $pericardialEff, $conclusion, $comments,
        ]
    );
} else {
    // UPDATE existing study
    sqlStatement(
        'UPDATE `form_card_echo` SET
            study_date = ?, status = ?, finalized_at = ?, finalized_by = ?,
            indication = ?, height_cm = ?, weight_kg = ?, bsa_m2 = ?,
            heart_rate_bpm = ?, bp_systolic_mmhg = ?, bp_diastolic_mmhg = ?,
            lvedd_mm = ?, lvesd_mm = ?, ivs_mm = ?, pw_mm = ?,
            lvedv_ml = ?, lvesv_ml = ?, lvef_pct = ?, lvef_method = ?,
            lv_mass_g = ?, lv_mass_index_g_m2 = ?, wall_motion = ?, wall_motion_detail = ?,
            mitral_e_cm_s = ?, mitral_a_cm_s = ?, mitral_e_a_ratio = ?, dec_time_ms = ?,
            e_prime_septal_cm_s = ?, e_prime_lateral_cm_s = ?, e_e_prime_ratio = ?,
            la_diameter_mm = ?, la_volume_index_ml_m2 = ?, ra_area_cm2 = ?,
            rv_basal_diameter_mm = ?, tapse_mm = ?, rv_s_prime_cm_s = ?,
            ivc_diameter_mm = ?, rap_mmhg = ?, tr_peak_velocity_m_s = ?, pasp_mmhg = ?,
            ao_root_mm = ?, asc_aorta_mm = ?,
            ao_regurg_grade = ?, ao_stenosis_grade = ?, ao_peak_velocity_m_s = ?,
            ao_mean_gradient_mmhg = ?, ao_valve_area_cm2 = ?,
            mv_regurg_grade = ?, mv_stenosis_grade = ?,
            mv_mean_gradient_mmhg = ?, mv_valve_area_cm2 = ?,
            tv_regurg_grade = ?, pv_regurg_grade = ?, valve_findings = ?,
            pericardial_effusion = ?, conclusion = ?, comments = ?
          WHERE id = ? AND pid = ?',
        [
            $studyDate, $status, $finalizedAt, $finalizedBy,
            $indication, $heightCm, $weightKg, $bsa,
            $heartRateBpm, $bpSystolic, $bpDiastolic,
            $lvedd, $lvesd, $ivs, $pw,
            $lvedv, $lvesv, $lvef, $lvefMethod,
            $lvMass, $lvMassIndex, $wallMotion, $wallMotionDetail,
            $mitralE, $mitralA, $eaRatio, $decTime,
            $ePrimeSeptal, $ePrimeLateral, $eePrimeRatio,
            $laDiameter, $laVolumeIndex, $raArea,
            $rvBasal, $tapse, $rvSPrime,
            $ivcDiameter, $rap, $trPeakVel, $pasp,
            $aoRoot, $ascAorta,
            $aoRegurg, $aoStenosis, $aoPeakVel, $aoMeanGrad, $aoArea,
            $mvRegurg, $mvStenosis, $mvMeanGrad, $mvArea,
            $tvRegurg, $pvRegurg, $valveFindings,
            $pericardialEff, $conclusion, $comments,
            $studyId, $pid,
        ]
    );
}

// ---------------------------------------------------------------------------
// Redirect to view.php
// ---------------------------------------------------------------------------
$viewUrl = $GLOBALS['rootdir']
    . '/forms/cardiology/cards/echo/view.php'
    . '?pid='          . urlencode((string)$pid)
    . '&encounter='    . urlencode((string)$encounter)
    . '&cardiology_id=' . urlencode((string)$cardiologyId)
    . '&id='           . urlencode((string)$studyId);

formJump($viewUrl);

