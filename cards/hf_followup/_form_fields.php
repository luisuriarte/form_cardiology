<?php

/**
 * Heart Failure Follow-up card – _form_fields.php
 *
 * Included by new.php and view.php.
 * Renders all form inputs for a heart failure follow-up study.
 *
 * Variables expected in scope:
 *   $study    ?array  Existing study row, or pre-filled defaults for a new study.
 *   $readOnly bool    True when status = 'final'.
 *   $nyhaClasses, $hfTypes, $etiologies, $hfStages, $edemaGrades, $adherences array
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\HfFollowup
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/CardDate.php';

$disabled = $readOnly ? 'disabled' : '';

function hfFld(string $name, ?array $study, string $default = ''): string
{
    return htmlspecialchars((string)($study[$name] ?? $default), ENT_QUOTES, 'UTF-8');
}

$studyDateVal = hfFld('study_date', $study, date('Y-m-d H:i:s'));

$lvefDateVal = hfFld('lvef_date', $study);
if (strpos($lvefDateVal, ' ') !== false) {
    $lvefDateVal = substr($lvefDateVal, 0, 10);
}
?>

<!-- ======================================================================= -->
<!-- Section: Functional Class & Heart Failure Profile                      -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-heart text-danger me-1"></i>
        <?php echo xlt('Functional Class & Heart Failure Profile'); ?>
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
                <label for="nyha_class" class="form-label">
                    <?php echo xlt('NYHA Functional Class'); ?>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <select class="form-select form-select-sm"
                        id="nyha_class"
                        name="nyha_class"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select NYHA class'); ?> --</option>
                    <?php foreach ($nyhaClasses as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (hfFld('nyha_class', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="nyha_numeric_display" class="form-label">
                    <?php echo xlt('NYHA Class Number'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo xlt('Calculated'); ?></span>
                </label>
                <input type="text"
                       class="form-control form-control-sm bg-light"
                       id="nyha_numeric_display"
                       value="<?php echo attr(hfFld('nyha_numeric', $study)); ?>"
                       readonly>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label for="hf_type" class="form-label">
                    <?php echo xlt('Heart Failure Type'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="hf_type"
                        name="hf_type"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select heart failure type'); ?> --</option>
                    <?php foreach ($hfTypes as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (hfFld('hf_type', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="etiology" class="form-label">
                    <?php echo xlt('Etiology'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="etiology"
                        name="etiology"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select etiology'); ?> --</option>
                    <?php foreach ($etiologies as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (hfFld('etiology', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="hf_stage" class="form-label">
                    <?php echo xlt('Heart Failure Stage'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="hf_stage"
                        name="hf_stage"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select heart failure stage'); ?> --</option>
                    <?php foreach ($hfStages as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (hfFld('hf_stage', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Volume Status & Vital Signs                                    -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-weight text-primary me-1"></i>
        <?php echo xlt('Volume Status & Vital Signs'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="weight_kg" class="form-label">
                    <?php echo xlt('Current Weight (kg)'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="weight_kg"
                       name="weight_kg"
                       min="20"
                       max="300"
                       value="<?php echo attr(hfFld('weight_kg', $study)); ?>"
                       placeholder="20 - 300"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="dry_weight_kg" class="form-label">
                    <?php echo xlt('Dry Weight (kg)'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="dry_weight_kg"
                       name="dry_weight_kg"
                       min="20"
                       max="300"
                       value="<?php echo attr(hfFld('dry_weight_kg', $study)); ?>"
                       placeholder="20 - 300"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="weight_change_display" class="form-label">
                    <?php echo xlt('Weight Change (kg)'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo xlt('Calculated'); ?></span>
                </label>
                <input type="text"
                       class="form-control form-control-sm bg-light"
                       id="weight_change_display"
                       value="<?php echo attr(hfFld('weight_change_kg', $study)); ?>"
                       readonly>
            </div>
            <div class="col-md-3">
                <label for="heart_rate_bpm" class="form-label">
                    <?php echo xlt('Heart Rate (bpm)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="heart_rate_bpm"
                       name="heart_rate_bpm"
                       min="20"
                       max="250"
                       value="<?php echo attr(hfFld('heart_rate_bpm', $study)); ?>"
                       placeholder="20 - 250"
                       <?php echo $disabled; ?>>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label for="bp_systolic_mmhg" class="form-label">
                    <?php echo xlt('Systolic Blood Pressure (mmHg)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="bp_systolic_mmhg"
                       name="bp_systolic_mmhg"
                       min="40"
                       max="300"
                       value="<?php echo attr(hfFld('bp_systolic_mmhg', $study)); ?>"
                       placeholder="40 - 300"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="bp_diastolic_mmhg" class="form-label">
                    <?php echo xlt('Diastolic Blood Pressure (mmHg)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="bp_diastolic_mmhg"
                       name="bp_diastolic_mmhg"
                       min="20"
                       max="200"
                       value="<?php echo attr(hfFld('bp_diastolic_mmhg', $study)); ?>"
                       placeholder="20 - 200"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="spo2_pct" class="form-label">
                    <?php echo xlt('Oxygen Saturation (%)'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="spo2_pct"
                       name="spo2_pct"
                       min="50"
                       max="100"
                       value="<?php echo attr(hfFld('spo2_pct', $study)); ?>"
                       placeholder="50 - 100"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="edema" class="form-label">
                    <?php echo xlt('Peripheral Edema'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="edema"
                        name="edema"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select edema grade'); ?> --</option>
                    <?php foreach ($edemaGrades as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (hfFld('edema', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="jugular_distension"
                           name="jugular_distension"
                           value="1"
                           <?php echo (hfFld('jugular_distension', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="jugular_distension">
                        <?php echo xlt('Jugular Venous Distension'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="rales"
                           name="rales"
                           value="1"
                           <?php echo (hfFld('rales', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="rales">
                        <?php echo xlt('Pulmonary Rales'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="orthopnea"
                           name="orthopnea"
                           value="1"
                           <?php echo (hfFld('orthopnea', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="orthopnea">
                        <?php echo xlt('Orthopnea'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="pnd"
                           name="pnd"
                           value="1"
                           <?php echo (hfFld('pnd', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="pnd">
                        <?php echo xlt('Paroxysmal Nocturnal Dyspnea'); ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="congestion_note" class="form-label">
                    <?php echo xlt('Congestion Findings'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="congestion_note"
                          name="congestion_note"
                          rows="2"
                          placeholder="<?php echo xla('Congestion findings and physical examination details'); ?>"
                          <?php echo $disabled; ?>><?php echo text(hfFld('congestion_note', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Cardiac Function & Laboratory Values                          -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-flask text-primary me-1"></i>
        <?php echo xlt('Cardiac Function & Laboratory Values'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="lvef_pct" class="form-label">
                    <?php echo xlt('LVEF (%)'); ?>
                </label>
                <input type="number"
                       step="0.1"
                       class="form-control form-control-sm"
                       id="lvef_pct"
                       name="lvef_pct"
                       min="5"
                       max="90"
                       value="<?php echo attr(hfFld('lvef_pct', $study)); ?>"
                       placeholder="5 - 90"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="lvef_date" class="form-label">
                    <?php echo xlt('LVEF Measurement Date'); ?>
                </label>
                <input type="text"
                       class="datepicker form-control form-control-sm"
                       id="lvef_date"
                       name="lvef_date"
                       value="<?php echo attr(\OpenEMR\Forms\Cardiology\CardDate::displayDate($lvefDateVal)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="nt_probnp_pg_ml" class="form-label">
                    <?php echo xlt('NT-proBNP (pg/mL)'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="nt_probnp_pg_ml"
                       name="nt_probnp_pg_ml"
                       min="0"
                       max="70000"
                       value="<?php echo attr(hfFld('nt_probnp_pg_ml', $study)); ?>"
                       placeholder="0 - 70000"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="bnp_pg_ml" class="form-label">
                    <?php echo xlt('BNP (pg/mL)'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="bnp_pg_ml"
                       name="bnp_pg_ml"
                       min="0"
                       max="5000"
                       value="<?php echo attr(hfFld('bnp_pg_ml', $study)); ?>"
                       placeholder="0 - 5000"
                       <?php echo $disabled; ?>>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label for="creatinine_mg_dl" class="form-label">
                    <?php echo xlt('Serum Creatinine (mg/dL)'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="creatinine_mg_dl"
                       name="creatinine_mg_dl"
                       min="0.1"
                       max="15"
                       value="<?php echo attr(hfFld('creatinine_mg_dl', $study)); ?>"
                       placeholder="0.1 - 15"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-4">
                <label for="potassium_mmol_l" class="form-label">
                    <?php echo xlt('Serum Potassium (mmol/L)'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="potassium_mmol_l"
                       name="potassium_mmol_l"
                       min="1.5"
                       max="8"
                       value="<?php echo attr(hfFld('potassium_mmol_l', $study)); ?>"
                       placeholder="1.5 - 8"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-4">
                <label for="sodium_mmol_l" class="form-label">
                    <?php echo xlt('Serum Sodium (mmol/L)'); ?>
                </label>
                <input type="number"
                       step="0.1"
                       class="form-control form-control-sm"
                       id="sodium_mmol_l"
                       name="sodium_mmol_l"
                       min="100"
                       max="170"
                       value="<?php echo attr(hfFld('sodium_mmol_l', $study)); ?>"
                       placeholder="100 - 170"
                       <?php echo $disabled; ?>>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Treatment & Follow-up                                          -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-pills text-primary me-1"></i>
        <?php echo xlt('Treatment & Follow-up'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="adherence" class="form-label">
                    <?php echo xlt('Adherence'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="adherence"
                        name="adherence"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select adherence'); ?> --</option>
                    <?php foreach ($adherences as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (hfFld('adherence', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="on_raas_inhibitor"
                           name="on_raas_inhibitor"
                           value="1"
                           <?php echo (hfFld('on_raas_inhibitor', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label" for="on_raas_inhibitor">
                        <?php echo xlt('On RAAS Inhibitor'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="on_beta_blocker"
                           name="on_beta_blocker"
                           value="1"
                           <?php echo (hfFld('on_beta_blocker', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label" for="on_beta_blocker">
                        <?php echo xlt('On Beta Blocker'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="on_mra"
                           name="on_mra"
                           value="1"
                           <?php echo (hfFld('on_mra', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label" for="on_mra">
                        <?php echo xlt('On MRA'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="on_sglt2_inhibitor"
                           name="on_sglt2_inhibitor"
                           value="1"
                           <?php echo (hfFld('on_sglt2_inhibitor', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label" for="on_sglt2_inhibitor">
                        <?php echo xlt('On SGLT2 Inhibitor'); ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="on_loop_diuretic"
                           name="on_loop_diuretic"
                           value="1"
                           <?php echo (hfFld('on_loop_diuretic', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label" for="on_loop_diuretic">
                        <?php echo xlt('On Loop Diuretic'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-9">
                <label for="medications_note" class="form-label">
                    <?php echo xlt('Medications & Doses'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="medications_note"
                          name="medications_note"
                          rows="2"
                          placeholder="<?php echo xla('Current cardiovascular medications and doses'); ?>"
                          <?php echo $disabled; ?>><?php echo text(hfFld('medications_note', $study)); ?></textarea>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label for="hospitalizations_since_last" class="form-label">
                    <?php echo xlt('Hospitalizations Since Last Visit'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="hospitalizations_since_last"
                       name="hospitalizations_since_last"
                       min="0"
                       max="50"
                       value="<?php echo attr(hfFld('hospitalizations_since_last', $study)); ?>"
                       placeholder="0 - 50"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="er_visits_since_last" class="form-label">
                    <?php echo xlt('Emergency Visits Since Last Visit'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="er_visits_since_last"
                       name="er_visits_since_last"
                       min="0"
                       max="50"
                       value="<?php echo attr(hfFld('er_visits_since_last', $study)); ?>"
                       placeholder="0 - 50"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="education_given"
                           name="education_given"
                           value="1"
                           <?php echo (hfFld('education_given', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="education_given">
                        <?php echo xlt('Patient Education Given'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <label for="next_control_date" class="form-label">
                    <?php echo xlt('Next Control Date'); ?>
                </label>
                <input type="text"
                       class="datepicker form-control form-control-sm"
                       id="next_control_date"
                       name="next_control_date"
                       value="<?php echo attr(\OpenEMR\Forms\Cardiology\CardDate::displayDate(hfFld('next_control_date', $study))); ?>"
                       <?php echo $disabled; ?>>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="plan" class="form-label">
                    <?php echo xlt('Plan'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="plan"
                          name="plan"
                          rows="2"
                          placeholder="<?php echo xla('Management plan for the next follow-up period'); ?>"
                          <?php echo $disabled; ?>><?php echo text(hfFld('plan', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Conclusion & Comments                                          -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-file-medical-alt text-success me-1"></i>
        <?php echo xlt('Conclusion & Comments'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
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
                          placeholder="<?php echo xla('Assessment conclusion printed in the report'); ?>"
                          <?php echo $disabled; ?>><?php echo text(hfFld('conclusion', $study)); ?></textarea>
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
                          <?php echo $disabled; ?>><?php echo text(hfFld('comments', $study)); ?></textarea>
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
