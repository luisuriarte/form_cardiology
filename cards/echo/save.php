<?php

/**
 * Echo card – save.php
 *
 * Handles POST from cards/echo/new.php and cards/echo/view.php.
 *
 * Checks:
 *   1. CSRF token (version-compatible via CardiologyContainer)
 *   2. ACL and card availability (via CardRegistry::canWrite)
 *   3. Container active and ownership matching pid and encounter
 *   4. Session pid and encounter consistency
 *   5. Study not finalized (using SELECT ... FOR UPDATE within transaction)
 *   6. Strict input validation with CardInput and RANGES bounds
 *   7. Finalizing requires a non-empty conclusion
 *
 * Uses a single associative array for both INSERT and UPDATE statements.
 * Wrapped in an atomic database transaction.
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
require_once(__DIR__ . '/../../lib/CardiologyContainer.php');
require_once(__DIR__ . '/../../lib/CardRegistry.php');
require_once(__DIR__ . '/../../lib/CardInput.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Forms\Cardiology\CardInput;
use OpenEMR\Forms\Cardiology\CardRegistry;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

// ---------------------------------------------------------------------------
// Plausibility ranges for input validation
// ---------------------------------------------------------------------------
const RANGES = [
    'height_cm'             => [50.0, 250.0],
    'weight_kg'             => [2.0, 400.0],
    'heart_rate_bpm'        => [20, 300],
    'bp_systolic_mmhg'      => [40, 300],
    'bp_diastolic_mmhg'     => [20, 200],
    'lvedd_mm'              => [20.0, 100.0],
    'lvesd_mm'              => [10.0, 90.0],
    'ivs_mm'                => [3.0, 30.0],
    'pw_mm'                 => [3.0, 30.0],
    'lvedv_ml'              => [20.0, 500.0],
    'lvesv_ml'              => [5.0, 400.0],
    'lvef_pct'              => [5.0, 90.0],
    'lv_mass_g'             => [20.0, 800.0],
    'mitral_e_cm_s'         => [10.0, 300.0],
    'mitral_a_cm_s'         => [10.0, 300.0],
    'dec_time_ms'           => [50, 600],
    'e_prime_septal_cm_s'   => [1.0, 30.0],
    'e_prime_lateral_cm_s'  => [1.0, 30.0],
    'la_diameter_mm'        => [10.0, 80.0],
    'la_volume_index_ml_m2' => [5.0, 120.0],
    'ra_area_cm2'           => [3.0, 50.0],
    'rv_basal_diameter_mm'  => [10.0, 80.0],
    'tapse_mm'              => [3.0, 40.0],
    'rv_s_prime_cm_s'       => [2.0, 30.0],
    'ivc_diameter_mm'       => [3.0, 40.0],
    'rap_mmhg'              => [0, 30],
    'tr_peak_velocity_m_s'  => [0.5, 6.0],
    'pasp_mmhg'             => [5, 150],
    'ao_root_mm'            => [10.0, 80.0],
    'asc_aorta_mm'          => [10.0, 90.0],
    'ao_peak_velocity_m_s'  => [0.5, 8.0],
    'ao_mean_gradient_mmhg' => [0.0, 150.0],
    'ao_valve_area_cm2'     => [0.1, 6.0],
    'mv_mean_gradient_mmhg' => [0.0, 40.0],
    'mv_valve_area_cm2'     => [0.3, 8.0],
];

// Helper to render validation error page
function echoRenderError(string $message, string $backUrl): void
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
if (!$registry->canWrite('echo', $userId)) {
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
        . '/cards/echo/view.php?id=' . urlencode((string)$studyId)
        . '&cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
} else {
    $backUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/echo/new.php?cardiology_id=' . urlencode((string)$cardiologyId)
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
    $indication   = CardInput::text($p['indication'] ?? null);
    $heightCm     = CardInput::decimal($p['height_cm'] ?? null, 'height_cm', RANGES['height_cm'][0], RANGES['height_cm'][1]);
    $weightKg     = CardInput::decimal($p['weight_kg'] ?? null, 'weight_kg', RANGES['weight_kg'][0], RANGES['weight_kg'][1]);
    $heartRateBpm = CardInput::int($p['heart_rate_bpm'] ?? null, 'heart_rate_bpm', RANGES['heart_rate_bpm'][0], RANGES['heart_rate_bpm'][1]);
    $bpSystolic   = CardInput::int($p['bp_systolic_mmhg'] ?? null, 'bp_systolic_mmhg', RANGES['bp_systolic_mmhg'][0], RANGES['bp_systolic_mmhg'][1]);
    $bpDiastolic  = CardInput::int($p['bp_diastolic_mmhg'] ?? null, 'bp_diastolic_mmhg', RANGES['bp_diastolic_mmhg'][0], RANGES['bp_diastolic_mmhg'][1]);

    // LV dimensions & systolic
    $lvedd            = CardInput::decimal($p['lvedd_mm'] ?? null, 'lvedd_mm', RANGES['lvedd_mm'][0], RANGES['lvedd_mm'][1]);
    $lvesd            = CardInput::decimal($p['lvesd_mm'] ?? null, 'lvesd_mm', RANGES['lvesd_mm'][0], RANGES['lvesd_mm'][1]);
    $ivs              = CardInput::decimal($p['ivs_mm'] ?? null, 'ivs_mm', RANGES['ivs_mm'][0], RANGES['ivs_mm'][1]);
    $pw               = CardInput::decimal($p['pw_mm'] ?? null, 'pw_mm', RANGES['pw_mm'][0], RANGES['pw_mm'][1]);
    $lvedv            = CardInput::decimal($p['lvedv_ml'] ?? null, 'lvedv_ml', RANGES['lvedv_ml'][0], RANGES['lvedv_ml'][1]);
    $lvesv            = CardInput::decimal($p['lvesv_ml'] ?? null, 'lvesv_ml', RANGES['lvesv_ml'][0], RANGES['lvesv_ml'][1]);
    $lvef             = CardInput::decimal($p['lvef_pct'] ?? null, 'lvef_pct', RANGES['lvef_pct'][0], RANGES['lvef_pct'][1]);
    $lvefMethod       = CardInput::listOption('card_ef_method', $p['lvef_method'] ?? null, 'lvef_method');
    $lvMass           = CardInput::decimal($p['lv_mass_g'] ?? null, 'lv_mass_g', RANGES['lv_mass_g'][0], RANGES['lv_mass_g'][1]);
    $wallMotion       = CardInput::listOption('card_wall_motion', $p['wall_motion'] ?? null, 'wall_motion');
    $wallMotionDetail = CardInput::text($p['wall_motion_detail'] ?? null);

    // LV diastolic
    $mitralE       = CardInput::decimal($p['mitral_e_cm_s'] ?? null, 'mitral_e_cm_s', RANGES['mitral_e_cm_s'][0], RANGES['mitral_e_cm_s'][1]);
    $mitralA       = CardInput::decimal($p['mitral_a_cm_s'] ?? null, 'mitral_a_cm_s', RANGES['mitral_a_cm_s'][0], RANGES['mitral_a_cm_s'][1]);
    $decTime       = CardInput::int($p['dec_time_ms'] ?? null, 'dec_time_ms', RANGES['dec_time_ms'][0], RANGES['dec_time_ms'][1]);
    $ePrimeSeptal  = CardInput::decimal($p['e_prime_septal_cm_s'] ?? null, 'e_prime_septal_cm_s', RANGES['e_prime_septal_cm_s'][0], RANGES['e_prime_septal_cm_s'][1]);
    $ePrimeLateral = CardInput::decimal($p['e_prime_lateral_cm_s'] ?? null, 'e_prime_lateral_cm_s', RANGES['e_prime_lateral_cm_s'][0], RANGES['e_prime_lateral_cm_s'][1]);

    // Atria
    $laDiameter    = CardInput::decimal($p['la_diameter_mm'] ?? null, 'la_diameter_mm', RANGES['la_diameter_mm'][0], RANGES['la_diameter_mm'][1]);
    $laVolumeIndex = CardInput::decimal($p['la_volume_index_ml_m2'] ?? null, 'la_volume_index_ml_m2', RANGES['la_volume_index_ml_m2'][0], RANGES['la_volume_index_ml_m2'][1]);
    $raArea        = CardInput::decimal($p['ra_area_cm2'] ?? null, 'ra_area_cm2', RANGES['ra_area_cm2'][0], RANGES['ra_area_cm2'][1]);

    // RV
    $rvBasal   = CardInput::decimal($p['rv_basal_diameter_mm'] ?? null, 'rv_basal_diameter_mm', RANGES['rv_basal_diameter_mm'][0], RANGES['rv_basal_diameter_mm'][1]);
    $tapse     = CardInput::decimal($p['tapse_mm'] ?? null, 'tapse_mm', RANGES['tapse_mm'][0], RANGES['tapse_mm'][1]);
    $rvSPrime  = CardInput::decimal($p['rv_s_prime_cm_s'] ?? null, 'rv_s_prime_cm_s', RANGES['rv_s_prime_cm_s'][0], RANGES['rv_s_prime_cm_s'][1]);
    $ivcDiameter = CardInput::decimal($p['ivc_diameter_mm'] ?? null, 'ivc_diameter_mm', RANGES['ivc_diameter_mm'][0], RANGES['ivc_diameter_mm'][1]);
    $rap       = CardInput::int($p['rap_mmhg'] ?? null, 'rap_mmhg', RANGES['rap_mmhg'][0], RANGES['rap_mmhg'][1]);
    $trPeakVel = CardInput::decimal($p['tr_peak_velocity_m_s'] ?? null, 'tr_peak_velocity_m_s', RANGES['tr_peak_velocity_m_s'][0], RANGES['tr_peak_velocity_m_s'][1]);
    $pasp      = CardInput::int($p['pasp_mmhg'] ?? null, 'pasp_mmhg', RANGES['pasp_mmhg'][0], RANGES['pasp_mmhg'][1]);

    // Aorta
    $aoRoot   = CardInput::decimal($p['ao_root_mm'] ?? null, 'ao_root_mm', RANGES['ao_root_mm'][0], RANGES['ao_root_mm'][1]);
    $ascAorta = CardInput::decimal($p['asc_aorta_mm'] ?? null, 'asc_aorta_mm', RANGES['asc_aorta_mm'][0], RANGES['asc_aorta_mm'][1]);

    // Valves
    $aoRegurg      = CardInput::listOption('card_valve_grade', $p['ao_regurg_grade'] ?? null, 'ao_regurg_grade');
    $aoStenosis    = CardInput::listOption('card_valve_grade', $p['ao_stenosis_grade'] ?? null, 'ao_stenosis_grade');
    $aoPeakVel     = CardInput::decimal($p['ao_peak_velocity_m_s'] ?? null, 'ao_peak_velocity_m_s', RANGES['ao_peak_velocity_m_s'][0], RANGES['ao_peak_velocity_m_s'][1]);
    $aoMeanGrad    = CardInput::decimal($p['ao_mean_gradient_mmhg'] ?? null, 'ao_mean_gradient_mmhg', RANGES['ao_mean_gradient_mmhg'][0], RANGES['ao_mean_gradient_mmhg'][1]);
    $aoArea        = CardInput::decimal($p['ao_valve_area_cm2'] ?? null, 'ao_valve_area_cm2', RANGES['ao_valve_area_cm2'][0], RANGES['ao_valve_area_cm2'][1]);
    $mvRegurg      = CardInput::listOption('card_valve_grade', $p['mv_regurg_grade'] ?? null, 'mv_regurg_grade');
    $mvStenosis    = CardInput::listOption('card_valve_grade', $p['mv_stenosis_grade'] ?? null, 'mv_stenosis_grade');
    $mvMeanGrad    = CardInput::decimal($p['mv_mean_gradient_mmhg'] ?? null, 'mv_mean_gradient_mmhg', RANGES['mv_mean_gradient_mmhg'][0], RANGES['mv_mean_gradient_mmhg'][1]);
    $mvArea        = CardInput::decimal($p['mv_valve_area_cm2'] ?? null, 'mv_valve_area_cm2', RANGES['mv_valve_area_cm2'][0], RANGES['mv_valve_area_cm2'][1]);
    $tvRegurg      = CardInput::listOption('card_valve_grade', $p['tv_regurg_grade'] ?? null, 'tv_regurg_grade');
    $pvRegurg      = CardInput::listOption('card_valve_grade', $p['pv_regurg_grade'] ?? null, 'pv_regurg_grade');
    $valveFindings = CardInput::text($p['valve_findings'] ?? null);

    // Pericardium
    $pericardialEff = CardInput::listOption('card_effusion_grade', $p['pericardial_effusion'] ?? null, 'pericardial_effusion');

    // Report
    $conclusion = CardInput::text($p['conclusion'] ?? null);
    $comments   = CardInput::text($p['comments'] ?? null);

    // Finalize requires a non-empty conclusion
    if ($action === 'save_final' && ($conclusion === null || $conclusion === '')) {
        throw new InvalidArgumentException(xlt('A conclusion is required to finalize the study.'));
    }
} catch (InvalidArgumentException $e) {
    echoRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// Server-side calculation of derived values
// ---------------------------------------------------------------------------
// BSA (Du Bois): 0.007184 * H^0.725 * W^0.425
$bsa = null;
if ($heightCm !== null && $weightKg !== null) {
    $h = (float)$heightCm;
    $w = (float)$weightKg;
    if ($h > 0 && $w > 0) {
        $bsa = number_format(round(0.007184 * pow($h, 0.725) * pow($w, 0.425), 2), 2, '.', '');
    }
}

// E/A ratio
$eaRatio = null;
if ($mitralE !== null && $mitralA !== null && (float)$mitralA > 0) {
    $eaRatio = number_format(round((float)$mitralE / (float)$mitralA, 2), 2, '.', '');
}

// E/e' ratio
$eePrimeRatio = null;
if ($mitralE !== null && $ePrimeSeptal !== null && $ePrimeLateral !== null) {
    $avgEPrime = ((float)$ePrimeSeptal + (float)$ePrimeLateral) / 2.0;
    if ($avgEPrime > 0) {
        $eePrimeRatio = number_format(round((float)$mitralE / $avgEPrime, 1), 1, '.', '');
    }
} elseif ($mitralE !== null && $ePrimeSeptal !== null && (float)$ePrimeSeptal > 0) {
    $eePrimeRatio = number_format(round((float)$mitralE / (float)$ePrimeSeptal, 1), 1, '.', '');
}

// LV mass index
$lvMassIndex = null;
if ($lvMass !== null && $bsa !== null && (float)$bsa > 0) {
    $lvMassIndex = number_format(round((float)$lvMass / (float)$bsa, 1), 1, '.', '');
}

// ---------------------------------------------------------------------------
// Status & audit
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
    'height_cm'             => $heightCm,
    'weight_kg'             => $weightKg,
    'bsa_m2'                => $bsa,
    'heart_rate_bpm'        => $heartRateBpm,
    'bp_systolic_mmhg'      => $bpSystolic,
    'bp_diastolic_mmhg'     => $bpDiastolic,

    // LV dimensions & systolic
    'lvedd_mm'              => $lvedd,
    'lvesd_mm'              => $lvesd,
    'ivs_mm'                => $ivs,
    'pw_mm'                 => $pw,
    'lvedv_ml'              => $lvedv,
    'lvesv_ml'              => $lvesv,
    'lvef_pct'              => $lvef,
    'lvef_method'           => $lvefMethod,
    'lv_mass_g'             => $lvMass,
    'lv_mass_index_g_m2'    => $lvMassIndex,
    'wall_motion'           => $wallMotion,
    'wall_motion_detail'    => $wallMotionDetail,

    // LV diastolic
    'mitral_e_cm_s'         => $mitralE,
    'mitral_a_cm_s'         => $mitralA,
    'mitral_e_a_ratio'      => $eaRatio,
    'dec_time_ms'           => $decTime,
    'e_prime_septal_cm_s'   => $ePrimeSeptal,
    'e_prime_lateral_cm_s'  => $ePrimeLateral,
    'e_e_prime_ratio'       => $eePrimeRatio,

    // Atria
    'la_diameter_mm'        => $laDiameter,
    'la_volume_index_ml_m2' => $laVolumeIndex,
    'ra_area_cm2'           => $raArea,

    // RV
    'rv_basal_diameter_mm'  => $rvBasal,
    'tapse_mm'              => $tapse,
    'rv_s_prime_cm_s'       => $rvSPrime,
    'ivc_diameter_mm'       => $ivcDiameter,
    'rap_mmhg'              => $rap,
    'tr_peak_velocity_m_s'  => $trPeakVel,
    'pasp_mmhg'             => $pasp,

    // Aorta
    'ao_root_mm'            => $aoRoot,
    'asc_aorta_mm'          => $ascAorta,

    // Valves
    'ao_regurg_grade'       => $aoRegurg,
    'ao_stenosis_grade'     => $aoStenosis,
    'ao_peak_velocity_m_s'  => $aoPeakVel,
    'ao_mean_gradient_mmhg' => $aoMeanGrad,
    'ao_valve_area_cm2'     => $aoArea,
    'mv_regurg_grade'       => $mvRegurg,
    'mv_stenosis_grade'     => $mvStenosis,
    'mv_mean_gradient_mmhg' => $mvMeanGrad,
    'mv_valve_area_cm2'     => $mvArea,
    'tv_regurg_grade'       => $tvRegurg,
    'pv_regurg_grade'       => $pvRegurg,
    'valve_findings'        => $valveFindings,

    // Pericardium
    'pericardial_effusion'  => $pericardialEff,

    // Report
    'conclusion'            => $conclusion,
    'comments'              => $comments,
];

// ---------------------------------------------------------------------------
// 5. Database Transaction
// ---------------------------------------------------------------------------
CardiologyContainer::startTransaction();

try {
    if ($studyId === 0) {
        // INSERT
        $columns = array_keys($data);
        $sql = 'INSERT INTO `form_card_echo` (`' . implode('`, `', $columns) . '`) '
             . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $studyId = (int)sqlInsert($sql, array_values($data));
    } else {
        // SELECT FOR UPDATE and verify
        $existing = sqlQuery(
            'SELECT id, status FROM `form_card_echo`
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

        $sql = 'UPDATE `form_card_echo` SET ' . implode(', ', $setParts)
             . ' WHERE id = ? AND pid = ? AND cardiology_id = ? AND status <> \'final\'';

        sqlStatement($sql, $params);
    }

    CardiologyContainer::commitTransaction();
} catch (Throwable $e) {
    CardiologyContainer::rollbackTransaction();
    echoRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// 6. Redirect to view.php
// ---------------------------------------------------------------------------
$viewUrl = CardiologyContainer::getFormRootUrl()
    . '/cards/echo/view.php'
    . '?pid='           . urlencode((string)$pid)
    . '&encounter='     . urlencode((string)$encounter)
    . '&cardiology_id=' . urlencode((string)$cardiologyId)
    . '&id='            . urlencode((string)$studyId);

formJump($viewUrl);
