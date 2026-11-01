<?php

/**
 * Preoperative Risk card – save.php
 *
 * Handles POST from cards/preop_risk/new.php and cards/preop_risk/view.php.
 *
 * Checks:
 *   1. CSRF token (version-compatible via CardiologyContainer)
 *   2. ACL and card availability (via CardRegistry::canWrite)
 *   3. Container active and ownership matching pid and encounter
 *   4. Session pid and encounter consistency
 *   5. Study not finalized (using SELECT ... FOR UPDATE within transaction)
 *   6. Strict input validation with CardInput and RANGES bounds
 *   7. Finalizing requires both recommendation and conclusion
 *
 * Uses a single associative array for both INSERT and UPDATE statements.
 * Wrapped in an atomic database transaction.
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
    'height_cm'         => [50.0, 250.0],
    'weight_kg'         => [2.0, 400.0],
    'heart_rate_bpm'    => [20, 300],
    'bp_systolic_mmhg'  => [40, 300],
    'bp_diastolic_mmhg' => [20, 200],
    'lvef_pct'          => [5.0, 90.0],
];

// Helper to render validation error page
function preopRenderError(string $message, string $backUrl): void
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
if (!$registry->canWrite('preop_risk', $userId)) {
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
        . '/cards/preop_risk/view.php?id=' . urlencode((string)$studyId)
        . '&cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
} else {
    $backUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/preop_risk/new.php?cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
}

// ---------------------------------------------------------------------------
// 4. Load stored list values so inactive options already saved stay editable
// ---------------------------------------------------------------------------
$stored = [];
if ($studyId > 0) {
    $storedRow = sqlQuery(
        'SELECT * FROM `form_card_preop_risk`
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

    // Planned surgery
    $surgeryDesc   = CardInput::text($p['surgery_description'] ?? null);
    $surgeryDateRaw = trim((string)($p['surgery_date'] ?? ''));
    $surgeryDate   = null;
    if ($surgeryDateRaw !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $surgeryDateRaw);
        if (!$dt || $dt->format('Y-m-d') !== $surgeryDateRaw) {
            throw new \InvalidArgumentException(xlt('Invalid date format in field surgery_date. Expected Y-m-d'));
        }
        $surgeryDate = $surgeryDateRaw;
    }
    $surgeryUrgency   = CardInput::listOption('card_surgery_urgency', $p['surgery_urgency'] ?? null, 'surgery_urgency', $storedValue('surgery_urgency'));
    $surgeryRiskClass = CardInput::listOption('card_surgery_risk', $p['surgery_risk_class'] ?? null, 'surgery_risk_class', $storedValue('surgery_risk_class'));

    // Vitals & measurements
    $heightCm    = CardInput::decimal($p['height_cm'] ?? null, 'height_cm', RANGES['height_cm'][0], RANGES['height_cm'][1]);
    $weightKg    = CardInput::decimal($p['weight_kg'] ?? null, 'weight_kg', RANGES['weight_kg'][0], RANGES['weight_kg'][1]);
    $heartRateBpm= CardInput::int($p['heart_rate_bpm'] ?? null, 'heart_rate_bpm', RANGES['heart_rate_bpm'][0], RANGES['heart_rate_bpm'][1]);
    $bpSys       = CardInput::int($p['bp_systolic_mmhg'] ?? null, 'bp_systolic_mmhg', RANGES['bp_systolic_mmhg'][0], RANGES['bp_systolic_mmhg'][1]);
    $bpDia       = CardInput::int($p['bp_diastolic_mmhg'] ?? null, 'bp_diastolic_mmhg', RANGES['bp_diastolic_mmhg'][0], RANGES['bp_diastolic_mmhg'][1]);
    $functionalCap = CardInput::listOption('card_functional_capacity', $p['functional_capacity'] ?? null, 'functional_capacity', $storedValue('functional_capacity'));
    $asaClass    = CardInput::listOption('card_asa_class', $p['asa_class'] ?? null, 'asa_class', $storedValue('asa_class'));
    $lvefPct     = CardInput::decimal($p['lvef_pct'] ?? null, 'lvef_pct', RANGES['lvef_pct'][0], RANGES['lvef_pct'][1]);

    // Lee RCRI flags
    $leeHighRisk  = CardInput::flag($p['lee_high_risk_surgery'] ?? null);
    $leeIschemic  = CardInput::flag($p['lee_ischemic_heart_disease'] ?? null);
    $leeHf        = CardInput::flag($p['lee_heart_failure'] ?? null);
    $leeCvd       = CardInput::flag($p['lee_cerebrovascular_disease'] ?? null);
    $leeInsulin   = CardInput::flag($p['lee_insulin_diabetes'] ?? null);
    $leeCreat     = CardInput::flag($p['lee_creatinine_gt_2'] ?? null);

    // Complementary studies
    $medicationsNote = CardInput::text($p['medications_note'] ?? null);
    $ecgSummary      = CardInput::text($p['ecg_summary'] ?? null);
    $echoSummary     = CardInput::text($p['echo_summary'] ?? null);
    $labsNote        = CardInput::text($p['labs_note'] ?? null);

    // Recommendation & Conclusion
    $recommendation   = CardInput::listOption('card_preop_recommendation', $p['recommendation'] ?? null, 'recommendation', $storedValue('recommendation'));
    $periopRecom      = CardInput::text($p['perioperative_recommendations'] ?? null);
    $conclusion       = CardInput::text($p['conclusion'] ?? null);
    $comments         = CardInput::text($p['comments'] ?? null);

    // Finalize requires BOTH recommendation and conclusion
    if ($action === 'save_final') {
        if ($recommendation === null || $recommendation === '') {
            throw new \InvalidArgumentException(xlt('A recommendation is required to finalize the study.'));
        }
        if ($conclusion === null || $conclusion === '') {
            throw new \InvalidArgumentException(xlt('A conclusion is required to finalize the study.'));
        }
    }
} catch (\InvalidArgumentException $e) {
    preopRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// Server-side calculation: Lee RCRI score and class
// ---------------------------------------------------------------------------
$leeScore = $leeHighRisk + $leeIschemic + $leeHf + $leeCvd + $leeInsulin + $leeCreat;

$leeClass = 'I';
if ($leeScore === 1) {
    $leeClass = 'II';
} elseif ($leeScore === 2) {
    $leeClass = 'III';
} elseif ($leeScore >= 3) {
    $leeClass = 'IV';
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
    'cardiology_id'                 => $cardiologyId,
    'pid'                           => $pid,
    'encounter'                     => $encounter,
    'user'                          => $authUser,
    'activity'                      => 1,

    // Study workflow
    'study_date'                    => $studyDate,
    'status'                        => $status,
    'finalized_at'                  => $finalizedAt,
    'finalized_by'                  => $finalizedBy,

    // Planned surgery & urgency
    'surgery_description'           => $surgeryDesc,
    'surgery_date'                  => $surgeryDate,
    'surgery_urgency'               => $surgeryUrgency,
    'surgery_risk_class'            => $surgeryRiskClass,

    // Vitals & measurements
    'height_cm'                     => $heightCm,
    'weight_kg'                     => $weightKg,
    'heart_rate_bpm'                => $heartRateBpm,
    'bp_systolic_mmhg'              => $bpSys,
    'bp_diastolic_mmhg'             => $bpDia,
    'functional_capacity'           => $functionalCap,
    'asa_class'                     => $asaClass,
    'lvef_pct'                      => $lvefPct,

    // Lee RCRI flags and score
    'lee_high_risk_surgery'         => $leeHighRisk,
    'lee_ischemic_heart_disease'    => $leeIschemic,
    'lee_heart_failure'             => $leeHf,
    'lee_cerebrovascular_disease'   => $leeCvd,
    'lee_insulin_diabetes'          => $leeInsulin,
    'lee_creatinine_gt_2'           => $leeCreat,
    'lee_score'                     => $leeScore,
    'lee_class'                     => $leeClass,

    // Complementary studies
    'medications_note'              => $medicationsNote,
    'ecg_summary'                   => $ecgSummary,
    'echo_summary'                  => $echoSummary,
    'labs_note'                     => $labsNote,

    // Recommendation & Conclusion
    'recommendation'                => $recommendation,
    'perioperative_recommendations' => $periopRecom,
    'conclusion'                    => $conclusion,
    'comments'                      => $comments,
];

// ---------------------------------------------------------------------------
// 5. Database Transaction
// ---------------------------------------------------------------------------
CardiologyContainer::startTransaction();

try {
    if ($studyId === 0) {
        // INSERT
        $columns = array_keys($data);
        $sql = 'INSERT INTO `form_card_preop_risk` (`' . implode('`, `', $columns) . '`) '
             . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $studyId = (int)sqlInsert($sql, array_values($data));
    } else {
        // SELECT FOR UPDATE and verify
        $existing = sqlQuery(
            'SELECT id, status FROM `form_card_preop_risk`
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

        $sql = 'UPDATE `form_card_preop_risk` SET ' . implode(', ', $setParts)
             . ' WHERE id = ? AND pid = ? AND cardiology_id = ? AND status <> \'final\'';

        sqlStatement($sql, $params);
    }

    CardiologyContainer::commitTransaction();
} catch (\Throwable $e) {
    CardiologyContainer::rollbackTransaction();
    preopRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// 6. Redirect to view.php
// ---------------------------------------------------------------------------
$viewUrl = CardiologyContainer::getFormRootUrl()
    . '/cards/preop_risk/view.php'
    . '?pid='           . urlencode((string)$pid)
    . '&encounter='     . urlencode((string)$encounter)
    . '&cardiology_id=' . urlencode((string)$cardiologyId)
    . '&id='            . urlencode((string)$studyId);

formJump($viewUrl);

