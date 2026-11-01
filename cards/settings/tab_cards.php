<?php

/**
 * Cardiology Settings – Cards tab.
 *
 * Users with cardiology/settings can change global activation, sort order and
 * the clinic default. Every user with patients/med can change their own
 * activation. The Settings tool card can never be globally disabled.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Settings
 */

declare(strict_types=1);

if (!defined('CARDIOLOGY_SETTINGS')) {
    http_response_code(403);
    exit;
}

use OpenEMR\Forms\Cardiology\CardAcl;

require_once(__DIR__ . '/../../lib/CardAcl.php');

if (!CardAcl::canUsePersonalSettings() && !CardAcl::canManageSettings()) {
    die(xlt('Not authorized'));
}

$modules = [];
$res = sqlStatement(
    'SELECT * FROM `card_modules` ORDER BY sort_order ASC, title ASC',
    []
);
while ($row = sqlFetchArray($res)) {
    $modules[] = $row;
}

$configMap = [];
$res = sqlStatement(
    'SELECT module_code, user_id, active
       FROM `card_config`
      WHERE user_id IN (0, ?)',
    [$userId]
);
while ($row = sqlFetchArray($res)) {
    $configMap[(string)$row['module_code']][(int)$row['user_id']] = (int)$row['active'] === 1;
}
?>
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-th-large text-primary me-1" aria-hidden="true"></i>
        <?php echo xlt('Card Activation and Order'); ?>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            <?php echo xlt('Global activation and sort order affect every user. Clinic default applies when a user has no personal override. My activation always wins for your own account.'); ?>
            <?php if ($canManage) : ?>
                <br>
                <?php echo xlt('The Settings tool card is always globally enabled so administrators can reach this screen.'); ?>
            <?php endif; ?>
        </p>

        <form method="post"
              action="<?php echo attr($settingsApi); ?>"
              class="cardiology-settings-ajax-form"
              data-settings-action="save_cards">
            <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrfToken); ?>">
            <input type="hidden" name="action" value="save_cards">

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle cardiology-cards-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo xlt('Card'); ?></th>
                            <th scope="col"><?php echo xlt('Type'); ?></th>
                            <th scope="col"><?php echo xlt('Global active'); ?></th>
                            <th scope="col"><?php echo xlt('Sort order'); ?></th>
                            <th scope="col"><?php echo xlt('Clinic default'); ?></th>
                            <th scope="col"><?php echo xlt('My activation'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($modules as $module) :
                        $code = (string)$module['code'];
                        $title = (string)$module['title'];
                        $icon = (string)($module['icon'] ?? 'fa-stethoscope');
                        $cardType = (string)($module['card_type'] ?? 'study');
                        $isTool = $cardType === 'tool';
                        $isSettings = $code === 'settings';
                        $globalActive = (int)($module['global_active'] ?? 1) === 1;
                        $sortOrder = (int)($module['sort_order'] ?? 100);
                        $clinicActive = $configMap[$code][0] ?? true;
                        $personalActive = $configMap[$code][$userId] ?? $clinicActive;
                        ?>
                        <tr>
                            <td>
                                <i class="fa <?php echo attr($icon); ?> text-primary me-1" aria-hidden="true"></i>
                                <strong><?php echo text(xl($title)); ?></strong>
                                <code class="ms-1"><?php echo text($code); ?></code>
                            </td>
                            <td>
                                <?php if ($isTool) : ?>
                                    <span class="badge bg-info text-dark"><?php echo xlt('Tool'); ?></span>
                                <?php else : ?>
                                    <span class="badge bg-secondary"><?php echo xlt('Study'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($canManage) : ?>
                                    <?php if ($isSettings) : ?>
                                        <input type="hidden" name="global_active[<?php echo attr($code); ?>]" value="1">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" checked disabled
                                                   aria-label="<?php echo xla('Always enabled'); ?>">
                                            <span class="small text-muted"><?php echo xlt('Always enabled'); ?></span>
                                        </div>
                                    <?php else : ?>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="global_active[<?php echo attr($code); ?>]"
                                                   id="global_active_<?php echo attr($code); ?>"
                                                   value="1"
                                                   <?php echo $globalActive ? 'checked' : ''; ?>>
                                            <label class="form-check-label small"
                                                   for="global_active_<?php echo attr($code); ?>">
                                                <?php echo $globalActive ? xlt('Enabled') : xlt('Disabled'); ?>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <?php if ($globalActive) : ?>
                                        <span class="badge bg-success"><?php echo xlt('Enabled'); ?></span>
                                    <?php else : ?>
                                        <span class="badge bg-secondary"><?php echo xlt('Disabled'); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($canManage) : ?>
                                    <input type="number"
                                           class="form-control form-control-sm cardiology-sort-input"
                                           name="sort_order[<?php echo attr($code); ?>]"
                                           value="<?php echo attr((string)$sortOrder); ?>"
                                           min="0"
                                           max="9999"
                                           step="1"
                                           required
                                           aria-label="<?php echo xla('Sort order'); ?>">
                                <?php else : ?>
                                    <?php echo text((string)$sortOrder); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($canManage) : ?>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="clinic_active[<?php echo attr($code); ?>]"
                                               id="clinic_active_<?php echo attr($code); ?>"
                                               value="1"
                                               <?php echo $clinicActive ? 'checked' : ''; ?>>
                                        <label class="form-check-label small"
                                               for="clinic_active_<?php echo attr($code); ?>">
                                            <?php echo $clinicActive ? xlt('Enabled') : xlt('Disabled'); ?>
                                        </label>
                                    </div>
                                <?php else : ?>
                                    <?php if ($clinicActive) : ?>
                                        <span class="badge bg-success"><?php echo xlt('Enabled'); ?></span>
                                    <?php else : ?>
                                        <span class="badge bg-secondary"><?php echo xlt('Disabled'); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (CardAcl::canUsePersonalSettings()) : ?>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="personal_active[<?php echo attr($code); ?>]"
                                               id="personal_active_<?php echo attr($code); ?>"
                                               value="1"
                                               <?php echo $personalActive ? 'checked' : ''; ?>>
                                        <label class="form-check-label small"
                                               for="personal_active_<?php echo attr($code); ?>">
                                            <?php echo $personalActive ? xlt('Enabled') : xlt('Disabled'); ?>
                                        </label>
                                    </div>
                                <?php else : ?>
                                    <?php echo $personalActive ? xlt('Enabled') : xlt('Disabled'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save me-1" aria-hidden="true"></i>
                    <?php echo xlt('Save card configuration'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
