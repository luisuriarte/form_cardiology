<?php

/**
 * Heart Failure Follow-up card – view.php
 *
 * Renders an existing heart failure follow-up study.
 * Read-only when status = 'final'.
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
    $newUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/hf_followup/new.php?pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter)
        . '&cardiology_id=' . urlencode((string)$cardiologyId);
    header('Location: ' . $newUrl);
    exit;
}

$study = sqlQuery(
    'SELECT * FROM `form_card_hf_followup` WHERE id = ? AND pid = ? AND activity = 1',
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

function hfLoadListV(string $listId, ?string $currentValue = null): array
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

$nyhaClasses = hfLoadListV('card_nyha', (string)($study['nyha_class'] ?? ''));
$hfTypes     = hfLoadListV('card_hf_type', (string)($study['hf_type'] ?? ''));
$etiologies  = hfLoadListV('card_hf_etiology', (string)($study['etiology'] ?? ''));
$hfStages    = hfLoadListV('card_hf_stage', (string)($study['hf_stage'] ?? ''));
$edemaGrades = hfLoadListV('card_edema_grade', (string)($study['edema'] ?? ''));
$adherences  = hfLoadListV('card_adherence', (string)($study['adherence'] ?? ''));

$statusLabel = $readOnly ? xlt('Final') : xlt('Draft');
$statusClass = $readOnly ? 'bg-success' : 'bg-warning text-dark';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Heart Failure Follow-up – View Study'); ?></title>
    <?php Header::setupHeader(['chart', 'datetime-picker']); ?>
    <link rel="stylesheet"
          href="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/css/cardiology.css?v=<?php echo attr($GLOBALS['v_js_includes']); ?>">
</head>
<body>
<div class="container-fluid cardiology-container mt-3">

    <div class="row cardiology-header mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <h4 class="mb-1">
                    <i class="fa fa-heart text-danger me-2" aria-hidden="true"></i>
                    <?php echo xlt('Heart Failure Follow-up'); ?>
                    <span class="badge <?php echo attr($statusClass); ?> ms-2 fs-6">
                        <?php echo text($statusLabel); ?>
                    </span>
                </h4>
                <div class="text-muted small">
                    <?php echo xlt('Encounter'); ?>: <?php echo text((string)$encounter); ?> &mdash;
                    <?php echo xlt('Study ID'); ?>: <?php echo text((string)$studyId); ?>
                    <?php if ($readOnly) : ?>
                    &mdash; <span class="text-danger"><?php echo xlt('Read-only'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <hr class="mt-2 mb-0">
        </div>
    </div>

    <form method="post"
          id="hf-followup-form"
          action="<?php echo attr(CardiologyContainer::getFormRootUrl()); ?>/cards/hf_followup/save.php">

        <input type="hidden" name="csrf_token_form"
               value="<?php echo attr(CardiologyContainer::collectCsrfToken()); ?>">
        <input type="hidden" name="pid"           value="<?php echo attr((string)$pid); ?>">
        <input type="hidden" name="encounter"     value="<?php echo attr((string)$encounter); ?>">
        <input type="hidden" name="cardiology_id" value="<?php echo attr((string)$cardiologyId); ?>">
        <input type="hidden" name="study_id"      value="<?php echo attr((string)$studyId); ?>">

        <?php require __DIR__ . '/_form_fields.php'; ?>

        <?php
        $cardCode  = 'hf_followup';
        $chartMode = 'view';
        require __DIR__ . '/../../templates/_charts.php';
        ?>

        <div class="row mt-3 mb-4">
            <div class="col-12">
                <?php if (!$readOnly) : ?>
                <button type="submit" name="action" value="save_draft"
                        class="btn btn-primary"
                        onclick="if (typeof top.restoreSession === 'function') { top.restoreSession(); }">
                    <?php echo xlt('Save Draft'); ?>
                </button>
                <button type="submit" name="action" value="save_final"
                        class="btn btn-success ms-2"
                        onclick="return confirm(<?php echo xlj('Finalize this study? It will become read-only.'); ?>)">
                    <?php echo xlt('Finalize'); ?>
                </button>
                <?php endif; ?>

                <a href="<?php echo attr(CardiologyContainer::getFormRootUrl()
                    . '/cards/hf_followup/print.php?id=' . urlencode((string)$studyId)
                    . '&cardiology_id=' . urlencode((string)$cardiologyId)
                    . '&pid=' . urlencode((string)$pid)
                    . '&encounter=' . urlencode((string)$encounter)); ?>"
                   target="_blank"
                   class="btn btn-outline-secondary <?php echo $readOnly ? '' : 'ms-2'; ?>">
                    <i class="fa fa-print me-1"></i><?php echo xlt('Print PDF'); ?>
                </a>

                <a href="<?php echo attr(CardiologyContainer::getFormRootUrl()
                    . '/view.php?id=' . urlencode((string)$cardiologyId)); ?>"
                   class="btn btn-secondary ms-2"
                   onclick="if (typeof top.restoreSession === 'function') { top.restoreSession(); }">
                    <?php echo xlt('Close'); ?>
                </a>
            </div>
        </div>
    </form>

    <?php
    $cardCode = 'hf_followup';
    require __DIR__ . '/../../templates/_documents.php';
    ?>

</div>
<script src="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/js/cardiology-documents.js?v=<?php echo attr($GLOBALS['v_js_includes']); ?>"></script>
</body>
</html>
