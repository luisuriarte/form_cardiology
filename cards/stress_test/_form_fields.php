<?php
/**
 * Stress Test card – _form_fields.php
 *
 * Shared form partial included by new.php and view.php.
 *
 * Expects in scope:
 *   $study        array|null  – row from form_card_stress_test, or null
 *   $stages       array       – rows from form_card_stress_test_stage (or [])
 *   $protocols    array       – list_options card_stress_protocol
 *   $bpResponses  array       – list_options card_bp_response
 *   $symptoms     array       – list_options card_stress_symptom
 *   $stopReasons  array       – list_options card_stop_reason
 *   $stressResults array      – list_options card_stress_result
 *   $readOnly     bool
 *
 * @package   OpenEMR
 * @subpackage Cardiology\StressTest
 */

require_once __DIR__ . '/../../lib/CardDate.php';

$fv = static function (string $key, string $default = '') use ($study): string {
    if ($study === null) { return $default; }
    return (string)($study[$key] ?? $default);
};

$optList = static function (array $opts, string $selected, string $blank = ''): string {
    $html = '';
    if ($blank !== '') {
        $sel  = ($selected === '') ? ' selected' : '';
        $html .= '<option value=""' . $sel . '>' . text($blank) . '</option>';
    }
    foreach ($opts as $o) {
        $sel   = ($o['option_id'] === $selected) ? ' selected' : '';
        $html .= '<option value="' . attr($o['option_id']) . '"' . $sel . '>'
               . text(xl($o['title'])) . '</option>';
    }
    return $html;
};

$dis = $readOnly ? ' disabled' : '';

$secsToMmss = static function (?string $secs): string {
    if ($secs === null || $secs === '') { return ''; }
    $s = (int)$secs;
    return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
};
?>
<?php if ($readOnly) : ?>
<div class="alert alert-info d-flex align-items-center mb-3">
    <i class="fa fa-lock me-2" aria-hidden="true"></i>
    <?php echo xlt('This study is finalized and cannot be edited.'); ?>
</div>
<?php endif; ?>

<!-- Section 1: Clinical Context -->
<fieldset class="mb-4">
<legend class="cardiology-section-title"><?php echo xlt('Clinical Context'); ?></legend>
<div class="row g-2">
    <div class="col-12 col-md-4">
        <label class="form-label" for="st_study_date">
            <?php echo xlt('Study Date'); ?> <span class="text-danger">*</span>
        </label>
        <input type="text" id="st_study_date" name="study_date"
               class="datetimepicker form-control form-control-sm"
               value="<?php echo attr(\OpenEMR\Forms\Cardiology\CardDate::displayDateTime($study['study_date'] ?? null)); ?>"
               required <?php echo $dis; ?>>
    </div>
    <div class="col-12 col-md-8">
        <label class="form-label" for="st_indication"><?php echo xlt('Indication'); ?></label>
        <input type="text" id="st_indication" name="indication"
               class="form-control form-control-sm"
               value="<?php echo attr($fv('indication')); ?>"
               placeholder="<?php echo xla('Indication for the test'); ?>"
               <?php echo $dis; ?>>
    </div>
</div>
<div class="row g-2 mt-1">
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_age_years"><?php echo xlt('Age (years)'); ?></label>
        <input type="number" id="st_age_years" name="age_years"
               class="form-control form-control-sm" min="0" max="130"
               value="<?php echo attr($fv('age_years')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_height_cm"><?php echo xlt('Height (cm)'); ?></label>
        <input type="number" id="st_height_cm" name="height_cm"
               class="form-control form-control-sm" step="0.1" min="0" max="300"
               value="<?php echo attr($fv('height_cm')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_weight_kg"><?php echo xlt('Weight (kg)'); ?></label>
        <input type="number" id="st_weight_kg" name="weight_kg"
               class="form-control form-control-sm" step="0.1" min="0" max="500"
               value="<?php echo attr($fv('weight_kg')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2 d-flex align-items-end pb-1">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="st_on_beta_blocker"
                   name="on_beta_blocker" value="1"
                   <?php echo ($fv('on_beta_blocker') === '1') ? 'checked' : ''; ?>
                   <?php echo $dis; ?>>
            <label class="form-check-label small" for="st_on_beta_blocker">
                <?php echo xlt('On beta-blocker'); ?>
            </label>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label small" for="st_protocol"><?php echo xlt('Protocol'); ?></label>
        <select id="st_protocol" name="protocol"
                class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($protocols, $fv('protocol'), xlt('-- Select --')); ?>
        </select>
    </div>
</div>
<div class="row g-2 mt-1">
    <div class="col-12 col-md-6">
        <label class="form-label small" for="st_medications_note"><?php echo xlt('Medications'); ?></label>
        <input type="text" id="st_medications_note" name="medications_note"
               class="form-control form-control-sm"
               value="<?php echo attr($fv('medications_note')); ?>"
               placeholder="<?php echo xla('Relevant medications at study time'); ?>"
               <?php echo $dis; ?>>
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label small" for="st_rest_ecg"><?php echo xlt('Resting ECG Findings'); ?></label>
        <input type="text" id="st_rest_ecg" name="rest_ecg_findings"
               class="form-control form-control-sm"
               value="<?php echo attr($fv('rest_ecg_findings')); ?>"
               placeholder="<?php echo xla('ECG findings before the test'); ?>"
               <?php echo $dis; ?>>
    </div>
</div>
</fieldset>

<!-- Section 2: Baseline values -->
<fieldset class="mb-4">
<legend class="cardiology-section-title"><?php echo xlt('Baseline Values'); ?></legend>
<div class="row g-2">
    <div class="col-6 col-md-3">
        <label class="form-label small" for="st_rest_hr_bpm"><?php echo xlt('Resting HR (bpm)'); ?></label>
        <input type="number" id="st_rest_hr_bpm" name="rest_hr_bpm"
               class="form-control form-control-sm" min="0" max="300"
               value="<?php echo attr($fv('rest_hr_bpm')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small" for="st_rest_bp_sys"><?php echo xlt('Resting BP Systolic (mmHg)'); ?></label>
        <input type="number" id="st_rest_bp_sys" name="rest_bp_systolic_mmhg"
               class="form-control form-control-sm" min="0" max="300"
               value="<?php echo attr($fv('rest_bp_systolic_mmhg')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small" for="st_rest_bp_dia"><?php echo xlt('Resting BP Diastolic (mmHg)'); ?></label>
        <input type="number" id="st_rest_bp_dia" name="rest_bp_diastolic_mmhg"
               class="form-control form-control-sm" min="0" max="200"
               value="<?php echo attr($fv('rest_bp_diastolic_mmhg')); ?>" <?php echo $dis; ?>>
    </div>
</div>
</fieldset>

<!-- Section 3: Performance -->
<fieldset class="mb-4">
<legend class="cardiology-section-title"><?php echo xlt('Exercise Performance'); ?></legend>
<div class="row g-2">
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_max_pred_hr"><?php echo xlt('Max Predicted HR'); ?></label>
        <input type="text" id="st_max_pred_hr" name="max_predicted_hr_bpm"
               class="form-control form-control-sm bg-light"
               value="<?php echo attr($fv('max_predicted_hr_bpm')); ?>"
               readonly title="<?php echo xla('Calculated: 220 - age'); ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_target_hr"><?php echo xlt('Target HR (85%)'); ?></label>
        <input type="text" id="st_target_hr" name="target_hr_bpm"
               class="form-control form-control-sm bg-light"
               value="<?php echo attr($fv('target_hr_bpm')); ?>"
               readonly title="<?php echo xla('Calculated: 85% of max predicted HR'); ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_peak_hr_bpm"><?php echo xlt('Peak HR (bpm)'); ?></label>
        <input type="number" id="st_peak_hr_bpm" name="peak_hr_bpm"
               class="form-control form-control-sm" min="0" max="300"
               value="<?php echo attr($fv('peak_hr_bpm')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_peak_hr_pct"><?php echo xlt('Peak HR % Predicted'); ?></label>
        <input type="text" id="st_peak_hr_pct" name="peak_hr_pct_predicted"
               class="form-control form-control-sm bg-light"
               value="<?php echo attr($fv('peak_hr_pct_predicted')); ?>"
               readonly title="<?php echo xla('Calculated: peak HR / max predicted × 100'); ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_peak_bp_sys"><?php echo xlt('Peak BP Sys. (mmHg)'); ?></label>
        <input type="number" id="st_peak_bp_sys" name="peak_bp_systolic_mmhg"
               class="form-control form-control-sm" min="0" max="400"
               value="<?php echo attr($fv('peak_bp_systolic_mmhg')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_peak_bp_dia"><?php echo xlt('Peak BP Dia. (mmHg)'); ?></label>
        <input type="number" id="st_peak_bp_dia" name="peak_bp_diastolic_mmhg"
               class="form-control form-control-sm" min="0" max="200"
               value="<?php echo attr($fv('peak_bp_diastolic_mmhg')); ?>" <?php echo $dis; ?>>
    </div>
</div>
<div class="row g-2 mt-1">
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_double_product"><?php echo xlt('Double Product'); ?></label>
        <input type="text" id="st_double_product" name="double_product"
               class="form-control form-control-sm bg-light"
               value="<?php echo attr($fv('double_product')); ?>"
               readonly title="<?php echo xla('Calculated: peak HR × peak systolic BP'); ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_exercise_duration_s"><?php echo xlt('Exercise Duration (s)'); ?></label>
        <input type="number" id="st_exercise_duration_s" name="exercise_duration_s"
               class="form-control form-control-sm" min="0" max="3600"
               value="<?php echo attr($fv('exercise_duration_s')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-1 d-flex align-items-end">
        <span id="st_duration_mmss" class="form-control form-control-sm bg-light text-center">
            <?php echo text($secsToMmss($fv('exercise_duration_s') ?: null)); ?>
        </span>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_mets_achieved"><?php echo xlt('METs Achieved'); ?></label>
        <input type="number" id="st_mets_achieved" name="mets_achieved"
               class="form-control form-control-sm" step="0.1" min="0" max="30"
               value="<?php echo attr($fv('mets_achieved')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small" for="st_bp_response"><?php echo xlt('BP Response'); ?></label>
        <select id="st_bp_response" name="bp_response"
                class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($bpResponses, $fv('bp_response'), xlt('-- Select --')); ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_hr_recovery"><?php echo xlt('HR Recovery 1 min'); ?></label>
        <input type="number" id="st_hr_recovery" name="hr_recovery_1min_bpm"
               class="form-control form-control-sm" min="0" max="200"
               value="<?php echo attr($fv('hr_recovery_1min_bpm')); ?>" <?php echo $dis; ?>>
    </div>
</div>
</fieldset>

<!-- Section 4: Symptoms, ST and arrhythmias -->
<fieldset class="mb-4">
<legend class="cardiology-section-title"><?php echo xlt('Symptoms, ST Segment & Arrhythmias'); ?></legend>
<div class="row g-2">
    <div class="col-12 col-md-4">
        <label class="form-label small" for="st_symptom"><?php echo xlt('Main Symptom'); ?></label>
        <select id="st_symptom" name="symptom"
                class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($symptoms, $fv('symptom'), xlt('-- Select --')); ?>
        </select>
    </div>
    <div class="col-12 col-md-5">
        <label class="form-label small"><?php echo xlt('Angina Index'); ?></label>
        <div class="d-flex gap-3 mt-1">
            <?php
            $anginaOpts = [['0', xlt('None')], ['1', xlt('Non-limiting')], ['2', xlt('Test-limiting')]];
            $curAngina  = $fv('angina_index', '0');
            foreach ($anginaOpts as [$av, $al]) : ?>
            <div class="form-check">
                <input class="form-check-input" type="radio"
                       name="angina_index" id="st_angina_<?php echo attr($av); ?>"
                       value="<?php echo attr($av); ?>"
                       <?php echo ($curAngina === $av) ? 'checked' : ''; ?>
                       <?php echo $dis; ?>>
                <label class="form-check-label small" for="st_angina_<?php echo attr($av); ?>">
                    <?php echo $al; ?>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="row g-2 mt-1">
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_st_dep"><?php echo xlt('ST Depression (mm)'); ?></label>
        <input type="number" id="st_st_dep" name="st_depression_mm"
               class="form-control form-control-sm" step="0.1" min="0" max="10"
               value="<?php echo attr($fv('st_depression_mm')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_st_elev"><?php echo xlt('ST Elevation (mm)'); ?></label>
        <input type="number" id="st_st_elev" name="st_elevation_mm"
               class="form-control form-control-sm" step="0.1" min="0" max="10"
               value="<?php echo attr($fv('st_elevation_mm')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small" for="st_st_leads"><?php echo xlt('ST Leads'); ?></label>
        <input type="text" id="st_st_leads" name="st_leads"
               class="form-control form-control-sm"
               value="<?php echo attr($fv('st_leads')); ?>"
               placeholder="<?php echo xla('e.g. V4-V6'); ?>"
               <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_st_onset_hr"><?php echo xlt('ST Onset HR (bpm)'); ?></label>
        <input type="number" id="st_st_onset_hr" name="st_onset_hr_bpm"
               class="form-control form-control-sm" min="0" max="300"
               value="<?php echo attr($fv('st_onset_hr_bpm')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-12 mt-1">
        <label class="form-label small" for="st_arrhythmias"><?php echo xlt('Arrhythmias'); ?></label>
        <input type="text" id="st_arrhythmias" name="arrhythmias"
               class="form-control form-control-sm"
               value="<?php echo attr($fv('arrhythmias')); ?>"
               placeholder="<?php echo xla('Arrhythmias observed during exercise or recovery'); ?>"
               <?php echo $dis; ?>>
    </div>
</div>
</fieldset>

<!-- Section 5: Result -->
<fieldset class="mb-4">
<legend class="cardiology-section-title"><?php echo xlt('Result'); ?></legend>
<div class="row g-2">
    <div class="col-12 col-md-4">
        <label class="form-label small" for="st_stop_reason"><?php echo xlt('Stop Reason'); ?></label>
        <select id="st_stop_reason" name="stop_reason"
                class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($stopReasons, $fv('stop_reason'), xlt('-- Select --')); ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_duke_score"><?php echo xlt('Duke Score'); ?></label>
        <input type="text" id="st_duke_score" name="duke_score"
               class="form-control form-control-sm bg-light"
               value="<?php echo attr($fv('duke_score')); ?>"
               readonly title="<?php echo xla('Calculated: min - 5×ST_depression - 4×angina_index'); ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="st_duke_label"><?php echo xlt('Duke Risk'); ?></label>
        <input type="text" id="st_duke_label" class="form-control form-control-sm bg-light" readonly value="">
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label small" for="st_result"><?php echo xlt('Overall Result'); ?></label>
        <select id="st_result" name="result"
                class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($stressResults, $fv('result'), xlt('-- Select --')); ?>
        </select>
    </div>
</div>
<div class="row g-2 mt-1">
    <div class="col-12">
        <label class="form-label" for="st_conclusion">
            <strong><?php echo xlt('Conclusion'); ?></strong>
        </label>
        <textarea id="st_conclusion" name="conclusion" class="form-control" rows="4"
                  placeholder="<?php echo xla('Study conclusion for the PDF report'); ?>"
                  <?php echo $dis; ?>><?php echo text($fv('conclusion')); ?></textarea>
    </div>
    <div class="col-12 mt-2">
        <label class="form-label" for="st_comments"><?php echo xlt('Comments'); ?></label>
        <textarea id="st_comments" name="comments" class="form-control" rows="2"
                  placeholder="<?php echo xla('Additional comments or recommendations'); ?>"
                  <?php echo $dis; ?>><?php echo text($fv('comments')); ?></textarea>
    </div>
</div>
</fieldset>

<!-- Section 6: Stages -->
<fieldset class="mb-4">
<legend class="cardiology-section-title"><?php echo xlt('Stage-by-Stage Records'); ?></legend>
<?php if (!$readOnly) : ?>
<div class="mb-2">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="st-add-stage">
        <i class="fa fa-plus me-1" aria-hidden="true"></i><?php echo xlt('Add Stage'); ?>
    </button>
</div>
<?php endif; ?>
<div class="table-responsive">
<table class="table table-sm table-bordered align-middle" id="st-stages-table">
    <thead class="table-light">
        <tr>
            <th><?php echo xlt('Phase'); ?></th>
            <th><?php echo xlt('Stage'); ?></th>
            <th><?php echo xlt('Elapsed (s)'); ?></th>
            <th><?php echo xlt('Speed (km/h)'); ?></th>
            <th><?php echo xlt('Grade (%)'); ?></th>
            <th>METs</th>
            <th>HR</th>
            <th><?php echo xlt('BP Sys'); ?></th>
            <th><?php echo xlt('BP Dia'); ?></th>
            <th>ST (mm)</th>
            <th><?php echo xlt('Notes'); ?></th>
            <?php if (!$readOnly) : ?><th></th><?php endif; ?>
        </tr>
    </thead>
    <tbody id="st-stages-tbody">
    <?php
    $phaseLabels = ['rest' => xlt('Rest'), 'exercise' => xlt('Exercise'), 'recovery' => xlt('Recovery')];
    foreach ($stages as $idx => $stage) :
        $stageId = (int)($stage['id'] ?? 0);
    ?>
    <tr class="st-stage-row">
        <input type="hidden" name="stages[<?php echo attr((string)$idx); ?>][id]"
               value="<?php echo attr((string)$stageId); ?>">
        <input type="hidden" name="stages[<?php echo attr((string)$idx); ?>][delete]"
               value="0" class="st-delete-flag">
        <td>
            <?php if ($readOnly) : ?>
                <?php echo text($phaseLabels[$stage['phase'] ?? ''] ?? $stage['phase'] ?? ''); ?>
            <?php else : ?>
            <select name="stages[<?php echo attr((string)$idx); ?>][phase]"
                    class="form-select form-select-sm">
                <?php foreach ($phaseLabels as $pv => $pl) : ?>
                <option value="<?php echo attr($pv); ?>"
                    <?php echo (($stage['phase'] ?? '') === $pv) ? 'selected' : ''; ?>>
                    <?php echo $pl; ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </td>
        <?php
        $stCols = [
            'stage_no'          => ['1',   '0','20'],
            'elapsed_s'         => ['1',   '0','3600'],
            'speed_kmh'         => ['0.1', '0','30'],
            'grade_pct'         => ['0.1', '0','30'],
            'mets'              => ['0.1', '0','30'],
            'hr_bpm'            => ['1',   '0','300'],
            'bp_systolic_mmhg'  => ['1',   '0','400'],
            'bp_diastolic_mmhg' => ['1',   '0','200'],
            'st_mm'             => ['0.1', '-5','5'],
        ];
        foreach ($stCols as $fn => [$step, $min, $max]) :
            $v = (string)($stage[$fn] ?? ''); ?>
        <td>
            <?php if ($readOnly) : ?>
                <?php echo text($v); ?>
            <?php else : ?>
            <input type="number"
                   name="stages[<?php echo attr((string)$idx); ?>][<?php echo attr($fn); ?>]"
                   class="form-control form-control-sm"
                   step="<?php echo attr($step); ?>"
                   min="<?php echo attr($min); ?>" max="<?php echo attr($max); ?>"
                   value="<?php echo attr($v); ?>">
            <?php endif; ?>
        </td>
        <?php endforeach; ?>
        <td>
            <?php if ($readOnly) : ?>
                <?php echo text($stage['symptom_note'] ?? ''); ?>
            <?php else : ?>
            <input type="text"
                   name="stages[<?php echo attr((string)$idx); ?>][symptom_note]"
                   class="form-control form-control-sm"
                   value="<?php echo attr($stage['symptom_note'] ?? ''); ?>">
            <?php endif; ?>
        </td>
        <?php if (!$readOnly) : ?>
        <td class="text-center">
            <button type="button" class="btn btn-xs btn-outline-danger st-remove-stage">
                <i class="fa fa-times" aria-hidden="true"></i>
            </button>
        </td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if (empty($stages)) : ?>
<p class="text-muted small"><?php echo xlt('No stages recorded.'); ?></p>
<?php endif; ?>

<?php if (!$readOnly) : ?>
<template id="st-stage-template">
    <tr class="st-stage-row">
        <input type="hidden" name="stages[__IDX__][id]" value="0">
        <input type="hidden" name="stages[__IDX__][delete]" value="0" class="st-delete-flag">
        <td>
            <select name="stages[__IDX__][phase]" class="form-select form-select-sm">
                <option value="exercise" selected><?php echo xlt('Exercise'); ?></option>
                <option value="rest"><?php echo xlt('Rest'); ?></option>
                <option value="recovery"><?php echo xlt('Recovery'); ?></option>
            </select>
        </td>
        <td><input type="number" name="stages[__IDX__][stage_no]"          class="form-control form-control-sm" min="0"  max="20"   step="1"   value=""></td>
        <td><input type="number" name="stages[__IDX__][elapsed_s]"         class="form-control form-control-sm" min="0"  max="3600" step="1"   value=""></td>
        <td><input type="number" name="stages[__IDX__][speed_kmh]"         class="form-control form-control-sm" min="0"  max="30"   step="0.1" value=""></td>
        <td><input type="number" name="stages[__IDX__][grade_pct]"         class="form-control form-control-sm" min="0"  max="30"   step="0.1" value=""></td>
        <td><input type="number" name="stages[__IDX__][mets]"              class="form-control form-control-sm" min="0"  max="30"   step="0.1" value=""></td>
        <td><input type="number" name="stages[__IDX__][hr_bpm]"            class="form-control form-control-sm" min="0"  max="300"  step="1"   value=""></td>
        <td><input type="number" name="stages[__IDX__][bp_systolic_mmhg]"  class="form-control form-control-sm" min="0"  max="400"  step="1"   value=""></td>
        <td><input type="number" name="stages[__IDX__][bp_diastolic_mmhg]" class="form-control form-control-sm" min="0"  max="200"  step="1"   value=""></td>
        <td><input type="number" name="stages[__IDX__][st_mm]"             class="form-control form-control-sm" min="-5" max="5"    step="0.1" value=""></td>
        <td><input type="text"   name="stages[__IDX__][symptom_note]"      class="form-control form-control-sm" value=""></td>
        <td class="text-center">
            <button type="button" class="btn btn-xs btn-outline-danger st-remove-stage">
                <i class="fa fa-times" aria-hidden="true"></i>
            </button>
        </td>
    </tr>
</template>
<?php endif; ?>
</fieldset>

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
