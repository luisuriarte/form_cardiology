<?php

/**
 * Chagas Disease Follow-up card – save.php
 *
 * Handles POST from cards/chagas/new.php and cards/chagas/view.php.
 *
 * Checks:
 *   1. CSRF token (version-compatible via CardiologyContainer)
 *   2. ACL and card availability (via CardRegistry::canWrite)
 *   3. Container active and ownership matching pid and encounter
 *   4. Session pid and encounter consistency
 *   5. Study not finalized (using SELECT ... FOR UPDATE within transaction)
 *   6. Strict input validation with CardInput and RANGES bounds
 *   7. Cross-field rules and finalization requirements
 *
 * No clinical classification is calculated or inferred. The disease phase,
 * both stages, the dip/classification fields and every finding are selected
 * by the physician. The form never evaluates staging criteria.
 *
 * Uses a single associative array for both INSERT and UPDATE statements.
 * Wrapped in an atomic database transaction.
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
require_once($GLOBALS['srcdir'] . '/forms.inc.php');
require_once(__DIR__ . '/../../lib/CardiologyContainer.php');
require_once(__DIR__ . '/../../lib/CardRegistry.php');
require_once(__DIR__ . '/../../lib/CardInput.php');
require_once(__DIR__ . '/../../lib/CardAudit.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Forms\Cardiology\CardAudit;
use OpenEMR\Forms\Cardiology\CardInput;
use OpenEMR\Forms\Cardiology\CardRegistry;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

// ---------------------------------------------------------------------------
// Plausibility bounds for input validation
// ---------------------------------------------------------------------------
const RANGES = [
    'lvef_pct'  => [5.0, 90.0],
    'lvedd_mm'  => [20.0, 100.0],
];

// Helper to render validation error page
function chagasRenderError(string $message, string $backUrl): void
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

/**
 * Validate and normalize an optional DATE input.
 */
function chagasDate(mixed $value, string $field): ?string
{
    if ($value === null) {
        return null;
    }

    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    $dt = \DateTime::createFromFormat('Y-m-d', $raw);
    if (!$dt || $dt->format('Y-m-d') !== $raw) {
        throw new \InvalidArgumentException(sprintf(xlt('Invalid date format in field %s. Expected Y-m-d'), $field));
    }

    return $raw;
}

/**
 * Reject measurement dates placed in the future.
 */
function chagasRejectFutureDate(?string $value, string $field): void
{
    if ($value !== null && $value > date('Y-m-d')) {
        throw new \InvalidArgumentException(sprintf(xlt('The date in field %s cannot be in the future.'), $field));
    }
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
if (!$registry->canWrite('chagas', $userId)) {
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
        . '/cards/chagas/view.php?id=' . urlencode((string)$studyId)
        . '&cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
} else {
    $backUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/chagas/new.php?cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
}

// ---------------------------------------------------------------------------
// 4. Load stored list values so inactive options already saved stay editable
// ---------------------------------------------------------------------------
$stored = [];
if ($studyId > 0) {
    $storedRow = sqlQuery(
        'SELECT * FROM `form_card_chagas`
          WHERE id = ? AND pid = ? AND cardiology_id = ? AND activity = 1
          LIMIT 1',
        [$studyId, $pid, $cardiologyId]
    );
    $stored = $storedRow ?: [];
}

$storedValue = static function (string $field) use ($stored): ?string {
    return isset($stored[$field]) ? (string)$stored[$field] : null;
};

// ---------------------------------------------------------------------------
// 5. Validate input data with CardInput
// ---------------------------------------------------------------------------
$p = $_POST;

try {
    $studyDate = CardInput::dateTime($p['study_date'] ?? null, 'study_date');

    $diseasePhase      = CardInput::listOption('card_chagas_phase', $p['disease_phase'] ?? null, 'disease_phase', $storedValue('disease_phase'));
    $transmissionRoute = CardInput::listOption('card_chagas_transmission', $p['transmission_route'] ?? null, 'transmission_route', $storedValue('transmission_route'));

    $originArea             = CardInput::text($p['origin_area'] ?? null);
    $endemicResidence       = CardInput::flag($p['endemic_residence'] ?? null);
    $epidemiologicalHistory = CardInput::text($p['epidemiological_history'] ?? null);

    $serologyTypes   = [];
    $serologyResults = [];
    $serologyDates   = [];
    for ($i = 1; $i <= 3; $i++) {
        $serologyTypes[$i]   = CardInput::listOption('card_chagas_serology_test', $p['serology_' . $i . '_type'] ?? null, 'serology_' . $i . '_type', $storedValue('serology_' . $i . '_type'));
        $serologyResults[$i] = CardInput::listOption('card_chagas_serology_result', $p['serology_' . $i . '_result'] ?? null, 'serology_' . $i . '_result', $storedValue('serology_' . $i . '_result'));
        $serologyDates[$i]   = chagasDate($p['serology_' . $i . '_date'] ?? null, 'serology_' . $i . '_date');
        chagasRejectFutureDate($serologyDates[$i], 'serology_' . $i . '_date');

        if ($serologyResults[$i] !== null && $serologyTypes[$i] === null) {
            throw new \InvalidArgumentException(sprintf(xlt('Serology test %d has a result but no test type.'), $i));
        }
        if ($serologyTypes[$i] !== null && $serologyResults[$i] === null) {
            throw new \InvalidArgumentException(sprintf(xlt('Serology test %d has a test type but no result.'), $i));
        }
    }

    $serologyStatus = CardInput::listOption('card_chagas_serology_status', $p['serology_status'] ?? null, 'serology_status', $storedValue('serology_status'));
    $serologyNote   = CardInput::text($p['serology_note'] ?? null);

    // Both stages are independent, physician-selected classifications.
    $kuschnirStage = CardInput::listOption('card_chagas_kuschnir', $p['kuschnir_stage'] ?? null, 'kuschnir_stage', $storedValue('kuschnir_stage'));
    $abcdStage     = CardInput::listOption('card_chagas_abcd', $p['abcd_stage'] ?? null, 'abcd_stage', $storedValue('abcd_stage'));

    // ECG
    $ecgDone = CardInput::flag($p['ecg_done'] ?? null);
    $ecgDate = chagasDate($p['ecg_date'] ?? null, 'ecg_date');
    chagasRejectFutureDate($ecgDate, 'ecg_date');

    $ecgNormal = CardInput::flag($p['ecg_normal'] ?? null);
    $ecgFlags  = [
        'ecg_rbbb'          => CardInput::flag($p['ecg_rbbb'] ?? null),
        'ecg_lafb'          => CardInput::flag($p['ecg_lafb'] ?? null),
        'ecg_lpfb'          => CardInput::flag($p['ecg_lpfb'] ?? null),
        'ecg_av_block'      => CardInput::flag($p['ecg_av_block'] ?? null),
        'ecg_q_waves'       => CardInput::flag($p['ecg_q_waves'] ?? null),
        'ecg_st_t_changes'  => CardInput::flag($p['ecg_st_t_changes'] ?? null),
        'ecg_low_voltage'   => CardInput::flag($p['ecg_low_voltage'] ?? null),
        'ecg_arrhythmia'    => CardInput::flag($p['ecg_arrhythmia'] ?? null),
        'ecg_other'         => CardInput::flag($p['ecg_other'] ?? null),
    ];
    $ecgFindings = CardInput::text($p['ecg_findings'] ?? null);

    if ($ecgNormal === 1 && in_array(1, $ecgFlags, true)) {
        throw new \InvalidArgumentException(xlt('A normal ECG cannot be combined with abnormal ECG findings.'));
    }

    // Chest X-ray
    $chestXrayDone = CardInput::flag($p['chest_xray_done'] ?? null);
    $chestXrayDate = chagasDate($p['chest_xray_date'] ?? null, 'chest_xray_date');
    chagasRejectFutureDate($chestXrayDate, 'chest_xray_date');
    $chestXrayCardiomegaly = CardInput::flag($p['chest_xray_cardiomegaly'] ?? null);
    $chestXrayFindings     = CardInput::text($p['chest_xray_findings'] ?? null);

    // Echocardiogram
    $echoDone = CardInput::flag($p['echo_done'] ?? null);
    $echoDate = chagasDate($p['echo_date'] ?? null, 'echo_date');
    chagasRejectFutureDate($echoDate, 'echo_date');
    $lvefPct      = CardInput::decimal($p['lvef_pct'] ?? null, 'lvef_pct', RANGES['lvef_pct'][0], RANGES['lvef_pct'][1]);
    $lveddMm      = CardInput::decimal($p['lvedd_mm'] ?? null, 'lvedd_mm', RANGES['lvedd_mm'][0], RANGES['lvedd_mm'][1]);
    $echoAneurysm = CardInput::flag($p['echo_aneurysm'] ?? null);
    $echoThrombus = CardInput::flag($p['echo_thrombus'] ?? null);
    $echoWallMotion = CardInput::listOption('card_wall_motion', $p['echo_wall_motion'] ?? null, 'echo_wall_motion', $storedValue('echo_wall_motion'));
    $echoFindings = CardInput::text($p['echo_findings'] ?? null);

    // Heart failure and functional class
    $heartFailure = CardInput::flag($p['heart_failure'] ?? null);
    $nyhaClass    = CardInput::listOption('card_nyha', $p['nyha_class'] ?? null, 'nyha_class', $storedValue('nyha_class'));
    $hfFindings   = CardInput::text($p['hf_findings'] ?? null);

    if ($nyhaClass !== null && $heartFailure !== 1) {
        throw new \InvalidArgumentException(xlt('An NYHA class can only be recorded when heart failure is present.'));
    }

    // Cardiac device
    $cardiacDevice = CardInput::listOption('card_chagas_device', $p['cardiac_device'] ?? null, 'cardiac_device', $storedValue('cardiac_device'));
    $deviceNote    = CardInput::text($p['device_note'] ?? null);

    // Digestive involvement
    $digestiveInvolvement = CardInput::flag($p['digestive_involvement'] ?? null);
    $megaesophagus        = CardInput::flag($p['megaesophagus'] ?? null);
    $megacolon            = CardInput::flag($p['megacolon'] ?? null);
    $constipation         = CardInput::flag($p['constipation'] ?? null);
    $digestiveFindings    = CardInput::text($p['digestive_findings'] ?? null);

    if (($megaesophagus === 1 || $megacolon === 1 || $constipation === 1) && $digestiveInvolvement !== 1) {
        throw new \InvalidArgumentException(xlt('Digestive findings require digestive involvement to be marked as present.'));
    }

    // Etiologic treatment (no doses or indication logic)
    $treatmentStatus    = CardInput::listOption('card_chagas_treatment_status', $p['treatment_status'] ?? null, 'treatment_status', $storedValue('treatment_status'));
    $treatmentDrug      = CardInput::listOption('card_chagas_treatment_drug', $p['treatment_drug'] ?? null, 'treatment_drug', $storedValue('treatment_drug'));
    $treatmentStartDate = chagasDate($p['treatment_start_date'] ?? null, 'treatment_start_date');
    $treatmentEndDate   = chagasDate($p['treatment_end_date'] ?? null, 'treatment_end_date');
    $treatmentNote      = CardInput::text($p['treatment_note'] ?? null);

    if ($treatmentStartDate !== null && $treatmentEndDate !== null && $treatmentEndDate < $treatmentStartDate) {
        throw new \InvalidArgumentException(xlt('The treatment end date cannot be before the treatment start date.'));
    }

    // Public health and family
    $mandatoryReport     = CardInput::flag($p['mandatory_report'] ?? null);
    $reportDate          = chagasDate($p['report_date'] ?? null, 'report_date');
    chagasRejectFutureDate($reportDate, 'report_date');
    $publicHealthNote    = CardInput::text($p['public_health_note'] ?? null);
    $familyScreeningDone = CardInput::flag($p['family_screening_done'] ?? null);
    $familyScreeningNote = CardInput::text($p['family_screening_note'] ?? null);

    if ($mandatoryReport === 1 && $reportDate === null) {
        throw new \InvalidArgumentException(xlt('A reporting date is required when the case is reported to public health.'));
    }

    // Plan and report
    $nextControlDate = chagasDate($p['next_control_date'] ?? null, 'next_control_date');
    if ($nextControlDate !== null && $nextControlDate <= substr($studyDate, 0, 10)) {
        throw new \InvalidArgumentException(xlt('The next control date must be after the study date.'));
    }

    $plan       = CardInput::text($p['plan'] ?? null);
    $conclusion = CardInput::text($p['conclusion'] ?? null);
    $comments   = CardInput::text($p['comments'] ?? null);

    // Finalize requirements
    if ($action === 'save_final') {
        if ($diseasePhase === null) {
            throw new \InvalidArgumentException(xlt('A disease phase is required to finalize the study.'));
        }
        if ($serologyStatus === null) {
            throw new \InvalidArgumentException(xlt('An overall serology status is required to finalize the study.'));
        }
        if ($conclusion === null || $conclusion === '') {
            throw new \InvalidArgumentException(xlt('A conclusion is required to finalize the study.'));
        }

        // Chronic forms require at least one physician-selected stage.
        if (strncmp($diseasePhase, 'chronic_', 8) === 0
            && $kuschnirStage === null && $abcdStage === null) {
            throw new \InvalidArgumentException(xlt('A Kuschnir or ABCD stage is required to finalize a chronic form.'));
        }

        if ($heartFailure === 1 && $nyhaClass === null) {
            throw new \InvalidArgumentException(xlt('An NYHA class is required when heart failure is present.'));
        }
    }
} catch (\InvalidArgumentException $e) {
    chagasRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// Status / workflow
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
    'cardiology_id'          => $cardiologyId,
    'pid'                    => $pid,
    'encounter'              => $encounter,
    'user'                   => $authUser,
    'activity'               => 1,

    // Study workflow
    'study_date'             => $studyDate,
    'status'                 => $status,
    'finalized_at'           => $finalizedAt,
    'finalized_by'           => $finalizedBy,

    // Phase and epidemiology
    'disease_phase'          => $diseasePhase,
    'origin_area'            => $originArea,
    'endemic_residence'      => $endemicResidence,
    'epidemiological_history' => $epidemiologicalHistory,
    'transmission_route'     => $transmissionRoute,

    // Serology
    'serology_1_type'        => $serologyTypes[1],
    'serology_1_result'      => $serologyResults[1],
    'serology_1_date'        => $serologyDates[1],
    'serology_2_type'        => $serologyTypes[2],
    'serology_2_result'      => $serologyResults[2],
    'serology_2_date'        => $serologyDates[2],
    'serology_3_type'        => $serologyTypes[3],
    'serology_3_result'      => $serologyResults[3],
    'serology_3_date'        => $serologyDates[3],
    'serology_status'        => $serologyStatus,
    'serology_note'          => $serologyNote,

    // Stage (independent physician-selected classifications)
    'kuschnir_stage'         => $kuschnirStage,
    'abcd_stage'             => $abcdStage,

    // ECG
    'ecg_done'               => $ecgDone,
    'ecg_date'               => $ecgDate,
    'ecg_normal'             => $ecgNormal,
    'ecg_rbbb'               => $ecgFlags['ecg_rbbb'],
    'ecg_lafb'               => $ecgFlags['ecg_lafb'],
    'ecg_lpfb'               => $ecgFlags['ecg_lpfb'],
    'ecg_av_block'           => $ecgFlags['ecg_av_block'],
    'ecg_q_waves'            => $ecgFlags['ecg_q_waves'],
    'ecg_st_t_changes'       => $ecgFlags['ecg_st_t_changes'],
    'ecg_low_voltage'        => $ecgFlags['ecg_low_voltage'],
    'ecg_arrhythmia'         => $ecgFlags['ecg_arrhythmia'],
    'ecg_other'              => $ecgFlags['ecg_other'],
    'ecg_findings'           => $ecgFindings,

    // Chest X-ray
    'chest_xray_done'        => $chestXrayDone,
    'chest_xray_date'        => $chestXrayDate,
    'chest_xray_cardiomegaly' => $chestXrayCardiomegaly,
    'chest_xray_findings'    => $chestXrayFindings,

    // Echocardiogram
    'echo_done'              => $echoDone,
    'echo_date'              => $echoDate,
    'lvef_pct'               => $lvefPct,
    'lvedd_mm'               => $lveddMm,
    'echo_aneurysm'          => $echoAneurysm,
    'echo_thrombus'          => $echoThrombus,
    'echo_wall_motion'       => $echoWallMotion,
    'echo_findings'          => $echoFindings,

    // Heart failure
    'heart_failure'          => $heartFailure,
    'nyha_class'             => $nyhaClass,
    'hf_findings'            => $hfFindings,

    // Device
    'cardiac_device'         => $cardiacDevice,
    'device_note'            => $deviceNote,

    // Digestive involvement
    'digestive_involvement'  => $digestiveInvolvement,
    'megaesophagus'          => $megaesophagus,
    'megacolon'              => $megacolon,
    'constipation'           => $constipation,
    'digestive_findings'     => $digestiveFindings,

    // Etiologic treatment
    'treatment_status'       => $treatmentStatus,
    'treatment_drug'         => $treatmentDrug,
    'treatment_start_date'   => $treatmentStartDate,
    'treatment_end_date'     => $treatmentEndDate,
    'treatment_note'         => $treatmentNote,

    // Public health and family
    'mandatory_report'       => $mandatoryReport,
    'report_date'            => $reportDate,
    'public_health_note'     => $publicHealthNote,
    'family_screening_done'  => $familyScreeningDone,
    'family_screening_note'  => $familyScreeningNote,

    // Plan and report
    'plan'                   => $plan,
    'next_control_date'      => $nextControlDate,
    'conclusion'             => $conclusion,
    'comments'               => $comments,
];

// ---------------------------------------------------------------------------
// 6. Database Transaction
// ---------------------------------------------------------------------------
CardiologyContainer::startTransaction();

try {
    if ($studyId === 0) {
        // INSERT
        $columns = array_keys($data);
        $sql = 'INSERT INTO `form_card_chagas` (`' . implode('`, `', $columns) . '`) '
             . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $studyId = (int)sqlInsert($sql, array_values($data));
    } else {
        // SELECT FOR UPDATE and verify
        $existing = sqlQuery(
            'SELECT id, status FROM `form_card_chagas`
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

        $sql = 'UPDATE `form_card_chagas` SET ' . implode(', ', $setParts)
             . ' WHERE id = ? AND pid = ? AND cardiology_id = ? AND status <> \'final\'';

        sqlStatement($sql, $params);
    }

    CardiologyContainer::commitTransaction();
} catch (\Throwable $e) {
    CardiologyContainer::rollbackTransaction();
    chagasRenderError($e->getMessage(), $backUrl);
}

// Audit after a successful commit. No clinical values are written to the log.
CardAudit::log(
    $status === 'final' ? 'study_finalize' : 'study_save',
    'chagas',
    [
        'study_id'      => $studyId,
        'pid'           => $pid,
        'encounter'     => $encounter,
        'cardiology_id' => $cardiologyId,
    ]
);

// ---------------------------------------------------------------------------
// 7. Redirect to view.php
// ---------------------------------------------------------------------------
$viewUrl = CardiologyContainer::getFormRootUrl()
    . '/cards/chagas/view.php'
    . '?pid='           . urlencode((string)$pid)
    . '&encounter='     . urlencode((string)$encounter)
    . '&cardiology_id=' . urlencode((string)$cardiologyId)
    . '&id='            . urlencode((string)$studyId);

formJump($viewUrl);
