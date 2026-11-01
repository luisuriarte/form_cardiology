<?php

/**
 * Cardiology container – report.php
 *
 * Called by the OpenEMR encounter summary to render a read-only snapshot of
 * the cardiology studies for a given encounter.
 *
 * Signature expected by OpenEMR:
 *   <formdir>_report($pid, $encounter, $cols, $id, $print = false)
 * With formdir "form_cardiology", OpenEMR calls form_cardiology_report().
 *
 * The function must echo HTML; it must NOT call Header::setupHeader() because
 * the encounter summary page already owns the <head>.
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

use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once(__DIR__ . '/lib/CardiologyContainer.php');

if (!function_exists('form_cardiology_report')) {
    /**
     * Render the encounter-summary snippet for the Cardiology container.
     *
     * @param int  $pid       Patient id.
     * @param int  $encounter Encounter number.
     * @param int  $cols      Number of columns (provided by the encounter summary; unused here).
     * @param int  $id        form_cardiology.id (the container primary key).
     * @param bool $print     True when rendering for print (encounter summary uses this flag).
     * @return void
     */
    function form_cardiology_report(int $pid, int $encounter, int $cols, int $id, bool $print = false): void
    {
        // Fetch the container row.
        $container = CardiologyContainer::getContainerById($id);
        if ($container === null) {
            return; // Nothing to show; the container was deleted.
        }

        // Load all active card modules to know their titles and table names.
        // Generic: lists studies for EVERY card registered in card_modules dynamically.
        $moduleResult = sqlStatement(
            "SELECT code, title, table_name FROM `card_modules`
              WHERE global_active = 1 AND card_type = 'study'
              ORDER BY sort_order ASC",
            []
        );
        $modules = [];
        while ($row = sqlFetchArray($moduleResult)) {
            $modules[$row['code']] = $row;
        }

        ?>
        <div class="cardiology-report">
            <?php if (!empty($container['reason'])) : ?>
            <div class="cardiology-report-row">
                <span class="cardiology-report-label"><?php echo xlt('Reason'); ?>:</span>
                <span class="cardiology-report-value"><?php echo text($container['reason']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($container['notes'])) : ?>
            <div class="cardiology-report-row">
                <span class="cardiology-report-label"><?php echo xlt('Notes'); ?>:</span>
                <span class="cardiology-report-value"><?php echo text($container['notes']); ?></span>
            </div>
            <?php endif; ?>

            <?php foreach ($modules as $code => $module) :
                $tableName = (string)$module['table_name'];

                // Validate table name format against whitelist regex.
                if (!preg_match('/^form_card_[a-z0-9_]+$/', $tableName)) {
                    continue;
                }

                // escape_table_name() in OpenEMR returns backtick-quoted table name or empty string if not found in listTables().
                $safeTable = escape_table_name($tableName);
                if (!$safeTable || $safeTable === '``') {
                    continue;
                }

                // Fetch active studies for this card in this encounter.
                $studyResult = sqlStatement(
                    'SELECT id, study_date, status, `user`, conclusion
                       FROM ' . $safeTable . '
                      WHERE cardiology_id = ? AND pid = ? AND encounter = ? AND activity = 1
                      ORDER BY study_date ASC, id ASC',
                    [$id, $pid, $encounter]
                );

                $studyRows = [];
                while ($sRow = sqlFetchArray($studyResult)) {
                    $studyRows[] = $sRow;
                }

                if (empty($studyRows)) {
                    continue; // Skip cards with no studies for this encounter.
                }
            ?>
            <div class="cardiology-report-card mt-2">
                <strong><?php echo text(xl($module['title'])); ?></strong>
                <ul class="list-unstyled ms-3 mb-0">
                    <?php foreach ($studyRows as $study) :
                        $statusLabel = ($study['status'] === 'final')
                            ? xlt('Final')
                            : xlt('Draft');
                        $badgeClass = ($study['status'] === 'final')
                            ? 'badge bg-success'
                            : 'badge bg-warning text-dark';
                    ?>
                    <li class="cardiology-report-study mt-1">
                        <span class="<?php echo attr($badgeClass); ?>"><?php echo $statusLabel; ?></span>
                        <span class="text-secondary"><?php echo text($study['study_date'] ?? ''); ?></span>
                        <?php if (!empty($study['user'])) : ?>
                        &ndash; <span class="text-muted"><?php echo text($study['user']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($study['conclusion'])) : ?>
                        <div class="cardiology-report-conclusion text-muted small ms-2">
                            <em><?php echo xlt('Conclusion'); ?>:</em> <?php echo text($study['conclusion']); ?>
                        </div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
