<?php

/**
 * Fitness card – print.php
 *
 * Renders a clean printable Cardiology Fitness Certificate (Apto cardiológico).
 * Opens in a new tab (target="_blank").
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Fitness
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
    'SELECT * FROM `form_card_fitness` WHERE id = ? AND pid = ? AND activity = 1',
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

// Calculate patient age at evaluation date
$patientAge = '';
if (!empty($patient['DOB']) && $patient['DOB'] !== '0000-00-00') {
    try {
        $dobDt = new DateTime((string)$patient['DOB']);
        $studyDt = new DateTime((string)$study['study_date']);
        $diff = $dobDt->diff($studyDt);
        if (!$diff->invert) {
            $patientAge = (string)$diff->y;
        }
    } catch (\Throwable $e) {
        $patientAge = '';
    }
}

// Fetch physician details from users table
$physician = sqlQuery(
    'SELECT fname, mname, lname, state_license_number FROM `users` WHERE username = ? LIMIT 1',
    [$study['user'] ?? '']
);
$physicianName = '';
if ($physician) {
    $physicianName = trim(($physician['fname'] ?? '') . ' ' . ($physician['mname'] ?? '') . ' ' . ($physician['lname'] ?? ''));
}
if ($physicianName === '') {
    $physicianName = (string)($study['user'] ?? '');
}
$licenseNumber = trim((string)($physician['state_license_number'] ?? ''));

function fitVal($v, string $unit = ''): string
{
    if ($v === null || $v === '') {
        return '<span class="text-muted">—</span>';
    }
    return text((string)$v) . ($unit ? ' <span class="text-muted small">' . text($unit) . '</span>' : '');
}

function fitListLabel(string $listId, ?string $optionId): string
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

$statusLabel = ($study['status'] === 'final') ? xlt('Final') : xlt('Draft');
$statusClass = ($study['status'] === 'final') ? 'text-success' : 'text-warning';

$evalDate = substr($study['study_date'] ?? '', 0, 10);
$determinationTitle = fitListLabel('card_fitness_determination', $study['determination']);

// Exact neutral declaration sentence
$declarationText = sprintf(
    xl('Based on the examination performed on %s, the patient is considered %s.'),
    $evalDate,
    $determinationTitle
);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Cardiology Fitness Certificate'); ?> – <?php echo text($patientName); ?></title>
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
        .certificate-declaration-box {
            border: 2px solid #198754;
            background: #f8fff9;
            padding: 1.25rem;
            margin: 1.5rem 0;
            border-radius: 4px;
        }
        .validity-badge {
            font-size: 1.1em;
            font-weight: 700;
            color: #0f5132;
        }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 11px; }
        }
    </style>
</head>
<body class="p-3">

<div class="no-print mb-3">
    <button class="btn btn-sm btn-primary" onclick="window.print()">
        <i class="fa fa-print me-1"></i><?php echo xlt('Print Certificate'); ?>
    </button>
    <button class="btn btn-sm btn-secondary ms-2" onclick="window.close()">
        <?php echo xlt('Close'); ?>
    </button>
</div>

<div class="report-header pb-2">
    <div class="row">
        <div class="col-8">
            <h4 class="mb-0 text-uppercase text-danger fw-bold">
                <?php echo xlt('Cardiology Fitness Certificate'); ?>
            </h4>
            <div class="text-muted small"><?php echo xlt('Cardiovascular Physical Activity Clearance'); ?></div>
            <div class="mt-2">
                <strong><?php echo xlt('Patient'); ?>:</strong> <?php echo text($patientName); ?>
                (ID: <?php echo text((string)$pid); ?>)
            </div>
            <div>
                <strong><?php echo xlt('DOB'); ?>:</strong> <?php echo text($patient['DOB'] ?? ''); ?>
                <?php if ($patientAge !== '') : ?>
                    (<?php echo text($patientAge); ?> <?php echo xlt('years'); ?>)
                <?php endif; ?>
                &nbsp;&nbsp;
                <strong><?php echo xlt('Sex'); ?>:</strong> <?php echo text($patient['sex'] ?? ''); ?>
                &nbsp;&nbsp;
                <strong><?php echo xlt('Encounter'); ?>:</strong> <?php echo text((string)$encounter); ?>
            </div>
        </div>
        <div class="col-4 text-end">
            <div>
                <strong><?php echo xlt('Evaluation Date'); ?>:</strong>
                <?php echo text($study['study_date'] ?? ''); ?>
            </div>
            <div>
                <strong><?php echo xlt('Physician'); ?>:</strong>
                <?php echo text($physicianName); ?>
            </div>
            <div>
                <strong><?php echo xlt('Status'); ?>:</strong>
                <span class="<?php echo attr($statusClass); ?> fw-bold"><?php echo $statusLabel; ?></span>
            </div>
            <?php if ($study['status'] === 'final') : ?>
            <div class="text-muted small">
                <?php echo xlt('Finalized'); ?>: <?php echo text($study['finalized_at'] ?? ''); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="section-title"><?php echo xlt('Evaluation Purpose'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Purpose'); ?></td>
            <td style="width: 25%;"><?php echo fitListLabel('card_fitness_purpose', $study['purpose']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Activity / Sport / Job Details'); ?></td>
            <td style="width: 25%;"><?php echo fitVal($study['purpose_detail']); ?></td>
        </tr>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Vital Signs & Physical Examination'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 16%;"><?php echo xlt('Height'); ?></td>
            <td style="width: 17%;"><?php echo fitVal($study['height_cm'], 'cm'); ?></td>
            <td class="field-label" style="width: 16%;"><?php echo xlt('Weight'); ?></td>
            <td style="width: 17%;"><?php echo fitVal($study['weight_kg'], 'kg'); ?></td>
            <td class="field-label" style="width: 17%;"><?php echo xlt('BMI'); ?></td>
            <td style="width: 17%;"><strong><?php echo fitVal($study['bmi'], 'kg/m²'); ?></strong></td>
        </tr>
        <tr>
            <td class="field-label"><?php echo xlt('Heart Rate'); ?></td>
            <td><?php echo fitVal($study['heart_rate_bpm'], 'bpm'); ?></td>
            <td class="field-label"><?php echo xlt('BP Systolic'); ?></td>
            <td><?php echo fitVal($study['bp_systolic_mmhg'], 'mmHg'); ?></td>
            <td class="field-label"><?php echo xlt('BP Diastolic'); ?></td>
            <td><?php echo fitVal($study['bp_diastolic_mmhg'], 'mmHg'); ?></td>
        </tr>
        <?php if (!empty($study['exam_findings'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Physical Examination'); ?></td>
            <td colspan="5"><?php echo text($study['exam_findings']); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="section-title"><?php echo xlt('Electrocardiogram & Diagnostic Studies'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
        <tr>
            <td class="field-label" style="width: 25%;"><?php echo xlt('Resting 12-Lead ECG'); ?></td>
            <td style="width: 25%;"><?php echo fitListLabel('card_fitness_ecg', $study['ecg_result']); ?></td>
            <td class="field-label" style="width: 25%;"><?php echo xlt('ECG Findings'); ?></td>
            <td style="width: 25%;"><?php echo fitVal($study['ecg_note']); ?></td>
        </tr>
        <?php if (!empty($study['additional_studies'])) : ?>
        <tr>
            <td class="field-label"><?php echo xlt('Additional Diagnostic Studies'); ?></td>
            <td colspan="3"><?php echo nl2br(text($study['additional_studies'])); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Certificate Declaration Box -->
<div class="certificate-declaration-box">
    <div class="text-center mb-3">
        <h5 class="fw-bold text-success text-uppercase mb-1"><?php echo xlt('Cardiovascular Determination'); ?></h5>
        <div class="fs-4 fw-bold text-dark my-2">
            <?php echo text($determinationTitle); ?>
        </div>
    </div>

    <!-- Official neutral statement -->
    <p class="fs-6 text-center text-secondary mb-3">
        <?php echo text($declarationText); ?>
    </p>

    <?php if (!empty($study['restrictions'])) : ?>
    <div class="alert alert-warning mb-2 py-2">
        <strong><?php echo xlt('Activity Restrictions'); ?>:</strong>
        <?php echo nl2br(text($study['restrictions'])); ?>
    </div>
    <?php endif; ?>

    <div class="row mt-3 pt-2 border-top">
        <div class="col-6">
            <span class="field-label"><?php echo xlt('Validity Expiration Date'); ?>:</span>
            <span class="validity-badge ms-2"><?php echo text($study['valid_until'] ?? '—'); ?></span>
        </div>
        <div class="col-6 text-end">
            <span class="text-muted small"><?php echo xlt('Issued on'); ?>: <?php echo text($evalDate); ?></span>
        </div>
    </div>
</div>

<?php if (!empty($study['conclusion'])) : ?>
<div class="mb-3">
    <span class="field-label"><?php echo xlt('Conclusion'); ?>:</span>
    <span class="field-value"><?php echo nl2br(text($study['conclusion'])); ?></span>
</div>
<?php endif; ?>

<?php if (!empty($study['comments'])) : ?>
<div class="mb-3">
    <span class="field-label"><?php echo xlt('Recommendations / Follow-up'); ?>:</span>
    <span class="field-value"><?php echo nl2br(text($study['comments'])); ?></span>
</div>
<?php endif; ?>

<!-- Physician Signature Block -->
<div class="mt-5 pt-4 text-end">
    <div style="display: inline-block; text-align: center; min-width: 280px;">
        <div style="border-top: 1px solid #495057; padding-top: 5px;">
            <strong><?php echo text($physicianName); ?></strong><br>
            <?php if ($licenseNumber !== '') : ?>
                <span class="text-muted small"><?php echo xlt('License / Reg. No.'); ?>: <?php echo text($licenseNumber); ?></span><br>
            <?php else : ?>
                <span class="text-muted small"><?php echo xlt('License / Reg. No.'); ?>: ___________________________</span><br>
            <?php endif; ?>
            <span class="text-muted small"><?php echo xlt('Cardiologist / Physician'); ?></span>
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

