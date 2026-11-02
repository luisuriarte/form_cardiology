<?php

/**
 * Anticoagulation card – new.php
 *
 * Renders a blank anticoagulation study form for the current encounter.
 * Non-identifying clinical context is pre-filled from the latest final study
 * of the same patient when one exists.
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

function anticoagLoadList(string $listId): array
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

$indications = anticoagLoadList('card_anticoag_indication');
$drugs       = anticoagLoadList('card_anticoag_drug');
$sampleTypes = anticoagLoadList('card_inr_sample');
$adherences  = anticoagLoadList('card_adherence');

// Pre-fill from the latest final study of the same patient.
$previous = sqlQuery(
    "SELECT indication, drug, sample_type, target_inr_low, target_inr_high, new_weekly_dose_mg
       FROM `form_card_anticoag`
      WHERE pid = ? AND status = 'final' AND activity = 1
      ORDER BY study_date DESC, id DESC
      LIMIT 1",
    [$pid]
);

$study = null;
if (!empty($previous)) {
    $study = [
        'indication'             => $previous['indication'] ?? null,
        'drug'                   => $previous['drug'] ?? null,
        'sample_type'            => $previous['sample_type'] ?? null,
        'target_inr_low'         => $previous['target_inr_low'] ?? null,
        'target_inr_high'        => $previous['target_inr_high'] ?? null,
        'current_weekly_dose_mg' => $previous['new_weekly_dose_mg'] ?? null,
    ];
}

$readOnly = false;
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Anticoagulation Monitoring – New Study'); ?></title>
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
                    <i class="fa fa-tint text-danger me-2" aria-hidden="true"></i>
                    <?php echo xlt('Anticoagulation Monitoring'); ?>
                </h4>
                <div class="text-muted small">
                    <?php echo xlt('New Study'); ?> &mdash;
                    <?php echo xlt('Encounter'); ?>: <?php echo text((string)$encounter); ?>
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
                        onclick="return confirm(<?php echo xlj('Finalize this study? It will become read-only.'); ?>)">
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
</body>
</html>
