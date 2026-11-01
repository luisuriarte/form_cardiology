<?php

/**
 * ECG card – save.php
 *
 * Handles POST from cards/ecg/new.php and cards/ecg/view.php.
 *
 * Checks:
 *   1. CSRF token (version-compatible via CardiologyContainer)
 *   2. ACL and card availability (via CardRegistry::canWrite)
 *   3. Container active and ownership matching pid and encounter
 *   4. Session pid and encounter consistency
 *   5. Study not finalized (using SELECT ... FOR UPDATE within transaction)
 *   6. Strict input validation with CardInput and RANGES bounds
 *   7. Finalizing requires a non-empty conclusion
 *
 * Uses a single associative array for both INSERT and UPDATE statements.
 * Wrapped in an atomic database transaction.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\ECG
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
    'heart_rate_bpm' => [20, 300],
    'axis_deg'       => [-180, 180],
    'pr_ms'          => [60, 400],
    'qrs_ms'         => [40, 300],
    'qt_ms'          => [200, 700],
];

// Helper to render validation error page
function ecgRenderError(string $message, string $backUrl): void
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
if (!$registry->canWrite('ecg', $userId)) {
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
        . '/cards/ecg/view.php?id=' . urlencode((string)$studyId)
        . '&cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
} else {
    $backUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/ecg/new.php?cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
}

// ---------------------------------------------------------------------------
// 4. Validate input data with CardInput
// ---------------------------------------------------------------------------
$p = $_POST;

try {
    $studyDate = CardInput::dateTime($p['study_date'] ?? null, 'study_date');

    // Clinical context
    $indication = CardInput::text($p['indication'] ?? null);

    // Rhythm & intervals
    $heartRateBpm = CardInput::int($p['heart_rate_bpm'] ?? null, 'heart_rate_bpm', RANGES['heart_rate_bpm'][0], RANGES['heart_rate_bpm'][1]);
    $rhythm       = CardInput::listOption('card_ecg_rhythm', $p['rhythm'] ?? null, 'rhythm');
    $axisDeg      = CardInput::int($p['axis_deg'] ?? null, 'axis_deg', RANGES['axis_deg'][0], RANGES['axis_deg'][1]);
    $prMs         = CardInput::int($p['pr_ms'] ?? null, 'pr_ms', RANGES['pr_ms'][0], RANGES['pr_ms'][1]);
    $qrsMs        = CardInput::int($p['qrs_ms'] ?? null, 'qrs_ms', RANGES['qrs_ms'][0], RANGES['qrs_ms'][1]);
    $qtMs         = CardInput::int($p['qt_ms'] ?? null, 'qt_ms', RANGES['qt_ms'][0], RANGES['qt_ms'][1]);

    // Conduction & morphology
    $avConduction   = CardInput::listOption('card_ecg_av_conduction', $p['av_conduction'] ?? null, 'av_conduction');
    $ventConduction = CardInput::listOption('card_ecg_vent_conduction', $p['ventricular_conduction'] ?? null, 'ventricular_conduction');
    $hypertrophy    = CardInput::listOption('card_ecg_hypertrophy', $p['hypertrophy'] ?? null, 'hypertrophy');
    $stTChanges     = CardInput::listOption('card_ecg_st_t', $p['st_t_changes'] ?? null, 'st_t_changes');
    $stTDetail      = CardInput::text($p['st_t_detail'] ?? null);
    $pathologicQ    = CardInput::flag($p['pathologic_q'] ?? null);
    $qLeads         = CardInput::text($p['q_leads'] ?? null);

    // Findings & Report
    $findings   = CardInput::text($p['findings'] ?? null);
    $conclusion = CardInput::text($p['conclusion'] ?? null);
    $comments   = CardInput::text($p['comments'] ?? null);

    // Finalize requires a non-empty conclusion
    if ($action === 'save_final' && ($conclusion === null || $conclusion === '')) {
        throw new \InvalidArgumentException(xlt('A conclusion is required to finalize the study.'));
    }
} catch (\InvalidArgumentException $e) {
    ecgRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// Server-side calculations: QTc Bazett and Fridericia
// ---------------------------------------------------------------------------
$qtcBazett      = null;
$qtcFridericia  = null;

if ($heartRateBpm !== null && $heartRateBpm > 0 && $qtMs !== null && $qtMs > 0) {
    $rr = 60.0 / (float)$heartRateBpm; // RR interval in seconds

    // Bazett: QTc = QT / sqrt(RR)
    $bazett = (float)$qtMs / sqrt($rr);
    $qtcBazett = (int)round($bazett);

    // Fridericia: QTc = QT / cbrt(RR)
    $fridericia = (float)$qtMs / pow($rr, 1.0 / 3.0);
    $qtcFridericia = (int)round($fridericia);
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

    // Clinical context
    'indication'             => $indication,

    // Rhythm & intervals
    'heart_rate_bpm'         => $heartRateBpm,
    'rhythm'                 => $rhythm,
    'axis_deg'               => $axisDeg,
    'pr_ms'                  => $prMs,
    'qrs_ms'                 => $qrsMs,
    'qt_ms'                  => $qtMs,
    'qtc_bazett_ms'          => $qtcBazett,
    'qtc_fridericia_ms'      => $qtcFridericia,

    // Conduction & morphology
    'av_conduction'          => $avConduction,
    'ventricular_conduction' => $ventConduction,
    'hypertrophy'            => $hypertrophy,
    'st_t_changes'           => $stTChanges,
    'st_t_detail'            => $stTDetail,
    'pathologic_q'           => $pathologicQ,
    'q_leads'                => $qLeads,

    // Findings, conclusion & comments
    'findings'               => $findings,
    'conclusion'             => $conclusion,
    'comments'               => $comments,
];

// ---------------------------------------------------------------------------
// 5. Database Transaction
// ---------------------------------------------------------------------------
CardiologyContainer::startTransaction();

try {
    if ($studyId === 0) {
        // INSERT
        $columns = array_keys($data);
        $sql = 'INSERT INTO `form_card_ecg` (`' . implode('`, `', $columns) . '`) '
             . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $studyId = (int)sqlInsert($sql, array_values($data));
    } else {
        // SELECT FOR UPDATE and verify
        $existing = sqlQuery(
            'SELECT id, status FROM `form_card_ecg`
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

        $sql = 'UPDATE `form_card_ecg` SET ' . implode(', ', $setParts)
             . ' WHERE id = ? AND pid = ? AND cardiology_id = ? AND status <> \'final\'';

        sqlStatement($sql, $params);
    }

    CardiologyContainer::commitTransaction();
} catch (\Throwable $e) {
    CardiologyContainer::rollbackTransaction();
    ecgRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// 6. Redirect to view.php
// ---------------------------------------------------------------------------
$viewUrl = CardiologyContainer::getFormRootUrl()
    . '/cards/ecg/view.php'
    . '?pid='           . urlencode((string)$pid)
    . '&encounter='     . urlencode((string)$encounter)
    . '&cardiology_id=' . urlencode((string)$cardiologyId)
    . '&id='            . urlencode((string)$studyId);

formJump($viewUrl);

