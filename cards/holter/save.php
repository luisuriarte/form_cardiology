<?php

/**
 * Holter Monitor card – save.php
 *
 * Handles POST from cards/holter/new.php and cards/holter/view.php.
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
 * The calculated PVC burden is recomputed here and never read from POST.
 *
 * Uses a single associative array for both INSERT and UPDATE statements.
 * Wrapped in an atomic database transaction.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Holter
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
    'recording_hours'      => [1, 168],
    'total_beats'          => [100, 2000000],
    'hr_min_bpm'           => [20, 200],
    'hr_mean_bpm'          => [30, 220],
    'hr_max_bpm'           => [40, 300],
    'af_burden_pct'        => [0.0, 100.0],
    'paced_beats_pct'      => [0.0, 100.0],
    'longest_pause_ms'     => [0, 30000],
    'pauses_gt_3s'         => [0, 1000],
    'pvc_total'            => [0, 500000],
    'pvc_couplets'         => [0, 100000],
    'nsvt_runs'            => [0, 10000],
    'longest_nsvt_beats'   => [0, 1000],
    'pac_total'            => [0, 500000],
    'svt_runs'             => [0, 10000],
    'longest_svt_beats'    => [0, 10000],
    'st_episodes'          => [0, 1000],
];

// Helper to render validation error page
function holterRenderError(string $message, string $backUrl): void
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
 * Validate and normalize an optional TIME input.
 *
 * Accepts HH:MM and HH:MM:SS and always returns HH:MM:SS.
 */
function holterTime(mixed $value, string $field): ?string
{
    return \OpenEMR\Forms\Cardiology\CardDate::parseTime($value, $field);
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
if (!$registry->canWrite('holter', $userId)) {
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
        . '/cards/holter/view.php?id=' . urlencode((string)$studyId)
        . '&cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
} else {
    $backUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/holter/new.php?cardiology_id=' . urlencode((string)$cardiologyId)
        . '&pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter);
}

// ---------------------------------------------------------------------------
// 4. Load stored list values so inactive options already saved stay editable
// ---------------------------------------------------------------------------
$stored = [];
if ($studyId > 0) {
    $storedRow = sqlQuery(
        'SELECT * FROM `form_card_holter`
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

    $indication   = CardInput::text($p['indication'] ?? null);
    $recordingHours = CardInput::int($p['recording_hours'] ?? null, 'recording_hours', RANGES['recording_hours'][0], RANGES['recording_hours'][1]);
    $totalBeats     = CardInput::int($p['total_beats'] ?? null, 'total_beats', RANGES['total_beats'][0], RANGES['total_beats'][1]);

    $hrMin  = CardInput::int($p['hr_min_bpm'] ?? null, 'hr_min_bpm', RANGES['hr_min_bpm'][0], RANGES['hr_min_bpm'][1]);
    $hrMean = CardInput::int($p['hr_mean_bpm'] ?? null, 'hr_mean_bpm', RANGES['hr_mean_bpm'][0], RANGES['hr_mean_bpm'][1]);
    $hrMax  = CardInput::int($p['hr_max_bpm'] ?? null, 'hr_max_bpm', RANGES['hr_max_bpm'][0], RANGES['hr_max_bpm'][1]);

    $hrMinTime = holterTime($p['hr_min_time'] ?? null, 'hr_min_time');
    $hrMaxTime = holterTime($p['hr_max_time'] ?? null, 'hr_max_time');

    $dominantRhythm = CardInput::listOption('card_ecg_rhythm', $p['dominant_rhythm'] ?? null, 'dominant_rhythm', $storedValue('dominant_rhythm'));

    $afPresent    = CardInput::flag($p['af_present'] ?? null);
    $afBurdenPct  = CardInput::decimal($p['af_burden_pct'] ?? null, 'af_burden_pct', RANGES['af_burden_pct'][0], RANGES['af_burden_pct'][1]);
    $pacedBeatsPct = CardInput::decimal($p['paced_beats_pct'] ?? null, 'paced_beats_pct', RANGES['paced_beats_pct'][0], RANGES['paced_beats_pct'][1]);

    $longestPauseMs = CardInput::int($p['longest_pause_ms'] ?? null, 'longest_pause_ms', RANGES['longest_pause_ms'][0], RANGES['longest_pause_ms'][1]);
    $pausesGt3s     = CardInput::int($p['pauses_gt_3s'] ?? null, 'pauses_gt_3s', RANGES['pauses_gt_3s'][0], RANGES['pauses_gt_3s'][1]);

    $pvcTotal   = CardInput::int($p['pvc_total'] ?? null, 'pvc_total', RANGES['pvc_total'][0], RANGES['pvc_total'][1]);
    $pvcCouplets = CardInput::int($p['pvc_couplets'] ?? null, 'pvc_couplets', RANGES['pvc_couplets'][0], RANGES['pvc_couplets'][1]);
    $nsvtRuns    = CardInput::int($p['nsvt_runs'] ?? null, 'nsvt_runs', RANGES['nsvt_runs'][0], RANGES['nsvt_runs'][1]);
    $longestNsvt = CardInput::int($p['longest_nsvt_beats'] ?? null, 'longest_nsvt_beats', RANGES['longest_nsvt_beats'][0], RANGES['longest_nsvt_beats'][1]);

    $pacTotal  = CardInput::int($p['pac_total'] ?? null, 'pac_total', RANGES['pac_total'][0], RANGES['pac_total'][1]);
    $svtRuns   = CardInput::int($p['svt_runs'] ?? null, 'svt_runs', RANGES['svt_runs'][0], RANGES['svt_runs'][1]);
    $longestSvt = CardInput::int($p['longest_svt_beats'] ?? null, 'longest_svt_beats', RANGES['longest_svt_beats'][0], RANGES['longest_svt_beats'][1]);

    $stEpisodes    = CardInput::int($p['st_episodes'] ?? null, 'st_episodes', RANGES['st_episodes'][0], RANGES['st_episodes'][1]);
    $stDescription = CardInput::text($p['st_description'] ?? null);
    $avBlockNote   = CardInput::text($p['av_block_note'] ?? null);

    $symptomsReported   = CardInput::text($p['symptoms_reported'] ?? null);
    $symptomCorrelation = CardInput::text($p['symptom_correlation'] ?? null);
    $findings           = CardInput::text($p['findings'] ?? null);
    $conclusion         = CardInput::text($p['conclusion'] ?? null);
    $comments           = CardInput::text($p['comments'] ?? null);

    // Cross-field rules
    if ($totalBeats !== null && $pvcTotal !== null && $pvcTotal > $totalBeats) {
        throw new \InvalidArgumentException(xlt('The PVC count cannot exceed the total beat count.'));
    }

    if ($hrMin !== null && $hrMean !== null && $hrMax !== null
        && !($hrMin <= $hrMean && $hrMean <= $hrMax)) {
        throw new \InvalidArgumentException(xlt('Heart rates must satisfy minimum less than or equal to mean less than or equal to maximum.'));
    }

    // Finalize requirements
    if ($action === 'save_final') {
        if ($recordingHours === null) {
            throw new \InvalidArgumentException(xlt('A recording duration is required to finalize the study.'));
        }
        if ($conclusion === null || $conclusion === '') {
            throw new \InvalidArgumentException(xlt('A conclusion is required to finalize the study.'));
        }
    }
} catch (\InvalidArgumentException $e) {
    holterRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// Server-side calculation: PVC burden percent of total beats
// ---------------------------------------------------------------------------
$pvcBurdenPct = null;
if ($pvcTotal !== null && $totalBeats !== null && $totalBeats > 0) {
    $pvcBurdenPct = number_format(
        ((float)$pvcTotal / (float)$totalBeats) * 100.0,
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

    // Recording and rhythm
    'indication'           => $indication,
    'recording_hours'      => $recordingHours,
    'total_beats'          => $totalBeats,
    'hr_min_bpm'           => $hrMin,
    'hr_mean_bpm'          => $hrMean,
    'hr_max_bpm'           => $hrMax,
    'hr_min_time'          => $hrMinTime,
    'hr_max_time'          => $hrMaxTime,
    'dominant_rhythm'      => $dominantRhythm,
    'af_present'           => $afPresent,
    'af_burden_pct'        => $afBurdenPct,
    'paced_beats_pct'      => $pacedBeatsPct,

    // Pauses and ventricular ectopy
    'longest_pause_ms'     => $longestPauseMs,
    'pauses_gt_3s'         => $pausesGt3s,
    'pvc_total'            => $pvcTotal,
    'pvc_burden_pct'       => $pvcBurdenPct,
    'pvc_couplets'         => $pvcCouplets,
    'nsvt_runs'            => $nsvtRuns,
    'longest_nsvt_beats'   => $longestNsvt,

    // Atrial ectopy, ST changes and AV block
    'pac_total'            => $pacTotal,
    'svt_runs'             => $svtRuns,
    'longest_svt_beats'    => $longestSvt,
    'st_episodes'          => $stEpisodes,
    'st_description'       => $stDescription,
    'av_block_note'        => $avBlockNote,

    // Symptoms, findings and report
    'symptoms_reported'    => $symptomsReported,
    'symptom_correlation'  => $symptomCorrelation,
    'findings'             => $findings,
    'conclusion'           => $conclusion,
    'comments'             => $comments,
];

// ---------------------------------------------------------------------------
// 6. Database Transaction
// ---------------------------------------------------------------------------
CardiologyContainer::startTransaction();

try {
    if ($studyId === 0) {
        // INSERT
        $columns = array_keys($data);
        $sql = 'INSERT INTO `form_card_holter` (`' . implode('`, `', $columns) . '`) '
             . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $studyId = (int)sqlInsert($sql, array_values($data));
    } else {
        // SELECT FOR UPDATE and verify
        $existing = sqlQuery(
            'SELECT id, status FROM `form_card_holter`
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

        $sql = 'UPDATE `form_card_holter` SET ' . implode(', ', $setParts)
             . ' WHERE id = ? AND pid = ? AND cardiology_id = ? AND status <> \'final\'';

        sqlStatement($sql, $params);
    }

    CardiologyContainer::commitTransaction();
} catch (\Throwable $e) {
    CardiologyContainer::rollbackTransaction();
    holterRenderError($e->getMessage(), $backUrl);
}

// ---------------------------------------------------------------------------
// 7. Redirect to view.php
// ---------------------------------------------------------------------------
$viewUrl = CardiologyContainer::getFormRootUrl()
    . '/cards/holter/view.php'
    . '?pid='           . urlencode((string)$pid)
    . '&encounter='     . urlencode((string)$encounter)
    . '&cardiology_id=' . urlencode((string)$cardiologyId)
    . '&id='            . urlencode((string)$studyId);

formJump($viewUrl);
