<?php

/**
 * Cardiology Settings – main screen.
 *
 * Opens from the Settings tool card inside an OpenEMR dialog iframe. Tabs are
 * gated by the Cardiology custom ACOs. Every POST is handled by api.php with
 * CSRF and server-side permission checks.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Settings
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once('../../../../globals.php');
require_once($GLOBALS['srcdir'] . '/api.inc.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Forms\Cardiology\CardAcl;
use OpenEMR\Forms\Cardiology\CardiologyContainer;
use OpenEMR\Forms\Cardiology\CardSettings;

require_once(__DIR__ . '/../../lib/CardiologyContainer.php');
require_once(__DIR__ . '/../../lib/CardAcl.php');
require_once(__DIR__ . '/../../lib/CardSettings.php');

if (!AclMain::aclCheckCore('patients', 'med') && !AclMain::aclCheckCore('encounters', 'coding') && !AclMain::aclCheckCore('admin', 'super')) {
    die(xlt('Not authorized'));
}

$userId        = (int)($_SESSION['authUserID'] ?? 0);
$canManage     = CardAcl::canManageSettings();
$canLists      = CardAcl::canEditLists();
$canPersonal   = CardAcl::canUsePersonalSettings();
$canAdmin      = AclMain::aclCheckCore('admin', 'super');
$csrfToken     = CardiologyContainer::collectCsrfToken();
$formRoot      = CardiologyContainer::getFormRootUrl();
$assetRoot     = CardiologyContainer::getWebRootUrl();
$settingsIndex = $formRoot . '/cards/settings/index.php';
$settingsApi   = $formRoot . '/cards/settings/api.php';

$tabs = [];
if ($canLists) {
    $tabs['lists'] = ['label' => xlt('Lists'), 'icon' => 'fa-list'];
}
if ($canManage || $canPersonal) {
    $tabs['cards'] = ['label' => xlt('Cards'), 'icon' => 'fa-th-large'];
    $tabs['preferences'] = ['label' => xlt('Preferences'), 'icon' => 'fa-sliders'];
}
if ($canAdmin) {
    $tabs['acl'] = ['label' => xlt('ACL'), 'icon' => 'fa-lock'];
}

if (empty($tabs)) {
    die(xlt('Not authorized'));
}

$requestedTab = trim((string)($_GET['tab'] ?? ''));
$tab = isset($tabs[$requestedTab]) ? $requestedTab : (string)array_key_first($tabs);

$message = '';
$messageClass = 'success';
$messageMap = [
    'list_added' => xlt('List option added.'),
    'list_renamed' => xlt('List option renamed.'),
    'list_moved' => xlt('List option moved.'),
    'list_default' => xlt('Default option updated.'),
    'list_updated' => xlt('List option updated.'),
    'settings_saved' => xlt('Settings saved successfully.'),
    'cards_saved' => xlt('Card configuration saved successfully.'),
];
$messageKey = trim((string)($_GET['msg'] ?? ''));
if (isset($messageMap[$messageKey])) {
    $message = $messageMap[$messageKey];
}

$aclResult = trim((string)($_GET['acl'] ?? ''));
if ($aclResult === 'ok') {
    $message = xlt('Cardiology ACL objects are installed.');
} elseif ($aclResult === 'partial') {
    $message = xlt('Cardiology ACL objects were installed, but some default groups were not found.');
    $messageClass = 'warning';
} elseif ($aclResult === 'failed') {
    $message = xlt('The Cardiology ACL objects could not be installed.');
    $messageClass = 'danger';
}

$aclInstalled = CardAcl::isAclInstalled();
$tableInstalled = CardSettings::isInstalled();

$tabFiles = [
    'lists' => __DIR__ . '/tab_lists.php',
    'cards' => __DIR__ . '/tab_cards.php',
    'preferences' => __DIR__ . '/tab_preferences.php',
    'acl' => __DIR__ . '/tab_acl.php',
];

define('CARDIOLOGY_SETTINGS', true);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Cardiology Settings'); ?></title>
    <?php Header::setupHeader(); ?>
    <link rel="stylesheet"
          href="<?php echo attr($assetRoot); ?>/css/cardiology.css?v=<?php echo attr($GLOBALS['v_js_includes']); ?>">
</head>
<body class="cardiology-settings-page"
      data-settings-api="<?php echo attr($settingsApi); ?>"
      data-settings-csrf="<?php echo attr($csrfToken); ?>"
      data-settings-index="<?php echo attr($settingsIndex); ?>">
<div class="container-fluid mt-3">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-2">
        <div>
            <h4 class="mb-1">
                <i class="fa fa-cog text-primary me-2" aria-hidden="true"></i>
                <?php echo xlt('Cardiology Settings'); ?>
            </h4>
            <p class="text-muted small mb-0">
                <?php echo xlt('Manage list catalogs, card activation and preferences for the Cardiology module.'); ?>
            </p>
        </div>
    </div>

    <?php if ($message !== '') : ?>
    <div class="alert alert-<?php echo attr($messageClass); ?> alert-dismissible fade show" role="alert">
        <?php echo text($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"
                aria-label="<?php echo xla('Close'); ?>"></button>
    </div>
    <?php endif; ?>

    <?php if (!$tableInstalled) : ?>
    <div class="alert alert-warning" role="alert">
        <i class="fa fa-exclamation-triangle me-1" aria-hidden="true"></i>
        <?php echo xlt('The card_settings table is not installed. Run sql/upgrade_002_settings.sql before saving preferences.'); ?>
    </div>
    <?php endif; ?>

    <?php if (!$aclInstalled) : ?>
    <div class="alert alert-info" role="alert">
        <i class="fa fa-info-circle me-1" aria-hidden="true"></i>
        <?php if ($canAdmin) : ?>
            <?php echo xlt('The custom Cardiology ACL objects are not installed. Use the ACL tab to install them.'); ?>
        <?php else : ?>
            <?php echo xlt('The custom Cardiology ACL objects are not installed. Ask an administrator to install them.'); ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <ul class="nav nav-tabs cardiology-settings-tabs mb-3" role="tablist">
        <?php foreach ($tabs as $tabKey => $tabInfo) : ?>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo $tab === $tabKey ? 'active' : ''; ?>"
               href="<?php echo attr($settingsIndex . '?tab=' . urlencode($tabKey)); ?>">
                <i class="fa <?php echo attr($tabInfo['icon']); ?> me-1" aria-hidden="true"></i>
                <?php echo text($tabInfo['label']); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="cardiology-settings-panel">
        <?php
        if (isset($tabFiles[$tab]) && file_exists($tabFiles[$tab])) {
            require $tabFiles[$tab];
        }
        ?>
    </div>
</div>
<script src="<?php echo attr($assetRoot); ?>/js/cardiology-settings.js?v=<?php echo attr($GLOBALS['v_js_includes']); ?>"></script>
</body>
</html>
