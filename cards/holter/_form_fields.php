<?php

/**
 * Holter Monitor card – _form_fields.php
 *
 * Included by new.php and view.php.
 * Renders all form inputs for a Holter study.
 *
 * Variables expected in scope:
 *   $study    ?array  Existing study row, or null for a new study.
 *   $readOnly bool    True when status = 'final'.
 *   $rhythms  array
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Holter
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/CardDate.php';

$disabled = $readOnly ? 'disabled' : '';

function holterFld(string $name, ?array $study, string $default = ''): string
{
    return htmlspecialchars((string)($study[$name] ?? $default), ENT_QUOTES, 'UTF-8');
}

function holterTimeValue(string $name, ?array $study): string
{
    $value = holterFld($name, $study);
    return $value !== '' ? substr($value, 0, 5) : '';
}

$studyDateVal = holterFld('study_date', $study, date('Y-m-d H:i:s'));
?>

<!-- ======================================================================= -->
<!-- Section: Recording & Indication                                         -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-clock text-danger me-1"></i>
        <?php echo xlt('Recording & Indication'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="study_date" class="form-label required">
                    <?php echo xlt('Study Date & Time'); ?>
                </label>
                <input type="text"
                       class="datetimepicker form-control form-control-sm"
                       id="study_date"
                       name="study_date"
                       value="<?php echo attr(\OpenEMR\Forms\Cardiology\CardDate::displayDateTime($studyDateVal)); ?>"
                       required
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-4">
                <label for="recording_hours" class="form-label">
                    <?php echo xlt('Recording Duration (hours)'); ?>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="recording_hours"
                       name="recording_hours"
                       min="1"
                       max="168"
                       value="<?php echo attr(holterFld('recording_hours', $study)); ?>"
                       placeholder="1 - 168"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-4">
                <label for="dominant_rhythm" class="form-label">
                    <?php echo xlt('Dominant Rhythm'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="dominant_rhythm"
                        name="dominant_rhythm"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select rhythm'); ?> --</option>
                    <?php foreach ($rhythms as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (holterFld('dominant_rhythm', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="indication" class="form-label">
                    <?php echo xlt('Indication'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="indication"
                          name="indication"
                          rows="2"
                          placeholder="<?php echo xla('Clinical indication for the Holter study'); ?>"
                          <?php echo $disabled; ?>><?php echo text(holterFld('indication', $study)); ?></textarea>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="af_present"
                           name="af_present"
                           value="1"
                           <?php echo (holterFld('af_present', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="af_present">
                        <?php echo xlt('Atrial Fibrillation Present'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <label for="af_burden_pct" class="form-label">
                    <?php echo xlt('AF Burden (%)'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="af_burden_pct"
                       name="af_burden_pct"
                       min="0"
                       max="100"
                       value="<?php echo attr(holterFld('af_burden_pct', $study)); ?>"
                       placeholder="0 - 100"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="paced_beats_pct" class="form-label">
                    <?php echo xlt('Paced Beats (%)'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="paced_beats_pct"
                       name="paced_beats_pct"
                       min="0"
                       max="100"
                       value="<?php echo attr(holterFld('paced_beats_pct', $study)); ?>"
                       placeholder="0 - 100"
                       <?php echo $disabled; ?>>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Heart Rate                                                     -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-heartbeat text-danger me-1"></i>
        <?php echo xlt('Heart Rate'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="total_beats" class="form-label">
                    <?php echo xlt('Total Beats Analyzed'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="total_beats"
                       name="total_beats"
                       min="100"
                       max="2000000"
                       value="<?php echo attr(holterFld('total_beats', $study)); ?>"
                       placeholder="100 - 2000000"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="hr_min_bpm" class="form-label">
                    <?php echo xlt('Minimum Heart Rate (bpm)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="hr_min_bpm"
                       name="hr_min_bpm"
                       min="20"
                       max="200"
                       value="<?php echo attr(holterFld('hr_min_bpm', $study)); ?>"
                       placeholder="20 - 200"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="hr_mean_bpm" class="form-label">
                    <?php echo xlt('Mean Heart Rate (bpm)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="hr_mean_bpm"
                       name="hr_mean_bpm"
                       min="30"
                       max="220"
                       value="<?php echo attr(holterFld('hr_mean_bpm', $study)); ?>"
                       placeholder="30 - 220"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="hr_max_bpm" class="form-label">
                    <?php echo xlt('Maximum Heart Rate (bpm)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="hr_max_bpm"
                       name="hr_max_bpm"
                       min="40"
                       max="300"
                       value="<?php echo attr(holterFld('hr_max_bpm', $study)); ?>"
                       placeholder="40 - 300"
                       <?php echo $disabled; ?>>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label for="hr_min_time" class="form-label">
                    <?php echo xlt('Time of Minimum Heart Rate'); ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="hr_min_time"
                       name="hr_min_time"
                       value="<?php echo attr(\OpenEMR\Forms\Cardiology\CardDate::displayTime(holterTimeValue('hr_min_time', $study))); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="hr_max_time" class="form-label">
                    <?php echo xlt('Time of Maximum Heart Rate'); ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="hr_max_time"
                       name="hr_max_time"
                       value="<?php echo attr(\OpenEMR\Forms\Cardiology\CardDate::displayTime(holterTimeValue('hr_max_time', $study))); ?>"
                       <?php echo $disabled; ?>>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Pauses & Ventricular Ectopy                                    -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-pause-circle text-warning me-1"></i>
        <?php echo xlt('Pauses & Ventricular Ectopy'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="longest_pause_ms" class="form-label">
                    <?php echo xlt('Longest Pause (ms)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="longest_pause_ms"
                       name="longest_pause_ms"
                       min="0"
                       max="30000"
                       value="<?php echo attr(holterFld('longest_pause_ms', $study)); ?>"
                       placeholder="0 - 30000"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="pauses_gt_3s" class="form-label">
                    <?php echo xlt('Pauses Longer Than 3 s'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="pauses_gt_3s"
                       name="pauses_gt_3s"
                       min="0"
                       max="1000"
                       value="<?php echo attr(holterFld('pauses_gt_3s', $study)); ?>"
                       placeholder="0 - 1000"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="pvc_total" class="form-label">
                    <?php echo xlt('PVC Count'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="pvc_total"
                       name="pvc_total"
                       min="0"
                       max="500000"
                       value="<?php echo attr(holterFld('pvc_total', $study)); ?>"
                       placeholder="0 - 500000"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="pvc_burden_display" class="form-label">
                    <?php echo xlt('PVC Burden (%)'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo xlt('Calculated'); ?></span>
                </label>
                <input type="text"
                       class="form-control form-control-sm bg-light"
                       id="pvc_burden_display"
                       value="<?php echo attr(holterFld('pvc_burden_pct', $study)); ?>"
                       readonly>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label for="pvc_couplets" class="form-label">
                    <?php echo xlt('PVC Couplets'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="pvc_couplets"
                       name="pvc_couplets"
                       min="0"
                       max="100000"
                       value="<?php echo attr(holterFld('pvc_couplets', $study)); ?>"
                       placeholder="0 - 100000"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="nsvt_runs" class="form-label">
                    <?php echo xlt('NSVT Runs'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="nsvt_runs"
                       name="nsvt_runs"
                       min="0"
                       max="10000"
                       value="<?php echo attr(holterFld('nsvt_runs', $study)); ?>"
                       placeholder="0 - 10000"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="longest_nsvt_beats" class="form-label">
                    <?php echo xlt('Longest NSVT Run (beats)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="longest_nsvt_beats"
                       name="longest_nsvt_beats"
                       min="0"
                       max="1000"
                       value="<?php echo attr(holterFld('longest_nsvt_beats', $study)); ?>"
                       placeholder="0 - 1000"
                       <?php echo $disabled; ?>>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Atrial Ectopy, ST Changes & AV Block                           -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-wave-square text-primary me-1"></i>
        <?php echo xlt('Atrial Ectopy, ST Changes & AV Block'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="pac_total" class="form-label">
                    <?php echo xlt('PAC Count'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="pac_total"
                       name="pac_total"
                       min="0"
                       max="500000"
                       value="<?php echo attr(holterFld('pac_total', $study)); ?>"
                       placeholder="0 - 500000"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="svt_runs" class="form-label">
                    <?php echo xlt('SVT Runs'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="svt_runs"
                       name="svt_runs"
                       min="0"
                       max="10000"
                       value="<?php echo attr(holterFld('svt_runs', $study)); ?>"
                       placeholder="0 - 10000"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="longest_svt_beats" class="form-label">
                    <?php echo xlt('Longest SVT Run (beats)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="longest_svt_beats"
                       name="longest_svt_beats"
                       min="0"
                       max="10000"
                       value="<?php echo attr(holterFld('longest_svt_beats', $study)); ?>"
                       placeholder="0 - 10000"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="st_episodes" class="form-label">
                    <?php echo xlt('ST Deviation Episodes'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="st_episodes"
                       name="st_episodes"
                       min="0"
                       max="1000"
                       value="<?php echo attr(holterFld('st_episodes', $study)); ?>"
                       placeholder="0 - 1000"
                       <?php echo $disabled; ?>>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="st_description" class="form-label">
                    <?php echo xlt('ST Episode Description'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="st_description"
                          name="st_description"
                          rows="2"
                          placeholder="<?php echo xla('ST-segment episode description'); ?>"
                          <?php echo $disabled; ?>><?php echo text(holterFld('st_description', $study)); ?></textarea>
            </div>
            <div class="col-12">
                <label for="av_block_note" class="form-label">
                    <?php echo xlt('AV Block Findings'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="av_block_note"
                          name="av_block_note"
                          rows="2"
                          placeholder="<?php echo xla('Atrioventricular block findings'); ?>"
                          <?php echo $disabled; ?>><?php echo text(holterFld('av_block_note', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Symptoms, Findings & Conclusion                                -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-file-medical-alt text-success me-1"></i>
        <?php echo xlt('Symptoms, Findings & Conclusion'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label for="symptoms_reported" class="form-label">
                    <?php echo xlt('Symptoms Reported'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="symptoms_reported"
                          name="symptoms_reported"
                          rows="2"
                          placeholder="<?php echo xla('Symptoms reported during the recording'); ?>"
                          <?php echo $disabled; ?>><?php echo text(holterFld('symptoms_reported', $study)); ?></textarea>
            </div>
            <div class="col-12">
                <label for="symptom_correlation" class="form-label">
                    <?php echo xlt('Symptom Correlation'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="symptom_correlation"
                          name="symptom_correlation"
                          rows="2"
                          placeholder="<?php echo xla('Symptom to rhythm correlation findings'); ?>"
                          <?php echo $disabled; ?>><?php echo text(holterFld('symptom_correlation', $study)); ?></textarea>
            </div>
            <div class="col-12">
                <label for="findings" class="form-label">
                    <?php echo xlt('Detailed Findings'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="findings"
                          name="findings"
                          rows="3"
                          placeholder="<?php echo xla('Detailed Holter findings'); ?>"
                          <?php echo $disabled; ?>><?php echo text(holterFld('findings', $study)); ?></textarea>
            </div>
            <div class="col-12">
                <label for="conclusion" class="form-label required">
                    <?php echo xlt('Conclusion'); ?>
                    <span class="text-danger">*</span>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <textarea class="form-control form-control-sm"
                          id="conclusion"
                          name="conclusion"
                          rows="3"
                          placeholder="<?php echo xla('Holter interpretation conclusion printed in the report'); ?>"
                          <?php echo $disabled; ?>><?php echo text(holterFld('conclusion', $study)); ?></textarea>
            </div>
            <div class="col-12">
                <label for="comments" class="form-label">
                    <?php echo xlt('Comments & Recommendations'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="comments"
                          name="comments"
                          rows="2"
                          placeholder="<?php echo xla('Additional comments or clinical recommendations'); ?>"
                          <?php echo $disabled; ?>><?php echo text(holterFld('comments', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    $('.datepicker').datetimepicker({
        <?php $datetimepicker_timepicker = false; $datetimepicker_showseconds = false; $datetimepicker_formatInput = true; require(\OpenEMR\Core\OEGlobalsBag::getInstance()->getSrcDir() . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); unset($datetimepicker_timepicker, $datetimepicker_showseconds, $datetimepicker_formatInput); ?>
    });
    $('.datetimepicker').datetimepicker({
        <?php $datetimepicker_timepicker = true; $datetimepicker_showseconds = false; $datetimepicker_formatInput = true; require(\OpenEMR\Core\OEGlobalsBag::getInstance()->getSrcDir() . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); unset($datetimepicker_timepicker, $datetimepicker_showseconds, $datetimepicker_formatInput); ?>
    });
});
</script>
