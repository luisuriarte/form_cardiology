<?php

/**
 * Cardiology container – view.php
 *
 * Renders the container screen for an existing container row.
 * Functionally identical to new.php but loads existing data (reason, notes,
 * studies) and uses the container id from the URL parameter.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once('../../globals.php');
require_once($GLOBALS['srcdir'] . '/api.inc.php');
require_once($GLOBALS['srcdir'] . '/forms.inc.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Forms\Cardiology\CardRegistry;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once(__DIR__ . '/lib/CardiologyContainer.php');
require_once(__DIR__ . '/lib/CardRegistry.php');

// ---------------------------------------------------------------------------
// ACL check
// ---------------------------------------------------------------------------
if (!AclMain::aclCheckCore('patients', 'med')) {
    die(xlt('Not authorized'));
}

// ---------------------------------------------------------------------------
// Resolve container id – prefer URL param, fall back to session context
// ---------------------------------------------------------------------------
/** @var int $pid */
/** @var int $encounter */

$containerIdParam = (int)($_GET['id'] ?? 0);

if ($containerIdParam > 0) {
    $container = CardiologyContainer::getContainerById($containerIdParam);
} else {
    // Fallback: find by session pid + encounter (e.g., opened via encounter list)
    if (empty($pid) || empty($encounter)) {
        die(xlt('No active patient or encounter.'));
    }
    $container = CardiologyContainer::getContainer((int)$pid, (int)$encounter);
}

if ($container === null) {
    // Container does not exist or is deleted; open new.php instead.
    header('Location: ' . CardiologyContainer::getFormRootUrl() . '/new.php');
    exit;
}

$containerPid       = (int)$container['pid'];
$containerEncounter = (int)$container['encounter'];
$containerId        = (int)$container['id'];

// Use the container's own pid/encounter so the page is correct even when
// the session context differs (e.g., opened from a report link).
$pid       = $containerPid;
$encounter = $containerEncounter;

// ---------------------------------------------------------------------------
// Resolve visible cards + load existing studies for this encounter
// ---------------------------------------------------------------------------
$userId   = (int)($_SESSION['authUserID'] ?? 0);
$registry = new CardRegistry();
$cards    = $registry->getVisibleCards($userId);

// Load study counts and study rows per card, for cards that are installed.
$studyCounts = [];
$studies     = [];

foreach ($cards as $card) {
    $code      = (string)$card['code'];
    $tableName = (string)$card['table_name'];

    if (!$card['installed']) {
        $studyCounts[$code] = 0;
        $studies[$code]     = [];
        continue;
    }

    // Count active studies
    $countRow = sqlQuery(
        'SELECT COUNT(*) AS cnt FROM `' . escape_table_name($tableName) . '`
          WHERE cardiology_id = ? AND pid = ? AND encounter = ? AND activity = 1',
        [$containerId, $pid, $encounter]
    );
    $studyCounts[$code] = (int)($countRow['cnt'] ?? 0);

    // Fetch study list (id, study_date, status, user)
    $res = sqlStatement(
        'SELECT id, study_date, status, `user`
           FROM `' . escape_table_name($tableName) . '`
          WHERE cardiology_id = ? AND pid = ? AND encounter = ? AND activity = 1
          ORDER BY study_date ASC, id ASC',
        [$containerId, $pid, $encounter]
    );
    $studies[$code] = [];
    while ($row = sqlFetchArray($res)) {
        $studies[$code][] = $row;
    }
}

// ---------------------------------------------------------------------------
// HTML output
// ---------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Cardiology'); ?></title>
    <?php Header::setupHeader(); ?>
    <link rel="stylesheet"
          href="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/css/cardiology.css?v=<?php echo attr($GLOBALS['v_js_includes']); ?>">
</head>
<body>
<div class="container-fluid cardiology-container mt-3">

    <?php require __DIR__ . '/templates/_header.php'; ?>

    <form method="post"
          id="cardiology-container-form"
          action="<?php echo attr(CardiologyContainer::getFormRootUrl()); ?>/save.php">

        <input type="hidden" name="csrf_token_form"
               value="<?php echo attr(CardiologyContainer::collectCsrfToken()); ?>">
        <input type="hidden" name="pid"          value="<?php echo attr((string)$pid); ?>">
        <input type="hidden" name="encounter"    value="<?php echo attr((string)$encounter); ?>">
        <input type="hidden" name="cardiology_id" value="<?php echo attr((string)$containerId); ?>">

        <?php require __DIR__ . '/templates/_reason_notes.php'; ?>
        <?php require __DIR__ . '/templates/_card_grid.php'; ?>

        <div class="row mt-3 mb-4">
            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-save"
                        onclick="top.restoreSession()">
                    <?php echo xlt('Save'); ?>
                </button>
                <button type="button" class="btn btn-secondary btn-cancel"
                        onclick="top.restoreSession(); parent.closeTab(window.name, false);">
                    <?php echo xlt('Cancel'); ?>
                </button>
            </div>
        </div>
    </form>

</div><!-- .cardiology-container -->

<script src="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/js/cardiology.js?v=<?php echo attr($GLOBALS['v_js_includes']); ?>"></script>
</body>
</html>

