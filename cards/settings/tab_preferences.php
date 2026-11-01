<?php

/**
 * Cardiology Settings – Preferences tab.
 *
 * Clinic preferences are visible only to users with cardiology/settings.
 * Personal defaults are stored per OpenEMR user in card_settings.
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
use OpenEMR\Forms\Cardiology\CardSettings;

require_once(__DIR__ . '/../../lib/CardAcl.php');
require_once(__DIR__ . '/../../lib/CardSettings.php');

if (!CardAcl::canManageSettings() && !CardAcl::canUsePersonalSettings()) {
    die(xlt('Not authorized'));
}

$definitions = CardSettings::definitions();
$clinicDefinitions = [];
$personalDefinitions = [];
foreach ($definitions as $key => $definition) {
    if ((string)$definition['scope'] === CardSettings::SCOPE_CLINIC) {
        $clinicDefinitions[$key] = $definition;
    } else {
        $personalDefinitions[$key] = $definition;
    }
}

$clinicValues = [];
foreach (array_keys($clinicDefinitions) as $key) {
    $clinicValues[$key] = CardSettings::getClinic($key);
}

$personalValues = [];
foreach (array_keys($personalDefinitions) as $key) {
    $personalValues[$key] = CardSettings::getPersonal($key, $userId);
}

$disabled = $tableInstalled ? '' : ' disabled';
?>
<?php if (CardAcl::canManageSettings()) : ?>
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-building text-primary me-1" aria-hidden="true"></i>
        <?php echo xlt('Clinic Preferences'); ?>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            <?php echo xlt('These values apply to every user unless a personal preference overrides them.'); ?>
        </p>

        <form method="post"
              action="<?php echo attr($settingsApi); ?>"
              class="cardiology-settings-ajax-form row g-3"
              data-settings-action="save_settings">
            <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrfToken); ?>">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="scope" value="clinic">

            <div class="col-md-4">
                <label class="form-label" for="setting_chart_max_studies">
                    <?php echo xlt('Maximum studies in charts'); ?>
                </label>
                <input type="number"
                       class="form-control"
                       id="setting_chart_max_studies"
                       name="settings[chart_max_studies]"
                       value="<?php echo attr($clinicValues['chart_max_studies'] ?? '60'); ?>"
                       min="<?php echo attr((string)$clinicDefinitions['chart_max_studies']['min']); ?>"
                       max="<?php echo attr((string)$clinicDefinitions['chart_max_studies']['max']); ?>"
                       step="1"
                       required
                       <?php echo $disabled; ?>>
                <div class="form-text">
                    <?php echo xlt('Between 10 and 200 studies per longitudinal chart.'); ?>
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="setting_fitness_validity_months">
                    <?php echo xlt('Fitness certificate validity (months)'); ?>
                </label>
                <input type="number"
                       class="form-control"
                       id="setting_fitness_validity_months"
                       name="settings[fitness_validity_months]"
                       value="<?php echo attr($clinicValues['fitness_validity_months'] ?? '12'); ?>"
                       min="<?php echo attr((string)$clinicDefinitions['fitness_validity_months']['min']); ?>"
                       max="<?php echo attr((string)$clinicDefinitions['fitness_validity_months']['max']); ?>"
                       step="1"
                       required
                       <?php echo $disabled; ?>>
                <div class="form-text">
                    <?php echo xlt('Default expiration offset when a new fitness certificate starts.'); ?>
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="setting_pdf_footer_text">
                    <?php echo xlt('PDF footer text'); ?>
                </label>
                <textarea class="form-control"
                          id="setting_pdf_footer_text"
                          name="settings[pdf_footer_text]"
                          rows="3"
                          maxlength="500"
                          <?php echo $disabled; ?>><?php echo text($clinicValues['pdf_footer_text'] ?? ''); ?></textarea>
                <div class="form-text">
                    <?php echo xlt('Optional text printed at the bottom of every Cardiology PDF.'); ?>
                </div>
            </div>

            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary" <?php echo $disabled; ?>>
                    <i class="fa fa-save me-1" aria-hidden="true"></i>
                    <?php echo xlt('Save clinic preferences'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (CardAcl::canUsePersonalSettings()) : ?>
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-user text-primary me-1" aria-hidden="true"></i>
        <?php echo xlt('My Preferences'); ?>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            <?php echo xlt('Personal defaults are applied when you start a new study. They do not change other users.'); ?>
        </p>

        <form method="post"
              action="<?php echo attr($settingsApi); ?>"
              class="cardiology-settings-ajax-form row g-3"
              data-settings-action="save_settings">
            <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrfToken); ?>">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="scope" value="user">

            <?php foreach ($personalDefinitions as $key => $definition) :
                $currentValue = (string)($personalValues[$key] ?? '');
                $options = CardSettings::listOptions((string)$definition['list'], $currentValue);
                ?>
                <div class="col-md-6">
                    <label class="form-label" for="setting_<?php echo attr($key); ?>">
                        <?php if ($key === 'default_stress_protocol') : ?>
                            <?php echo xlt('Default stress test protocol'); ?>
                        <?php elseif ($key === 'default_lvef_method') : ?>
                            <?php echo xlt('Default LVEF measurement method'); ?>
                        <?php else : ?>
                            <?php echo text($key); ?>
                        <?php endif; ?>
                    </label>
                    <select class="form-select"
                            id="setting_<?php echo attr($key); ?>"
                            name="settings[<?php echo attr($key); ?>]"
                            <?php echo $disabled; ?>>
                        <option value=""><?php echo xlt('-- No default --'); ?></option>
                        <?php foreach ($options as $option) :
                            $optionId = (string)$option['option_id'];
                            $optionTitle = (string)xl_list_label((string)$option['title']);
                            if ((int)$option['activity'] !== 1) {
                                $optionTitle .= ' (' . xlt('Inactive') . ')';
                            }
                            ?>
                            <option value="<?php echo attr($optionId); ?>"
                                    <?php echo $optionId === $currentValue ? 'selected' : ''; ?>>
                                <?php echo text($optionTitle); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>

            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary" <?php echo $disabled; ?>>
                    <i class="fa fa-save me-1" aria-hidden="true"></i>
                    <?php echo xlt('Save my preferences'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
