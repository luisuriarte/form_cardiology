<?php

/**
 * Stress Test card – _form_fields.php
 *
 * Shared partial rendered by new.php and view.php.
 *
 * Expected scope variables:
 *   $study        array|null   Row from form_card_stress_test, or null when new.
 *   $stages       array        Rows from form_card_stress_test_stage ([] when new).
 *   $protocols    array        list_options rows for card_stress_protocol.
 *   $bpResponses  array        list_options rows for card_bp_response.
 *   $symptoms     array        list_options rows for card_stress_symptom.
 *   $stopReasons  array        list_options rows for card_stop_reason.
 *   $stressResults array       list_options rows for card_stress_result.
 *   $readOnly     bool         True when the study is finalised.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\StressTest
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Helpers: shorthand to pull a value from $study or return ''.
$sv = static function (string $key) use ($study): string {
    return (string)($study[$key] ?? '');
};

$roAttr = $readOnly ? ' readonly' : '';
$disAttr = $readOnly ? ' disabled' : '';
?>

<!-- =========================================================================
     Section 1 – Clinical context
     ========================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-semibold">
        <i class="fa fa-clipboard-list me-2" aria-hidden="true"></i>
        <?php echo xlt('Clinical Context'); ?>
    </div>
    <div class="card-body">

        <div class="row g-3">

            <!-- Study date -->
            <div class="col-md-4">
                <label for="study_date" class="form-label">
                    <?php echo xlt('Study Date / Time'); ?>
                    <span class="text-danger">*</span>
                </label>
                <input type="datetime-local"
                       id="study_date"
                       name="study_date"
                       class="form-control"
                       required
                       value="<?php echo attr(
                           $study ? date('Y-m-d\TH:i', strtotime($study['study_date'])) : date('Y-m-d\TH:i')
                       ); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <!-- Age (readonly, computed by save.php from patient DOB) -->
            <div class="col-md-2">
                <label for="age_years" class="form-label">
                    <?php echo xlt('Age (years)'); ?>
                </label>
                <input type="number"
                       id="age_years"
                       name="age_years"
                       class="form-control"
                       min="0" max="130"
                       value="<?php echo attr($sv('age_years')); ?>"
                       readonly>
                <small class="text-muted"><?php echo xlt('Auto-calculated from DOB'); ?></small>
            </div>

            <!-- Height -->
            <div class="col-md-2">
                <label for="height_cm" class="form-label">
                    <?php echo xlt('Height (cm)'); ?>
                </label>
                <input type="number"
                       id="height_cm"
                       name="height_cm"
                       class="form-control"
                       step="0.1" min="0" max="300"
                       value="<?php echo attr($sv('height_cm')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <!-- Weight -->
            <div class="col-md-2">
                <label for="weight_kg" class="form-label">
                    <?php echo xlt('Weight (kg)'); ?>
                </label>
                <input type="number"
                       id="weight_kg"
                       name="weight_kg"
                       class="form-control"
                       step="0.1" min="0" max="500"
                       value="<?php echo attr($sv('weight_kg')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <!-- Beta-blocker -->
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input type="checkbox"
                           id="on_beta_blocker"
                           name="on_beta_blocker"
                           class="form-check-input"
                           value="1"
                           <?php echo (($sv('on_beta_blocker') === '1') ? 'checked' : ''); ?>
                           <?php echo $disAttr; ?>>
                    <label for="on_beta_blocker" class="form-check-label">
                        <?php echo xlt('On Beta-blocker'); ?>
                    </label>
                </div>
            </div>

            <!-- Indication -->
            <div class="col-md-6">
                <label for="indication" class="form-label">
                    <?php echo xlt('Indication'); ?>
                </label>
                <textarea id="indication"
                          name="indication"
                          class="form-control"
                          rows="2"
                          <?php echo $roAttr; ?>><?php echo text($sv('indication')); ?></textarea>
            </div>

            <!-- Medications note -->
            <div class="col-md-6">
                <label for="medications_note" class="form-label">
                    <?php echo xlt('Relevant Medications'); ?>
                </label>
                <textarea id="medications_note"
                          name="medications_note"
                          class="form-control"
                          rows="2"
                          <?php echo $roAttr; ?>><?php echo text($sv('medications_note')); ?></textarea>
            </div>

            <!-- Protocol -->
            <div class="col-md-4">
                <label for="protocol" class="form-label">
                    <?php echo xlt('Protocol'); ?>
                </label>
                <select id="protocol"
                        name="protocol"
                        class="form-select"
                        <?php echo $disAttr; ?>>
                    <option value=""><?php echo xlt('-- Select --'); ?></option>
                    <?php foreach ($protocols as $opt): ?>
                        <option value="<?php echo attr($opt['option_id']); ?>"
                            <?php echo ($sv('protocol') === $opt['option_id'] ? 'selected' : ''); ?>>
                            <?php echo xlt($opt['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Rest ECG findings -->
            <div class="col-md-8">
                <label for="rest_ecg_findings" class="form-label">
                    <?php echo xlt('Resting ECG Findings'); ?>
                </label>
                <textarea id="rest_ecg_findings"
                          name="rest_ecg_findings"
                          class="form-control"
                          rows="2"
                          <?php echo $roAttr; ?>><?php echo text($sv('rest_ecg_findings')); ?></textarea>
            </div>

        </div><!-- .row -->
    </div><!-- .card-body -->
</div><!-- Section 1 -->


<!-- =========================================================================
     Section 2 – Baseline values
     ========================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-semibold">
        <i class="fa fa-heartbeat me-2" aria-hidden="true"></i>
        <?php echo xlt('Baseline Values (Rest)'); ?>
    </div>
    <div class="card-body">

        <div class="row g-3">

            <div class="col-md-3">
                <label for="rest_hr_bpm" class="form-label">
                    <?php echo xlt('Rest HR (bpm)'); ?>
                </label>
                <input type="number"
                       id="rest_hr_bpm"
                       name="rest_hr_bpm"
                       class="form-control"
                       min="0" max="300"
                       value="<?php echo attr($sv('rest_hr_bpm')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <div class="col-md-3">
                <label for="rest_bp_systolic_mmhg" class="form-label">
                    <?php echo xlt('Rest BP Systolic (mmHg)'); ?>
                </label>
                <input type="number"
                       id="rest_bp_systolic_mmhg"
                       name="rest_bp_systolic_mmhg"
                       class="form-control"
                       min="0" max="400"
                       value="<?php echo attr($sv('rest_bp_systolic_mmhg')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <div class="col-md-3">
                <label for="rest_bp_diastolic_mmhg" class="form-label">
                    <?php echo xlt('Rest BP Diastolic (mmHg)'); ?>
                </label>
                <input type="number"
                       id="rest_bp_diastolic_mmhg"
                       name="rest_bp_diastolic_mmhg"
                       class="form-control"
                       min="0" max="300"
                       value="<?php echo attr($sv('rest_bp_diastolic_mmhg')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

        </div>
    </div>
</div><!-- Section 2 -->


<!-- =========================================================================
     Section 3 – Exercise performance
     ========================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-semibold">
        <i class="fa fa-running me-2" aria-hidden="true"></i>
        <?php echo xlt('Exercise Performance'); ?>
    </div>
    <div class="card-body">

        <div class="row g-3">

            <!-- Max predicted HR (readonly, computed JS/server) -->
            <div class="col-md-3">
                <label for="max_predicted_hr_bpm" class="form-label">
                    <?php echo xlt('Max Predicted HR (bpm)'); ?>
                </label>
                <input type="number"
                       id="max_predicted_hr_bpm"
                       name="max_predicted_hr_bpm"
                       class="form-control bg-light"
                       value="<?php echo attr($sv('max_predicted_hr_bpm')); ?>"
                       readonly>
                <small class="text-muted"><?php echo xlt('220 − age'); ?></small>
            </div>

            <!-- Target HR (readonly) -->
            <div class="col-md-3">
                <label for="target_hr_bpm" class="form-label">
                    <?php echo xlt('Target HR (bpm)'); ?>
                </label>
                <input type="number"
                       id="target_hr_bpm"
                       name="target_hr_bpm"
                       class="form-control bg-light"
                       value="<?php echo attr($sv('target_hr_bpm')); ?>"
                       readonly>
                <small class="text-muted"><?php echo xlt('85% of max predicted'); ?></small>
            </div>

            <!-- Peak HR -->
            <div class="col-md-3">
                <label for="peak_hr_bpm" class="form-label">
                    <?php echo xlt('Peak HR (bpm)'); ?>
                </label>
                <input type="number"
                       id="peak_hr_bpm"
                       name="peak_hr_bpm"
                       class="form-control"
                       min="0" max="400"
                       value="<?php echo attr($sv('peak_hr_bpm')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <!-- Peak HR % of predicted (readonly) -->
            <div class="col-md-3">
                <label for="peak_hr_pct_predicted" class="form-label">
                    <?php echo xlt('Peak HR % of Predicted'); ?>
                </label>
                <input type="number"
                       id="peak_hr_pct_predicted"
                       name="peak_hr_pct_predicted"
                       class="form-control bg-light"
                       step="0.1"
                       value="<?php echo attr($sv('peak_hr_pct_predicted')); ?>"
                       readonly>
            </div>

            <!-- Peak BP systolic -->
            <div class="col-md-3">
                <label for="peak_bp_systolic_mmhg" class="form-label">
                    <?php echo xlt('Peak BP Systolic (mmHg)'); ?>
                </label>
                <input type="number"
                       id="peak_bp_systolic_mmhg"
                       name="peak_bp_systolic_mmhg"
                       class="form-control"
                       min="0" max="400"
                       value="<?php echo attr($sv('peak_bp_systolic_mmhg')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <!-- Peak BP diastolic -->
            <div class="col-md-3">
                <label for="peak_bp_diastolic_mmhg" class="form-label">
                    <?php echo xlt('Peak BP Diastolic (mmHg)'); ?>
                </label>
                <input type="number"
                       id="peak_bp_diastolic_mmhg"
                       name="peak_bp_diastolic_mmhg"
                       class="form-control"
                       min="0" max="300"
                       value="<?php echo attr($sv('peak_bp_diastolic_mmhg')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <!-- Double product (readonly) -->
            <div class="col-md-3">
                <label for="double_product" class="form-label">
                    <?php echo xlt('Double Product'); ?>
                </label>
                <input type="number"
                       id="double_product"
                       name="double_product"
                       class="form-control bg-light"
                       value="<?php echo attr($sv('double_product')); ?>"
                       readonly>
                <small class="text-muted"><?php echo xlt('Peak HR × Peak SBP'); ?></small>
            </div>

            <!-- Exercise duration (seconds) + mm:ss display -->
            <div class="col-md-3">
                <label for="exercise_duration_s" class="form-label">
                    <?php echo xlt('Exercise Duration (s)'); ?>
                </label>
                <input type="number"
                       id="exercise_duration_s"
                       name="exercise_duration_s"
                       class="form-control"
                       min="0"
                       value="<?php echo attr($sv('exercise_duration_s')); ?>"
                       <?php echo $roAttr; ?>>
                <small class="text-muted">
                    <?php echo xlt('mm:ss'); ?>:
                    <span id="exercise_duration_mmss" class="fw-semibold">
                        <?php
                        $durS = (int)$sv('exercise_duration_s');
                        echo text(sprintf('%d:%02d', intdiv($durS, 60), $durS % 60));
                        ?>
                    </span>
                </small>
            </div>

            <!-- METs achieved -->
            <div class="col-md-3">
                <label for="mets_achieved" class="form-label">
                    <?php echo xlt('METs Achieved'); ?>
                </label>
                <input type="number"
                       id="mets_achieved"
                       name="mets_achieved"
                       class="form-control"
                       step="0.1" min="0"
                       value="<?php echo attr($sv('mets_achieved')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <!-- BP response -->
            <div class="col-md-3">
                <label for="bp_response" class="form-label">
                    <?php echo xlt('BP Response'); ?>
                </label>
                <select id="bp_response"
                        name="bp_response"
                        class="form-select"
                        <?php echo $disAttr; ?>>
                    <option value=""><?php echo xlt('-- Select --'); ?></option>
                    <?php foreach ($bpResponses as $opt): ?>
                        <option value="<?php echo attr($opt['option_id']); ?>"
                            <?php echo ($sv('bp_response') === $opt['option_id'] ? 'selected' : ''); ?>>
                            <?php echo xlt($opt['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- HR recovery at 1 min -->
            <div class="col-md-3">
                <label for="hr_recovery_1min_bpm" class="form-label">
                    <?php echo xlt('HR Recovery 1 min (bpm)'); ?>
                </label>
                <input type="number"
                       id="hr_recovery_1min_bpm"
                       name="hr_recovery_1min_bpm"
                       class="form-control"
                       min="0" max="300"
                       value="<?php echo attr($sv('hr_recovery_1min_bpm')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

        </div>
    </div>
</div><!-- Section 3 -->


<!-- =========================================================================
     Section 4 – Symptoms, ST and arrhythmias
     ========================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-semibold">
        <i class="fa fa-stethoscope me-2" aria-hidden="true"></i>
        <?php echo xlt('Symptoms, ST Segment and Arrhythmias'); ?>
    </div>
    <div class="card-body">

        <div class="row g-3">

            <!-- Symptom -->
            <div class="col-md-4">
                <label for="symptom" class="form-label">
                    <?php echo xlt('Main Symptom'); ?>
                </label>
                <select id="symptom"
                        name="symptom"
                        class="form-select"
                        <?php echo $disAttr; ?>>
                    <option value=""><?php echo xlt('-- Select --'); ?></option>
                    <?php foreach ($symptoms as $opt): ?>
                        <option value="<?php echo attr($opt['option_id']); ?>"
                            <?php echo ($sv('symptom') === $opt['option_id'] ? 'selected' : ''); ?>>
                            <?php echo xlt($opt['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Angina index (radio) -->
            <div class="col-md-4">
                <label class="form-label d-block"><?php echo xlt('Angina Index'); ?></label>
                <?php
                $anginaOptions = [
                    '0' => xlt('None'),
                    '1' => xlt('Non-limiting angina'),
                    '2' => xlt('Test-limiting angina'),
                ];
                $anginaCurrent = $sv('angina_index');
                foreach ($anginaOptions as $val => $label):
                ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input"
                           type="radio"
                           name="angina_index"
                           id="angina_index_<?php echo attr($val); ?>"
                           value="<?php echo attr($val); ?>"
                           <?php echo ($anginaCurrent === $val ? 'checked' : ($anginaCurrent === '' && $val === '0' ? 'checked' : '')); ?>
                           <?php echo $disAttr; ?>>
                    <label class="form-check-label" for="angina_index_<?php echo attr($val); ?>">
                        <?php echo $label; ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ST depression -->
            <div class="col-md-2">
                <label for="st_depression_mm" class="form-label">
                    <?php echo xlt('ST Depression (mm)'); ?>
                </label>
                <input type="number"
                       id="st_depression_mm"
                       name="st_depression_mm"
                       class="form-control"
                       step="0.1" min="0"
                       value="<?php echo attr($sv('st_depression_mm')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <!-- ST elevation -->
            <div class="col-md-2">
                <label for="st_elevation_mm" class="form-label">
                    <?php echo xlt('ST Elevation (mm)'); ?>
                </label>
                <input type="number"
                       id="st_elevation_mm"
                       name="st_elevation_mm"
                       class="form-control"
                       step="0.1" min="0"
                       value="<?php echo attr($sv('st_elevation_mm')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <!-- ST leads -->
            <div class="col-md-3">
                <label for="st_leads" class="form-label">
                    <?php echo xlt('ST Leads'); ?>
                </label>
                <input type="text"
                       id="st_leads"
                       name="st_leads"
                       class="form-control"
                       maxlength="64"
                       placeholder="<?php echo xla('e.g. V4-V6'); ?>"
                       value="<?php echo attr($sv('st_leads')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <!-- ST onset HR -->
            <div class="col-md-3">
                <label for="st_onset_hr_bpm" class="form-label">
                    <?php echo xlt('ST Onset HR (bpm)'); ?>
                </label>
                <input type="number"
                       id="st_onset_hr_bpm"
                       name="st_onset_hr_bpm"
                       class="form-control"
                       min="0" max="400"
                       value="<?php echo attr($sv('st_onset_hr_bpm')); ?>"
                       <?php echo $roAttr; ?>>
            </div>

            <!-- Arrhythmias -->
            <div class="col-md-6">
                <label for="arrhythmias" class="form-label">
                    <?php echo xlt('Arrhythmias'); ?>
                </label>
                <textarea id="arrhythmias"
                          name="arrhythmias"
                          class="form-control"
                          rows="2"
                          <?php echo $roAttr; ?>><?php echo text($sv('arrhythmias')); ?></textarea>
            </div>

        </div>
    </div>
</div><!-- Section 4 -->


<!-- =========================================================================
     Section 5 – Result
     ========================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-semibold">
        <i class="fa fa-check-circle me-2" aria-hidden="true"></i>
        <?php echo xlt('Result'); ?>
    </div>
    <div class="card-body">

        <div class="row g-3">

            <!-- Stop reason -->
            <div class="col-md-4">
                <label for="stop_reason" class="form-label">
                    <?php echo xlt('Stop Reason'); ?>
                </label>
                <select id="stop_reason"
                        name="stop_reason"
                        class="form-select"
                        <?php echo $disAttr; ?>>
                    <option value=""><?php echo xlt('-- Select --'); ?></option>
                    <?php foreach ($stopReasons as $opt): ?>
                        <option value="<?php echo attr($opt['option_id']); ?>"
                            <?php echo ($sv('stop_reason') === $opt['option_id'] ? 'selected' : ''); ?>>
                            <?php echo xlt($opt['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Duke score (readonly, computed) -->
            <div class="col-md-3">
                <label for="duke_score" class="form-label">
                    <?php echo xlt('Duke Treadmill Score'); ?>
                </label>
                <input type="number"
                       id="duke_score"
                       name="duke_score"
                       class="form-control bg-light fw-bold"
                       step="0.1"
                       value="<?php echo attr($sv('duke_score')); ?>"
                       readonly>
                <small class="text-muted">
                    <?php echo xlt('min − 5×ST dep − 4×angina idx'); ?>
                </small>
            </div>

            <!-- Result -->
            <div class="col-md-5">
                <label for="result" class="form-label">
                    <?php echo xlt('Test Result'); ?>
                </label>
                <select id="result"
                        name="result"
                        class="form-select"
                        <?php echo $disAttr; ?>>
                    <option value=""><?php echo xlt('-- Select --'); ?></option>
                    <?php foreach ($stressResults as $opt): ?>
                        <option value="<?php echo attr($opt['option_id']); ?>"
                            <?php echo ($sv('result') === $opt['option_id'] ? 'selected' : ''); ?>>
                            <?php echo xlt($opt['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Conclusion -->
            <div class="col-md-6">
                <label for="conclusion" class="form-label">
                    <?php echo xlt('Conclusion'); ?>
                </label>
                <textarea id="conclusion"
                          name="conclusion"
                          class="form-control"
                          rows="3"
                          <?php echo $roAttr; ?>><?php echo text($sv('conclusion')); ?></textarea>
            </div>

            <!-- Comments -->
            <div class="col-md-6">
                <label for="comments" class="form-label">
                    <?php echo xlt('Comments'); ?>
                </label>
                <textarea id="comments"
                          name="comments"
                          class="form-control"
                          rows="3"
                          <?php echo $roAttr; ?>><?php echo text($sv('comments')); ?></textarea>
            </div>

        </div>
    </div>
</div><!-- Section 5 -->


<!-- =========================================================================
     Section 6 – Protocol stages (dynamic table)
     ========================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
        <span>
            <i class="fa fa-table me-2" aria-hidden="true"></i>
            <?php echo xlt('Protocol Stages'); ?>
        </span>
        <?php if (!$readOnly): ?>
        <button type="button"
                id="btn-add-stage"
                class="btn btn-sm btn-outline-primary">
            <i class="fa fa-plus me-1" aria-hidden="true"></i>
            <?php echo xlt('Add Stage'); ?>
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="stages-table">
                <thead class="table-light">
                    <tr>
                        <th><?php echo xlt('Phase'); ?></th>
                        <th><?php echo xlt('Stage #'); ?></th>
                        <th><?php echo xlt('Elapsed (s)'); ?></th>
                        <th><?php echo xlt('Speed (km/h)'); ?></th>
                        <th><?php echo xlt('Grade (%)'); ?></th>
                        <th><?php echo xlt('METs'); ?></th>
                        <th><?php echo xlt('HR (bpm)'); ?></th>
                        <th><?php echo xlt('BP Sys'); ?></th>
                        <th><?php echo xlt('BP Dia'); ?></th>
                        <th><?php echo xlt('ST (mm)'); ?></th>
                        <th><?php echo xlt('Symptoms/Notes'); ?></th>
                        <?php if (!$readOnly): ?>
                        <th class="text-center"><?php echo xlt('Del'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="stages-tbody">
                    <?php
                    $phaseLabels = [
                        'rest'     => xlt('Rest'),
                        'exercise' => xlt('Exercise'),
                        'recovery' => xlt('Recovery'),
                    ];
                    foreach ($stages as $idx => $stage):
                    ?>
                    <tr class="stage-row" data-idx="<?php echo attr((string)$idx); ?>">
                        <input type="hidden"
                               name="stages[<?php echo attr((string)$idx); ?>][id]"
                               value="<?php echo attr((string)($stage['id'] ?? 0)); ?>">
                        <input type="hidden"
                               name="stages[<?php echo attr((string)$idx); ?>][delete]"
                               class="stage-delete-flag"
                               value="0">
                        <td>
                            <?php if ($readOnly): ?>
                                <?php echo text($phaseLabels[$stage['phase']] ?? $stage['phase']); ?>
                            <?php else: ?>
                                <select name="stages[<?php echo attr((string)$idx); ?>][phase]"
                                        class="form-select form-select-sm">
                                    <option value="rest"     <?php echo ($stage['phase'] === 'rest'     ? 'selected' : ''); ?>><?php echo xlt('Rest'); ?></option>
                                    <option value="exercise" <?php echo ($stage['phase'] === 'exercise' ? 'selected' : ''); ?>><?php echo xlt('Exercise'); ?></option>
                                    <option value="recovery" <?php echo ($stage['phase'] === 'recovery' ? 'selected' : ''); ?>><?php echo xlt('Recovery'); ?></option>
                                </select>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($readOnly): ?>
                                <?php echo text((string)($stage['stage_no'] ?? '')); ?>
                            <?php else: ?>
                                <input type="number"
                                       name="stages[<?php echo attr((string)$idx); ?>][stage_no]"
                                       class="form-control form-control-sm"
                                       min="0" value="<?php echo attr((string)($stage['stage_no'] ?? '')); ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($readOnly): ?>
                                <?php echo text((string)($stage['elapsed_s'] ?? '')); ?>
                            <?php else: ?>
                                <input type="number"
                                       name="stages[<?php echo attr((string)$idx); ?>][elapsed_s]"
                                       class="form-control form-control-sm"
                                       min="0" value="<?php echo attr((string)($stage['elapsed_s'] ?? '')); ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($readOnly): ?>
                                <?php echo text((string)($stage['speed_kmh'] ?? '')); ?>
                            <?php else: ?>
                                <input type="number"
                                       name="stages[<?php echo attr((string)$idx); ?>][speed_kmh]"
                                       class="form-control form-control-sm"
                                       step="0.1" min="0" value="<?php echo attr((string)($stage['speed_kmh'] ?? '')); ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($readOnly): ?>
                                <?php echo text((string)($stage['grade_pct'] ?? '')); ?>
                            <?php else: ?>
                                <input type="number"
                                       name="stages[<?php echo attr((string)$idx); ?>][grade_pct]"
                                       class="form-control form-control-sm"
                                       step="0.1" min="0" value="<?php echo attr((string)($stage['grade_pct'] ?? '')); ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($readOnly): ?>
                                <?php echo text((string)($stage['mets'] ?? '')); ?>
                            <?php else: ?>
                                <input type="number"
                                       name="stages[<?php echo attr((string)$idx); ?>][mets]"
                                       class="form-control form-control-sm"
                                       step="0.1" min="0" value="<?php echo attr((string)($stage['mets'] ?? '')); ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($readOnly): ?>
                                <?php echo text((string)($stage['hr_bpm'] ?? '')); ?>
                            <?php else: ?>
                                <input type="number"
                                       name="stages[<?php echo attr((string)$idx); ?>][hr_bpm]"
                                       class="form-control form-control-sm"
                                       min="0" value="<?php echo attr((string)($stage['hr_bpm'] ?? '')); ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($readOnly): ?>
                                <?php echo text((string)($stage['bp_systolic_mmhg'] ?? '')); ?>
                            <?php else: ?>
                                <input type="number"
                                       name="stages[<?php echo attr((string)$idx); ?>][bp_systolic_mmhg]"
                                       class="form-control form-control-sm"
                                       min="0" value="<?php echo attr((string)($stage['bp_systolic_mmhg'] ?? '')); ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($readOnly): ?>
                                <?php echo text((string)($stage['bp_diastolic_mmhg'] ?? '')); ?>
                            <?php else: ?>
                                <input type="number"
                                       name="stages[<?php echo attr((string)$idx); ?>][bp_diastolic_mmhg]"
                                       class="form-control form-control-sm"
                                       min="0" value="<?php echo attr((string)($stage['bp_diastolic_mmhg'] ?? '')); ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($readOnly): ?>
                                <?php echo text((string)($stage['st_mm'] ?? '')); ?>
                            <?php else: ?>
                                <input type="number"
                                       name="stages[<?php echo attr((string)$idx); ?>][st_mm]"
                                       class="form-control form-control-sm"
                                       step="0.1" value="<?php echo attr((string)($stage['st_mm'] ?? '')); ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($readOnly): ?>
                                <?php echo text((string)($stage['symptom_note'] ?? '')); ?>
                            <?php else: ?>
                                <input type="text"
                                       name="stages[<?php echo attr((string)$idx); ?>][symptom_note]"
                                       class="form-control form-control-sm"
                                       maxlength="255"
                                       value="<?php echo attr((string)($stage['symptom_note'] ?? '')); ?>">
                            <?php endif; ?>
                        </td>
                        <?php if (!$readOnly): ?>
                        <td class="text-center">
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger btn-remove-stage"
                                    title="<?php echo xla('Remove stage'); ?>">
                                &times;
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div><!-- .table-responsive -->
    </div><!-- .card-body -->
</div><!-- Section 6 -->
