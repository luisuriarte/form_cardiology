<?php

/**
 * Fitness card – new.php
 *
 * Renders a blank Cardiology Fitness Certificate (cardiology fitness certificate) form.
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

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once(__DIR__ . '/../../lib/CardiologyContainer.php');

if (!AclMain::aclCheckCore('patients', 'med')) {
    die(xlt('Not authorized'));
}

$cardiologyId = (int)($_GET['cardiology_id'] ?? 0);
$pid          = (int)($_GET['pid']           ?? $GLOBALS['pid'] ?? 0);
$encounter    = (int)($_GET['encounter']     ?? $GLOBALS['encounter'] ?? 0);

if ($cardiologyId <= 0 || $pid <= 0 || $encounter <= 0) {
    die(xlt('Missing required parameters.'));
}

$container = CardiologyContainer::getContainerById($cardiologyId);
if ($container === null) {
    die(xlt('The cardiology record is not available or has been deleted.'));
}

function fitnessLoadList(string $listId): array
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

$purposes       = fitnessLoadList('card_fitness_purpose');
$ecgResults     = fitnessLoadList('card_fitness_ecg');
$determinations = fitnessLoadList('card_fitness_determination');

// Pre-fill valid_until with study date + 12 months (editable)
$study = [
    'valid_until' => date('Y-m-d', strtotime('+12 months')),
];
$readOnly = false;
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Cardiology Fitness Certificate – New Evaluation'); ?></title>
    <?php Header::setupHeader(); ?>
    <link rel="stylesheet"
          href="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/css/cardiology.css?v=<?php echo attr($GLOBALS['v_js_includes']); ?>">
</head>
<body>
<div class="container-fluid cardiology-container mt-3">

    <div class="row cardiology-header mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <h4 class="mb-1">
                    <i class="fa fa-certificate text-danger me-2" aria-hidden="true"></i>
                    <?php echo xlt('Cardiology Fitness Certificate'); ?>
                </h4>
                <div class="text-muted small">
                    <?php echo xlt('New Evaluation'); ?> &mdash;
                    <?php echo xlt('Encounter'); ?>: <?php echo text((string)$encounter); ?>
                </div>
            </div>
            <hr class="mt-2 mb-0">
        </div>
    </div>

    <form method="post"
          id="fitness-form"
          action="<?php echo attr(CardiologyContainer::getFormRootUrl()); ?>/cards/fitness/save.php">

        <input type="hidden" name="csrf_token_form"
               value="<?php echo attr(CardiologyContainer::collectCsrfToken()); ?>">
        <input type="hidden" name="pid"           value="<?php echo attr((string)$pid); ?>">
        <input type="hidden" name="encounter"     value="<?php echo attr((string)$encounter); ?>">
        <input type="hidden" name="cardiology_id" value="<?php echo attr((string)$cardiologyId); ?>">
        <input type="hidden" name="study_id"      value="0">

        <?php require __DIR__ . '/_form_fields.php'; ?>

        <div class="row mt-3 mb-4">
            <div class="col-12">
                <button type="submit" name="action" value="save_draft"
                        class="btn btn-primary"
                        onclick="if (typeof top.restoreSession === 'function') { top.restoreSession(); }">
                    <?php echo xlt('Save Draft'); ?>
                </button>
                <button type="submit" name="action" value="save_final"
                        class="btn btn-success ms-2"
                        onclick="return confirm(<?php echo xlj('Finalize this certificate? It will become read-only.'); ?>)">
                    <?php echo xlt('Finalize'); ?>
                </button>
                <a href="<?php echo attr(CardiologyContainer::getFormRootUrl()
                    . '/view.php?id=' . urlencode((string)$cardiologyId)); ?>"
                   class="btn btn-secondary ms-2"
                   onclick="if (typeof top.restoreSession === 'function') { top.restoreSession(); }">
                    <?php echo xlt('Cancel'); ?>
                </a>
            </div>
        </div>
    </form>

</div>
<script src="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/cards/fitness/fitness.js?v=<?php echo attr($GLOBALS['v_js_includes']); ?>"></script>
</body>
</html>

