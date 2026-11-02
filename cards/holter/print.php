<?php

/**
 * Holter Monitor card – print.php
 *
 * Renders a clean printable report for a Holter study.
 * Opens in a new tab (target="_blank").
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Holter
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
    'SELECT * FROM `form_card_holter` WHERE id = ? AND pid = ? AND activity = 1',
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

function holterVal($v, string $unit = ''): string
{
    if ($v === null || $v === '') {
        return '<span class="text-muted">—</span>';
    }
    return text((string)$v) . ($unit ? ' <span class="text-muted small">' . text($unit) . '</span>' : '');
}

function holterListLabel(string $listId, ?string $optionId): string
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

function holterYesNo($value): string
{
    return ((int)($value ?? 0) === 1) ? xlt('Yes') : xlt('No');
}

$statusLabel = ($study['status'] === 'final') ? xlt('Final') : xlt('Draft');
$statusClass = ($study['status'] === 'final') ? 'text-success' : 'text-warning';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Holter Monitor Report'); ?> – <?php echo text($patientName); ?></title>
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
            <h4 class="mb-0"><?php echo xlt('Holter Monitor Report'); ?></h4>
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

<?php if (!empty($study['indication'])) : ?>
<div class="mb-3">
    <span class="field-label"><?php echo xlt('Indication'); ?>:</span>
    <span class="field-value"><?php echo nl2br(text($study['indication'])); ?></span>
</div>
<?php endif; ?>

<div class="section-title"><?php echo xlt('Recording & Rhythm'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Recording Duration'); ?></td>
            <td style="width: 25%;"><?php echo holterVal($study['recording_hours'], 'hours'); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Total Beats Analyzed'); ?></td>
            <td style="width: 25%;"><?php echo holterVal($study['total_beats']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Dominant Rhythm'); ?></td>
            <td><?php echo holterListLabel('card_ecg_rhythm', $study['dominant_rhythm']); ?></td>
            <td class="field-label"><?php echo xlt('Paced Beats'); ?></td>
            <td><?php echo holterVal($study['paced_beats_pct'], '%'); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Atrial Fibrillation Present'); ?></td>
            <td><?php echo holterYesNo($study['af_present']); ?></td>
            <td class="field-label"><?php echo xlt('AF Burden'); ?></td>
            <td><?php echo holterVal($study['af_burden_pct'], '%'); ?></td>
        </tr>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Heart Rate'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Minimum Heart Rate'); ?></td>
            <td style="width: 25%;"><?php echo holterVal($study['hr_min_bpm'], 'bpm'); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Time of Minimum Heart Rate'); ?></td>
            <td style="width: 25%;"><?php echo holterVal($study['hr_min_time']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Mean Heart Rate'); ?></td>
            <td><?php echo holterVal($study['hr_mean_bpm'], 'bpm'); ?></td>
            <td class="field-label"><?php echo xlt('Time of Maximum Heart Rate'); ?></td>
            <td><?php echo holterVal($study['hr_max_time']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Maximum Heart Rate'); ?></td>
            <td colspan="3"><?php echo holterVal($study['hr_max_bpm'], 'bpm'); ?></td>
        </tr>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Pauses & Ventricular Ectopy'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Longest Pause'); ?></td>
            <td style="width: 25%;"><?php echo holterVal($study['longest_pause_ms'], 'ms'); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Pauses Longer Than 3 s'); ?></td>
            <td style="width: 25%;"><?php echo holterVal($study['pauses_gt_3s']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('PVC Count'); ?></td>
            <td><?php echo holterVal($study['pvc_total']); ?></td>
            <td class="field-label"><?php echo xlt('PVC Burden'); ?></td>
            <td><strong><?php echo holterVal($study['pvc_burden_pct'], '%'); ?></strong></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('PVC Couplets'); ?></td>
            <td><?php echo holterVal($study['pvc_couplets']); ?></td>
            <td class="field-label"><?php echo xlt('NSVT Runs'); ?></td>
            <td><?php echo holterVal($study['nsvt_runs']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Longest NSVT Run'); ?></td>
            <td colspan="3"><?php echo holterVal($study['longest_nsvt_beats'], 'beats'); ?></td>
        </tr>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Atrial Ectopy, ST Changes & AV Block'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('PAC Count'); ?></td>
            <td style="width: 25%;"><?php echo holterVal($study['pac_total']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('SVT Runs'); ?></td>
            <td style="width: 25%;"><?php echo holterVal($study['svt_runs']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Longest SVT Run'); ?></td>
            <td><?php echo holterVal($study['longest_svt_beats'], 'beats'); ?></td>
            <td class="field-label"><?php echo xlt('ST Deviation Episodes'); ?></td>
            <td><?php echo holterVal($study['st_episodes']); ?></td>
        </tr>
        <?php if (!empty($study['st_description'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('ST Episode Description'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['st_description'])); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($study['av_block_note'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('AV Block Findings'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['av_block_note'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($study['symptoms_reported'])) : ?>
<div class="section-title"><?php echo xlt('Symptoms Reported'); ?></div>
<div class="p-2 border rounded bg-white">
    <?php echo nl2br(text($study['symptoms_reported'])); ?>
</div>
<?php endif; ?>

<?php if (!empty($study['symptom_correlation'])) : ?>
<div class="section-title"><?php echo xlt('Symptom Correlation'); ?></div>
<div class="p-2 border rounded bg-white">
    <?php echo nl2br(text($study['symptom_correlation'])); ?>
</div>
<?php endif; ?>

<?php if (!empty($study['findings'])) : ?>
<div class="section-title"><?php echo xlt('Detailed Findings'); ?></div>
<div class="p-2 border rounded bg-white">
    <?php echo nl2br(text($study['findings'])); ?>
</div>
<?php endif; ?>

<!-- Charts -->
<?php
$cardCode  = 'holter';
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
$attachments = \OpenEMR\Forms\Cardiology\CardDocuments::listForStudy('holter', $studyId, $pid);
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
