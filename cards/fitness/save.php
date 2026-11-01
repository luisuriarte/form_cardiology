<?php

/**
 * Fitness card – save.php
 *
 * Handles POST from cards/fitness/new.php and cards/fitness/view.php.
 *
 * Checks:
 *   1. CSRF token (version-compatible via CardiologyContainer)
 *   2. ACL and card availability (via CardRegistry::canWrite)
 *   3. Container active and ownership matching pid and encounter
 *   4. Session pid and encounter consistency
 *   5. Study not finalized (using SELECT ... FOR UPDATE within transaction)
 *   6. Strict input validation with CardInput and RANGES bounds
 *   7. Finalizing requires determination, valid_until (> study_date), restrictions
 *      if determination is 'fit_restrictions', and conclusion.
 *
 * Uses a single associative array for both INSERT and UPDATE statements.
 * Wrapped in an atomic database transaction.
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
];

// Helper to render validation error page
function fitRenderError(string $message, string $backUrl): void
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
if (!$registry->canWrite('fitness', $userId)) {
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
        . '/cards/fitness/view.php?id=' . urlencode((string)$studyId)
        . '&cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
} else {
    $backUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/fitness/new.php?cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
}

// ---------------------------------------------------------------------------
// 4. Validate input data with CardInput
// ---------------------------------------------------------------------------
$p = $_POST;

try {
    $studyDate = CardInput::dateTime($p['study_date'] ?? null, 'study_date');

    // Purpose & context
    $purpose       = CardInput::listOption('card_fitness_purpose', $p['purpose'] ?? null, 'purpose');
    $purposeDetail = CardInput::text($p['purpose_detail'] ?? null);

    // Symptoms & history
    $symptomsNote  = CardInput::text($p['symptoms_note'] ?? null);
    $familyHistory = CardInput::text($p['family_history_note'] ?? null);

    // Vitals & exam
    $heightCm     = CardInput::decimal($p['height_cm'] ?? null, 'height_cm', RANGES['height_cm'][0], RANGES['height_cm'][1]);
    $weightKg     = CardInput::decimal($p['weight_kg'] ?? null, 'weight_kg', RANGES['weight_kg'][0], RANGES['weight_kg'][1]);
    $heartRateBpm = CardInput::int($p['heart_rate_bpm'] ?? null, 'heart_rate_bpm', RANGES['heart_rate_bpm'][0], RANGES['heart_rate_bpm'][1]);
    $bpSys        = CardInput::int($p['bp_systolic_mmhg'] ?? null, 'bp_systolic_mmhg', RANGES['bp_systolic_mmhg'][0], RANGES['bp_systolic_mmhg'][1]);
    $bpDia        = CardInput::int($p['bp_diastolic_mmhg'] ?? null, 'bp_diastolic_mmhg', RANGES['bp_diastolic_mmhg'][0], RANGES['bp_diastolic_mmhg'][1]);
    $examFindings = CardInput::text($p['exam_findings'] ?? null);

    // ECG & additional studies
    $ecgResult         = CardInput::listOption('card_fitness_ecg', $p['ecg_result'] ?? null, 'ecg_result');
    $ecgNote           = CardInput::text($p['ecg_note'] ?? null);
    $additionalStudies = CardInput::text($p['additional_studies'] ?? null);

    // Determination & validity
    $determination = CardInput::listOption('card_fitness_determination', $p['determination'] ?? null, 'determination');
    $restrictions  = CardInput::text($p['restrictions'] ?? null);

    $validUntilRaw = trim((string)($p['valid_until'] ?? ''));
    $validUntil    = null;
    if ($validUntilRaw !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $validUntilRaw);
        if (!$dt || $dt->format('Y-m-d') !== $validUntilRaw) {
            throw new \InvalidArgumentException(xlt('Invalid date format in field valid_until. Expected Y-m-d'));
        }
        $validUntil = $validUntilRaw;
    }

    $conclusion = CardInput::text($p['conclusion'] ?? null);
    $comments   = CardInput::text($p['comments'] ?? null);

    // Finalize validation rules
    if ($action === 'save_final') {
        if ($determination === null || $determination === '') {
            throw new \InvalidArgumentException(xlt('A determination is required to finalize the study.'));
        }
        if ($validUntil === null || $validUntil === '') {
            throw new \InvalidArgumentException(xlt('A validity date is required to finalize the study.'));
        }
        $studyDay = substr($studyDate, 0, 10);
        if ($validUntil <= $studyDay) {
            throw new \InvalidArgumentException(xlt('The validity date must be after the study date.'));
        }
        if ($determination === 'fit_restrictions' && ($restrictions === null || $restrictions === '')) {
            throw new \InvalidArgumentException(xlt('Restrictions must be specified when the determination is Fit with Restrictions.'));
        }
        if ($conclusion === null || $conclusion === '') {
            throw new \InvalidArgumentException(xlt('A conclusion is required to finalize the study.'));
        }
    }
} catch (\InvalidArgumentException $e) {
    fitRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// Server-side calculation: Body Mass Index (BMI)
// ---------------------------------------------------------------------------
$bmi = null;
if ($heightCm !== null && $weightKg !== null) {
    $h = (float)$heightCm;
    $w = (float)$weightKg;
    if ($h > 0 && $w > 0) {
        $hM  = $h / 100.0;
        $bmi = number_format(round($w / ($hM * $hM), 1), 1, '.', '');
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
    'cardiology_id'        => $cardiologyId,
    'pid'                  => $pid,
    'encounter'            => $encounter,
    'user'                 => $authUser,
    'activity'             => 1,

    // Study workflow
    'study_date'           => $studyDate,
    'status'               => $status,
    'finalized_at'         => $finalizedAt,
    'finalized_by'         => $finalizedBy,

    // Purpose & context
    'purpose'              => $purpose,
    'purpose_detail'       => $purposeDetail,

    // Symptoms & history
    'symptoms_note'        => $symptomsNote,
    'family_history_note'  => $familyHistory,

    // Vitals & exam
    'height_cm'            => $heightCm,
    'weight_kg'            => $weightKg,
    'bmi'                  => $bmi,
    'heart_rate_bpm'       => $heartRateBpm,
    'bp_systolic_mmhg'     => $bpSys,
    'bp_diastolic_mmhg'    => $bpDia,
    'exam_findings'        => $examFindings,

    // ECG & additional studies
    'ecg_result'           => $ecgResult,
    'ecg_note'             => $ecgNote,
    'additional_studies'   => $additionalStudies,

    // Determination & certificate validity
    'determination'        => $determination,
    'restrictions'         => $restrictions,
    'valid_until'          => $validUntil,
    'conclusion'           => $conclusion,
    'comments'             => $comments,
];

// ---------------------------------------------------------------------------
// 5. Database Transaction
// ---------------------------------------------------------------------------
CardiologyContainer::startTransaction();

try {
    if ($studyId === 0) {
        // INSERT
        $columns = array_keys($data);
        $sql = 'INSERT INTO `form_card_fitness` (`' . implode('`, `', $columns) . '`) '
             . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $studyId = (int)sqlInsert($sql, array_values($data));
    } else {
        // SELECT FOR UPDATE and verify
        $existing = sqlQuery(
            'SELECT id, status FROM `form_card_fitness`
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

        $sql = 'UPDATE `form_card_fitness` SET ' . implode(', ', $setParts)
             . ' WHERE id = ? AND pid = ? AND cardiology_id = ? AND status <> \'final\'';

        sqlStatement($sql, $params);
    }

    CardiologyContainer::commitTransaction();
} catch (\Throwable $e) {
    CardiologyContainer::rollbackTransaction();
    fitRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// 6. Redirect to view.php
// ---------------------------------------------------------------------------
$viewUrl = CardiologyContainer::getFormRootUrl()
    . '/cards/fitness/view.php'
    . '?pid='           . urlencode((string)$pid)
    . '&encounter='     . urlencode((string)$encounter)
    . '&cardiology_id=' . urlencode((string)$cardiologyId)
    . '&id='            . urlencode((string)$studyId);

formJump($viewUrl);

