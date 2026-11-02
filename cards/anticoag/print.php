<?php

/**
 * Anticoagulation card – print.php
 *
 * Renders a clean printable report for an anticoagulation study.
 * Opens in a new tab (target="_blank").
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Anticoag
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once('../../../../globals.php');
require_once($GLOBALS['srcdir'] . '/api.inc.php');
require_once(__DIR__ . '/../../lib/CardiologyContainer.php');
require_once(__DIR__ . '/../../lib/CardSettings.php');
require_once(__DIR__ . '/../../lib/CardAnticoag.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Forms\Cardiology\CardAnticoag;
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
    'SELECT * FROM `form_card_anticoag` WHERE id = ? AND pid = ? AND activity = 1',
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

function anticoagVal($v, string $unit = ''): string
{
    if ($v === null || $v === '') {
        return '<span class="text-muted">—</span>';
    }
    return text((string)$v) . ($unit ? ' <span class="text-muted small">' . text($unit) . '</span>' : '');
}

function anticoagListLabel(string $listId, ?string $optionId): string
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

function anticoagPositionLabel(?string $code): string
{
    return match ($code) {
        'in' => xlt('In range'),
        'below' => xlt('Below range'),
        'above' => xlt('Above range'),
        default => '<span class="text-muted">—</span>',
    };
}

$ttr = CardAnticoag::ttr($pid, (string)$study['study_date']);

$statusLabel = ($study['status'] === 'final') ? xlt('Final') : xlt('Draft');
$statusClass = ($study['status'] === 'final') ? 'text-success' : 'text-warning';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Anticoagulation Report'); ?> – <?php echo text($patientName); ?></title>
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
            <h4 class="mb-0"><?php echo xlt('Anticoagulation Monitoring Report'); ?></h4>
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

<div class="section-title"><?php echo xlt('Clinical Context & Treatment'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Indication'); ?></td>
            <td style="width: 25%;"><?php echo anticoagListLabel('card_anticoag_indication', $study['indication']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Anticoagulant Drug'); ?></td>
            <td style="width: 25%;"><?php echo anticoagListLabel('card_anticoag_drug', $study['drug']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('INR Sample Type'); ?></td>
            <td colspan="3"><?php echo anticoagListLabel('card_inr_sample', $study['sample_type']); ?></td>
        </tr>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('INR & Target Range'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('INR Value'); ?></td>
            <td style="width: 25%;"><strong><?php echo anticoagVal($study['inr_value']); ?></strong></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Target INR Range'); ?></td>
            <td style="width: 25%;">
                <?php if ($study['target_inr_low'] !== null && $study['target_inr_low'] !== ''
                    && $study['target_inr_high'] !== null && $study['target_inr_high'] !== '') : ?>
                    <?php echo text((string)$study['target_inr_low']); ?>
                    &ndash;
                    <?php echo text((string)$study['target_inr_high']); ?>
                <?php else : ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('INR Position'); ?></td>
            <td><strong><?php echo anticoagPositionLabel($study['in_range'] ?? null); ?></strong></td>
            <td class="field-label"><?php echo xlt('TTR (Rosendaal method)'); ?></td>
            <td>
                <?php if ($ttr['ttr_pct'] !== null) : ?>
                    <strong><?php echo text($ttr['ttr_pct']); ?> %</strong>
                <?php else : ?>
                    <span class="text-muted"><?php echo text($ttr['message'] ?? xlt('Not enough data')); ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Days evaluated'); ?></td>
            <td><?php echo text(number_format((float)$ttr['days_evaluated'], 1, '.', '')); ?></td>
            <td class="field-label"><?php echo xlt('TTR Note'); ?></td>
            <td class="text-muted small"><?php echo xlt('Intervals longer than 56 days are excluded.'); ?></td>
        </tr>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Weekly Dose'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Current Weekly Dose'); ?></td>
            <td style="width: 25%;"><?php echo anticoagVal($study['current_weekly_dose_mg'], 'mg'); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('New Weekly Dose'); ?></td>
            <td style="width: 25%;"><?php echo anticoagVal($study['new_weekly_dose_mg'], 'mg'); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Dose Change'); ?></td>
            <td><?php echo anticoagVal($study['dose_change_pct'], '%'); ?></td>
            <td class="field-label"><?php echo xlt('Dosing Schedule'); ?></td>
            <td><?php echo anticoagVal($study['dosing_schedule']); ?></td>
        </tr>
        <?php if (!empty($study['missed_doses'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Missed Doses'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['missed_doses'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Events & Adherence'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Bleeding Event'); ?></td>
            <td style="width: 25%;"><?php echo ((int)($study['bleeding_event'] ?? 0) === 1) ? xlt('Yes') : xlt('No'); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Thrombotic Event'); ?></td>
            <td style="width: 25%;"><?php echo ((int)($study['thrombotic_event'] ?? 0) === 1) ? xlt('Yes') : xlt('No'); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Adherence'); ?></td>
            <td colspan="3"><?php echo anticoagListLabel('card_adherence', $study['adherence']); ?></td>
        </tr>
        <?php if (!empty($study['event_detail'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Event Detail'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['event_detail'])); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($study['interacting_factors'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Interacting Factors'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['interacting_factors'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Follow-up & Plan'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Next Control Date'); ?></td>
            <td style="width: 25%;"><?php echo anticoagVal($study['next_control_date']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Plan'); ?></td>
            <td style="width: 25%;"><?php echo anticoagVal($study['plan']); ?></td>
        </tr>
    </tbody>
</table>

<!-- Charts -->
<?php
$cardCode  = 'anticoag';
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
$attachments = \OpenEMR\Forms\Cardiology\CardDocuments::listForStudy('anticoag', $studyId, $pid);
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
