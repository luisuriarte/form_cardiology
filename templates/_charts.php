<?php

/**
 * Cardiology Charts Partial.
 *
 * Renders longitudinal evolution and within-study trend charts.
 * Supports both view mode (collapsible card) and print mode (static display).
 *
 * Expected variables in calling scope:
 *   $cardCode  string  Card identifier ('echo', 'stress_test', 'ecg')
 *   $pid       int     Patient ID
 *   $studyId   int     Current study ID
 *   $chartMode string  'view' (default) or 'print'
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/CardiologyContainer.php';
require_once __DIR__ . '/../lib/CardCharts.php';

use OpenEMR\Forms\Cardiology\CardCharts;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

$cardCode  = $cardCode ?? '';
$pid       = (int)($pid ?? 0);
$studyId   = (int)($studyId ?? 0);
$chartMode = $chartMode ?? 'view';

if ($cardCode === '' || $pid <= 0) {
    return;
}

$defs = CardCharts::getDefinitions($cardCode);
if (empty($defs)) {
    return;
}

// For stress_test, stage charts for the open study appear first, then longitudinal charts
$studyCharts = ($studyId > 0) ? CardCharts::getStudySeries($cardCode, $studyId, $pid) : [];
$longitudinalCharts = CardCharts::getLongitudinal($cardCode, $pid, $studyId);

$allCharts = array_merge($studyCharts, $longitudinalCharts);
if (empty($allCharts)) {
    return;
}

$isPrint = ($chartMode === 'print');
$sectionTitle = !empty($studyCharts) ? xlt('Evolution and Stage Trends') : xlt('Evolution');
?>

<?php if (!$isPrint) : ?>
<div class="card mb-3 cardiology-charts-section">
    <div class="card-header bg-light d-flex justify-content-between align-items-center"
         role="button"
         data-toggle="collapse"
         data-bs-toggle="collapse"
         data-target="#cardiology-charts-collapse"
         data-bs-target="#cardiology-charts-collapse"
         aria-expanded="false"
         aria-controls="cardiology-charts-collapse">
        <span class="font-weight-bold fw-bold">
            <i class="fa fa-line-chart text-danger me-2" aria-hidden="true"></i>
            <?php echo $sectionTitle; ?>
        </span>
        <i class="fa fa-chevron-down text-muted" aria-hidden="true"></i>
    </div>
    <div id="cardiology-charts-collapse" class="collapse">
        <div class="card-body">
            <div class="row">
                <?php foreach ($allCharts as $chart) : ?>
                <div class="col-12 col-md-6 mb-3">
                    <div class="card h-100 cardiology-chart-card shadow-sm" data-chart-id="<?php echo attr($chart['id']); ?>">
                        <div class="card-body p-2 d-flex flex-column">
                            <h6 class="card-title text-center small font-weight-bold fw-bold mb-2 text-muted">
                                <?php echo text($chart['title']); ?>
                                <?php if (!empty($chart['unit'])) : ?>
                                    <span class="font-weight-normal fw-normal">(<?php echo text($chart['unit']); ?>)</span>
                                <?php endif; ?>
                            </h6>
                            <div class="cardiology-chart-wrapper flex-grow-1 position-relative" style="min-height: 200px;">
                                <canvas id="chart-canvas-<?php echo attr($chart['id']); ?>"
                                        aria-label="<?php echo attr($chart['title']); ?>"
                                        role="img"></canvas>
                                <div class="cardiology-chart-empty text-center text-muted small p-4 d-none">
                                    <?php echo xlt('No data to plot'); ?>
                                </div>
                            </div>
                        </div>
                        <script type="application/json" class="cardiology-chart-json">
                            <?php echo json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
                        </script>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php else : ?>
<div class="cardiology-charts-section mb-3">
    <div class="section-title">
        <?php echo $sectionTitle; ?>
    </div>
    <div class="row">
        <?php foreach ($allCharts as $chart) : ?>
        <div class="col-6 mb-3">
            <div class="card h-100 cardiology-chart-card" data-chart-id="<?php echo attr($chart['id']); ?>">
                <div class="card-body p-2 d-flex flex-column">
                    <h6 class="card-title text-center small font-weight-bold fw-bold mb-2 text-muted">
                        <?php echo text($chart['title']); ?>
                        <?php if (!empty($chart['unit'])) : ?>
                            <span class="font-weight-normal fw-normal">(<?php echo text($chart['unit']); ?>)</span>
                        <?php endif; ?>
                    </h6>
                    <div class="cardiology-chart-wrapper flex-grow-1 position-relative" style="min-height: 180px;">
                        <canvas id="chart-canvas-<?php echo attr($chart['id']); ?>"
                                aria-label="<?php echo attr($chart['title']); ?>"
                                role="img"></canvas>
                        <div class="cardiology-chart-empty text-center text-muted small p-4 d-none">
                            <?php echo xlt('No data to plot'); ?>
                        </div>
                    </div>
                </div>
                <script type="application/json" class="cardiology-chart-json">
                    <?php echo json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
                </script>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<script src="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/js/cardiology-charts.js?v=<?php echo attr($GLOBALS['v_js_includes'] ?? '1'); ?>"></script>

