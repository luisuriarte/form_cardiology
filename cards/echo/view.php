<?php

/**
 * Echo card – view.php
 *
 * Renders an existing Doppler Echocardiogram study.
 * If the study status is 'final', all fields are rendered read-only.
 *
 * GET parameters:
 *   pid           int  Patient id
 *   encounter     int  Encounter number
 *   cardiology_id int  form_cardiology.id (parent container)
 *   id            int  form_card_echo.id  (study primary key)
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Echo
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

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
/** @var int $pid */
/** @var int $encounter */
$cardiologyId = (int)($_GET['cardiology_id'] ?? 0);
$studyId      = (int)($_GET['id']            ?? 0);
$pid          = (int)($_GET['pid']           ?? $pid);
$encounter    = (int)($_GET['encounter']     ?? $encounter);

if ($cardiologyId <= 0 || $pid <= 0 || $encounter <= 0) {
    die(xlt('Missing required parameters.'));
}

// Verify parent container is active
$container = CardiologyContainer::getContainerById($cardiologyId);
if ($container === null) {
    die(xlt('The cardiology record is not available or has been deleted.'));
}

// ---------------------------------------------------------------------------
// Load study (redirect to new.php when not found)
// ---------------------------------------------------------------------------
$study = null;
if ($studyId > 0) {
    $study = sqlQuery(
        'SELECT * FROM `form_card_echo`
          WHERE id = ? AND cardiology_id = ? AND pid = ? AND encounter = ? AND activity = 1
          LIMIT 1',
        [$studyId, $cardiologyId, $pid, $encounter]
    );
    if (empty($study)) {
        $study = null;
    }
}

if ($study === null) {
    // Study not found – redirect to create a new one.
    $newUrl = $GLOBALS['rootdir']
        . '/forms/cardiology/cards/echo/new.php?'
        . http_build_query([
            'pid'           => $pid,
            'encounter'     => $encounter,
            'cardiology_id' => $cardiologyId,
        ]);
    header('Location: ' . $newUrl);
    exit;
}

// ---------------------------------------------------------------------------
// Determine read-only mode
// ---------------------------------------------------------------------------
$readOnly = ((string)($study['status'] ?? '') === 'final');

// ---------------------------------------------------------------------------
// Load list options
// ---------------------------------------------------------------------------
if (!function_exists('echoGetListOptions')) {
    function echoGetListOptions(string $listId): array
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
}

$valveGrades    = echoGetListOptions('card_valve_grade');
$wallMotions    = echoGetListOptions('card_wall_motion');
$effusionGrades = echoGetListOptions('card_effusion_grade');
$efMethods      = echoGetListOptions('card_ef_method');

// ---------------------------------------------------------------------------
// Resolve finalization metadata for display
// ---------------------------------------------------------------------------
$finalizedAt  = $study['finalized_at']  ?? '';
$finalizedBy  = $study['finalized_by']  ?? '';
$studyStatus  = (string)($study['status'] ?? 'draft');
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Echocardiogram – View Study'); ?></title>
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
                    <i class="fa fa-heartbeat text-danger me-2" aria-hidden="true"></i>
                    <?php echo xlt('Doppler Echocardiogram'); ?>
                    <?php if ($readOnly): ?>
                        <span class="badge bg-secondary ms-2"><?php echo xlt('Final'); ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark ms-2"><?php echo xlt('Draft'); ?></span>
                    <?php endif; ?>
                </h4>
                <div class="text-muted small">
                    <?php echo xlt('Encounter'); ?>: <?php echo text((string)$encounter); ?>
                    <?php if ($readOnly && $finalizedAt !== ''): ?>
                        &mdash;
                        <?php echo xlt('Finalized'); ?>:
                        <?php echo text($finalizedAt); ?>
                        <?php if ($finalizedBy !== ''): ?>
                            (<?php echo text($finalizedBy); ?>)
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <hr class="mt-2 mb-0">
        </div>
    </div>

    <?php if ($readOnly): ?>
        <!-- Read-only: no form wrapper needed, but we still use one for the hidden fields -->
        <form id="echo-form">
    <?php else: ?>
        <form method="post"
              id="echo-form"
              action="<?php echo attr($GLOBALS['rootdir']); ?>/forms/cardiology/cards/echo/save.php">

            <input type="hidden" name="csrf_token_form"
                   value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>">
            <input type="hidden" name="pid"           value="<?php echo attr((string)$pid); ?>">
            <input type="hidden" name="encounter"     value="<?php echo attr((string)$encounter); ?>">
            <input type="hidden" name="cardiology_id" value="<?php echo attr((string)$cardiologyId); ?>">
            <input type="hidden" name="study_id"      value="<?php echo attr((string)$studyId); ?>">
    <?php endif; ?>

        <?php require __DIR__ . '/_form_fields.php'; ?>

        <div class="row mt-3 mb-4">
            <div class="col-12">
                <?php if ($readOnly): ?>
                    <!-- Final study: print + back only -->
                    <a href="<?php echo attr($GLOBALS['rootdir']); ?>/forms/cardiology/cards/echo/print.php?<?php
                        echo attr_url(http_build_query([
                            'pid'           => $pid,
                            'encounter'     => $encounter,
                            'cardiology_id' => $cardiologyId,
                            'id'            => $studyId,
                        ]));
                    ?>"
                       class="btn btn-outline-secondary"
                       target="_blank"
                       onclick="top.restoreSession()">
                        <i class="fa fa-print me-1" aria-hidden="true"></i>
                        <?php echo xlt('Print'); ?>
                    </a>
                    <a href="<?php echo attr($GLOBALS['rootdir']); ?>/forms/cardiology/view.php?id=<?php echo attr_url((string)$cardiologyId); ?>"
                       class="btn btn-secondary ms-2"
                       onclick="top.restoreSession()">
                        <?php echo xlt('Back'); ?>
                    </a>
                <?php else: ?>
                    <!-- Draft: full action buttons -->
                    <button type="submit" name="action" value="save_draft"
                            class="btn btn-primary"
                            onclick="top.restoreSession()">
                        <?php echo xlt('Save Draft'); ?>
                    </button>
                    <button type="submit" name="action" value="save_final"
                            class="btn btn-success ms-2"
                            onclick="if(!confirm(<?php echo xlj('Finalize this study? It will become read-only.'); ?>)){return false;} top.restoreSession();">
                        <?php echo xlt('Finalize'); ?>
                    </button>
                    <a href="<?php echo attr($GLOBALS['rootdir']); ?>/forms/cardiology/view.php?id=<?php echo attr_url((string)$cardiologyId); ?>"
                       class="btn btn-secondary ms-2"
                       onclick="top.restoreSession()">
                        <?php echo xlt('Cancel'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

    </form>

</div><!-- .cardiology-container -->

<?php if (!$readOnly): ?>
<script src="<?php echo attr($GLOBALS['webroot']); ?>/interface/forms/cardiology/cards/echo/echo.js?v=<?php echo attr($GLOBALS['v_js_includes']); ?>"></script>
<?php endif; ?>
</body>
</html>
