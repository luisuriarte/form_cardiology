<?php

/**
 * Echo card – print.php
 *
 * Renders a clean, printable report for a Doppler Echocardiogram study.
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

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

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

// Load patient
$patient = sqlQuery(
    'SELECT fname, mname, lname, DOB, sex FROM `patient_data` WHERE pid = ? LIMIT 1',
    [$pid]
);
$patientName = trim(
    ($patient['fname'] ?? '') . ' '
    . ($patient['mname'] ?? '') . ' '
    . ($patient['lname'] ?? '')
);

// Helper: display a value or em-dash
function echoVal($v, string $unit = ''): string
{
    if ($v === null || $v === '') {
        return '<span class="text-muted">—</span>';
    }
    return text((string)$v) . ($unit ? ' <span class="text-muted small">' . text($unit) . '</span>' : '');
}

// Load list label helper
function echoListLabel(string $listId, string $optionId): string
{
    if ($optionId === '' || $optionId === null) {
        return '—';
    }
    $row = sqlQuery(
        'SELECT title FROM list_options WHERE list_id = ? AND option_id = ?',
        [$listId, $optionId]
    );
    return $row ? text(xl($row['title'])) : text($optionId);
}

$statusLabel = ($study['status'] === 'final') ? xlt('Final') : xlt('Draft');
$statusClass = ($study['status'] === 'final') ? 'text-success' : 'text-warning';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Echocardiogram Report'); ?> – <?php echo text($patientName); ?></title>
    <link rel="stylesheet"
          href="<?php echo attr($GLOBALS['webroot']); ?>/public/assets/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/css/cardiology.css">
    <?php
    if (class_exists(\OpenEMR\Core\Header::class)) {
        echo \OpenEMR\Core\Header::setupAssets(['chart']);
    } else {
        $assets = $GLOBALS['assets_static_relative'] ?? ($GLOBALS['webroot'] . '/public/assets');
        echo '<script src="' . attr($assets) . '/chart.js/dist/chart.umd.js"></script>';
    }
    ?>
    <style>
        body { font-size: 13px; }
        .report-header { border-bottom: 2px solid #dc3545; margin-bottom: 1rem; }
        .section-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase;
                         color: #dc3545; border-bottom: 1px solid #dee2e6;
                         padding-bottom: 2px; margin: 1rem 0 0.5rem; }
        .field-label  { font-weight: 600; color: #495057; }
        .field-value  { color: #212529; }
        .conclusion-box { border-left: 4px solid #dc3545; padding: 0.75rem 1rem;
                          background: #fff8f8; margin-top: 1rem; }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 11px; }
        }
    </style>
</head>
<body class="p-3">

<!-- Print button -->
<div class="no-print mb-3">
    <button class="btn btn-sm btn-primary" onclick="if (window.CardiologyCharts) { window.CardiologyCharts.onReady(function() { window.print(); }); } else { window.print(); }">
        <i class="fa fa-print me-1"></i><?php echo xlt('Print'); ?>
    </button>
    <button class="btn btn-sm btn-secondary ms-2" onclick="window.close()">
        <?php echo xlt('Close'); ?>
    </button>
</div>

<!-- Report header -->
<div class="report-header pb-2">
    <div class="row">
        <div class="col-8">
            <h4 class="mb-0">
                <?php echo xlt('Doppler Echocardiogram Report'); ?>
            </h4>
            <div>
                <strong><?php echo xlt('Patient'); ?>:</strong> <?php echo text($patientName); ?>
                (ID: <?php echo text((string)$pid); ?>)
            </div>
            <div>
                <strong><?php echo xlt('DOB'); ?>:</strong> <?php echo text($patient['DOB'] ?? ''); ?>
                &nbsp;&nbsp;
                <strong><?php echo xlt('Sex'); ?>:</strong> <?php echo text($patient['sex'] ?? ''); ?>
            </div>
        </div>
        <div class="col-4 text-end">
            <div>
                <strong><?php echo xlt('Study Date'); ?>:</strong>
                <?php echo text($study['study_date'] ?? ''); ?>
            </div>
            <div>
                <strong><?php echo xlt('Physician'); ?>:</strong>
                <?php echo text($study['user'] ?? ''); ?>
            </div>
            <div>
                <strong><?php echo xlt('Status'); ?>:</strong>
                <span class="<?php echo attr($statusClass); ?> fw-bold"><?php echo $statusLabel; ?></span>
            </div>
            <?php if ($study['status'] === 'final') : ?>
            <div class="text-muted small">
                <?php echo xlt('Finalized'); ?>: <?php echo text($study['finalized_at'] ?? ''); ?>
                <?php if (!empty($study['finalized_by'])) : ?>
                    <?php echo xlt('by'); ?> <?php echo text($study['finalized_by']); ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Indication -->
<?php if (!empty($study['indication'])) : ?>
<div class="mb-2">
    <span class="field-label"><?php echo xlt('Indication'); ?>:</span>
    <span class="field-value"><?php echo text($study['indication']); ?></span>
</div>
<?php endif; ?>

<!-- Clinical Context -->
<div class="section-title"><?php echo xlt('Clinical Context'); ?></div>
<div class="row">
    <div class="col-6">
        <table class="table table-sm table-borderless mb-0">
            <tr>
                <td class="field-label"><?php echo xlt('Height'); ?></td>
                <td><?php echo echoVal($study['height_cm'], 'cm'); ?></td>
                <td class="field-label"><?php echo xlt('Weight'); ?></td>
                <td><?php echo echoVal($study['weight_kg'], 'kg'); ?></td>
            </tr>
            <tr>
                <td class="field-label"><?php echo xlt('BSA'); ?></td>
                <td><?php echo echoVal($study['bsa_m2'], 'm²'); ?></td>
                <td class="field-label"><?php echo xlt('Heart Rate'); ?></td>
                <td><?php echo echoVal($study['heart_rate_bpm'], 'bpm'); ?></td>
            </tr>
            <tr>
                <td class="field-label"><?php echo xlt('BP'); ?></td>
                <td colspan="3">
                    <?php echo echoVal($study['bp_systolic_mmhg']); ?>/<?php echo echoVal($study['bp_diastolic_mmhg'], 'mmHg'); ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- LV Systolic -->
<div class="section-title"><?php echo xlt('Left Ventricle – Dimensions & Systolic Function'); ?></div>
<div class="row">
    <div class="col-12">
        <table class="table table-sm table-bordered">
            <tbody>
            <tr>
                <td class="field-label">LVEDD</td><td><?php echo echoVal($study['lvedd_mm'], 'mm'); ?></td>
                <td class="field-label">LVESD</td><td><?php echo echoVal($study['lvesd_mm'], 'mm'); ?></td>
                <td class="field-label">IVS</td><td><?php echo echoVal($study['ivs_mm'], 'mm'); ?></td>
                <td class="field-label">PW</td><td><?php echo echoVal($study['pw_mm'], 'mm'); ?></td>
            </tr>
            <tr>
                <td class="field-label">LVEDV</td><td><?php echo echoVal($study['lvedv_ml'], 'mL'); ?></td>
                <td class="field-label">LVESV</td><td><?php echo echoVal($study['lvesv_ml'], 'mL'); ?></td>
                <td class="field-label">LVEF</td><td><?php echo echoVal($study['lvef_pct'], '%'); ?></td>
                <td class="field-label"><?php echo xlt('Method'); ?></td>
                <td><?php echo echoListLabel('card_ef_method', $study['lvef_method'] ?? ''); ?></td>
            </tr>
            <tr>
                <td class="field-label"><?php echo xlt('LV Mass'); ?></td><td><?php echo echoVal($study['lv_mass_g'], 'g'); ?></td>
                <td class="field-label"><?php echo xlt('LV Mass Index'); ?></td><td><?php echo echoVal($study['lv_mass_index_g_m2'], 'g/m²'); ?></td>
                <td class="field-label"><?php echo xlt('Wall Motion'); ?></td>
                <td><?php echo echoListLabel('card_wall_motion', $study['wall_motion'] ?? ''); ?></td>
                <td class="field-label"><?php echo xlt('Detail'); ?></td><td><?php echo echoVal($study['wall_motion_detail']); ?></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- LV Diastolic -->
<div class="section-title"><?php echo xlt('Left Ventricle – Diastolic Function'); ?></div>
<table class="table table-sm table-bordered">
    <tr>
        <td class="field-label">E</td><td><?php echo echoVal($study['mitral_e_cm_s'], 'cm/s'); ?></td>
        <td class="field-label">A</td><td><?php echo echoVal($study['mitral_a_cm_s'], 'cm/s'); ?></td>
        <td class="field-label">E/A</td><td><?php echo echoVal($study['mitral_e_a_ratio']); ?></td>
        <td class="field-label"><?php echo xlt('Dec. Time'); ?></td><td><?php echo echoVal($study['dec_time_ms'], 'ms'); ?></td>
    </tr>
    <tr>
        <td class="field-label">e' Septal</td><td><?php echo echoVal($study['e_prime_septal_cm_s'], 'cm/s'); ?></td>
        <td class="field-label">e' Lateral</td><td><?php echo echoVal($study['e_prime_lateral_cm_s'], 'cm/s'); ?></td>
        <td class="field-label">E/e'</td><td><?php echo echoVal($study['e_e_prime_ratio']); ?></td>
        <td colspan="2"></td>
    </tr>
</table>

<!-- Atria -->
<div class="section-title"><?php echo xlt('Atria'); ?></div>
<table class="table table-sm table-bordered">
    <tr>
        <td class="field-label"><?php echo xlt('LA Diameter'); ?></td><td><?php echo echoVal($study['la_diameter_mm'], 'mm'); ?></td>
        <td class="field-label"><?php echo xlt('LA Volume Index'); ?></td><td><?php echo echoVal($study['la_volume_index_ml_m2'], 'mL/m²'); ?></td>
        <td class="field-label"><?php echo xlt('RA Area'); ?></td><td><?php echo echoVal($study['ra_area_cm2'], 'cm²'); ?></td>
    </tr>
</table>

<!-- RV & Pulmonary -->
<div class="section-title"><?php echo xlt('Right Ventricle & Pulmonary Pressure'); ?></div>
<table class="table table-sm table-bordered">
    <tr>
        <td class="field-label"><?php echo xlt('RV Basal Diam.'); ?></td><td><?php echo echoVal($study['rv_basal_diameter_mm'], 'mm'); ?></td>
        <td class="field-label">TAPSE</td><td><?php echo echoVal($study['tapse_mm'], 'mm'); ?></td>
        <td class="field-label">S'</td><td><?php echo echoVal($study['rv_s_prime_cm_s'], 'cm/s'); ?></td>
        <td class="field-label">IVC</td><td><?php echo echoVal($study['ivc_diameter_mm'], 'mm'); ?></td>
    </tr>
    <tr>
        <td class="field-label">RAP</td><td><?php echo echoVal($study['rap_mmhg'], 'mmHg'); ?></td>
        <td class="field-label">TR Vel.</td><td><?php echo echoVal($study['tr_peak_velocity_m_s'], 'm/s'); ?></td>
        <td class="field-label">PASP</td><td><?php echo echoVal($study['pasp_mmhg'], 'mmHg'); ?></td>
        <td colspan="2"></td>
    </tr>
</table>

<!-- Aorta -->
<div class="section-title"><?php echo xlt('Aorta'); ?></div>
<table class="table table-sm table-bordered">
    <tr>
        <td class="field-label"><?php echo xlt('Aortic Root'); ?></td><td><?php echo echoVal($study['ao_root_mm'], 'mm'); ?></td>
        <td class="field-label"><?php echo xlt('Ascending Aorta'); ?></td><td><?php echo echoVal($study['asc_aorta_mm'], 'mm'); ?></td>
    </tr>
</table>

<!-- Valves -->
<div class="section-title"><?php echo xlt('Valves'); ?></div>
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr>
            <th><?php echo xlt('Valve'); ?></th>
            <th><?php echo xlt('Regurgitation'); ?></th>
            <th><?php echo xlt('Stenosis'); ?></th>
            <th><?php echo xlt('Peak Vel. (m/s)'); ?></th>
            <th><?php echo xlt('Mean Grad. (mmHg)'); ?></th>
            <th><?php echo xlt('Area (cm²)'); ?></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="field-label"><?php echo xlt('Aortic'); ?></td>
            <td><?php echo echoListLabel('card_valve_grade', $study['ao_regurg_grade'] ?? ''); ?></td>
            <td><?php echo echoListLabel('card_valve_grade', $study['ao_stenosis_grade'] ?? ''); ?></td>
            <td><?php echo echoVal($study['ao_peak_velocity_m_s']); ?></td>
            <td><?php echo echoVal($study['ao_mean_gradient_mmhg']); ?></td>
            <td><?php echo echoVal($study['ao_valve_area_cm2']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Mitral'); ?></td>
            <td><?php echo echoListLabel('card_valve_grade', $study['mv_regurg_grade'] ?? ''); ?></td>
            <td><?php echo echoListLabel('card_valve_grade', $study['mv_stenosis_grade'] ?? ''); ?></td>
            <td>—</td>
            <td><?php echo echoVal($study['mv_mean_gradient_mmhg']); ?></td>
            <td><?php echo echoVal($study['mv_valve_area_cm2']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Tricuspid'); ?></td>
            <td><?php echo echoListLabel('card_valve_grade', $study['tv_regurg_grade'] ?? ''); ?></td>
            <td>—</td><td>—</td><td>—</td><td>—</td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Pulmonary'); ?></td>
            <td><?php echo echoListLabel('card_valve_grade', $study['pv_regurg_grade'] ?? ''); ?></td>
            <td>—</td><td>—</td><td>—</td><td>—</td>
        </tr>
    </tbody>
</table>
<?php if (!empty($study['valve_findings'])) : ?>
<p class="small"><strong><?php echo xlt('Additional Valve Findings'); ?>:</strong>
    <?php echo text($study['valve_findings']); ?></p>
<?php endif; ?>

<!-- Pericardium -->
<div class="section-title"><?php echo xlt('Pericardium'); ?></div>
<p>
    <strong><?php echo xlt('Pericardial Effusion'); ?>:</strong>
    <?php echo echoListLabel('card_effusion_grade', $study['pericardial_effusion'] ?? ''); ?>
</p>

<!-- Charts -->
<?php
$cardCode  = 'echo';
$chartMode = 'print';
require __DIR__ . '/../../templates/_charts.php';
?>

<!-- Conclusion -->
<?php if (!empty($study['conclusion']) || !empty($study['comments'])) : ?>
<div class="conclusion-box">
    <?php if (!empty($study['conclusion'])) : ?>
    <div class="mb-2">
        <strong><?php echo xlt('Conclusion'); ?>:</strong><br>
        <?php echo nl2br(text($study['conclusion'])); ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($study['comments'])) : ?>
    <div>
        <strong><?php echo xlt('Comments'); ?>:</strong><br>
        <?php echo nl2br(text($study['comments'])); ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="text-muted small mt-3 border-top pt-2">
    <?php echo xlt('Report generated'); ?>: <?php echo date('Y-m-d H:i'); ?>
    &nbsp;&nbsp; <?php echo xlt('Encounter'); ?>: <?php echo text((string)$encounter); ?>
    &nbsp;&nbsp; <?php echo xlt('Study ID'); ?>: <?php echo text((string)$studyId); ?>
</div>

<?php $pdfFooterText = \OpenEMR\Forms\Cardiology\CardSettings::getClinic('pdf_footer_text'); ?>
<?php if ($pdfFooterText !== '') : ?>
<div class="border-top mt-3 pt-2 small text-muted cardiology-pdf-footer">
    <?php echo nl2br(text($pdfFooterText)); ?>
</div>
<?php endif; ?>

</body>
</html>

