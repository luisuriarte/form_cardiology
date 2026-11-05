<?php

/**
 * Anticoagulation card – save.php
 *
 * Handles POST from cards/anticoag/new.php and cards/anticoag/view.php.
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
 * Calculated values (in_range and dose_change_pct) are recomputed here and
 * never read from POST.
 *
 * Uses a single associative array for both INSERT and UPDATE statements.
 * Wrapped in an atomic database transaction.
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
    'inr_value'              => [0.5, 10.0],
    'target_inr_low'         => [1.0, 4.0],
    'target_inr_high'        => [1.5, 5.0],
    'current_weekly_dose_mg' => [0.0, 200.0],
    'new_weekly_dose_mg'     => [0.0, 200.0],
];

// Helper to render validation error page
function anticoagRenderError(string $message, string $backUrl): void
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
function anticoagDate(mixed $value, string $field): ?string
{
    return \OpenEMR\Forms\Cardiology\CardDate::parseDate($value, $field);
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
if (!$registry->canWrite('anticoag', $userId)) {
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
        . '/cards/anticoag/view.php?id=' . urlencode((string)$studyId)
        . '&cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
} else {
    $backUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/anticoag/new.php?cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
}

// ---------------------------------------------------------------------------
// 4. Load stored list values so inactive options already saved stay editable
// ---------------------------------------------------------------------------
$stored = [];
if ($studyId > 0) {
    $storedRow = sqlQuery(
        'SELECT * FROM `form_card_anticoag`
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

    $indication  = CardInput::listOption('card_anticoag_indication', $p['indication'] ?? null, 'indication', $storedValue('indication'));
    $drug        = CardInput::listOption('card_anticoag_drug', $p['drug'] ?? null, 'drug', $storedValue('drug'));
    $sampleType  = CardInput::listOption('card_inr_sample', $p['sample_type'] ?? null, 'sample_type', $storedValue('sample_type'));

    $inrValue    = CardInput::decimal($p['inr_value'] ?? null, 'inr_value', RANGES['inr_value'][0], RANGES['inr_value'][1]);
    $targetLow   = CardInput::decimal($p['target_inr_low'] ?? null, 'target_inr_low', RANGES['target_inr_low'][0], RANGES['target_inr_low'][1]);
    $targetHigh  = CardInput::decimal($p['target_inr_high'] ?? null, 'target_inr_high', RANGES['target_inr_high'][0], RANGES['target_inr_high'][1]);

    $currentDose = CardInput::decimal($p['current_weekly_dose_mg'] ?? null, 'current_weekly_dose_mg', RANGES['current_weekly_dose_mg'][0], RANGES['current_weekly_dose_mg'][1]);
    $newDose     = CardInput::decimal($p['new_weekly_dose_mg'] ?? null, 'new_weekly_dose_mg', RANGES['new_weekly_dose_mg'][0], RANGES['new_weekly_dose_mg'][1]);

    $dosingSchedule = CardInput::text($p['dosing_schedule'] ?? null);
    $missedDoses    = CardInput::text($p['missed_doses'] ?? null);

    $bleedingEvent    = CardInput::flag($p['bleeding_event'] ?? null);
    $thromboticEvent  = CardInput::flag($p['thrombotic_event'] ?? null);
    $eventDetail      = CardInput::text($p['event_detail'] ?? null);

    $adherence          = CardInput::listOption('card_adherence', $p['adherence'] ?? null, 'adherence', $storedValue('adherence'));
    $interactingFactors = CardInput::text($p['interacting_factors'] ?? null);

    $nextControlDate = anticoagDate($p['next_control_date'] ?? null, 'next_control_date');

    $plan       = CardInput::text($p['plan'] ?? null);
    $conclusion = CardInput::text($p['conclusion'] ?? null);
    $comments   = CardInput::text($p['comments'] ?? null);

    // Cross-field rules
    if ($targetLow !== null && $targetHigh !== null && (float)$targetHigh <= (float)$targetLow) {
        throw new \InvalidArgumentException(xlt('The upper target INR must be greater than the lower target INR.'));
    }

    if (($bleedingEvent === 1 || $thromboticEvent === 1) && ($eventDetail === null || $eventDetail === '')) {
        throw new \InvalidArgumentException(xlt('Event detail is required when a bleeding or thrombotic event is recorded.'));
    }

    // Finalize requirements
    if ($action === 'save_final') {
        if ($inrValue === null) {
            throw new \InvalidArgumentException(xlt('An INR value is required to finalize the study.'));
        }
        if ($targetLow === null || $targetHigh === null) {
            throw new \InvalidArgumentException(xlt('A target INR range is required to finalize the study.'));
        }
        if ($nextControlDate === null) {
            throw new \InvalidArgumentException(xlt('A next control date is required to finalize the study.'));
        }
        if ($conclusion === null || $conclusion === '') {
            throw new \InvalidArgumentException(xlt('A conclusion is required to finalize the study.'));
        }

        $studyDateOnly = substr($studyDate, 0, 10);
        if ($nextControlDate <= $studyDateOnly) {
            throw new \InvalidArgumentException(xlt('The next control date must be after the study date.'));
        }

        $maxNextDate = date('Y-m-d', (int)strtotime($studyDateOnly . ' +365 days'));
        if ($nextControlDate > $maxNextDate) {
            throw new \InvalidArgumentException(xlt('The next control date cannot be more than 365 days after the study date.'));
        }
    }
} catch (\InvalidArgumentException $e) {
    anticoagRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// Server-side calculations
// ---------------------------------------------------------------------------
$inRange = null;
if ($inrValue !== null && $targetLow !== null && $targetHigh !== null) {
    $inr = (float)$inrValue;
    $low = (float)$targetLow;
    $high = (float)$targetHigh;

    if ($inr < $low) {
        $inRange = 'below';
    } elseif ($inr > $high) {
        $inRange = 'above';
    } else {
        $inRange = 'in';
    }
}

$doseChangePct = null;
if ($currentDose !== null && $newDose !== null && (float)$currentDose > 0.0) {
    $doseChangePct = number_format(
        (((float)$newDose - (float)$currentDose) / (float)$currentDose) * 100.0,
        2,
        '.',
        ''
    );
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

    // Anticoagulation data
    'indication'             => $indication,
    'drug'                   => $drug,
    'sample_type'            => $sampleType,
    'inr_value'              => $inrValue,
    'target_inr_low'         => $targetLow,
    'target_inr_high'        => $targetHigh,
    'in_range'               => $inRange,
    'current_weekly_dose_mg' => $currentDose,
    'new_weekly_dose_mg'     => $newDose,
    'dose_change_pct'        => $doseChangePct,
    'dosing_schedule'        => $dosingSchedule,
    'missed_doses'           => $missedDoses,
    'bleeding_event'         => $bleedingEvent,
    'thrombotic_event'       => $thromboticEvent,
    'event_detail'           => $eventDetail,
    'adherence'              => $adherence,
    'interacting_factors'    => $interactingFactors,
    'next_control_date'      => $nextControlDate,
    'plan'                   => $plan,
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
        $sql = 'INSERT INTO `form_card_anticoag` (`' . implode('`, `', $columns) . '`) '
             . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $studyId = (int)sqlInsert($sql, array_values($data));
    } else {
        // SELECT FOR UPDATE and verify
        $existing = sqlQuery(
            'SELECT id, status FROM `form_card_anticoag`
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

        $sql = 'UPDATE `form_card_anticoag` SET ' . implode(', ', $setParts)
             . ' WHERE id = ? AND pid = ? AND cardiology_id = ? AND status <> \'final\'';

        sqlStatement($sql, $params);
    }

    CardiologyContainer::commitTransaction();
} catch (\Throwable $e) {
    CardiologyContainer::rollbackTransaction();
    anticoagRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// 7. Redirect to view.php
// ---------------------------------------------------------------------------
$viewUrl = CardiologyContainer::getFormRootUrl()
    . '/cards/anticoag/view.php'
    . '?pid='           . urlencode((string)$pid)
    . '&encounter='     . urlencode((string)$encounter)
    . '&cardiology_id=' . urlencode((string)$cardiologyId)
    . '&id='            . urlencode((string)$studyId);

formJump($viewUrl);
