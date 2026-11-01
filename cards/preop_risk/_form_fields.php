<?php

/**
 * Preoperative Risk card – _form_fields.php
 *
 * Included by new.php and view.php.
 * Renders all form inputs for Preoperative Cardiovascular Risk.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\PreopRisk
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

$disabled = $readOnly ? 'disabled' : '';

function preopFld(string $name, ?array $study, string $default = ''): string
{
    return htmlspecialchars((string)($study[$name] ?? $default), ENT_QUOTES, 'UTF-8');
}

$studyDateVal = preopFld('study_date', $study, date('Y-m-d\TH:i'));
if (strpos($studyDateVal, ' ') !== false) {
    $studyDateVal = str_replace(' ', 'T', substr($studyDateVal, 0, 16));
}
?>

<!-- ======================================================================= -->
<!-- Section: Planned Surgery & Clinical Context                             -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-procedures text-primary me-1"></i>
        <?php echo xlt('Planned Surgery & Urgency'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="study_date" class="form-label required">
                    <?php echo xlt('Evaluation Date & Time'); ?>
                </label>
                <input type="datetime-local"
                       class="form-control form-control-sm"
                       id="study_date"
                       name="study_date"
                       value="<?php echo attr($studyDateVal); ?>"
                       required
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-5">
                <label for="surgery_description" class="form-label">
                    <?php echo xlt('Planned Surgery / Procedure'); ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="surgery_description"
                       name="surgery_description"
                       value="<?php echo attr(preopFld('surgery_description', $study)); ?>"
                       placeholder="<?php echo xla('e.g. Total hip arthroplasty, colectomy'); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-2">
                <label for="surgery_date" class="form-label">
                    <?php echo xlt('Surgery Date'); ?>
                </label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="surgery_date"
                       name="surgery_date"
                       value="<?php echo attr(preopFld('surgery_date', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-2">
                <label for="surgery_urgency" class="form-label">
                    <?php echo xlt('Surgical Urgency'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="surgery_urgency"
                        name="surgery_urgency"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select urgency'); ?> --</option>
                    <?php foreach ($surgeryUrgencies as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (preopFld('surgery_urgency', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label for="surgery_risk_class" class="form-label">
                    <?php echo xlt('Inherent Surgical Risk (Physician-set)'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="surgery_risk_class"
                        name="surgery_risk_class"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select surgical risk'); ?> --</option>
                    <?php foreach ($surgeryRisks as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (preopFld('surgery_risk_class', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="functional_capacity" class="form-label">
                    <?php echo xlt('Functional Capacity'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="functional_capacity"
                        name="functional_capacity"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select capacity'); ?> --</option>
                    <?php foreach ($functionalCapacities as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (preopFld('functional_capacity', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="asa_class" class="form-label">
                    <?php echo xlt('ASA Physical Status'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="asa_class"
                        name="asa_class"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select ASA status'); ?> --</option>
                    <?php foreach ($asaClasses as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (preopFld('asa_class', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Vital Signs & Measurements                                     -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-heartbeat text-danger me-1"></i>
        <?php echo xlt('Vital Signs & Cardiac Function'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label for="height_cm" class="form-label"><?php echo xlt('Height (cm)'); ?></label>
                <input type="number" step="0.1" class="form-control form-control-sm"
                       id="height_cm" name="height_cm"
                       min="50" max="250"
                       value="<?php echo attr(preopFld('height_cm', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-2">
                <label for="weight_kg" class="form-label"><?php echo xlt('Weight (kg)'); ?></label>
                <input type="number" step="0.1" class="form-control form-control-sm"
                       id="weight_kg" name="weight_kg"
                       min="2" max="400"
                       value="<?php echo attr(preopFld('weight_kg', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-2">
                <label for="heart_rate_bpm" class="form-label"><?php echo xlt('Heart Rate (bpm)'); ?></label>
                <input type="number" class="form-control form-control-sm"
                       id="heart_rate_bpm" name="heart_rate_bpm"
                       min="20" max="300"
                       value="<?php echo attr(preopFld('heart_rate_bpm', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-2">
                <label for="bp_systolic_mmhg" class="form-label"><?php echo xlt('BP Systolic (mmHg)'); ?></label>
                <input type="number" class="form-control form-control-sm"
                       id="bp_systolic_mmhg" name="bp_systolic_mmhg"
                       min="40" max="300"
                       value="<?php echo attr(preopFld('bp_systolic_mmhg', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-2">
                <label for="bp_diastolic_mmhg" class="form-label"><?php echo xlt('BP Diastolic (mmHg)'); ?></label>
                <input type="number" class="form-control form-control-sm"
                       id="bp_diastolic_mmhg" name="bp_diastolic_mmhg"
                       min="20" max="200"
                       value="<?php echo attr(preopFld('bp_diastolic_mmhg', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-2">
                <label for="lvef_pct" class="form-label"><?php echo xlt('LVEF (%)'); ?></label>
                <input type="number" step="0.1" class="form-control form-control-sm"
                       id="lvef_pct" name="lvef_pct"
                       min="5" max="90"
                       value="<?php echo attr(preopFld('lvef_pct', $study)); ?>"
                       placeholder="<?php echo xla('e.g. 55'); ?>"
                       <?php echo $disabled; ?>>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Lee Revised Cardiac Risk Index (RCRI)                          -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2 d-flex justify-content-between align-items-center">
        <div>
            <i class="fa fa-calculator text-primary me-1"></i>
            <?php echo xlt('Revised Cardiac Risk Index (Lee RCRI)'); ?>
        </div>
        <div>
            <span class="badge bg-primary me-2">
                <?php echo xlt('Score'); ?>: <span id="lee_score_display"><?php echo text(preopFld('lee_score', $study, '0')); ?></span> / 6
            </span>
            <span class="badge bg-dark">
                <?php echo xlt('Class'); ?>: <span id="lee_class_display"><?php echo text(preopFld('lee_class', $study, 'I')); ?></span>
            </span>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input lee-flag" type="checkbox"
                           id="lee_high_risk_surgery" name="lee_high_risk_surgery" value="1"
                           <?php echo (preopFld('lee_high_risk_surgery', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label" for="lee_high_risk_surgery">
                        <?php echo xlt('High-risk surgery (intraperitoneal, intrathoracic, or suprainguinal vascular)'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input lee-flag" type="checkbox"
                           id="lee_ischemic_heart_disease" name="lee_ischemic_heart_disease" value="1"
                           <?php echo (preopFld('lee_ischemic_heart_disease', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label" for="lee_ischemic_heart_disease">
                        <?php echo xlt('History of ischemic heart disease (prior MI, positive stress test, angina, nitrate use)'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input lee-flag" type="checkbox"
                           id="lee_heart_failure" name="lee_heart_failure" value="1"
                           <?php echo (preopFld('lee_heart_failure', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label" for="lee_heart_failure">
                        <?php echo xlt('History of congestive heart failure'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input lee-flag" type="checkbox"
                           id="lee_cerebrovascular_disease" name="lee_cerebrovascular_disease" value="1"
                           <?php echo (preopFld('lee_cerebrovascular_disease', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label" for="lee_cerebrovascular_disease">
                        <?php echo xlt('History of cerebrovascular disease (stroke or TIA)'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input lee-flag" type="checkbox"
                           id="lee_insulin_diabetes" name="lee_insulin_diabetes" value="1"
                           <?php echo (preopFld('lee_insulin_diabetes', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label" for="lee_insulin_diabetes">
                        <?php echo xlt('Preoperative insulin therapy for diabetes mellitus'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input lee-flag" type="checkbox"
                           id="lee_creatinine_gt_2" name="lee_creatinine_gt_2" value="1"
                           <?php echo (preopFld('lee_creatinine_gt_2', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label" for="lee_creatinine_gt_2">
                        <?php echo xlt('Preoperative serum creatinine > 2.0 mg/dL (> 177 µmol/L)'); ?>
                    </label>
                </div>
            </div>
        </div>

        <input type="hidden" id="lee_score" name="lee_score" value="<?php echo attr(preopFld('lee_score', $study, '0')); ?>">
        <input type="hidden" id="lee_class" name="lee_class" value="<?php echo attr(preopFld('lee_class', $study, 'I')); ?>">
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Complementary Studies & Relevant Labs                          -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-notes-medical text-primary me-1"></i>
        <?php echo xlt('Complementary Studies & Medications'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="ecg_summary" class="form-label"><?php echo xlt('Preoperative ECG Summary'); ?></label>
                <textarea class="form-control form-control-sm" id="ecg_summary" name="ecg_summary" rows="2"
                          placeholder="<?php echo xla('Rhythm, conduction or ST-T changes'); ?>"
                          <?php echo $disabled; ?>><?php echo text(preopFld('ecg_summary', $study)); ?></textarea>
            </div>
            <div class="col-md-6">
                <label for="echo_summary" class="form-label"><?php echo xlt('Echocardiogram Summary'); ?></label>
                <textarea class="form-control form-control-sm" id="echo_summary" name="echo_summary" rows="2"
                          placeholder="<?php echo xla('LVEF, wall motion, valve pathology'); ?>"
                          <?php echo $disabled; ?>><?php echo text(preopFld('echo_summary', $study)); ?></textarea>
            </div>
            <div class="col-md-6">
                <label for="medications_note" class="form-label"><?php echo xlt('Current Medications'); ?></label>
                <textarea class="form-control form-control-sm" id="medications_note" name="medications_note" rows="2"
                          placeholder="<?php echo xla('Beta blockers, statins, antiplatelets, anticoagulants, ACEi/ARBs'); ?>"
                          <?php echo $disabled; ?>><?php echo text(preopFld('medications_note', $study)); ?></textarea>
            </div>
            <div class="col-md-6">
                <label for="labs_note" class="form-label"><?php echo xlt('Relevant Laboratory Values'); ?></label>
                <textarea class="form-control form-control-sm" id="labs_note" name="labs_note" rows="2"
                          placeholder="<?php echo xla('Creatinine, hemoglobin, electrolytes, coagulation profile'); ?>"
                          <?php echo $disabled; ?>><?php echo text(preopFld('labs_note', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Recommendation, Conclusion & Perioperative Plan                -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-clipboard-check text-success me-1"></i>
        <?php echo xlt('Recommendation & Perioperative Plan'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="recommendation" class="form-label required">
                    <?php echo xlt('Cardiovascular Recommendation'); ?>
                    <span class="text-danger">*</span>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <select class="form-select form-select-sm"
                        id="recommendation"
                        name="recommendation"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select recommendation'); ?> --</option>
                    <?php foreach ($recommendations as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (preopFld('recommendation', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="perioperative_recommendations" class="form-label">
                    <?php echo xlt('Perioperative Management Instructions'); ?>
                </label>
                <textarea class="form-control form-control-sm" id="perioperative_recommendations" name="perioperative_recommendations" rows="2"
                          placeholder="<?php echo xla('e.g. Continue beta blockers, hold ACEi morning of surgery'); ?>"
                          <?php echo $disabled; ?>><?php echo text(preopFld('perioperative_recommendations', $study)); ?></textarea>
            </div>
            <div class="col-12">
                <label for="conclusion" class="form-label required">
                    <?php echo xlt('Conclusion & Risk Assessment Summary'); ?>
                    <span class="text-danger">*</span>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <textarea class="form-control form-control-sm" id="conclusion" name="conclusion" rows="3"
                          placeholder="<?php echo xla('Final cardiovascular risk summary printed in the report'); ?>"
                          <?php echo $disabled; ?>><?php echo text(preopFld('conclusion', $study)); ?></textarea>
            </div>
            <div class="col-12">
                <label for="comments" class="form-label">
                    <?php echo xlt('Additional Comments'); ?>
                </label>
                <textarea class="form-control form-control-sm" id="comments" name="comments" rows="2"
                          placeholder="<?php echo xla('Additional comments or follow-up notes'); ?>"
                          <?php echo $disabled; ?>><?php echo text(preopFld('comments', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

