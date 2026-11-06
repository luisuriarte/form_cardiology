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

/**
 * Emits the CSS and JS for the hover popup that shows a card's studies on the
 * encounter summary. The popup payload travels in data attributes / a JSON
 * script block per card so several cards share one script.
 */
function cardiology_report_assets(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <style>
        .cardiology-report-hover-title:hover {
            background-color: #eef5fb;
        }
        .cardiology-report-popup {
            position: fixed;
            z-index: 1080;
            display: none;
            pointer-events: none;
            background: #ffffff;
            border: 1px solid #b0bec5;
            border-radius: 8px;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.28);
            padding: 10px;
            max-width: 420px;
        }
        .cardiology-report-popup-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: #0d47a1;
            margin-bottom: 4px;
        }
        .cardiology-report-popup-row {
            font-size: 0.8rem;
            margin-top: 3px;
            line-height: 1.35;
        }
        .cardiology-report-popup-row b {
            color: #455a64;
        }
    </style>
    <script>
        (function () {
            'use strict';
            if (typeof window.cardiologyReportPopupInit === 'function') {
                return;
            }
            window.cardiologyReportPopupInit = function (root) {
                root = root || document;
                root.querySelectorAll('.cardiology-report-popup').forEach(function (popup) {
                    if (popup.parentNode !== document.body) {
                        document.body.appendChild(popup);
                    }
                    popup.style.display = 'none';
                });

                function render(popup, data) {
                    const body = popup.querySelector('.cardiology-report-popup-body');
                    body.textContent = '';
                    const title = document.createElement('div');
                    title.className = 'cardiology-report-popup-title';
                    title.textContent = data.title;
                    body.appendChild(title);
                    (data.studies || []).forEach(function (s) {
                        const line = document.createElement('div');
                        line.className = 'cardiology-report-popup-row';
                        const b = document.createElement('b');
                        b.textContent = s.date + ' — ' + s.status + ': ';
                        line.appendChild(b);
                        line.appendChild(document.createTextNode(s.conclusion || ''));
                        body.appendChild(line);
                    });
                }

                function place(popup, evt) {
                    const margin = 16;
                    const rect = popup.getBoundingClientRect();
                    let left = evt.clientX + margin;
                    let top = evt.clientY + margin;
                    if (left + rect.width > window.innerWidth - margin) {
                        left = window.innerWidth - rect.width - margin;
                    }
                    if (left < margin) {
                        left = margin;
                    }
                    if (top + rect.height > window.innerHeight - margin) {
                        top = evt.clientY - rect.height - margin;
                    }
                    if (top < margin) {
                        top = margin;
                    }
                    popup.style.left = left + 'px';
                    popup.style.top = top + 'px';
                }

                document.addEventListener('mouseover', function (evt) {
                    if (!evt.target || typeof evt.target.closest !== 'function') {
                        return;
                    }
                    const el = evt.target.closest('[data-cardiology-report]');
                    if (!el) {
                        return;
                    }
                    const popup = document.querySelector(
                        '.cardiology-report-popup[data-cardiology-report="' + el.getAttribute('data-cardiology-report') + '"]'
                    );
                    if (!popup) {
                        return;
                    }
                    let data = null;
                    try {
                        data = JSON.parse(popup.getAttribute('data-cardiology-studies') || '{}');
                    } catch (e) {
                        data = null;
                    }
                    if (!data) {
                        return;
                    }
                    popup.style.display = 'block';
                    popup.setAttribute('aria-hidden', 'false');
                    render(popup, data);
                    place(popup, evt);
                });
                document.addEventListener('mousemove', function (evt) {
                    const open = document.querySelector('.cardiology-report-popup[aria-hidden="false"]');
                    if (open) {
                        place(open, evt);
                    }
                });
                document.addEventListener('mouseout', function (evt) {
                    if (!evt.target || typeof evt.target.closest !== 'function') {
                        return;
                    }
                    const el = evt.target.closest('[data-cardiology-report]');
                    if (!el) {
                        return;
                    }
                    const to = evt.relatedTarget;
                    if (to && el.contains(to)) {
                        return;
                    }
                    document.querySelectorAll('.cardiology-report-popup').forEach(function (popup) {
                        popup.style.display = 'none';
                        popup.setAttribute('aria-hidden', 'true');
                    });
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' || e.key === 'Esc') {
                        document.querySelectorAll('.cardiology-report-popup').forEach(function (popup) {
                            popup.style.display = 'none';
                            popup.setAttribute('aria-hidden', 'true');
                        });
                    }
                });
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () { window.cardiologyReportPopupInit(document); });
            } else {
                window.cardiologyReportPopupInit(document);
            }
        })();
    </script>
    <?php
}

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

        $cardsWithStudies = [];
        foreach ($modules as $code => $module) {
            $tableName = (string)$module['table_name'];
            if (!preg_match('/^form_card_[a-z0-9_]+$/', $tableName)) {
                continue;
            }
            $safeTable = escape_table_name($tableName);
            if (!$safeTable || $safeTable === '``') {
                continue;
            }
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
                continue;
            }
            $cardsWithStudies[$code] = [
                'title' => xl($module['title']),
                'icon' => (string)($module['icon'] ?? 'fa-stethoscope'),
                'studies' => $studyRows,
            ];
        }

        if (empty($cardsWithStudies)) {
            return;
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

            <?php
            require_once(__DIR__ . '/lib/CardDate.php');
            require_once(__DIR__ . '/lib/CardPrintReport.php');
            ?>
            <div class="row">
            <?php foreach ($cardsWithStudies as $code => $cardInfo) :
                // Payload for the hover popup (studies of this card).
                $payloadStudies = [];
                foreach ($cardInfo['studies'] as $study) {
                    $payloadStudies[] = [
                        'date' => \OpenEMR\Forms\Cardiology\CardDate::displayDateTime((string)($study['study_date'] ?? '')),
                        'status' => ($study['status'] === 'final') ? xlt('Final') : xlt('Draft'),
                        'conclusion' => (string)($study['conclusion'] ?? ''),
                    ];
                }
                $payload = [
                    'title' => $cardInfo['title'],
                    'studies' => $payloadStudies,
                ];
            ?>
            <?php if ($print) : ?>
                <!-- Print: compact inline list (no hover in PDF). -->
                <div class="col-12 col-md-6 col-lg-4 mb-2">
                    <strong><?php echo text($cardInfo['title']); ?></strong>
                    <ul class="list-unstyled ms-3 mb-0">
                        <?php foreach ($cardInfo['studies'] as $study) : ?>
                        <li class="small">
                            <span class="text-secondary"><?php echo text(\OpenEMR\Forms\Cardiology\CardDate::displayDateTime((string)($study['study_date'] ?? ''))); ?></span>
                            &ndash; <?php echo ($study['status'] === 'final') ? xlt('Final') : xlt('Draft'); ?>
                            <?php if (!empty($study['conclusion'])) : ?>
                            <div class="text-muted"><em><?php echo xlt('Conclusion'); ?>:</em> <?php echo text($study['conclusion']); ?></div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else : ?>
                <!-- Screen: title chip in a multi-column grid; studies in a hover popup. -->
                <div class="col-12 col-md-6 col-lg-4 mb-2">
                    <span class="cardiology-report-hover-title d-block text-truncate"
                          data-cardiology-report="<?php echo attr($code); ?>"
                          title="<?php echo attr($cardInfo['title']); ?>"
                          style="cursor: help; padding: 4px 8px; border: 1px solid #dee2e6; border-radius: 6px;">
                        <i class="fa <?php echo attr($cardInfo['icon']); ?> me-1" aria-hidden="true"></i>
                        <strong><?php echo text($cardInfo['title']); ?></strong>
                        <span class="badge bg-secondary ms-1"><?php echo count($cardInfo['studies']); ?></span>
                    </span>
                    <div class="cardiology-report-popup" data-cardiology-report="<?php echo attr($code); ?>"
                         data-cardiology-studies="<?php echo attr(json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>"
                         role="dialog" aria-hidden="true" style="display: none;">
                        <div class="cardiology-report-popup-body"></div>
                    </div>
                </div>
            <?php endif; ?>
            <?php endforeach; ?>
            </div>
            <?php if (!$print) : ?>
                <?php cardiology_report_assets(); ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
