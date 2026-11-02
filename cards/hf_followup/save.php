<?php

/**
 * Heart Failure Follow-up card – save.php
 *
 * Handles POST from cards/hf_followup/new.php and cards/hf_followup/view.php.
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
 * Calculated values (nyha_numeric and weight_change_kg) are recomputed here
 * and never read from POST.
 *
 * Uses a single associative array for both INSERT and UPDATE statements.
 * Wrapped in an atomic database transaction.
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
require_once($GLOBALS['srcdir'] . '/forms.inc.php');
require_once(__DIR__ . '/../../lib/CardiologyContainer.php');
require_once(__DIR__ . '/../../lib/CardRegistry.php');
require_once(__DIR__ . '/../../lib/CardInput.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Forms\Cardiology\CardInput;
use OpenEMR\Forms\Cardiology\CardRegistry;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

// ---------------------------------------------------------------------------
// Plausibility bounds for input validation
// ---------------------------------------------------------------------------
const RANGES = [
    'weight_kg'                  => [20.0, 300.0],
    'dry_weight_kg'              => [20.0, 300.0],
    'heart_rate_bpm'             => [20, 250],
    'bp_systolic_mmhg'           => [40, 300],
    'bp_diastolic_mmhg'          => [20, 200],
    'spo2_pct'                   => [50.0, 100.0],
    'lvef_pct'                   => [5.0, 90.0],
    'nt_probnp_pg_ml'            => [0.0, 70000.0],
    'bnp_pg_ml'                  => [0.0, 5000.0],
    'creatinine_mg_dl'           => [0.1, 15.0],
    'potassium_mmol_l'           => [1.5, 8.0],
    'sodium_mmol_l'              => [100.0, 170.0],
    'hospitalizations_since_last' => [0, 50],
    'er_visits_since_last'       => [0, 50],
];

// Helper to render validation error page
function hfRenderError(string $message, string $backUrl): void
{
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title><?php echo xlt('Cardiology Error'); ?></title>
        <link rel="stylesheet" href="<?php echo attr($GLOBALS['webroot']); ?>/public/assets/dist/css/bootstrap.min.css">
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
function hfDate(mixed $value, string $field): ?string
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
 * Map the NYHA list option to its numeric class.
 */
function hfNyhaNumeric(?string $nyhaClass): ?int
{
    return match ($nyhaClass) {
        'i' => 1,
        'ii' => 2,
        'iii' => 3,
        'iv' => 4,
        default => null,
    };
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
if (!$registry->canWrite('hf_followup', $userId)) {
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
        . '/cards/hf_followup/view.php?id=' . urlencode((string)$studyId)
        . '&cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
} else {
    $backUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/hf_followup/new.php?cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
}

// ---------------------------------------------------------------------------
// 4. Load stored list values so inactive options already saved stay editable
// ---------------------------------------------------------------------------
$stored = [];
if ($studyId > 0) {
    $storedRow = sqlQuery(
        'SELECT * FROM `form_card_hf_followup`
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

    $nyhaClass = CardInput::listOption('card_nyha', $p['nyha_class'] ?? null, 'nyha_class', $storedValue('nyha_class'));
    $hfType    = CardInput::listOption('card_hf_type', $p['hf_type'] ?? null, 'hf_type', $storedValue('hf_type'));
    $etiology  = CardInput::listOption('card_hf_etiology', $p['etiology'] ?? null, 'etiology', $storedValue('etiology'));
    $hfStage   = CardInput::listOption('card_hf_stage', $p['hf_stage'] ?? null, 'hf_stage', $storedValue('hf_stage'));

    $weightKg    = CardInput::decimal($p['weight_kg'] ?? null, 'weight_kg', RANGES['weight_kg'][0], RANGES['weight_kg'][1]);
    $dryWeightKg = CardInput::decimal($p['dry_weight_kg'] ?? null, 'dry_weight_kg', RANGES['dry_weight_kg'][0], RANGES['dry_weight_kg'][1]);

    $heartRate  = CardInput::int($p['heart_rate_bpm'] ?? null, 'heart_rate_bpm', RANGES['heart_rate_bpm'][0], RANGES['heart_rate_bpm'][1]);
    $bpSys      = CardInput::int($p['bp_systolic_mmhg'] ?? null, 'bp_systolic_mmhg', RANGES['bp_systolic_mmhg'][0], RANGES['bp_systolic_mmhg'][1]);
    $bpDia      = CardInput::int($p['bp_diastolic_mmhg'] ?? null, 'bp_diastolic_mmhg', RANGES['bp_diastolic_mmhg'][0], RANGES['bp_diastolic_mmhg'][1]);
    $spo2       = CardInput::decimal($p['spo2_pct'] ?? null, 'spo2_pct', RANGES['spo2_pct'][0], RANGES['spo2_pct'][1]);

    $edema = CardInput::listOption('card_edema_grade', $p['edema'] ?? null, 'edema', $storedValue('edema'));

    $jugularDistension = CardInput::flag($p['jugular_distension'] ?? null);
    $rales             = CardInput::flag($p['rales'] ?? null);
    $orthopnea         = CardInput::flag($p['orthopnea'] ?? null);
    $pnd               = CardInput::flag($p['pnd'] ?? null);
    $congestionNote    = CardInput::text($p['congestion_note'] ?? null);

    $lvefPct  = CardInput::decimal($p['lvef_pct'] ?? null, 'lvef_pct', RANGES['lvef_pct'][0], RANGES['lvef_pct'][1]);
    $lvefDate = hfDate($p['lvef_date'] ?? null, 'lvef_date');

    $ntProbnp  = CardInput::decimal($p['nt_probnp_pg_ml'] ?? null, 'nt_probnp_pg_ml', RANGES['nt_probnp_pg_ml'][0], RANGES['nt_probnp_pg_ml'][1]);
    $bnp       = CardInput::decimal($p['bnp_pg_ml'] ?? null, 'bnp_pg_ml', RANGES['bnp_pg_ml'][0], RANGES['bnp_pg_ml'][1]);
    $creatinine = CardInput::decimal($p['creatinine_mg_dl'] ?? null, 'creatinine_mg_dl', RANGES['creatinine_mg_dl'][0], RANGES['creatinine_mg_dl'][1]);
    $potassium = CardInput::decimal($p['potassium_mmol_l'] ?? null, 'potassium_mmol_l', RANGES['potassium_mmol_l'][0], RANGES['potassium_mmol_l'][1]);
    $sodium    = CardInput::decimal($p['sodium_mmol_l'] ?? null, 'sodium_mmol_l', RANGES['sodium_mmol_l'][0], RANGES['sodium_mmol_l'][1]);

    $adherence = CardInput::listOption('card_adherence', $p['adherence'] ?? null, 'adherence', $storedValue('adherence'));

    $onRaas   = CardInput::flag($p['on_raas_inhibitor'] ?? null);
    $onBeta   = CardInput::flag($p['on_beta_blocker'] ?? null);
    $onMra    = CardInput::flag($p['on_mra'] ?? null);
    $onSglt2  = CardInput::flag($p['on_sglt2_inhibitor'] ?? null);
    $onLoop   = CardInput::flag($p['on_loop_diuretic'] ?? null);

    $medicationsNote = CardInput::text($p['medications_note'] ?? null);

    $hospitalizations = CardInput::int($p['hospitalizations_since_last'] ?? null, 'hospitalizations_since_last', RANGES['hospitalizations_since_last'][0], RANGES['hospitalizations_since_last'][1]);
    $erVisits         = CardInput::int($p['er_visits_since_last'] ?? null, 'er_visits_since_last', RANGES['er_visits_since_last'][0], RANGES['er_visits_since_last'][1]);
    $educationGiven   = CardInput::flag($p['education_given'] ?? null);

    $nextControlDate = hfDate($p['next_control_date'] ?? null, 'next_control_date');

    $plan       = CardInput::text($p['plan'] ?? null);
    $conclusion = CardInput::text($p['conclusion'] ?? null);
    $comments   = CardInput::text($p['comments'] ?? null);

    // Cross-field rules
    if ($lvefDate !== null && $lvefDate > date('Y-m-d')) {
        throw new \InvalidArgumentException(xlt('The LVEF date cannot be in the future.'));
    }

    if ($nextControlDate !== null && $nextControlDate <= substr($studyDate, 0, 10)) {
        throw new \InvalidArgumentException(xlt('The next control date must be after the study date.'));
    }

    // Finalize requirements
    if ($action === 'save_final') {
        if ($nyhaClass === null || $nyhaClass === '') {
            throw new \InvalidArgumentException(xlt('An NYHA class is required to finalize the study.'));
        }
        if ($conclusion === null || $conclusion === '') {
            throw new \InvalidArgumentException(xlt('A conclusion is required to finalize the study.'));
        }
    }
} catch (\InvalidArgumentException $e) {
    hfRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// Server-side calculations
// ---------------------------------------------------------------------------
$nyhaNumeric = hfNyhaNumeric($nyhaClass);

$weightChangeKg = null;
if ($weightKg !== null) {
    $previousSql = 'SELECT weight_kg
                      FROM `form_card_hf_followup`
                     WHERE pid = ?
                       AND status = \'final\'
                       AND activity = 1
                       AND study_date < ?';
    $previousParams = [$pid, $studyDate];

    if ($studyId > 0) {
        $previousSql .= ' AND id <> ?';
        $previousParams[] = $studyId;
    }

    $previousSql .= ' ORDER BY study_date DESC, id DESC LIMIT 1';

    $previousRow = sqlQuery($previousSql, $previousParams);
    if ($previousRow && $previousRow['weight_kg'] !== null && $previousRow['weight_kg'] !== '') {
        $weightChangeKg = number_format(
            (float)$weightKg - (float)$previousRow['weight_kg'],
            3,
            '.',
            ''
        );
    }
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
    'cardiology_id'              => $cardiologyId,
    'pid'                        => $pid,
    'encounter'                  => $encounter,
    'user'                       => $authUser,
    'activity'                   => 1,

    // Study workflow
    'study_date'                 => $studyDate,
    'status'                     => $status,
    'finalized_at'               => $finalizedAt,
    'finalized_by'               => $finalizedBy,

    // Functional class and heart failure profile
    'nyha_class'                 => $nyhaClass,
    'nyha_numeric'               => $nyhaNumeric,
    'hf_type'                    => $hfType,
    'etiology'                   => $etiology,
    'hf_stage'                   => $hfStage,

    // Volume and vital signs
    'weight_kg'                  => $weightKg,
    'dry_weight_kg'              => $dryWeightKg,
    'weight_change_kg'           => $weightChangeKg,
    'heart_rate_bpm'             => $heartRate,
    'bp_systolic_mmhg'           => $bpSys,
    'bp_diastolic_mmhg'          => $bpDia,
    'spo2_pct'                   => $spo2,
    'edema'                      => $edema,
    'jugular_distension'         => $jugularDistension,
    'rales'                      => $rales,
    'orthopnea'                  => $orthopnea,
    'pnd'                        => $pnd,
    'congestion_note'            => $congestionNote,

    // Cardiac function and laboratory values
    'lvef_pct'                   => $lvefPct,
    'lvef_date'                  => $lvefDate,
    'nt_probnp_pg_ml'            => $ntProbnp,
    'bnp_pg_ml'                  => $bnp,
    'creatinine_mg_dl'           => $creatinine,
    'potassium_mmol_l'           => $potassium,
    'sodium_mmol_l'              => $sodium,

    // Treatment and follow-up
    'adherence'                  => $adherence,
    'on_raas_inhibitor'          => $onRaas,
    'on_beta_blocker'            => $onBeta,
    'on_mra'                     => $onMra,
    'on_sglt2_inhibitor'         => $onSglt2,
    'on_loop_diuretic'           => $onLoop,
    'medications_note'           => $medicationsNote,
    'hospitalizations_since_last' => $hospitalizations,
    'er_visits_since_last'       => $erVisits,
    'education_given'            => $educationGiven,
    'plan'                       => $plan,
    'next_control_date'          => $nextControlDate,
    'conclusion'                 => $conclusion,
    'comments'                   => $comments,
];

// ---------------------------------------------------------------------------
// 6. Database Transaction
// ---------------------------------------------------------------------------
CardiologyContainer::startTransaction();

try {
    if ($studyId === 0) {
        // INSERT
        $columns = array_keys($data);
        $sql = 'INSERT INTO `form_card_hf_followup` (`' . implode('`, `', $columns) . '`) '
             . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $studyId = (int)sqlInsert($sql, array_values($data));
    } else {
        // SELECT FOR UPDATE and verify
        $existing = sqlQuery(
            'SELECT id, status FROM `form_card_hf_followup`
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

        $sql = 'UPDATE `form_card_hf_followup` SET ' . implode(', ', $setParts)
             . ' WHERE id = ? AND pid = ? AND cardiology_id = ? AND status <> \'final\'';

        sqlStatement($sql, $params);
    }

    CardiologyContainer::commitTransaction();
} catch (\Throwable $e) {
    CardiologyContainer::rollbackTransaction();
    hfRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// 7. Redirect to view.php
// ---------------------------------------------------------------------------
$viewUrl = CardiologyContainer::getFormRootUrl()
    . '/cards/hf_followup/view.php'
    . '?pid='           . urlencode((string)$pid)
    . '&encounter='     . urlencode((string)$encounter)
    . '&cardiology_id=' . urlencode((string)$cardiologyId)
    . '&id='            . urlencode((string)$studyId);

formJump($viewUrl);
