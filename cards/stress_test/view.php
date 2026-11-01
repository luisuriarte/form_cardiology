<?php

/**
 * Stress Test card – view.php
 *
 * Renders an existing Exercise Stress Test study.
 * If status = 'final' the form is rendered read-only.
 *
 * GET parameters:
 *   pid           int  Patient id
 *   encounter     int  Encounter number
 *   cardiology_id int  form_cardiology.id (parent container)
 *   id            int  form_card_stress_test.id (study primary key)
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
require_once($GLOBALS['srcdir'] . '/forms.inc.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once(__DIR__ . '/../../lib/CardiologyContainer.php');

// ---------------------------------------------------------------------------
// ACL check
// ---------------------------------------------------------------------------
if (!AclMain::aclCheckCore('patients', 'med')) {
    die(xlt('Not authorized'));
}

// ---------------------------------------------------------------------------
// Resolve parameters
// ---------------------------------------------------------------------------
$cardiologyId = (int)($_GET['cardiology_id'] ?? 0);
$studyId      = (int)($_GET['id']            ?? 0);
$pid          = (int)($_GET['pid']           ?? $pid);
$encounter    = (int)($_GET['encounter']     ?? $encounter);

if ($cardiologyId <= 0 || $studyId <= 0 || $pid <= 0 || $encounter <= 0) {
    die(xlt('Missing required parameters.'));
}

// Verify parent container is active.
$container = CardiologyContainer::getContainerById($cardiologyId);
if ($container === null) {
    die(xlt('The cardiology record is not available or has been deleted.'));
}

// ---------------------------------------------------------------------------
// Load the study
// ---------------------------------------------------------------------------
$study = sqlQuery(
    'SELECT * FROM `form_card_stress_test`
      WHERE id = ? AND cardiology_id = ? AND pid = ? AND encounter = ? AND activity = 1
      LIMIT 1',
    [$studyId, $cardiologyId, $pid, $encounter]
);

if (!$study || empty($study['id'])) {
    die(xlt('Study not found or has been deleted.'));
}

// ---------------------------------------------------------------------------
// Load protocol stages for this study
// ---------------------------------------------------------------------------
$stages = [];
$stagesRes = sqlStatement(
    'SELECT * FROM `form_card_stress_test_stage`
      WHERE stress_test_id = ?
      ORDER BY phase ASC, stage_no ASC, elapsed_s ASC',
    [$studyId]
);
while ($row = sqlFetchArray($stagesRes)) {
    $stages[] = $row;
}

// ---------------------------------------------------------------------------
// Determine read-only state
// ---------------------------------------------------------------------------
$readOnly = ((string)($study['status'] ?? '') === 'final');

// ---------------------------------------------------------------------------
// Load list options
// ---------------------------------------------------------------------------
function stressViewGetListOptions(string $listId): array
{
    $opts = [];
    $res  = sqlStatement(
        "SELECT option_id, title FROM list_options
          WHERE list_id = ? AND activity = 1
          ORDER BY seq ASC",
        [$listId]
    );
    while ($row = sqlFetchArray($res)) {
        $opts[] = $row;
    }
    return $opts;
}

$protocols     = stressViewGetListOptions('card_stress_protocol');
$bpResponses   = stressViewGetListOptions('card_bp_response');
$symptoms      = stressViewGetListOptions('card_stress_symptom');
$stopReasons   = stressViewGetListOptions('card_stop_reason');
$stressResults = stressViewGetListOptions('card_stress_result');
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Stress Test – Study'); ?> #<?php echo text((string)$studyId); ?></title>
    <?php Header::setupHeader(); ?>
    <link rel="stylesheet"
          href="<?php echo attr($GLOBALS['webroot']); ?>/interface/forms/cardiology/css/cardiology.css?v=<?php echo attr($GLOBALS['v_js_includes']); ?>">
</head>
<body>
<div class="container-fluid cardiology-container mt-3">

    <!-- Page header -->
    <div class="row cardiology-header mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <h4 class="mb-1">
                    <i class="fa fa-running text-primary me-2" aria-hidden="true"></i>
                    <?php echo xlt('Exercise Stress Test'); ?>
                    <?php if ($readOnly): ?>
                        <span class="badge bg-success ms-2"><?php echo xlt('Final'); ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark ms-2"><?php echo xlt('Draft'); ?></span>
                    <?php endif; ?>
                </h4>
                <div class="text-muted small">
                    <?php echo xlt('Study Date'); ?>:
                    <?php echo text(date('Y-m-d H:i', strtotime((string)$study['study_date']))); ?>
                    &mdash;
                    <?php echo xlt('Encounter'); ?>: <?php echo text((string)$encounter); ?>
                </div>
            </div>
            <hr class="mt-2 mb-0">
        </div>
    </div>

    <?php if ($readOnly): ?>
    <!-- Finalised notice + print link -->
    <div class="alert alert-info d-flex align-items-center mb-3">
        <i class="fa fa-lock me-2" aria-hidden="true"></i>
        <div>
            <?php echo xlt('This study has been finalized and is read-only.'); ?>
            <?php if (!empty($study['finalized_by']) && !empty($study['finalized_at'])): ?>
                <?php echo xlt('Finalized by'); ?>
                <strong><?php echo text((string)$study['finalized_by']); ?></strong>
                <?php echo xlt('on'); ?>
                <?php echo text(date('Y-m-d H:i', strtotime((string)$study['finalized_at']))); ?>.
            <?php endif; ?>
        </div>
        <a href="<?php echo attr($GLOBALS['rootdir']); ?>/forms/cardiology/cards/stress_test/print.php?id=<?php echo attr((string)$studyId); ?>&pid=<?php echo attr((string)$pid); ?>&encounter=<?php echo attr((string)$encounter); ?>&cardiology_id=<?php echo attr((string)$cardiologyId); ?>"
           class="btn btn-sm btn-outline-secondary ms-auto"
           target="_blank">
            <i class="fa fa-print me-1" aria-hidden="true"></i>
            <?php echo xlt('Print'); ?>
        </a>
    </div>
    <?php endif; ?>

    <form method="post"
          id="stress-test-form"
          action="<?php echo attr($GLOBALS['rootdir']); ?>/forms/cardiology/cards/stress_test/save.php">

        <input type="hidden" name="csrf_token_form"
               value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>">
        <input type="hidden" name="pid"           value="<?php echo attr((string)$pid); ?>">
        <input type="hidden" name="encounter"     value="<?php echo attr((string)$encounter); ?>">
        <input type="hidden" name="cardiology_id" value="<?php echo attr((string)$cardiologyId); ?>">
        <input type="hidden" name="study_id"      value="<?php echo attr((string)$studyId); ?>">

        <?php require __DIR__ . '/_form_fields.php'; ?>

        <div class="row mt-3 mb-4">
            <div class="col-12">
                <?php if (!$readOnly): ?>
                <button type="submit" name="action" value="save_draft"
                        class="btn btn-primary"
                        onclick="top.restoreSession()">
                    <?php echo xlt('Save Draft'); ?>
                </button>
                <button type="submit" name="action" value="save_final"
                        class="btn btn-success ms-2"
                        onclick="return confirm(<?php echo xlj('Finalize this study? It will become read-only.'); ?>)">
                    <?php echo xlt('Finalize'); ?>
                </button>
                <?php endif; ?>
                <a href="<?php echo attr($GLOBALS['rootdir']); ?>/forms/cardiology/view.php?id=<?php echo attr((string)$cardiologyId); ?>"
                   class="btn btn-secondary ms-2"
                   onclick="top.restoreSession()">
                    <?php echo xlt('Back to Container'); ?>
                </a>
                <a href="<?php echo attr($GLOBALS['rootdir']); ?>/forms/cardiology/cards/stress_test/print.php?id=<?php echo attr((string)$studyId); ?>&pid=<?php echo attr((string)$pid); ?>&encounter=<?php echo attr((string)$encounter); ?>&cardiology_id=<?php echo attr((string)$cardiologyId); ?>"
                   class="btn btn-outline-secondary ms-2"
                   target="_blank">
                    <i class="fa fa-print me-1" aria-hidden="true"></i>
                    <?php echo xlt('Print'); ?>
                </a>
            </div>
        </div>
    </form>

</div><!-- .cardiology-container -->
<?php if (!$readOnly): ?>
<script src="<?php echo attr($GLOBALS['webroot']); ?>/interface/forms/cardiology/cards/stress_test/stress_test.js?v=<?php echo attr($GLOBALS['v_js_includes']); ?>"></script>
<?php endif; ?>
</body>
</html>
