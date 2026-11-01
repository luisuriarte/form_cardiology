<?php

/**
 * Echo card – view.php
 *
 * Renders an existing Doppler Echocardiogram study.
 * Read-only when status = 'final'.
 *
 * GET parameters:
 *   pid           int  Patient id
 *   encounter     int  Encounter number
 *   cardiology_id int  form_cardiology.id
 *   id            int  form_card_echo.id
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Echo
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
        . '/cards/echo/new.php?pid=' . urlencode((string)$pid)
        . '&encounter=' . urlencode((string)$encounter)
        . '&cardiology_id=' . urlencode((string)$cardiologyId);
    header('Location: ' . $newUrl);
    exit;
}

$study = sqlQuery(
    'SELECT * FROM `form_card_echo` WHERE id = ? AND pid = ? AND activity = 1',
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

function echoLoadListV(string $listId): array
{
    $opts = [];
    $res  = sqlStatement(
        "SELECT option_id, title FROM list_options WHERE list_id = ? AND activity = 1 ORDER BY seq ASC",
        [$listId]
    );
    while ($row = sqlFetchArray($res)) {
        $opts[] = $row;
    }
    return $opts;
}

$valveGrades    = echoLoadListV('card_valve_grade');
$wallMotions    = echoLoadListV('card_wall_motion');
$effusionGrades = echoLoadListV('card_effusion_grade');
$efMethods      = echoLoadListV('card_ef_method');

$statusLabel = $readOnly ? xlt('Final') : xlt('Draft');
$statusClass = $readOnly ? 'bg-success' : 'bg-warning text-dark';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Echocardiogram – View Study'); ?></title>
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
                    <i class="fa fa-heartbeat text-danger me-2" aria-hidden="true"></i>
                    <?php echo xlt('Doppler Echocardiogram'); ?>
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
                . '/cards/echo/print.php?pid=' . urlencode((string)$pid)
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

    <form method="post" id="echo-form"
          action="<?php echo attr(CardiologyContainer::getFormRootUrl()); ?>/cards/echo/save.php">

        <input type="hidden" name="csrf_token_form"
               value="<?php echo attr(CardiologyContainer::collectCsrfToken()); ?>">
        <input type="hidden" name="pid"           value="<?php echo attr((string)$pid); ?>">
        <input type="hidden" name="encounter"     value="<?php echo attr((string)$encounter); ?>">
        <input type="hidden" name="cardiology_id" value="<?php echo attr((string)$cardiologyId); ?>">
        <input type="hidden" name="study_id"      value="<?php echo attr((string)$studyId); ?>">

        <?php require __DIR__ . '/_form_fields.php'; ?>

        <?php
        $cardCode  = 'echo';
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

</div>
<script src="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/cards/echo/echo.js?v=<?php echo attr($GLOBALS['v_js_includes']); ?>"></script>
</body>
</html>
