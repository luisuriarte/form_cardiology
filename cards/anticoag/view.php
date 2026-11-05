<?php

/**
 * Anticoagulation card – view.php
 *
 * Renders an existing anticoagulation study.
 * Read-only when status = 'final'.
 * Shows the calculated time in therapeutic range (TTR).
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

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Forms\Cardiology\CardAnticoag;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once(__DIR__ . '/../../lib/CardiologyContainer.php');
require_once(__DIR__ . '/../../lib/CardAnticoag.php');

if (!AclMain::aclCheckCore('patients', 'med')) {
    die(xlt('Not authorized'));
}

$studyId      = (int)($_GET['id']            ?? 0);
$cardiologyId = (int)($_GET['cardiology_id'] ?? 0);
$pid          = (int)($_GET['pid']           ?? $GLOBALS['pid'] ?? 0);
$encounter    = (int)($_GET['encounter']     ?? $GLOBALS['encounter'] ?? 0);

if ($studyId <= 0) {
    $newUrl = CardiologyContainer::getFormRootUrl()
        . '/cards/anticoag/new.php?pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter)
        . '&cardiology_id=' . urlencode((string)$cardiologyId);
    header('Location: ' . $newUrl);
    exit;
}

$study = sqlQuery(
    'SELECT * FROM `form_card_anticoag` WHERE id = ? AND pid = ? AND activity = 1',
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

function anticoagLoadListV(string $listId, ?string $currentValue = null): array
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

$indications = anticoagLoadListV('card_anticoag_indication', (string)($study['indication'] ?? ''));
$drugs       = anticoagLoadListV('card_anticoag_drug', (string)($study['drug'] ?? ''));
$sampleTypes = anticoagLoadListV('card_inr_sample', (string)($study['sample_type'] ?? ''));
$adherences  = anticoagLoadListV('card_adherence', (string)($study['adherence'] ?? ''));

$ttr = CardAnticoag::ttr($pid, (string)$study['study_date']);

$statusLabel = $readOnly ? xlt('Final') : xlt('Draft');
$statusClass = $readOnly ? 'bg-success' : 'bg-warning text-dark';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Anticoagulation Monitoring – View Study'); ?></title>
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
                    <i class="fa fa-tint text-danger me-2" aria-hidden="true"></i>
                    <?php echo xlt('Anticoagulation Monitoring'); ?>
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
          id="anticoag-form"
          action="<?php echo attr(CardiologyContainer::getFormRootUrl()); ?>/cards/anticoag/save.php">

        <input type="hidden" name="csrf_token_form"
               value="<?php echo attr(CardiologyContainer::collectCsrfToken()); ?>">
        <input type="hidden" name="pid"           value="<?php echo attr((string)$pid); ?>">
        <input type="hidden" name="encounter"     value="<?php echo attr((string)$encounter); ?>">
        <input type="hidden" name="cardiology_id" value="<?php echo attr((string)$cardiologyId); ?>">
        <input type="hidden" name="study_id"      value="<?php echo attr((string)$studyId); ?>">

        <?php require __DIR__ . '/_form_fields.php'; ?>

        <div class="card mb-3">
            <div class="card-header bg-light fw-bold py-2">
                <i class="fa fa-percent text-primary me-1"></i>
                <?php echo xlt('Time in Therapeutic Range'); ?>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <div class="text-muted small"><?php echo xlt('TTR (Rosendaal method)'); ?></div>
                        <div class="fs-4 fw-bold">
                            <?php if ($ttr['ttr_pct'] !== null) : ?>
                                <?php echo text($ttr['ttr_pct']); ?> %
                            <?php else : ?>
                                <span class="fs-6 text-muted"><?php echo text($ttr['message'] ?? xlt('Not enough data')); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small"><?php echo xlt('Days evaluated'); ?></div>
                        <div class="fs-5">
                            <?php echo text(number_format((float)$ttr['days_evaluated'], 1, '.', '')); ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small mb-0">
                            <?php echo xlt('Intervals longer than 56 days are excluded.'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $cardCode  = 'anticoag';
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
                    . '/cards/anticoag/print.php?id=' . urlencode((string)$studyId)
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
    $cardCode = 'anticoag';
    require __DIR__ . '/../../templates/_documents.php';
    ?>

</div>
<script src="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/js/cardiology-documents.js?v=<?php echo attr($GLOBALS['v_js_includes']); ?>"></script>
</body>
</html>
