<?php

/**
 * Stress Test card – print.php
 *
 * Renders a clean, printable report for an Exercise Stress Test study.
 * Opens in a new tab (target="_blank"). No OpenEMR navigation chrome.
 *
 * GET parameters:
 *   pid           int
 *   encounter     int
 *   cardiology_id int
 *   id            int  form_card_stress_test.id
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\StressTest
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

// Load study
$study = sqlQuery(
    'SELECT * FROM `form_card_stress_test` WHERE id = ? AND pid = ? AND activity = 1',
    [$studyId, $pid]
);
if (!$study || !isset($study['id'])) {
    die(xlt('Study not found or has been deleted.'));
}

// Load stages
$stagesRaw = sqlStatement(
    'SELECT * FROM `form_card_stress_test_stage`
      WHERE stress_test_id = ?
      ORDER BY phase, stage_no, elapsed_s',
    [$studyId]
);
$stages = [];
while ($row = sqlFetchArray($stagesRaw)) {
    $stages[] = $row;
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

// ---------------------------------------------------------------------------
// Helper: display a value or em-dash
// ---------------------------------------------------------------------------
function stVal($v, string $unit = ''): string
{
    if ($v === null || $v === '') {
        return '<span class="text-muted">—</span>';
    }
    return text((string)$v) . ($unit ? ' <span class="text-muted small">' . text($unit) . '</span>' : '');
}

// ---------------------------------------------------------------------------
// Helper: fetch a list option label
// ---------------------------------------------------------------------------
function stListLabel(string $listId, string $optionId): string
{
    if ($optionId === '' || $optionId === null) {
        return '—';
    }
    $row = sqlQuery(
        'SELECT title FROM list_options WHERE list_id = ? AND option_id = ? AND activity = 1',
        [$listId, $optionId]
    );
    return $row ? text(xl($row['title'])) : text($optionId);
}

// ---------------------------------------------------------------------------
// Duke score risk classification
// ---------------------------------------------------------------------------
function dukeBadge(?string $score): string
{
    if ($score === null || $score === '') {
        return '<span class="text-muted">—</span>';
    }
    $s = (float)$score;
    if ($s >= 5) {
        return '<span class="badge bg-success fs-6">' . xlt('Low risk') . ' (' . text($score) . ')</span>';
    } elseif ($s >= -10) {
        return '<span class="badge bg-warning text-dark fs-6">' . xlt('Intermediate risk') . ' (' . text($score) . ')</span>';
    } else {
        return '<span class="badge bg-danger fs-6">' . xlt('High risk') . ' (' . text($score) . ')</span>';
    }
}

// ---------------------------------------------------------------------------
// Duration formatter (seconds → mm:ss)
// ---------------------------------------------------------------------------
function stDuration(?string $secs): string
{
    if ($secs === null || $secs === '') {
        return '—';
    }
    $s = (int)$secs;
    return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
}

$statusLabel = ($study['status'] === 'final') ? xlt('Final') : xlt('Draft');
$statusClass = ($study['status'] === 'final') ? 'text-success' : 'text-warning';

$phaseLabels = [
    'rest'     => xlt('Rest'),
    'exercise' => xlt('Exercise'),
    'recovery' => xlt('Recovery'),
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Exercise Stress Test Report'); ?> – <?php echo text($patientName); ?></title>
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
        .duke-box { border-left: 4px solid #0d6efd; padding: 0.6rem 1rem;
                    background: #f0f4ff; margin: 0.5rem 0 1rem; }
        .result-box { border: 2px solid #198754; border-radius: 0.375rem;
                      padding: 0.6rem 1rem; background: #f0fff4; margin: 0.5rem 0 1rem; }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 11px; }
        }
    </style>
</head>
<body class="p-3">

<!-- Print button -->
<div class="no-print mb-3">
    <button class="btn btn-sm btn-primary" onclick="window.print()">
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
                <?php echo xlt('Exercise Stress Test Report'); ?>
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

<!-- ===================================================================== -->
<!-- Clinical Context                                                        -->
<!-- ===================================================================== -->
<div class="section-title"><?php echo xlt('Clinical Context'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
    <tr>
        <td class="field-label"><?php echo xlt('Age (years)'); ?></td>
        <td><?php echo stVal($study['age_years']); ?></td>
        <td class="field-label"><?php echo xlt('Height (cm)'); ?></td>
        <td><?php echo stVal($study['height_cm'], 'cm'); ?></td>
        <td class="field-label"><?php echo xlt('Weight (kg)'); ?></td>
        <td><?php echo stVal($study['weight_kg'], 'kg'); ?></td>
    </tr>
    <tr>
        <td class="field-label"><?php echo xlt('Protocol'); ?></td>
        <td><?php echo stListLabel('card_stress_protocol', $study['protocol'] ?? ''); ?></td>
        <td class="field-label"><?php echo xlt('On beta-blocker'); ?></td>
        <td><?php echo ($study['on_beta_blocker'] ?? 0) ? xlt('Yes') : xlt('No'); ?></td>
        <td class="field-label"><?php echo xlt('Medications'); ?></td>
        <td><?php echo stVal($study['medications_note']); ?></td>
    </tr>
    <?php if (!empty($study['rest_ecg_findings'])) : ?>
    <tr>
        <td class="field-label"><?php echo xlt('Resting ECG Findings'); ?></td>
        <td colspan="5"><?php echo text($study['rest_ecg_findings']); ?></td>
    </tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- ===================================================================== -->
<!-- Baseline Values                                                         -->
<!-- ===================================================================== -->
<div class="section-title"><?php echo xlt('Baseline Values'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
    <tr>
        <td class="field-label"><?php echo xlt('Resting HR (bpm)'); ?></td>
        <td><?php echo stVal($study['rest_hr_bpm'], 'bpm'); ?></td>
        <td class="field-label"><?php echo xlt('Resting BP Systolic (mmHg)'); ?></td>
        <td><?php echo stVal($study['rest_bp_systolic_mmhg'], 'mmHg'); ?></td>
        <td class="field-label"><?php echo xlt('Resting BP Diastolic (mmHg)'); ?></td>
        <td><?php echo stVal($study['rest_bp_diastolic_mmhg'], 'mmHg'); ?></td>
    </tr>
    </tbody>
</table>

<!-- ===================================================================== -->
<!-- Exercise Performance                                                    -->
<!-- ===================================================================== -->
<div class="section-title"><?php echo xlt('Exercise Performance'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
    <tr>
        <td class="field-label"><?php echo xlt('Max Predicted HR'); ?></td>
        <td><?php echo stVal($study['max_predicted_hr_bpm'], 'bpm'); ?></td>
        <td class="field-label"><?php echo xlt('Target HR (85%)'); ?></td>
        <td><?php echo stVal($study['target_hr_bpm'], 'bpm'); ?></td>
        <td class="field-label"><?php echo xlt('Peak HR (bpm)'); ?></td>
        <td><?php echo stVal($study['peak_hr_bpm'], 'bpm'); ?></td>
        <td class="field-label"><?php echo xlt('Peak HR % Predicted'); ?></td>
        <td><?php echo stVal($study['peak_hr_pct_predicted'], '%'); ?></td>
    </tr>
    <tr>
        <td class="field-label"><?php echo xlt('Peak BP Sys. (mmHg)'); ?></td>
        <td><?php echo stVal($study['peak_bp_systolic_mmhg'], 'mmHg'); ?></td>
        <td class="field-label"><?php echo xlt('Peak BP Dia. (mmHg)'); ?></td>
        <td><?php echo stVal($study['peak_bp_diastolic_mmhg'], 'mmHg'); ?></td>
        <td class="field-label"><?php echo xlt('Double Product'); ?></td>
        <td><?php echo stVal($study['double_product']); ?></td>
        <td class="field-label"><?php echo xlt('METs Achieved'); ?></td>
        <td><?php echo stVal($study['mets_achieved']); ?></td>
    </tr>
    <tr>
        <td class="field-label"><?php echo xlt('Exercise Duration (s)'); ?></td>
        <td>
            <?php echo stVal($study['exercise_duration_s'], 's'); ?>
            <?php if (!empty($study['exercise_duration_s'])) : ?>
                <span class="text-muted small">(<?php echo text(stDuration($study['exercise_duration_s'])); ?>)</span>
            <?php endif; ?>
        </td>
        <td class="field-label"><?php echo xlt('BP Response'); ?></td>
        <td><?php echo stListLabel('card_bp_response', $study['bp_response'] ?? ''); ?></td>
        <td class="field-label"><?php echo xlt('HR Recovery 1 min'); ?></td>
        <td><?php echo stVal($study['hr_recovery_1min_bpm'], 'bpm'); ?></td>
        <td></td><td></td>
    </tr>
    </tbody>
</table>

<!-- ===================================================================== -->
<!-- Symptoms, ST & Arrhythmias                                             -->
<!-- ===================================================================== -->
<div class="section-title"><?php echo xlt('Symptoms, ST Segment & Arrhythmias'); ?></div>
<table class="table table-sm table-bordered">
    <tbody>
    <tr>
        <td class="field-label"><?php echo xlt('Main Symptom'); ?></td>
        <td><?php echo stListLabel('card_stress_symptom', $study['symptom'] ?? ''); ?></td>
        <td class="field-label"><?php echo xlt('Angina Index'); ?></td>
        <td>
            <?php
            $anginaMap = ['0' => xlt('None'), '1' => xlt('Non-limiting'), '2' => xlt('Test-limiting')];
            $ai = (string)($study['angina_index'] ?? '');
            echo isset($anginaMap[$ai]) ? $anginaMap[$ai] : stVal($ai);
            ?>
        </td>
    </tr>
    <tr>
        <td class="field-label"><?php echo xlt('ST Depression (mm)'); ?></td>
        <td><?php echo stVal($study['st_depression_mm'], 'mm'); ?></td>
        <td class="field-label"><?php echo xlt('ST Elevation (mm)'); ?></td>
        <td><?php echo stVal($study['st_elevation_mm'], 'mm'); ?></td>
    </tr>
    <tr>
        <td class="field-label"><?php echo xlt('ST Leads'); ?></td>
        <td><?php echo stVal($study['st_leads']); ?></td>
        <td class="field-label"><?php echo xlt('ST Onset HR (bpm)'); ?></td>
        <td><?php echo stVal($study['st_onset_hr_bpm'], 'bpm'); ?></td>
    </tr>
    <?php if (!empty($study['arrhythmias'])) : ?>
    <tr>
        <td class="field-label"><?php echo xlt('Arrhythmias'); ?></td>
        <td colspan="3"><?php echo text($study['arrhythmias']); ?></td>
    </tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- ===================================================================== -->
<!-- Duke Score & Result                                                     -->
<!-- ===================================================================== -->
<div class="section-title"><?php echo xlt('Result'); ?></div>

<div class="row">
    <div class="col-md-6">
        <div class="duke-box">
            <strong><?php echo xlt('Duke Score'); ?>:</strong>
            <?php echo dukeBadge($study['duke_score'] ?? null); ?>
            <div class="text-muted small mt-1">
                <?php echo xlt('Stop Reason'); ?>:
                <?php echo stListLabel('card_stop_reason', $study['stop_reason'] ?? ''); ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="result-box">
            <strong><?php echo xlt('Overall Result'); ?>:</strong>
            <span class="fs-6 ms-2">
                <?php echo stListLabel('card_stress_result', $study['result'] ?? ''); ?>
            </span>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- Stage-by-Stage Records                                                  -->
<!-- ===================================================================== -->
<?php if (!empty($stages)) : ?>
<div class="section-title"><?php echo xlt('Stage-by-Stage Records'); ?></div>
<div class="table-responsive">
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr>
            <th><?php echo xlt('Phase'); ?></th>
            <th><?php echo xlt('Stage'); ?></th>
            <th><?php echo xlt('Elapsed (s)'); ?></th>
            <th><?php echo xlt('Speed (km/h)'); ?></th>
            <th><?php echo xlt('Grade (%)'); ?></th>
            <th>METs</th>
            <th>HR</th>
            <th><?php echo xlt('BP Sys'); ?></th>
            <th><?php echo xlt('BP Dia'); ?></th>
            <th>ST (mm)</th>
            <th><?php echo xlt('Notes'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($stages as $stage) : ?>
    <tr>
        <td><?php echo text($phaseLabels[$stage['phase'] ?? ''] ?? ($stage['phase'] ?? '')); ?></td>
        <td><?php echo stVal($stage['stage_no']); ?></td>
        <td><?php echo stVal($stage['elapsed_s']); ?></td>
        <td><?php echo stVal($stage['speed_kmh']); ?></td>
        <td><?php echo stVal($stage['grade_pct']); ?></td>
        <td><?php echo stVal($stage['mets']); ?></td>
        <td><?php echo stVal($stage['hr_bpm']); ?></td>
        <td><?php echo stVal($stage['bp_systolic_mmhg']); ?></td>
        <td><?php echo stVal($stage['bp_diastolic_mmhg']); ?></td>
        <td><?php echo stVal($stage['st_mm']); ?></td>
        <td><?php echo stVal($stage['symptom_note']); ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<!-- ===================================================================== -->
<!-- Conclusion                                                              -->
<!-- ===================================================================== -->
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

</body>
</html>

