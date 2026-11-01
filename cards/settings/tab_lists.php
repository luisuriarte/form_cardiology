<?php

/**
 * Cardiology Settings – Lists tab.
 *
 * Only registered card_* list_options catalogs are shown. Option identifiers
 * are immutable and options are never deleted. Deactivating an option that is
 * already referenced by clinical data requires an explicit confirmation.
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
use OpenEMR\Forms\Cardiology\CardLists;

require_once(__DIR__ . '/../../lib/CardAcl.php');
require_once(__DIR__ . '/../../lib/CardLists.php');

if (!CardAcl::canEditLists()) {
    die(xlt('Not authorized'));
}

$lists = CardLists::registeredLists();
$selectedList = trim((string)($_GET['list'] ?? ''));
if (!isset($lists[$selectedList])) {
    $selectedList = $lists !== [] ? (string)array_key_first($lists) : '';
}

$options = [];
$usageCounts = [];
$maxOptions = CardLists::MAX_OPTIONS;
$columnUsage = 0;
if ($selectedList !== '') {
    $options = CardLists::options($selectedList);
    $usageCounts = CardLists::usageCounts($selectedList);
    $columnUsage = CardLists::columnUsage($selectedList);
}
?>
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-list text-primary me-1" aria-hidden="true"></i>
        <?php echo xlt('Cardiology List Catalogs'); ?>
    </div>
    <div class="card-body">
        <?php if ($lists === []) : ?>
        <div class="alert alert-warning mb-0" role="alert">
            <?php echo xlt('No registered Cardiology lists were found. Run the module table.sql file first.'); ?>
        </div>
        <?php else : ?>
        <p class="text-muted small">
            <?php echo xlt('Only lists registered in list_options with the card_ prefix can be edited here. Option identifiers never change and options are never deleted.'); ?>
        </p>

        <ul class="nav nav-pills flex-wrap gap-1 mb-3 cardiology-list-selector">
            <?php foreach ($lists as $listId => $listTitle) : ?>
            <li class="nav-item">
                <a class="nav-link btn-sm <?php echo $listId === $selectedList ? 'active' : ''; ?>"
                   href="<?php echo attr($settingsIndex . '?tab=lists&list=' . urlencode($listId)); ?>">
                    <?php echo text(xl_list_label($listTitle)); ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <h6 class="mb-0">
                <?php echo text(xl_list_label($lists[$selectedList] ?? '')); ?>
                <code class="ms-2"><?php echo text($selectedList); ?></code>
            </h6>
            <div class="text-muted small">
                <?php echo xlt('Options'); ?>: <?php echo text((string)count($options)); ?> / <?php echo text((string)$maxOptions); ?>
                &nbsp;
                <?php echo xlt('Referenced columns'); ?>: <?php echo text((string)$columnUsage); ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle cardiology-list-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo xlt('Title'); ?></th>
                        <th scope="col"><?php echo xlt('Option ID'); ?></th>
                        <th scope="col"><?php echo xlt('Default'); ?></th>
                        <th scope="col"><?php echo xlt('Active'); ?></th>
                        <th scope="col"><?php echo xlt('Usage'); ?></th>
                        <th scope="col" class="text-end"><?php echo xlt('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($options as $option) :
                    $optionId = (string)$option['option_id'];
                    $title = (string)$option['title'];
                    $translatedTitle = (string)xl_list_label($title);
                    $isDefault = (int)$option['is_default'] === 1;
                    $isActive = (int)$option['activity'] === 1;
                    $usage = (int)($usageCounts[$optionId] ?? 0);
                    $confirmMessage = sprintf(
                        xlt('This option is used in %d record(s). Deactivate it anyway?'),
                        $usage
                    );
                    ?>
                    <tr>
                        <td>
                            <form method="post"
                                  action="<?php echo attr($settingsApi); ?>"
                                  class="cardiology-settings-ajax-form"
                                  data-settings-action="list_rename">
                                <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrfToken); ?>">
                                <input type="hidden" name="action" value="list_rename">
                                <input type="hidden" name="list_id" value="<?php echo attr($selectedList); ?>">
                                <input type="hidden" name="option_id" value="<?php echo attr($optionId); ?>">
                                <div class="input-group input-group-sm">
                                    <input type="text"
                                           class="form-control"
                                           name="title"
                                           value="<?php echo attr($title); ?>"
                                           maxlength="255"
                                           required
                                           aria-label="<?php echo xla('Option title'); ?>">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <?php echo xlt('Save'); ?>
                                    </button>
                                </div>
                                <?php if ($translatedTitle !== $title) : ?>
                                <div class="form-text"><?php echo text($translatedTitle); ?></div>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td><code><?php echo text($optionId); ?></code></td>
                        <td>
                            <?php if ($isDefault) : ?>
                                <span class="badge bg-primary"><?php echo xlt('Default'); ?></span>
                            <?php elseif ($isActive) : ?>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-settings-action="list_default"
                                        data-list-id="<?php echo attr($selectedList); ?>"
                                        data-option-id="<?php echo attr($optionId); ?>">
                                    <?php echo xlt('Make default'); ?>
                                </button>
                            <?php else : ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isActive) : ?>
                                <span class="badge bg-success"><?php echo xlt('Active'); ?></span>
                            <?php else : ?>
                                <span class="badge bg-secondary"><?php echo xlt('Inactive'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($usage > 0) : ?>
                                <span class="badge bg-info text-dark" title="<?php echo xla('Records that use this option'); ?>">
                                    <?php echo text((string)$usage); ?>
                                </span>
                            <?php else : ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        data-settings-action="list_move"
                                        data-direction="up"
                                        data-list-id="<?php echo attr($selectedList); ?>"
                                        data-option-id="<?php echo attr($optionId); ?>"
                                        title="<?php echo xla('Move up'); ?>"
                                        aria-label="<?php echo xla('Move up'); ?>">
                                    <i class="fa fa-arrow-up" aria-hidden="true"></i>
                                </button>
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        data-settings-action="list_move"
                                        data-direction="down"
                                        data-list-id="<?php echo attr($selectedList); ?>"
                                        data-option-id="<?php echo attr($optionId); ?>"
                                        title="<?php echo xla('Move down'); ?>"
                                        aria-label="<?php echo xla('Move down'); ?>">
                                    <i class="fa fa-arrow-down" aria-hidden="true"></i>
                                </button>
                                <?php if ($isActive) : ?>
                                <button type="button"
                                        class="btn btn-outline-warning"
                                        data-settings-action="list_toggle"
                                        data-list-id="<?php echo attr($selectedList); ?>"
                                        data-option-id="<?php echo attr($optionId); ?>"
                                        data-active="0"
                                        data-usage="<?php echo attr((string)$usage); ?>"
                                        data-confirm="<?php echo $usage > 0 ? attr($confirmMessage) : ''; ?>"
                                        <?php echo $isDefault ? 'disabled' : ''; ?>
                                        title="<?php echo $isDefault ? xla('Set another default option before deactivating this one') : xla('Deactivate'); ?>">
                                    <?php echo xlt('Deactivate'); ?>
                                </button>
                                <?php else : ?>
                                <button type="button"
                                        class="btn btn-outline-success"
                                        data-settings-action="list_toggle"
                                        data-list-id="<?php echo attr($selectedList); ?>"
                                        data-option-id="<?php echo attr($optionId); ?>"
                                        data-active="1"
                                        data-usage="<?php echo attr((string)$usage); ?>"
                                        data-confirm="">
                                    <?php echo xlt('Activate'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (count($options) >= $maxOptions) : ?>
        <div class="alert alert-warning" role="alert">
            <?php echo xlt('This list reached the maximum of 200 options. Deactivate or reuse existing options instead.'); ?>
        </div>
        <?php else : ?>
        <form method="post"
              action="<?php echo attr($settingsApi); ?>"
              class="cardiology-settings-ajax-form row g-2 align-items-end mt-3"
              data-settings-action="list_add">
            <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrfToken); ?>">
            <input type="hidden" name="action" value="list_add">
            <input type="hidden" name="list_id" value="<?php echo attr($selectedList); ?>">
            <div class="col-md-6">
                <label class="form-label" for="cardiology-new-option-title">
                    <?php echo xlt('New option title'); ?>
                </label>
                <input type="text"
                       class="form-control"
                       id="cardiology-new-option-title"
                       name="title"
                       maxlength="255"
                       required>
                <div class="form-text">
                    <?php echo xlt('The option identifier is generated automatically and cannot be changed later.'); ?>
                </div>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-plus me-1" aria-hidden="true"></i>
                    <?php echo xlt('Add option'); ?>
                </button>
            </div>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
