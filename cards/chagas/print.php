<?php

/**
 * Chagas Disease Follow-up card – print.php
 *
 * Renders a clean printable report for a Chagas disease follow-up study.
 * Opens in a new tab (target="_blank").
 *
 * The report lists the physician-selected phase, stages and findings. It does
 * not generate a clinical interpretation or evaluate staging criteria.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Chagas
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

$study = sqlQuery(
    'SELECT * FROM `form_card_chagas` WHERE id = ? AND pid = ? AND activity = 1',
    [$studyId, $pid]
);
if (!$study || !isset($study['id'])) {
    die(xlt('Study not found or has been deleted.'));
}

$patient = sqlQuery(
    'SELECT fname, mname, lname, DOB, sex FROM `patient_data` WHERE pid = ? LIMIT 1',
    [$pid]
);
$patientName = trim(
    ($patient['fname'] ?? '') . ' '
    . ($patient['mname'] ?? '') . ' '
    . ($patient['lname'] ?? '')
);

function chagasVal($v, string $unit = ''): string
{
    if ($v === null || $v === '') {
        return '<span class="text-muted">—</span>';
    }
    return text((string)$v) . ($unit ? ' <span class="text-muted small">' . text($unit) . '</span>' : '');
}

function chagasListLabel(string $listId, ?string $optionId): string
{
    if ($optionId === null || $optionId === '') {
        return '<span class="text-muted">—</span>';
    }
    $row = sqlQuery(
        'SELECT title FROM list_options WHERE list_id = ? AND option_id = ?',
        [$listId, $optionId]
    );
    return $row ? text(xl_list_label($row['title'])) : text($optionId);
}

function chagasYesNo($value): string
{
    return ((int)($value ?? 0) === 1) ? xlt('Yes') : xlt('No');
}

$ecgFlagLabels = [
    'ecg_rbbb'         => xlt('Right Bundle Branch Block'),
    'ecg_lafb'         => xlt('Left Anterior Fascicular Block'),
    'ecg_lpfb'         => xlt('Left Posterior Fascicular Block'),
    'ecg_av_block'     => xlt('Atrioventricular Block'),
    'ecg_q_waves'      => xlt('Pathologic Q Waves'),
    'ecg_st_t_changes' => xlt('ST-T Changes'),
    'ecg_low_voltage'  => xlt('Low Voltage'),
    'ecg_arrhythmia'   => xlt('Arrhythmia'),
    'ecg_other'        => xlt('Other ECG Finding'),
];
$setEcgFlags = [];
foreach ($ecgFlagLabels as $field => $label) {
    if ((int)($study[$field] ?? 0) === 1) {
        $setEcgFlags[] = $label;
    }
}

$statusLabel = ($study['status'] === 'final') ? xlt('Final') : xlt('Draft');
$statusClass = ($study['status'] === 'final') ? 'text-success' : 'text-warning';

$hasEcg = (int)($study['ecg_done'] ?? 0) === 1
    || !empty($study['ecg_date'])
    || !empty($setEcgFlags)
    || !empty($study['ecg_findings']);
$hasChestXray = (int)($study['chest_xray_done'] ?? 0) === 1
    || !empty($study['chest_xray_date'])
    || (int)($study['chest_xray_cardiomegaly'] ?? 0) === 1
    || !empty($study['chest_xray_findings']);
$hasEcho = (int)($study['echo_done'] ?? 0) === 1
    || !empty($study['echo_date'])
    || $study['lvef_pct'] !== null
    || $study['lvedd_mm'] !== null
    || (int)($study['echo_aneurysm'] ?? 0) === 1
    || (int)($study['echo_thrombus'] ?? 0) === 1
    || !empty($study['echo_wall_motion'])
    || !empty($study['echo_findings']);
$hasHf = (int)($study['heart_failure'] ?? 0) === 1
    || !empty($study['nyha_class'])
    || !empty($study['hf_findings']);
$hasDevice = !empty($study['cardiac_device']) || !empty($study['device_note']);
$hasDigestive = (int)($study['digestive_involvement'] ?? 0) === 1
    || (int)($study['megaesophagus'] ?? 0) === 1
    || (int)($study['megacolon'] ?? 0) === 1
    || (int)($study['constipation'] ?? 0) === 1
    || !empty($study['digestive_findings']);
$hasTreatment = !empty($study['treatment_status'])
    || !empty($study['treatment_drug'])
    || !empty($study['treatment_start_date'])
    || !empty($study['treatment_end_date'])
    || !empty($study['treatment_note']);
$hasPublicHealth = (int)($study['mandatory_report'] ?? 0) === 1
    || !empty($study['report_date'])
    || !empty($study['public_health_note'])
    || (int)($study['family_screening_done'] ?? 0) === 1
    || !empty($study['family_screening_note']);
$hasStage = !empty($study['kuschnir_stage']) || !empty($study['abcd_stage']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Chagas Disease Report'); ?> – <?php echo text($patientName); ?></title>
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
        .stage-note { font-size: 0.78rem; color: #6c757d; font-style: italic; }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 11px; }
        }
    </style>
</head>
<body class="p-3">

<div class="no-print mb-3">
    <button class="btn btn-sm btn-primary" onclick="if (window.CardiologyCharts) { window.CardiologyCharts.onReady(function() { window.print(); }); } else { window.print(); }">
        <i class="fa fa-print me-1"></i><?php echo xlt('Print'); ?>
    </button>
    <button class="btn btn-sm btn-secondary ms-2" onclick="window.close()">
        <?php echo xlt('Close'); ?>
    </button>
</div>

<div class="report-header pb-2">
    <div class="row">
        <div class="col-8">
            <h4 class="mb-0"><?php echo xlt('Chagas Disease Report'); ?></h4>
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
                    (<?php echo text($study['finalized_by']); ?>)
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="section-title"><?php echo xlt('Phase & Epidemiology'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Disease Phase / Clinical Form'); ?></td>
            <td style="width: 25%;"><?php echo chagasListLabel('card_chagas_phase', $study['disease_phase']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Probable Transmission Route'); ?></td>
            <td style="width: 25%;"><?php echo chagasListLabel('card_chagas_transmission', $study['transmission_route']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Country or Region of Origin'); ?></td>
            <td><?php echo chagasVal($study['origin_area']); ?></td>
            <td class="field-label"><?php echo xlt('Residence in an Endemic Area'); ?></td>
            <td><?php echo chagasYesNo($study['endemic_residence']); ?></td>
        </tr>
        <?php if (!empty($study['epidemiological_history'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Epidemiological History'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['epidemiological_history'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php
$serologyRows = [];
for ($i = 1; $i <= 3; $i++) {
    $type   = $study['serology_' . $i . '_type'] ?? null;
    $result = $study['serology_' . $i . '_result'] ?? null;
    $date   = $study['serology_' . $i . '_date'] ?? null;
    if ($type !== null || $result !== null || $date !== null) {
        $serologyRows[] = [$i, $type, $result, $date];
    }
}
?>
<div class="section-title"><?php echo xlt('Serology'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Overall Serology Status'); ?></td>
            <td colspan="3"><?php echo chagasListLabel('card_chagas_serology_status', $study['serology_status']); ?></td>
        </tr>
        <?php foreach ($serologyRows as [$i, $type, $result, $date]) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Serology Test'); ?> <?php echo text((string)$i); ?></td>
            <td><?php echo chagasListLabel('card_chagas_serology_test', $type); ?></td>
            <td><?php echo xlt('Result'); ?>: <?php echo chagasListLabel('card_chagas_serology_result', $result); ?></td>
            <td><?php echo xlt('Date'); ?>: <?php echo chagasVal($date); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!empty($study['serology_note'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Serology Details'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['serology_note'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($hasStage) : ?>
<div class="section-title"><?php echo xlt('Stage'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <?php if (!empty($study['kuschnir_stage'])) : ?>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Kuschnir Stage'); ?></td>
            <td><?php echo chagasListLabel('card_chagas_kuschnir', $study['kuschnir_stage']); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($study['abcd_stage'])) : ?>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('ABCD Stage'); ?></td>
            <td><?php echo chagasListLabel('card_chagas_abcd', $study['abcd_stage']); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
<div class="stage-note">
    <?php echo xlt('Stages are selected by the physician according to the consensus applied; the criteria are not evaluated by this form.'); ?>
</div>
<?php endif; ?>

<?php if ($hasEcg) : ?>
<div class="section-title"><?php echo xlt('Electrocardiogram'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('ECG Performed'); ?></td>
            <td style="width: 25%;"><?php echo chagasYesNo($study['ecg_done']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('ECG Date'); ?></td>
            <td style="width: 25%;"><?php echo chagasVal($study['ecg_date']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('ECG Normal'); ?></td>
            <td><?php echo chagasYesNo($study['ecg_normal']); ?></td>
            <td class="field-label"><?php echo xlt('Findings'); ?></td>
            <td><?php echo !empty($setEcgFlags) ? text(implode(', ', $setEcgFlags)) : '<span class="text-muted">—</span>'; ?></td>
        </tr>
        <?php if (!empty($study['ecg_findings'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('ECG Findings'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['ecg_findings'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($hasChestXray) : ?>
<div class="section-title"><?php echo xlt('Chest X-ray'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Chest X-ray Performed'); ?></td>
            <td style="width: 25%;"><?php echo chagasYesNo($study['chest_xray_done']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Chest X-ray Date'); ?></td>
            <td style="width: 25%;"><?php echo chagasVal($study['chest_xray_date']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Cardiomegaly'); ?></td>
            <td colspan="3"><?php echo chagasYesNo($study['chest_xray_cardiomegaly']); ?></td>
        </tr>
        <?php if (!empty($study['chest_xray_findings'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Chest X-ray Findings'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['chest_xray_findings'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($hasEcho) : ?>
<div class="section-title"><?php echo xlt('Echocardiogram'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Echocardiogram Performed'); ?></td>
            <td style="width: 25%;"><?php echo chagasYesNo($study['echo_done']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Echocardiogram Date'); ?></td>
            <td style="width: 25%;"><?php echo chagasVal($study['echo_date']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('LVEF'); ?></td>
            <td><?php echo chagasVal($study['lvef_pct'], '%'); ?></td>
            <td class="field-label"><?php echo xlt('LV End-Diastolic Diameter'); ?></td>
            <td><?php echo chagasVal($study['lvedd_mm'], 'mm'); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Left Ventricular Aneurysm'); ?></td>
            <td><?php echo chagasYesNo($study['echo_aneurysm']); ?></td>
            <td class="field-label"><?php echo xlt('Left Ventricular Thrombus'); ?></td>
            <td><?php echo chagasYesNo($study['echo_thrombus']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Wall Motion'); ?></td>
            <td colspan="3"><?php echo chagasListLabel('card_wall_motion', $study['echo_wall_motion']); ?></td>
        </tr>
        <?php if (!empty($study['echo_findings'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Echocardiogram Findings'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['echo_findings'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($hasHf) : ?>
<div class="section-title"><?php echo xlt('Heart Failure & Functional Class'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Heart Failure Present'); ?></td>
            <td style="width: 25%;"><?php echo chagasYesNo($study['heart_failure']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('NYHA Functional Class'); ?></td>
            <td style="width: 25%;"><?php echo chagasListLabel('card_nyha', $study['nyha_class']); ?></td>
        </tr>
        <?php if (!empty($study['hf_findings'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Heart Failure Findings'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['hf_findings'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($hasDevice) : ?>
<div class="section-title"><?php echo xlt('Cardiac Device'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Device'); ?></td>
            <td style="width: 25%;"><?php echo chagasListLabel('card_chagas_device', $study['cardiac_device']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Device Details'); ?></td>
            <td style="width: 25%;"><?php echo chagasVal($study['device_note']); ?></td>
        </tr>
    </tbody>
</table>
<?php endif; ?>

<?php if ($hasDigestive) : ?>
<div class="section-title"><?php echo xlt('Digestive Involvement'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Digestive Involvement Present'); ?></td>
            <td style="width: 25%;"><?php echo chagasYesNo($study['digestive_involvement']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Megaesophagus'); ?></td>
            <td style="width: 25%;"><?php echo chagasYesNo($study['megaesophagus']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Megacolon'); ?></td>
            <td><?php echo chagasYesNo($study['megacolon']); ?></td>
            <td class="field-label"><?php echo xlt('Chronic Constipation'); ?></td>
            <td><?php echo chagasYesNo($study['constipation']); ?></td>
        </tr>
        <?php if (!empty($study['digestive_findings'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Digestive Findings'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['digestive_findings'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($hasTreatment) : ?>
<div class="section-title"><?php echo xlt('Etiologic Treatment'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Treatment Status'); ?></td>
            <td style="width: 25%;"><?php echo chagasListLabel('card_chagas_treatment_status', $study['treatment_status']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Treatment Drug'); ?></td>
            <td style="width: 25%;"><?php echo chagasListLabel('card_chagas_treatment_drug', $study['treatment_drug']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Treatment Start Date'); ?></td>
            <td><?php echo chagasVal($study['treatment_start_date']); ?></td>
            <td class="field-label"><?php echo xlt('Treatment End Date'); ?></td>
            <td><?php echo chagasVal($study['treatment_end_date']); ?></td>
        </tr>
        <?php if (!empty($study['treatment_note'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Treatment Details'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['treatment_note'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($hasPublicHealth) : ?>
<div class="section-title"><?php echo xlt('Public Health & Family'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Reported to Public Health'); ?></td>
            <td style="width: 25%;"><?php echo chagasYesNo($study['mandatory_report']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Reporting Date'); ?></td>
            <td style="width: 25%;"><?php echo chagasVal($study['report_date']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Family Screening Performed'); ?></td>
            <td colspan="3"><?php echo chagasYesNo($study['family_screening_done']); ?></td>
        </tr>
        <?php if (!empty($study['public_health_note'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Public Health Details'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['public_health_note'])); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($study['family_screening_note'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Family Screening Details'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['family_screening_note'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if (!empty($study['plan']) || !empty($study['next_control_date'])) : ?>
<div class="section-title"><?php echo xlt('Plan'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <?php if (!empty($study['next_control_date'])) : ?>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Next Control Date'); ?></td>
            <td><?php echo chagasVal($study['next_control_date']); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($study['plan'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Plan'); ?></td>
            <td><?php echo nl2br(text($study['plan'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Charts -->
<?php
$cardCode  = 'chagas';
$chartMode = 'print';
require __DIR__ . '/../../templates/_charts.php';
?>

<div class="conclusion-box">
    <div class="fw-bold text-danger text-uppercase mb-1">
        <?php echo xlt('Conclusion'); ?>
    </div>
    <div style="font-size: 1.05em; font-weight: 500;">
        <?php echo nl2br(text($study['conclusion'] ?? '—')); ?>
    </div>
</div>

<?php if (!empty($study['comments'])) : ?>
<div class="mt-3">
    <span class="field-label"><?php echo xlt('Comments & Recommendations'); ?>:</span>
    <span class="field-value"><?php echo nl2br(text($study['comments'])); ?></span>
</div>
<?php endif; ?>

<!-- Attached Documents -->
<?php
require_once __DIR__ . '/../../lib/CardDocuments.php';
$attachments = \OpenEMR\Forms\Cardiology\CardDocuments::listForStudy('chagas', $studyId, $pid);
?>
<?php if (!empty($attachments)) : ?>
<div class="section-title mt-3"><?php echo xlt('Attached Documents'); ?></div>
<table class="table table-sm table-bordered mt-2">
    <thead>
        <tr>
            <th><?php echo xlt('Document Title'); ?></th>
            <th><?php echo xlt('Category'); ?></th>
            <th><?php echo xlt('Date'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($attachments as $att) : ?>
        <tr>
            <td><?php echo text($att['doc_name'] ?? ''); ?></td>
            <td><?php echo text(xl($att['category_name'] ?? '')); ?></td>
            <td><?php echo text($att['doc_date'] ?? $att['linked_at'] ?? ''); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="mt-5 pt-4 text-end">
    <div style="display: inline-block; text-align: center; min-width: 250px;">
        <div style="border-top: 1px solid #495057; padding-top: 5px;">
            <?php echo text($study['user'] ?? ''); ?><br>
            <span class="text-muted small"><?php echo xlt('Interpreting Physician'); ?></span>
        </div>
    </div>
</div>

<?php $pdfFooterText = \OpenEMR\Forms\Cardiology\CardSettings::getClinic('pdf_footer_text'); ?>
<?php if ($pdfFooterText !== '') : ?>
<div class="border-top mt-3 pt-2 small text-muted cardiology-pdf-footer">
    <?php echo nl2br(text($pdfFooterText)); ?>
</div>
<?php endif; ?>

</body>
</html>
