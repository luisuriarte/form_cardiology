<?php

/**
 * Cardiology container – new.php
 *
 * Entry point when the encounter form menu opens this form for the first time.
 * If an active container already exists for (pid, encounter), redirects to
 * view.php to avoid creating a duplicate.
 *
 * The container row itself is NOT created here; it is created lazily by
 * CardiologyContainer::ensureContainer() when the user saves reason/notes
 * or when a child card is first created (save.php).
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

// ---------------------------------------------------------------------------
// Bootstrap the lib classes (PSR-4 autoload is not guaranteed in all OE
// versions when the form lives outside the main src/ tree, so we include
// them explicitly).
// ---------------------------------------------------------------------------
require_once(__DIR__ . '/lib/CardiologyContainer.php');
require_once(__DIR__ . '/lib/CardRegistry.php');

// ---------------------------------------------------------------------------
// ACL check – require patients / med (read at minimum)
// ---------------------------------------------------------------------------
if (!AclMain::aclCheckCore('patients', 'med')) {
    die(xlt('Not authorized'));
}

// ---------------------------------------------------------------------------
// Session context – $pid and $encounter come from globals.php
// ---------------------------------------------------------------------------
/** @var int $pid */
/** @var int $encounter */

if (empty($pid) || empty($encounter)) {
    die(xlt('No active patient or encounter.'));
}

// ---------------------------------------------------------------------------
// Redirect to view.php if a container already exists for this encounter
// ---------------------------------------------------------------------------
$existingContainer = CardiologyContainer::getContainer((int)$pid, (int)$encounter);
if ($existingContainer !== null) {
    // Use a server-side redirect before any output.
    $viewUrl = $GLOBALS['rootdir']
        . '/forms/cardiology/view.php?id='
        . urlencode((string)$existingContainer['id']);
    header('Location: ' . $viewUrl);
    exit;
}

// ---------------------------------------------------------------------------
// Resolve visible cards for the current user
// ---------------------------------------------------------------------------
$userId   = (int)($_SESSION['authUserID'] ?? 0);
$registry = new CardRegistry();
$cards    = $registry->getVisibleCards($userId);

// ---------------------------------------------------------------------------
// Load active studies per card for this encounter (all zero because no
// container exists yet – included for template symmetry with view.php)
// ---------------------------------------------------------------------------
// $studyCounts is keyed by card code; starts empty for a new container.
$studyCounts = [];
$studies     = [];   // keyed by card code → list of study rows

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
          href="<?php echo attr($GLOBALS['webroot']); ?>/interface/forms/cardiology/css/cardiology.css?v=<?php echo attr($GLOBALS['v_js_includes']); ?>">
</head>
<body>
<div class="container-fluid cardiology-container mt-3">

    <?php require __DIR__ . '/templates/_header.php'; ?>

    <form method="post"
          id="cardiology-container-form"
          action="<?php echo attr($GLOBALS['rootdir']); ?>/forms/cardiology/save.php">

        <input type="hidden" name="csrf_token_form"
               value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>">
        <input type="hidden" name="pid"       value="<?php echo attr((string)$pid); ?>">
        <input type="hidden" name="encounter" value="<?php echo attr((string)$encounter); ?>">
        <input type="hidden" name="cardiology_id" value=""><!-- filled on first save -->

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

<script src="<?php echo attr($GLOBALS['webroot']); ?>/interface/forms/cardiology/js/cardiology.js?v=<?php echo attr($GLOBALS['v_js_includes']); ?>"></script>
</body>
</html>

