<?php

/**
 * Ambulatory Blood Pressure Monitoring card – _form_fields.php
 *
 * Included by new.php and view.php.
 * Renders all form inputs for an ABPM study.
 *
 * Variables expected in scope:
 *   $study    ?array  Existing study row, or null for a new study.
 *   $readOnly bool    True when status = 'final'.
 *   $dippingPatterns, $abpmClassifications array
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Abpm
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/CardDate.php';

$disabled = $readOnly ? 'disabled' : '';

function abpmFld(string $name, ?array $study, string $default = ''): string
{
    return htmlspecialchars((string)($study[$name] ?? $default), ENT_QUOTES, 'UTF-8');
}

function abpmTimeValue(string $name, ?array $study): string
{
    $value = abpmFld($name, $study);
    return $value !== '' ? substr($value, 0, 5) : '';
}

$studyDateVal = abpmFld('study_date', $study, date('Y-m-d H:i:s'));
?>

<!-- ======================================================================= -->
<!-- Section: Recording & Device                                             -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-clock text-danger me-1"></i>
        <?php echo xlt('Recording & Device'); ?>
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
                <label for="device_model" class="form-label">
                    <?php echo xlt('Device Model'); ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="device_model"
                       name="device_model"
                       maxlength="100"
                       value="<?php echo attr(abpmFld('device_model', $study)); ?>"
                       placeholder="<?php echo xla('Device model or manufacturer'); ?>"
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
                       min="6"
                       max="72"
                       value="<?php echo attr(abpmFld('recording_hours', $study)); ?>"
                       placeholder="6 - 72"
                       <?php echo $disabled; ?>>
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
                          placeholder="<?php echo xla('Clinical indication for the ABPM study'); ?>"
                          <?php echo $disabled; ?>><?php echo text(abpmFld('indication', $study)); ?></textarea>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label for="readings_total" class="form-label">
                    <?php echo xlt('Total Readings'); ?>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="readings_total"
                       name="readings_total"
                       min="1"
                       max="500"
                       value="<?php echo attr(abpmFld('readings_total', $study)); ?>"
                       placeholder="1 - 500"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="readings_valid" class="form-label">
                    <?php echo xlt('Valid Readings'); ?>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="readings_valid"
                       name="readings_valid"
                       min="0"
                       max="500"
                       value="<?php echo attr(abpmFld('readings_valid', $study)); ?>"
                       placeholder="0 - 500"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="valid_pct_display" class="form-label">
                    <?php echo xlt('Valid Readings (%)'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo xlt('Calculated'); ?></span>
                </label>
                <input type="text"
                       class="form-control form-control-sm bg-light"
                       id="valid_pct_display"
                       value="<?php echo attr(abpmFld('valid_pct', $study)); ?>"
                       readonly>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="on_antihypertensives"
                           name="on_antihypertensives"
                           value="1"
                           <?php echo (abpmFld('on_antihypertensives', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="on_antihypertensives">
                        <?php echo xlt('On Antihypertensive Treatment'); ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label for="day_start_time" class="form-label">
                    <?php echo xlt('Daytime Start Time'); ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="day_start_time"
                       name="day_start_time"
                       value="<?php echo attr(\OpenEMR\Forms\Cardiology\CardDate::displayTime(abpmTimeValue('day_start_time', $study))); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="night_start_time" class="form-label">
                    <?php echo xlt('Nighttime Start Time'); ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="night_start_time"
                       name="night_start_time"
                       value="<?php echo attr(\OpenEMR\Forms\Cardiology\CardDate::displayTime(abpmTimeValue('night_start_time', $study))); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-6">
                <label for="medications_note" class="form-label">
                    <?php echo xlt('Antihypertensive Medications'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="medications_note"
                          name="medications_note"
                          rows="1"
                          placeholder="<?php echo xla('Current antihypertensive medications and doses'); ?>"
                          <?php echo $disabled; ?>><?php echo text(abpmFld('medications_note', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Office Measurements                                            -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-stethoscope text-primary me-1"></i>
        <?php echo xlt('Office Measurements'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="office_sbp_mmhg" class="form-label">
                    <?php echo xlt('Office Systolic Blood Pressure (mmHg)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="office_sbp_mmhg"
                       name="office_sbp_mmhg"
                       min="40"
                       max="300"
                       value="<?php echo attr(abpmFld('office_sbp_mmhg', $study)); ?>"
                       placeholder="40 - 300"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-4">
                <label for="office_dbp_mmhg" class="form-label">
                    <?php echo xlt('Office Diastolic Blood Pressure (mmHg)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="office_dbp_mmhg"
                       name="office_dbp_mmhg"
                       min="20"
                       max="200"
                       value="<?php echo attr(abpmFld('office_dbp_mmhg', $study)); ?>"
                       placeholder="20 - 200"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-4">
                <label for="office_hr_bpm" class="form-label">
                    <?php echo xlt('Office Heart Rate (bpm)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="office_hr_bpm"
                       name="office_hr_bpm"
                       min="20"
                       max="250"
                       value="<?php echo attr(abpmFld('office_hr_bpm', $study)); ?>"
                       placeholder="20 - 250"
                       <?php echo $disabled; ?>>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: 24-Hour Means                                                  -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-heartbeat text-danger me-1"></i>
        <?php echo xlt('24-Hour Means'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="sbp_24h" class="form-label">
                    <?php echo xlt('24-Hour Systolic Blood Pressure (mmHg)'); ?>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="sbp_24h"
                       name="sbp_24h"
                       min="40"
                       max="300"
                       value="<?php echo attr(abpmFld('sbp_24h', $study)); ?>"
                       placeholder="40 - 300"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="dbp_24h" class="form-label">
                    <?php echo xlt('24-Hour Diastolic Blood Pressure (mmHg)'); ?>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="dbp_24h"
                       name="dbp_24h"
                       min="20"
                       max="200"
                       value="<?php echo attr(abpmFld('dbp_24h', $study)); ?>"
                       placeholder="20 - 200"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="hr_24h" class="form-label">
                    <?php echo xlt('24-Hour Heart Rate (bpm)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="hr_24h"
                       name="hr_24h"
                       min="20"
                       max="250"
                       value="<?php echo attr(abpmFld('hr_24h', $study)); ?>"
                       placeholder="20 - 250"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="pulse_pressure_display" class="form-label">
                    <?php echo xlt('24-Hour Pulse Pressure (mmHg)'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo xlt('Calculated'); ?></span>
                </label>
                <input type="text"
                       class="form-control form-control-sm bg-light"
                       id="pulse_pressure_display"
                       value="<?php echo attr(abpmFld('pulse_pressure_24h', $study)); ?>"
                       readonly>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Daytime & Nighttime Means                                      -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-sun text-warning me-1"></i>
        <?php echo xlt('Daytime & Nighttime Means'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="sbp_day" class="form-label">
                    <?php echo xlt('Daytime Systolic Blood Pressure (mmHg)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="sbp_day"
                       name="sbp_day"
                       min="40"
                       max="300"
                       value="<?php echo attr(abpmFld('sbp_day', $study)); ?>"
                       placeholder="40 - 300"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="dbp_day" class="form-label">
                    <?php echo xlt('Daytime Diastolic Blood Pressure (mmHg)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="dbp_day"
                       name="dbp_day"
                       min="20"
                       max="200"
                       value="<?php echo attr(abpmFld('dbp_day', $study)); ?>"
                       placeholder="20 - 200"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="hr_day" class="form-label">
                    <?php echo xlt('Daytime Heart Rate (bpm)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="hr_day"
                       name="hr_day"
                       min="20"
                       max="250"
                       value="<?php echo attr(abpmFld('hr_day', $study)); ?>"
                       placeholder="20 - 250"
                       <?php echo $disabled; ?>>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label for="sbp_night" class="form-label">
                    <?php echo xlt('Nighttime Systolic Blood Pressure (mmHg)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="sbp_night"
                       name="sbp_night"
                       min="40"
                       max="300"
                       value="<?php echo attr(abpmFld('sbp_night', $study)); ?>"
                       placeholder="40 - 300"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="dbp_night" class="form-label">
                    <?php echo xlt('Nighttime Diastolic Blood Pressure (mmHg)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="dbp_night"
                       name="dbp_night"
                       min="20"
                       max="200"
                       value="<?php echo attr(abpmFld('dbp_night', $study)); ?>"
                       placeholder="20 - 200"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="hr_night" class="form-label">
                    <?php echo xlt('Nighttime Heart Rate (bpm)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="hr_night"
                       name="hr_night"
                       min="20"
                       max="250"
                       value="<?php echo attr(abpmFld('hr_night', $study)); ?>"
                       placeholder="20 - 250"
                       <?php echo $disabled; ?>>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label for="dip_sbp_display" class="form-label">
                    <?php echo xlt('Systolic Dip (%)'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo xlt('Calculated'); ?></span>
                </label>
                <input type="text"
                       class="form-control form-control-sm bg-light"
                       id="dip_sbp_display"
                       value="<?php echo attr(abpmFld('dip_sbp_pct', $study)); ?>"
                       readonly>
            </div>
            <div class="col-md-3">
                <label for="dip_dbp_display" class="form-label">
                    <?php echo xlt('Diastolic Dip (%)'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo xlt('Calculated'); ?></span>
                </label>
                <input type="text"
                       class="form-control form-control-sm bg-light"
                       id="dip_dbp_display"
                       value="<?php echo attr(abpmFld('dip_dbp_pct', $study)); ?>"
                       readonly>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Maximum & Minimum Readings                                     -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-arrows-alt-v text-secondary me-1"></i>
        <?php echo xlt('Maximum & Minimum Readings'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="sbp_max" class="form-label">
                    <?php echo xlt('Maximum Systolic Blood Pressure (mmHg)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="sbp_max"
                       name="sbp_max"
                       min="40"
                       max="300"
                       value="<?php echo attr(abpmFld('sbp_max', $study)); ?>"
                       placeholder="40 - 300"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="dbp_max" class="form-label">
                    <?php echo xlt('Maximum Diastolic Blood Pressure (mmHg)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="dbp_max"
                       name="dbp_max"
                       min="20"
                       max="200"
                       value="<?php echo attr(abpmFld('dbp_max', $study)); ?>"
                       placeholder="20 - 200"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="hr_max" class="form-label">
                    <?php echo xlt('Maximum Heart Rate (bpm)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="hr_max"
                       name="hr_max"
                       min="20"
                       max="250"
                       value="<?php echo attr(abpmFld('hr_max', $study)); ?>"
                       placeholder="20 - 250"
                       <?php echo $disabled; ?>>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label for="sbp_min" class="form-label">
                    <?php echo xlt('Minimum Systolic Blood Pressure (mmHg)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="sbp_min"
                       name="sbp_min"
                       min="40"
                       max="300"
                       value="<?php echo attr(abpmFld('sbp_min', $study)); ?>"
                       placeholder="40 - 300"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="dbp_min" class="form-label">
                    <?php echo xlt('Minimum Diastolic Blood Pressure (mmHg)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="dbp_min"
                       name="dbp_min"
                       min="20"
                       max="200"
                       value="<?php echo attr(abpmFld('dbp_min', $study)); ?>"
                       placeholder="20 - 200"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="hr_min" class="form-label">
                    <?php echo xlt('Minimum Heart Rate (bpm)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="hr_min"
                       name="hr_min"
                       min="20"
                       max="250"
                       value="<?php echo attr(abpmFld('hr_min', $study)); ?>"
                       placeholder="20 - 250"
                       <?php echo $disabled; ?>>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Classification, Findings & Conclusion                          -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-file-medical-alt text-success me-1"></i>
        <?php echo xlt('Classification, Findings & Conclusion'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="dipping_pattern" class="form-label">
                    <?php echo xlt('Dipping Pattern (physician-selected)'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="dipping_pattern"
                        name="dipping_pattern"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select dipping pattern'); ?> --</option>
                    <?php foreach ($dippingPatterns as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (abpmFld('dipping_pattern', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="abpm_classification" class="form-label">
                    <?php echo xlt('ABPM Classification (physician-selected)'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="abpm_classification"
                        name="abpm_classification"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select ABPM classification'); ?> --</option>
                    <?php foreach ($abpmClassifications as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (abpmFld('abpm_classification', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="findings" class="form-label">
                    <?php echo xlt('Detailed Findings'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="findings"
                          name="findings"
                          rows="3"
                          placeholder="<?php echo xla('Detailed ABPM findings'); ?>"
                          <?php echo $disabled; ?>><?php echo text(abpmFld('findings', $study)); ?></textarea>
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
                          placeholder="<?php echo xla('ABPM interpretation conclusion printed in the report'); ?>"
                          <?php echo $disabled; ?>><?php echo text(abpmFld('conclusion', $study)); ?></textarea>
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
                          <?php echo $disabled; ?>><?php echo text(abpmFld('comments', $study)); ?></textarea>
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
