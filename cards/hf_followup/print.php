<?php

/**
 * Heart Failure Follow-up card – print.php
 *
 * Renders a clean printable report for a heart failure follow-up study.
 * Opens in a new tab (target="_blank").
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\HfFollowup
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
    'SELECT * FROM `form_card_hf_followup` WHERE id = ? AND pid = ? AND activity = 1',
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

function hfVal($v, string $unit = ''): string
{
    if ($v === null || $v === '') {
        return '<span class="text-muted">—</span>';
    }
    return text((string)$v) . ($unit ? ' <span class="text-muted small">' . text($unit) . '</span>' : '');
}

function hfListLabel(string $listId, ?string $optionId): string
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

function hfYesNo($value): string
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
    <title><?php echo xlt('Heart Failure Follow-up Report'); ?> – <?php echo text($patientName); ?></title>
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
            <h4 class="mb-0"><?php echo xlt('Heart Failure Follow-up Report'); ?></h4>
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

<div class="section-title"><?php echo xlt('Functional Class & Heart Failure Profile'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('NYHA Functional Class'); ?></td>
            <td style="width: 25%;"><?php echo hfListLabel('card_nyha', $study['nyha_class']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('NYHA Class Number'); ?></td>
            <td style="width: 25%;"><?php echo hfVal($study['nyha_numeric']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Heart Failure Type'); ?></td>
            <td><?php echo hfListLabel('card_hf_type', $study['hf_type']); ?></td>
            <td class="field-label"><?php echo xlt('Etiology'); ?></td>
            <td><?php echo hfListLabel('card_hf_etiology', $study['etiology']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Heart Failure Stage'); ?></td>
            <td colspan="3"><?php echo hfListLabel('card_hf_stage', $study['hf_stage']); ?></td>
        </tr>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Volume Status & Vital Signs'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Current Weight'); ?></td>
            <td style="width: 25%;"><?php echo hfVal($study['weight_kg'], 'kg'); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Dry Weight'); ?></td>
            <td style="width: 25%;"><?php echo hfVal($study['dry_weight_kg'], 'kg'); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Weight Change'); ?></td>
            <td><strong><?php echo hfVal($study['weight_change_kg'], 'kg'); ?></strong></td>
            <td class="field-label"><?php echo xlt('Peripheral Edema'); ?></td>
            <td><?php echo hfListLabel('card_edema_grade', $study['edema']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Heart Rate'); ?></td>
            <td><?php echo hfVal($study['heart_rate_bpm'], 'bpm'); ?></td>
            <td class="field-label"><?php echo xlt('Blood Pressure'); ?></td>
            <td>
                <?php if (($study['bp_systolic_mmhg'] ?? '') !== '' || ($study['bp_diastolic_mmhg'] ?? '') !== '') : ?>
                    <?php echo hfVal($study['bp_systolic_mmhg']); ?>
                    /
                    <?php echo hfVal($study['bp_diastolic_mmhg']); ?>
                    <span class="text-muted small">mmHg</span>
                <?php else : ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Oxygen Saturation'); ?></td>
            <td><?php echo hfVal($study['spo2_pct'], '%'); ?></td>
            <td class="field-label"><?php echo xlt('Jugular Venous Distension'); ?></td>
            <td><?php echo hfYesNo($study['jugular_distension']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Pulmonary Rales'); ?></td>
            <td><?php echo hfYesNo($study['rales']); ?></td>
            <td class="field-label"><?php echo xlt('Orthopnea / PND'); ?></td>
            <td>
                <?php echo hfYesNo($study['orthopnea']); ?>
                /
                <?php echo hfYesNo($study['pnd']); ?>
            </td>
        </tr>
        <?php if (!empty($study['congestion_note'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Congestion Findings'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['congestion_note'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Cardiac Function & Laboratory Values'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('LVEF'); ?></td>
            <td style="width: 25%;"><?php echo hfVal($study['lvef_pct'], '%'); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('LVEF Measurement Date'); ?></td>
            <td style="width: 25%;"><?php echo hfVal($study['lvef_date']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('NT-proBNP'); ?></td>
            <td><?php echo hfVal($study['nt_probnp_pg_ml'], 'pg/mL'); ?></td>
            <td class="field-label"><?php echo xlt('BNP'); ?></td>
            <td><?php echo hfVal($study['bnp_pg_ml'], 'pg/mL'); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Serum Creatinine'); ?></td>
            <td><?php echo hfVal($study['creatinine_mg_dl'], 'mg/dL'); ?></td>
            <td class="field-label"><?php echo xlt('Serum Potassium'); ?></td>
            <td><?php echo hfVal($study['potassium_mmol_l'], 'mmol/L'); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Serum Sodium'); ?></td>
            <td colspan="3"><?php echo hfVal($study['sodium_mmol_l'], 'mmol/L'); ?></td>
        </tr>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Treatment & Follow-up'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Adherence'); ?></td>
            <td style="width: 25%;"><?php echo hfListLabel('card_adherence', $study['adherence']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Patient Education Given'); ?></td>
            <td style="width: 25%;"><?php echo hfYesNo($study['education_given']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('On RAAS Inhibitor'); ?></td>
            <td><?php echo hfYesNo($study['on_raas_inhibitor']); ?></td>
            <td class="field-label"><?php echo xlt('On Beta Blocker'); ?></td>
            <td><?php echo hfYesNo($study['on_beta_blocker']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('On MRA'); ?></td>
            <td><?php echo hfYesNo($study['on_mra']); ?></td>
            <td class="field-label"><?php echo xlt('On SGLT2 Inhibitor'); ?></td>
            <td><?php echo hfYesNo($study['on_sglt2_inhibitor']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('On Loop Diuretic'); ?></td>
            <td><?php echo hfYesNo($study['on_loop_diuretic']); ?></td>
            <td class="field-label"><?php echo xlt('Hospitalizations Since Last Visit'); ?></td>
            <td><?php echo hfVal($study['hospitalizations_since_last']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Emergency Visits Since Last Visit'); ?></td>
            <td><?php echo hfVal($study['er_visits_since_last']); ?></td>
            <td class="field-label"><?php echo xlt('Next Control Date'); ?></td>
            <td><?php echo hfVal($study['next_control_date']); ?></td>
        </tr>
        <?php if (!empty($study['medications_note'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Medications & Doses'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['medications_note'])); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($study['plan'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Plan'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['plan'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Charts -->
<?php
$cardCode  = 'hf_followup';
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
$attachments = \OpenEMR\Forms\Cardiology\CardDocuments::listForStudy('hf_followup', $studyId, $pid);
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
