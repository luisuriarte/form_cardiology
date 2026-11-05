<?php

/**
 * Echo card – print.php (modernized)
 *
 * Renders a complete printable report for a Doppler Echocardiogram study,
 * mirroring every section of _form_fields.php (Clinical Context, LV systolic
 * and diastolic function, atria, RV/pulmonary pressure, aorta, valves,
 * pericardium, report). Patient and encounter data come from OpenEMR's
 * PatientService / EncounterService via CardPrintReport.
 *
 * Opens in a new tab (target="_blank"). No OpenEMR navigation chrome.
 *
 * GET parameters:
 *   pid           int
 *   encounter     int
 *   cardiology_id int
 *   id            int  form_card_echo.id
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
require_once(__DIR__ . '/../../lib/CardiologyContainer.php');
require_once(__DIR__ . '/../../lib/CardSettings.php');
require_once(__DIR__ . '/../../lib/CardPrintReport.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Forms\Cardiology\CardiologyContainer;
use OpenEMR\Forms\Cardiology\CardPrintReport;

// ---------------------------------------------------------------------------
// Institutional logo for printable reports (Dompdf-safe data URI)
// ---------------------------------------------------------------------------
require_once(__DIR__ . '/../../lib/CardPrintAssets.php');

$logoBase64 = \OpenEMR\Forms\Cardiology\CardPrintAssets::logoDataUri();

// If no logo found, keep $logoBase64 empty (PDF will have no header image).

if (!AclMain::aclCheckCore('patients', 'med')) {
    die(xlt('Not authorized'));
}

$studyId      = (int)($_GET['id']            ?? 0);
$cardiologyId = (int)($_GET['cardiology_id'] ?? 0);
$pid          = (int)($_GET['pid']           ?? 0);
$encounter    = (int)($_GET['encounter']     ?? 0);

if ($studyId <= 0 || $pid <= 0) {
    die(xlt('Missing required parameters.'));
}

// Load study
$study = sqlQuery(
    'SELECT * FROM `form_card_echo` WHERE id = ? AND pid = ? AND activity = 1',
    [$studyId, $pid]
);
if (!$study || !isset($study['id'])) {
    die(xlt('Study not found or has been deleted.'));
}

$encounter = (int)($study['encounter'] ?? $encounter);
$status    = (string)($study['status'] ?? '');

// Patient + encounter data via OpenEMR services (PatientService / EncounterService).
$patient   = CardPrintReport::patient($pid);
$encounterRow = CardPrintReport::encounter($pid, $encounter);

$fv = static function (string $key) use ($study): string {
    return CardPrintReport::formatValue($study[$key] ?? null);
};
$fl = static function (string $key, string $listId = '') use ($study): string {
    return CardPrintReport::formatValue($study[$key] ?? null, $listId !== '' ? $listId : null);
};

// ---- Build HTML for Dompdf ----
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(xlt('Doppler Echocardiogram Report')) ?> - OpenEMR</title>
    <style>
        <?= CardPrintReport::style() ?>
    </style>
</head>
<body>

    <?= CardPrintReport::toolbarHtml() ?>

    <!-- Institutional Logo + Header -->
    <?php if ($logoBase64): ?>
        <div class="logo"><img src="<?= htmlspecialchars($logoBase64) ?>" alt="Logo"></div>
    <?php endif; ?>
    <div class="header">
        <div class="header-left">
            <div class="clinic-name"><?= defined('CLINIC_NAME') ? CLINIC_NAME : '' ?></div>
            <div class="clinic-sub"><?= defined('CLINIC_ADDRESS') ? CLINIC_ADDRESS : '' ?></div>
            <div class="clinic-sub"><?= defined('CLINIC_PHONE') && CLINIC_PHONE ? 'Tel: ' . CLINIC_PHONE : '' ?></div>
            <div class="clinic-sub"><?= defined('CLINIC_EMAIL') && CLINIC_EMAIL ? 'Email: ' . CLINIC_EMAIL : '' ?></div>
            <div class="clinic-sub"><?= defined('CLINIC_WEB') && CLINIC_WEB ? 'Web: ' . CLINIC_WEB : '' ?></div>
        </div>
        <div class="header-right">
            <div><?= xlt('BIOCHEMISTRY & DIAGNOSTIC SERVICE') ?></div>
            <div><?= xlt('Unified Electronic Protocol') ?></div>
            <div style="margin-top: 2px;"><?= xlt('Issue Date') ?>: <?= \OpenEMR\Forms\Cardiology\CardPrintReport::formatValue(date('Y-m-d H:i:s')) ?></div>
        </div>
    </div>

    <!-- Report Title -->
    <div class="report-title"><?= htmlspecialchars(xlt('Doppler Echocardiogram Report')) ?></div>
    <div class="report-meta">
        <?= xlt('Encounter') ?>: <?= $encounter > 0 ? $encounter : '—' ?>
        &mdash; <?= xlt('Study') ?> #<?= $studyId ?>
        <?php if (!empty($study['user'])) : ?>
        &mdash; <?= htmlspecialchars(CardPrintReport::userDisplayName((string)$study['user'])) ?>
        <?php endif; ?>
        &mdash; <?= $status === 'final' ? xlt('Final') : xlt('Draft') ?>
    </div>

    <!-- Patient + Encounter (OpenEMR PatientService / EncounterService) -->
    <?= CardPrintReport::patientEncounterTable($study, $patient, $encounterRow, $pid, $encounter, $studyId) ?>

    <?php if ($status === 'final') : ?>
    <div class="report-text" style="border:1px solid #bae6fd;background:#f0f9ff;">
        <?= xlt('This study is finalized and cannot be edited.') ?>
    </div>
    <?php endif; ?>

    <!-- Clinical Context -->
    <?= CardPrintReport::section(xlt('Clinical Context'), [
        [xlt('Study Date'), $fv('study_date')],
        [xlt('Indication'), $fv('indication')],
        [xlt('Height (cm)'), $fv('height_cm')],
        [xlt('Weight (kg)'), $fv('weight_kg')],
        [xlt('BSA (m²)'), $fv('bsa_m2')],
        [xlt('Heart Rate (bpm)'), $fv('heart_rate_bpm')],
        [xlt('BP Systolic (mmHg)'), $fv('bp_systolic_mmhg')],
        [xlt('BP Diastolic (mmHg)'), $fv('bp_diastolic_mmhg')],
    ]) ?>

    <!-- Left Ventricle – Dimensions & Systolic Function -->
    <?= CardPrintReport::section(xlt('Left Ventricle – Dimensions & Systolic Function'), [
        [xlt('LVEDD (mm)'), $fv('lvedd_mm')],
        [xlt('LVESD (mm)'), $fv('lvesd_mm')],
        [xlt('IVS (mm)'), $fv('ivs_mm')],
        [xlt('PW (mm)'), $fv('pw_mm')],
        [xlt('LVEDV (mL)'), $fv('lvedv_ml')],
        [xlt('LVESV (mL)'), $fv('lvesv_ml')],
        [xlt('LVEF (%)'), $fv('lvef_pct')],
        [xlt('LV Mass (g)'), $fv('lv_mass_g')],
        [xlt('LV Mass Index (g/m²)'), $fv('lv_mass_index_g_m2')],
        [xlt('LVEF Method'), $fl('lvef_method', 'card_ef_method')],
        [xlt('Global Wall Motion'), $fl('wall_motion', 'card_wall_motion')],
        [xlt('Segmental Wall Motion Detail'), $fv('wall_motion_detail')],
    ]) ?>

    <!-- Left Ventricle – Diastolic Function -->
    <?= CardPrintReport::section(xlt('Left Ventricle – Diastolic Function'), [
        [xlt('Mitral E (cm/s)'), $fv('mitral_e_cm_s')],
        [xlt('Mitral A (cm/s)'), $fv('mitral_a_cm_s')],
        [xlt('E/A Ratio'), $fv('mitral_e_a_ratio')],
        [xlt('Dec. Time (ms)'), $fv('dec_time_ms')],
        [xlt("e' Septal (cm/s)"), $fv('e_prime_septal_cm_s')],
        [xlt("e' Lateral (cm/s)"), $fv('e_prime_lateral_cm_s')],
        [xlt("E/e' Ratio"), $fv('e_e_prime_ratio')],
    ]) ?>

    <!-- Atria -->
    <?= CardPrintReport::section(xlt('Atria'), [
        [xlt('LA Diameter (mm)'), $fv('la_diameter_mm')],
        [xlt('LA Volume Index (mL/m²)'), $fv('la_volume_index_ml_m2')],
        [xlt('RA Area (cm²)'), $fv('ra_area_cm2')],
    ]) ?>

    <!-- Right Ventricle & Pulmonary Pressure -->
    <?= CardPrintReport::section(xlt('Right Ventricle & Pulmonary Pressure'), [
        [xlt('RV Basal Diam. (mm)'), $fv('rv_basal_diameter_mm')],
        [xlt('TAPSE (mm)'), $fv('tapse_mm')],
        [xlt("RV S' (cm/s)"), $fv('rv_s_prime_cm_s')],
        [xlt('IVC Diameter (mm)'), $fv('ivc_diameter_mm')],
        [xlt('RAP (mmHg)'), $fv('rap_mmhg')],
        [xlt('TR Peak Vel. (m/s)'), $fv('tr_peak_velocity_m_s')],
        [xlt('PASP (mmHg)'), $fv('pasp_mmhg')],
    ]) ?>

    <!-- Aorta -->
    <?= CardPrintReport::section(xlt('Aorta'), [
        [xlt('Aortic Root (mm)'), $fv('ao_root_mm')],
        [xlt('Ascending Aorta (mm)'), $fv('asc_aorta_mm')],
    ]) ?>

    <!-- Valves -->
    <?= CardPrintReport::section(xlt('Aortic Valve'), [
        [xlt('Regurgitation'), $fl('ao_regurg_grade', 'card_valve_grade')],
        [xlt('Stenosis'), $fl('ao_stenosis_grade', 'card_valve_grade')],
        [xlt('Peak Vel. (m/s)'), $fv('ao_peak_velocity_m_s')],
        [xlt('Mean Gradient (mmHg)'), $fv('ao_mean_gradient_mmhg')],
        [xlt('Valve Area (cm²)'), $fv('ao_valve_area_cm2')],
    ]) ?>
    <?= CardPrintReport::section(xlt('Mitral Valve'), [
        [xlt('Regurgitation'), $fl('mv_regurg_grade', 'card_valve_grade')],
        [xlt('Stenosis'), $fl('mv_stenosis_grade', 'card_valve_grade')],
        [xlt('Mean Gradient (mmHg)'), $fv('mv_mean_gradient_mmhg')],
        [xlt('Valve Area (cm²)'), $fv('mv_valve_area_cm2')],
    ]) ?>
    <?= CardPrintReport::section(xlt('Tricuspid & Pulmonary Valves'), [
        [xlt('Tricuspid Regurgitation'), $fl('tv_regurg_grade', 'card_valve_grade')],
        [xlt('Pulmonary Regurgitation'), $fl('pv_regurg_grade', 'card_valve_grade')],
    ]) ?>
    <?= CardPrintReport::section(xlt('Additional Valve Findings'), [
        [xlt('Additional Valve Findings'), $fv('valve_findings')],
    ], 1, true) ?>

    <!-- Pericardium -->
    <?= CardPrintReport::section(xlt('Pericardium'), [
        [xlt('Pericardial Effusion'), $fl('pericardial_effusion', 'card_effusion_grade')],
    ]) ?>

    <!-- Report -->
    <?= CardPrintReport::section(xlt('Report'), [
        [xlt('Conclusion'), $fv('conclusion')],
        [xlt('Comments'), $fv('comments')],
    ], 1, true) ?>

    <!-- Evolution charts (own page) + attached images (own pages) -->
    <?= CardPrintReport::chartsHtml('echo', $studyId, $pid) ?>
    <?= CardPrintReport::attachedDocumentsHtml('echo', $studyId, $pid) ?>

    <!-- Disclaimer -->
    <div class="disclaimer">
        <?= xlt('Report generated on') ?>: <?= \OpenEMR\Forms\Cardiology\CardPrintReport::formatValue(date('Y-m-d H:i:s')) ?> | <?= xlt('OpenEMR') ?>
    </div>
</body>
</html>
<?php
// Download as a real PDF file when ?download=1 (Dompdf, server-side).
// Otherwise the buffered HTML is flushed and shown in the browser.
if (($_GET['download'] ?? '') === '1') {
    $reportHtml = (string)ob_get_clean();
    CardPrintReport::downloadPdf(
        $reportHtml,
        'Echocardiogram_study_' . $studyId . '_encounter_' . $encounter . '.pdf'
    );
}
?>
