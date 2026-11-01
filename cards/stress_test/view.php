<?php
/**
 * Stress Test card – view.php
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

if (!AclMain::aclCheckCore('patients', 'med')) {
    die(xlt('Not authorized'));
}

$studyId      = (int)($_GET['id']            ?? 0);
$cardiologyId = (int)($_GET['cardiology_id'] ?? 0);
$pid          = (int)($_GET['pid']           ?? $GLOBALS['pid'] ?? 0);
$encounter    = (int)($_GET['encounter']     ?? $GLOBALS['encounter'] ?? 0);

if ($studyId <= 0) {
    header('Location: ' . CardiologyContainer::getFormRootUrl()
        . '/cards/stress_test/new.php?pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter)
        . '&cardiology_id=' . urlencode((string)$cardiologyId));
    exit;
}

$study = sqlQuery(
    'SELECT * FROM `form_card_stress_test` WHERE id = ? AND pid = ? AND activity = 1',
    [$studyId, $pid]
);
if (!$study || !isset($study['id'])) {
    die(xlt('Study not found or has been deleted.'));
}

$cardiologyId = (int)$study['cardiology_id'];
$encounter    = (int)$study['encounter'];
$readOnly     = ($study['status'] === 'final');

$container = CardiologyContainer::getContainerById($cardiologyId);
if ($container === null) {
    die(xlt('The cardiology record is not available or has been deleted.'));
}

// Load stages
$stagesRaw = sqlStatement(
    'SELECT * FROM `form_card_stress_test_stage`
      WHERE stress_test_id = ?
      ORDER BY phase, stage_no, elapsed_s',
    [$studyId]
);
$stages = [];
while ($row = sqlFetchArray($stagesRaw)) { $stages[] = $row; }

function stLoadListV(string $listId, ?string $currentValue = null): array
{
    $opts = [];
    $res  = sqlStatement(
        'SELECT option_id, title, activity
           FROM list_options
          WHERE list_id = ?
          ORDER BY seq ASC',
        [$listId]
    );
    while ($row = sqlFetchArray($res)) {
        if ((int)$row['activity'] === 1 || (string)$row['option_id'] === (string)$currentValue) {
            $opts[] = $row;
        }
    }
    return $opts;
}

$protocols     = stLoadListV('card_stress_protocol', (string)($study['protocol'] ?? ''));
$bpResponses   = stLoadListV('card_bp_response', (string)($study['bp_response'] ?? ''));
$symptoms      = stLoadListV('card_stress_symptom', (string)($study['symptom'] ?? ''));
$stopReasons   = stLoadListV('card_stop_reason', (string)($study['stop_reason'] ?? ''));
$stressResults = stLoadListV('card_stress_result', (string)($study['result'] ?? ''));

$statusLabel = $readOnly ? xlt('Final') : xlt('Draft');
$statusClass = $readOnly ? 'bg-success' : 'bg-warning text-dark';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Exercise Stress Test – View Study'); ?></title>
    <?php Header::setupHeader('chart'); ?>
    <link rel="stylesheet"
          href="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/css/cardiology.css?v=<?php echo attr($GLOBALS['v_js_includes']); ?>">
</head>
<body>
<div class="container-fluid cardiology-container mt-3">
    <div class="row cardiology-header mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <h4 class="mb-1">
                    <i class="fa fa-running text-danger me-2" aria-hidden="true"></i>
                    <?php echo xlt('Exercise Stress Test'); ?>
                    <span class="badge <?php echo attr($statusClass); ?> ms-2">
                        <?php echo $statusLabel; ?>
                    </span>
                </h4>
                <div class="text-muted small">
                    <?php echo xlt('Encounter'); ?>: <?php echo text((string)$encounter); ?>
                    &mdash; <?php echo xlt('Study'); ?> #<?php echo text((string)$studyId); ?>
                    <?php if (!empty($study['user'])) : ?>
                    &mdash; <?php echo text($study['user']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <hr class="mt-2 mb-0">
        </div>
    </div>

    <?php if ($readOnly) : ?>
    <div class="row mb-3">
        <div class="col-12">
            <a href="<?php echo attr(CardiologyContainer::getFormRootUrl()
                . '/cards/stress_test/print.php?pid=' . urlencode((string)$pid)
                . '&encounter=' . urlencode((string)$encounter)
                . '&cardiology_id=' . urlencode((string)$cardiologyId)
                . '&id=' . urlencode((string)$studyId)); ?>"
               class="btn btn-info" target="_blank" onclick="top.restoreSession()">
                <i class="fa fa-print me-1" aria-hidden="true"></i>
                <?php echo xlt('Print PDF'); ?>
            </a>
            <a href="<?php echo attr(CardiologyContainer::getFormRootUrl()
                . '/view.php?id=' . urlencode((string)$cardiologyId)); ?>"
               class="btn btn-secondary ms-2" onclick="top.restoreSession()">
                <?php echo xlt('Back'); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <form method="post" id="stress-test-form"
          action="<?php echo attr(CardiologyContainer::getFormRootUrl()); ?>/cards/stress_test/save.php">

        <input type="hidden" name="csrf_token_form"
               value="<?php echo attr(CardiologyContainer::collectCsrfToken()); ?>">
        <input type="hidden" name="pid"           value="<?php echo attr((string)$pid); ?>">
        <input type="hidden" name="encounter"     value="<?php echo attr((string)$encounter); ?>">
        <input type="hidden" name="cardiology_id" value="<?php echo attr((string)$cardiologyId); ?>">
        <input type="hidden" name="study_id"      value="<?php echo attr((string)$studyId); ?>">

        <?php require __DIR__ . '/_form_fields.php'; ?>

        <?php
        $cardCode  = 'stress_test';
        $chartMode = 'view';
        require __DIR__ . '/../../templates/_charts.php';
        ?>

        <?php if (!$readOnly) : ?>
        <div class="row mt-3 mb-4">
            <div class="col-12">
                <button type="submit" name="action" value="save_draft"
                        class="btn btn-primary" onclick="top.restoreSession()">
                    <?php echo xlt('Save Draft'); ?>
                </button>
                <button type="submit" name="action" value="save_final"
                        class="btn btn-success ms-2"
                        onclick="return confirm(<?php echo xlj('Finalize this study? It will become read-only.'); ?>)">
                    <?php echo xlt('Finalize'); ?>
                </button>
                <a href="<?php echo attr(CardiologyContainer::getFormRootUrl()
                    . '/view.php?id=' . urlencode((string)$cardiologyId)); ?>"
                   class="btn btn-secondary ms-2" onclick="top.restoreSession()">
                    <?php echo xlt('Cancel'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </form>

    <?php
    $cardCode = 'stress_test';
    require __DIR__ . '/../../templates/_documents.php';
    ?>
</div>
<script src="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/cards/stress_test/stress_test.js?v=<?php echo attr($GLOBALS['v_js_includes']); ?>"></script>
<script src="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/js/cardiology-documents.js?v=<?php echo attr($GLOBALS['v_js_includes']); ?>"></script>
</body>
</html>
