<?php

/**
 * Cardiology container – config.php
 *
 * Administration screen to enable or disable cards:
 *   - Clinic-wide default  (user_id = 0  in card_config)
 *   - Per-user override    (user_id = users.id)
 *
 * Access requires admin / super ACL.
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
require_once(__DIR__ . '/lib/CardiologyContainer.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

// ---------------------------------------------------------------------------
// ACL: only admins may access this page
// ---------------------------------------------------------------------------
if (!AclMain::aclCheckCore('admin', 'super')) {
    die(xlt('Not authorized'));
}

// ---------------------------------------------------------------------------
// Handle POST – update card_config rows
// ---------------------------------------------------------------------------
$saved   = false;
$saveErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CardiologyContainer::verifyCsrfToken()) {
        CsrfUtils::csrfNotVerified();
    }

    $scope  = $_POST['scope'] ?? 'clinic';    // 'clinic' or 'user'
    $userId = (int)($_POST['config_user_id'] ?? 0);

    // scope = 'clinic'  → user_id = 0
    // scope = 'user'    → user_id = selected user
    $configUserId = ($scope === 'user') ? $userId : 0;

    // Read all module codes from card_modules.
    $allCodes = [];
    $res = sqlStatement('SELECT code FROM `card_modules`', []);
    while ($row = sqlFetchArray($res)) {
        $allCodes[] = $row['code'];
    }

    // For each code, the checkbox being present in POST means "active".
    foreach ($allCodes as $code) {
        $active = isset($_POST['active_' . $code]) ? 1 : 0;

        // Upsert: update if exists, insert if not.
        $existing = sqlQuery(
            'SELECT id FROM `card_config` WHERE module_code = ? AND user_id = ?',
            [$code, $configUserId]
        );

        if ($existing && isset($existing['id'])) {
            sqlStatement(
                'UPDATE `card_config` SET active = ? WHERE id = ?',
                [$active, (int)$existing['id']]
            );
        } else {
            sqlStatement(
                'INSERT INTO `card_config` (module_code, user_id, active) VALUES (?, ?, ?)',
                [$code, $configUserId, $active]
            );
        }
    }

    $saved = true;
}

// ---------------------------------------------------------------------------
// Load data for the form
// ---------------------------------------------------------------------------

// All card modules.
$modules = [];
$res = sqlStatement('SELECT * FROM `card_modules` ORDER BY sort_order ASC', []);
while ($row = sqlFetchArray($res)) {
    $modules[] = $row;
}

// All active users (for per-user configuration).
$users = [];
$res = sqlStatement(
    "SELECT id, username, fname, lname
       FROM `users`
      WHERE active = 1 AND username != ''
      ORDER BY lname ASC, fname ASC",
    []
);
while ($row = sqlFetchArray($res)) {
    $users[] = $row;
}

// Current scope selection (kept across the page after save).
$currentScope  = $_POST['scope'] ?? 'clinic';
$currentUserId = (int)($_POST['config_user_id'] ?? 0);

/**
 * Load card_config rows for a given user_id into a map: code → bool(active).
 *
 * @param  int $userId
 * @return array<string,bool>
 */
function loadConfigForUser(int $userId): array
{
    $map = [];
    $res = sqlStatement(
        'SELECT module_code, active FROM `card_config` WHERE user_id = ?',
        [$userId]
    );
    while ($row = sqlFetchArray($res)) {
        $map[$row['module_code']] = (bool)(int)$row['active'];
    }
    return $map;
}

$configUserId = ($currentScope === 'user') ? $currentUserId : 0;
$configMap    = loadConfigForUser($configUserId);

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Cardiology Card Configuration'); ?></title>
    <?php Header::setupHeader(); ?>
    <link rel="stylesheet"
          href="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/css/cardiology.css?v=<?php echo attr($GLOBALS['v_js_includes']); ?>">
</head>
<body>
<div class="container mt-4">
    <h3>
        <i class="fa fa-cog me-2" aria-hidden="true"></i>
        <?php echo xlt('Cardiology – Card Configuration'); ?>
    </h3>
    <p class="text-muted">
        <?php echo xlt('Enable or disable individual cardiology cards for the entire clinic or per user.'); ?>
    </p>

    <?php if ($saved) : ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo xlt('Settings saved successfully.'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo xla('Close'); ?>"></button>
    </div>
    <?php endif; ?>

    <!-- Scope selector: reload page to switch between clinic and per-user -->
    <form method="get" class="mb-3 row g-2 align-items-end" id="scope-selector-form">
        <div class="col-auto">
            <label for="scope_select" class="col-form-label">
                <?php echo xlt('Configure for'); ?>:
            </label>
        </div>
        <div class="col-auto">
            <select id="scope_select" name="scope" class="form-select form-select-sm"
                    onchange="document.getElementById('scope-selector-form').submit();">
                <option value="clinic" <?php echo ($currentScope === 'clinic') ? 'selected' : ''; ?>>
                    <?php echo xlt('Clinic default'); ?>
                </option>
                <option value="user" <?php echo ($currentScope === 'user') ? 'selected' : ''; ?>>
                    <?php echo xlt('Specific user'); ?>
                </option>
            </select>
        </div>
        <?php if ($currentScope === 'user') : ?>
        <div class="col-auto">
            <select name="config_user_id" class="form-select form-select-sm"
                    onchange="this.form.submit();">
                <option value="0"><?php echo xlt('-- Select user --'); ?></option>
                <?php foreach ($users as $u) : ?>
                <option value="<?php echo attr((string)$u['id']); ?>"
                    <?php echo ($currentUserId === (int)$u['id']) ? 'selected' : ''; ?>>
                    <?php echo text($u['lname'] . ', ' . $u['fname']
                        . ' (' . $u['username'] . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </form>

    <!-- Main configuration form -->
    <form method="post" id="cardiology-config-form">
        <input type="hidden" name="csrf_token_form"
               value="<?php echo attr(CardiologyContainer::collectCsrfToken()); ?>">
        <input type="hidden" name="scope"
               value="<?php echo attr($currentScope); ?>">
        <input type="hidden" name="config_user_id"
               value="<?php echo attr((string)$currentUserId); ?>">

        <div class="card">
            <div class="card-header">
                <?php if ($currentScope === 'clinic') : ?>
                <strong><?php echo xlt('Clinic-wide defaults'); ?></strong>
                <span class="text-muted small ms-2">
                    (<?php echo xlt('user_id = 0'); ?>)
                </span>
                <?php else : ?>
                <strong><?php echo xlt('Per-user overrides'); ?></strong>
                <?php if ($currentUserId > 0) :
                    // Find user name
                    $selUser = array_values(array_filter($users, fn($u) => (int)$u['id'] === $currentUserId))[0] ?? null;
                    if ($selUser) : ?>
                    <span class="text-muted small ms-2">
                        (<?php echo text($selUser['lname'] . ', ' . $selUser['fname']
                            . ' – ' . $selUser['username']); ?>)
                    </span>
                    <?php endif;
                endif; ?>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($modules)) : ?>
                <p class="text-muted"><?php echo xlt('No card modules found in card_modules table.'); ?></p>
                <?php else : ?>
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th><?php echo xlt('Card'); ?></th>
                            <th><?php echo xlt('Code'); ?></th>
                            <th><?php echo xlt('Global Active'); ?></th>
                            <th class="text-center"><?php echo xlt('Active (this scope)'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($modules as $mod) :
                        $code      = (string)$mod['code'];
                        $title     = xl((string)$mod['title']);
                        $globalOn  = (bool)(int)$mod['global_active'];
                        // If no config row exists for this scope, the card is active by default.
                        $scopeActive = $configMap[$code] ?? true;
                    ?>
                    <tr>
                        <td>
                            <?php if (!empty($mod['icon'])) : ?>
                            <i class="fa <?php echo attr($mod['icon']); ?> me-1" aria-hidden="true"></i>
                            <?php endif; ?>
                            <?php echo text($title); ?>
                        </td>
                        <td><code><?php echo text($code); ?></code></td>
                        <td>
                            <?php if ($globalOn) : ?>
                            <span class="badge bg-success"><?php echo xlt('Yes'); ?></span>
                            <?php else : ?>
                            <span class="badge bg-secondary"><?php echo xlt('No'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="active_<?php echo attr($code); ?>"
                                       name="active_<?php echo attr($code); ?>"
                                       value="1"
                                       <?php echo $scopeActive ? 'checked' : ''; ?>
                                       <?php echo !$globalOn ? 'disabled title="' . xla('Card is globally disabled') . '"' : ''; ?>>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save me-1" aria-hidden="true"></i>
                        <?php echo xlt('Save'); ?>
                    </button>
                    <a href="<?php echo attr($GLOBALS['webroot']); ?>/interface/main/main_screen.php"
                       class="btn btn-secondary ms-2">
                        <?php echo xlt('Cancel'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>

</div><!-- .container -->

<script src="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/js/cardiology.js?v=<?php echo attr($GLOBALS['v_js_includes']); ?>"></script>
</body>
</html>

