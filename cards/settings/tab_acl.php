<?php

/**
 * Cardiology Settings – ACL tab.
 *
 * Shows the state of the two custom cardiology ACOs and allows an OpenEMR
 * administrator to install or repair the default group grants.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Settings
 */

declare(strict_types=1);

if (!defined('CARDIOLOGY_SETTINGS')) {
    http_response_code(403);
    exit;
}

if (!AclMain::aclCheckCore('admin', 'super')) {
    die(xlt('Not authorized'));
}

$aclInstalled = CardAcl::isAclInstalled();
$aclStatus = CardAcl::status();
$acoLabels = [
    CardAcl::ACO_LISTS => xlt('List catalogs'),
    CardAcl::ACO_SETTINGS => xlt('Clinic settings and card configuration'),
];
?>
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-lock text-primary me-1" aria-hidden="true"></i>
        <?php echo xlt('Cardiology ACL'); ?>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            <?php echo xlt('These custom phpGACL objects control access to list management and clinic settings. OpenEMR administrators always pass these checks.'); ?>
        </p>

        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle cardiology-acl-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo xlt('ACO'); ?></th>
                        <th scope="col"><?php echo xlt('Purpose'); ?></th>
                        <th scope="col"><?php echo xlt('Status'); ?></th>
                        <th scope="col"><?php echo xlt('Default groups'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($acoLabels as $acoValue => $acoLabel) :
                    $acoExists = !empty($aclStatus['acos'][$acoValue]);
                    ?>
                    <tr>
                        <td><code>cardiology/<?php echo text($acoValue); ?></code></td>
                        <td><?php echo text($acoLabel); ?></td>
                        <td>
                            <?php if ($acoExists) : ?>
                                <span class="badge bg-success"><?php echo xlt('Installed'); ?></span>
                            <?php else : ?>
                                <span class="badge bg-danger"><?php echo xlt('Not installed'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php foreach (CardAcl::defaultGroups($acoValue) as $groupName) :
                                $granted = !empty($aclStatus['groups'][$acoValue][$groupName]);
                                ?>
                                <span class="badge <?php echo $granted ? 'bg-success' : 'bg-secondary'; ?> me-1">
                                    <?php echo text($groupName); ?>
                                    <?php echo $granted ? '✓' : '×'; ?>
                                </span>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($aclInstalled) : ?>
        <div class="alert alert-success mb-3" role="alert">
            <?php echo xlt('The Cardiology ACL objects are installed. Re-running the installer repairs missing default group grants.'); ?>
        </div>
        <?php else : ?>
        <div class="alert alert-warning mb-3" role="alert">
            <?php echo xlt('The Cardiology ACL objects are not fully installed. Install them to let non-admin users manage lists or settings.'); ?>
        </div>
        <?php endif; ?>

        <form method="post"
              action="<?php echo attr($settingsIndex); ?>/acl_install.php"
              class="d-flex justify-content-end">
            <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrfToken); ?>">
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-shield-halved me-1" aria-hidden="true"></i>
                <?php echo xlt('Install or repair Cardiology ACL'); ?>
            </button>
        </form>
    </div>
</div>
