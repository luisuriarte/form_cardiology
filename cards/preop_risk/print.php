<?php

/**
 * Preoperative Risk card – print.php
 *
 * Renders a clean printable report for a Preoperative Cardiovascular Risk Assessment.
 * Opens in a new tab (target="_blank").
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\PreopRisk
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once('../../../../globals.php');
require_once($GLOBALS['srcdir'] . '/api.inc.php');
require_once(__DIR__ . '/../../lib/CardiologyContainer.php');

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
    'SELECT * FROM `form_card_preop_risk` WHERE id = ? AND pid = ? AND activity = 1',
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

function preopVal($v, string $unit = ''): string
{
    if ($v === null || $v === '') {
        return '<span class="text-muted">—</span>';
    }
    return text((string)$v) . ($unit ? ' <span class="text-muted small">' . text($unit) . '</span>' : '');
}

function preopListLabel(string $listId, ?string $optionId): string
{
    if ($optionId === null || $optionId === '') {
        return '<span class="text-muted">—</span>';
    }
    $row = sqlQuery(
        'SELECT title FROM list_options WHERE list_id = ? AND option_id = ? AND activity = 1',
        [$listId, $optionId]
    );
    return $row ? text(xl_list_label($row['title'])) : text($optionId);
}

$statusLabel = ($study['status'] === 'final') ? xlt('Final') : xlt('Draft');
$statusClass = ($study['status'] === 'final') ? 'text-success' : 'text-warning';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Preoperative Risk Report'); ?> – <?php echo text($patientName); ?></title>
    <link rel="stylesheet"
          href="<?php echo attr($GLOBALS['webroot']); ?>/public/assets/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/css/cardiology.css">
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
    <button class="btn btn-sm btn-primary" onclick="window.print()">
        <i class="fa fa-print me-1"></i><?php echo xlt('Print'); ?>
    </button>
    <button class="btn btn-sm btn-secondary ms-2" onclick="window.close()">
        <?php echo xlt('Close'); ?>
    </button>
</div>

<div class="report-header pb-2">
    <div class="row">
        <div class="col-8">
            <h4 class="mb-0"><?php echo xlt('Preoperative Cardiovascular Risk Assessment'); ?></h4>
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
                <strong><?php echo xlt('Evaluation Date'); ?>:</strong>
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

<div class="section-title"><?php echo xlt('Planned Surgery & Urgency'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Surgery / Procedure'); ?></td>
            <td style="width: 25%;"><?php echo preopVal($study['surgery_description']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Surgery Date'); ?></td>
            <td style="width: 25%;"><?php echo preopVal($study['surgery_date']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Surgical Urgency'); ?></td>
            <td><?php echo preopListLabel('card_surgery_urgency', $study['surgery_urgency']); ?></td>
            <td class="field-label"><?php echo xlt('Inherent Surgical Risk'); ?></td>
            <td><?php echo preopListLabel('card_surgery_risk', $study['surgery_risk_class']); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Functional Capacity'); ?></td>
            <td><?php echo preopListLabel('card_functional_capacity', $study['functional_capacity']); ?></td>
            <td class="field-label"><?php echo xlt('ASA Physical Status'); ?></td>
            <td><?php echo preopListLabel('card_asa_class', $study['asa_class']); ?></td>
        </tr>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Vital Signs & Measurements'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 20%;"><?php echo xlt('Height'); ?></td>
            <td style="width: 13%;"><?php echo preopVal($study['height_cm'], 'cm'); ?></td>
            <td class="field-label" style="width: 20%;"><?php echo xlt('Weight'); ?></td>
            <td style="width: 13%;"><?php echo preopVal($study['weight_kg'], 'kg'); ?></td>
            <td class="field-label" style="width: 20%;"><?php echo xlt('Heart Rate'); ?></td>
            <td style="width: 14%;"><?php echo preopVal($study['heart_rate_bpm'], 'bpm'); ?></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('BP Systolic'); ?></td>
            <td><?php echo preopVal($study['bp_systolic_mmhg'], 'mmHg'); ?></td>
            <td class="field-label"><?php echo xlt('BP Diastolic'); ?></td>
            <td><?php echo preopVal($study['bp_diastolic_mmhg'], 'mmHg'); ?></td>
            <td class="field-label"><?php echo xlt('LVEF'); ?></td>
            <td><?php echo preopVal($study['lvef_pct'], '%'); ?></td>
        </tr>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Revised Cardiac Risk Index (Lee RCRI)'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 30%;"><?php echo xlt('Lee RCRI Score'); ?></td>
            <td style="width: 20%;"><strong><?php echo text((string)($study['lee_score'] ?? '0')); ?> / 6</strong></td>
            <td class="field-label" style="width: 30%;"><?php echo xlt('Lee RCRI Class'); ?></td>
            <td style="width: 20%;"><strong class="fs-6"><?php echo text((string)($study['lee_class'] ?? 'I')); ?></strong></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Identified Risk Factors'); ?></td>
            <td colspan="3">
                <ul class="mb-0 ps-3">
                    <?php
                    $factors = [];
                    if (!empty($study['lee_high_risk_surgery']))      $factors[] = xlt('High-risk surgery');
                    if (!empty($study['lee_ischemic_heart_disease'])) $factors[] = xlt('History of ischemic heart disease');
                    if (!empty($study['lee_heart_failure']))          $factors[] = xlt('History of congestive heart failure');
                    if (!empty($study['lee_cerebrovascular_disease'])) $factors[] = xlt('History of cerebrovascular disease');
                    if (!empty($study['lee_insulin_diabetes']))       $factors[] = xlt('Preoperative insulin therapy for diabetes');
                    if (!empty($study['lee_creatinine_gt_2']))        $factors[] = xlt('Preoperative serum creatinine > 2.0 mg/dL');

                    if (empty($factors)) {
                        echo '<li>' . xlt('None (Score 0)') . '</li>';
                    } else {
                        foreach ($factors as $f) {
                            echo '<li>' . text($f) . '</li>';
                        }
                    }
                    ?>
                </ul>
            </td>
        </tr>
    </tbody>
</table>

<?php if (!empty($study['ecg_summary']) || !empty($study['echo_summary']) || !empty($study['medications_note']) || !empty($study['labs_note'])) : ?>
<div class="section-title"><?php echo xlt('Complementary Studies & Medications'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <?php if (!empty($study['ecg_summary'])) : ?>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Preoperative ECG'); ?></td>
            <td><?php echo nl2br(text($study['ecg_summary'])); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($study['echo_summary'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Echocardiogram'); ?></td>
            <td><?php echo nl2br(text($study['echo_summary'])); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($study['medications_note'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Current Medications'); ?></td>
            <td><?php echo nl2br(text($study['medications_note'])); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($study['labs_note'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Relevant Labs'); ?></td>
            <td><?php echo nl2br(text($study['labs_note'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="section-title"><?php echo xlt('Recommendation'); ?></div>
<div class="p-2 border rounded bg-white mb-2">
    <strong><?php echo xlt('Cardiovascular Clearance Recommendation'); ?>:</strong>
    <span class="fs-6 ms-2 fw-bold text-primary">
        <?php echo preopListLabel('card_preop_recommendation', $study['recommendation']); ?>
    </span>
</div>

<?php if (!empty($study['perioperative_recommendations'])) : ?>
<div class="mb-3">
    <span class="field-label"><?php echo xlt('Perioperative Management Instructions'); ?>:</span>
    <div class="p-2 border rounded bg-light mt-1">
        <?php echo nl2br(text($study['perioperative_recommendations'])); ?>
    </div>
</div>
<?php endif; ?>

<div class="conclusion-box">
    <div class="fw-bold text-danger text-uppercase mb-1">
        <?php echo xlt('Conclusion & Assessment Summary'); ?>
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

<div class="mt-5 pt-4 text-end">
    <div style="display: inline-block; text-align: center; min-width: 250px;">
        <div style="border-top: 1px solid #495057; padding-top: 5px;">
            <?php echo text($study['user'] ?? ''); ?><br>
            <span class="text-muted small"><?php echo xlt('Evaluating Cardiologist'); ?></span>
        </div>
    </div>
</div>

</body>
</html>

