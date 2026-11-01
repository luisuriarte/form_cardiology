<?php

/**
 * Fitness card – _form_fields.php
 *
 * Included by new.php and view.php.
 * Renders all form inputs for Cardiology Fitness Certificate.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Fitness
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

$disabled = $readOnly ? 'disabled' : '';

function fitFld(string $name, ?array $study, string $default = ''): string
{
    return htmlspecialchars((string)($study[$name] ?? $default), ENT_QUOTES, 'UTF-8');
}

$studyDateVal = fitFld('study_date', $study, date('Y-m-d\TH:i'));
if (strpos($studyDateVal, ' ') !== false) {
    $studyDateVal = str_replace(' ', 'T', substr($studyDateVal, 0, 16));
}

$validUntilVal = fitFld('valid_until', $study, date('Y-m-d', strtotime('+12 months')));
?>

<!-- ======================================================================= -->
<!-- Section: Purpose & Examination Context                                  -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-info-circle text-primary me-1"></i>
        <?php echo xlt('Purpose & Examination Context'); ?>
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
            <div class="col-md-4">
                <label for="purpose" class="form-label">
                    <?php echo xlt('Evaluation Purpose'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="purpose"
                        name="purpose"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select purpose'); ?> --</option>
                    <?php foreach ($purposes as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (fitFld('purpose', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label for="purpose_detail" class="form-label">
                    <?php echo xlt('Specific Sport / Activity / Job Detail'); ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="purpose_detail"
                       name="purpose_detail"
                       value="<?php echo attr(fitFld('purpose_detail', $study)); ?>"
                       placeholder="<?php echo xla('e.g. Marathon running, soccer, gym training, occupational'); ?>"
                       <?php echo $disabled; ?>>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Clinical History & Symptoms                                    -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-notes-medical text-primary me-1"></i>
        <?php echo xlt('Clinical History & Symptoms'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="symptoms_note" class="form-label">
                    <?php echo xlt('Cardiovascular Symptoms / History'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="symptoms_note"
                          name="symptoms_note"
                          rows="2"
                          placeholder="<?php echo xla('Chest pain, dyspnea, palpitations, syncope or asymptomatic'); ?>"
                          <?php echo $disabled; ?>><?php echo text(fitFld('symptoms_note', $study)); ?></textarea>
            </div>
            <div class="col-md-6">
                <label for="family_history_note" class="form-label">
                    <?php echo xlt('Family History'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="family_history_note"
                          name="family_history_note"
                          rows="2"
                          placeholder="<?php echo xla('Sudden cardiac death, premature CAD, inherited arrhythmias, cardiomyopathy'); ?>"
                          <?php echo $disabled; ?>><?php echo text(fitFld('family_history_note', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Physical Examination & Vitals                                  -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-heartbeat text-danger me-1"></i>
        <?php echo xlt('Physical Examination & Vitals'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label for="fit_height_cm" class="form-label"><?php echo xlt('Height (cm)'); ?></label>
                <input type="number" step="0.1" class="form-control form-control-sm"
                       id="fit_height_cm" name="height_cm"
                       min="50" max="250"
                       value="<?php echo attr(fitFld('height_cm', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-2">
                <label for="fit_weight_kg" class="form-label"><?php echo xlt('Weight (kg)'); ?></label>
                <input type="number" step="0.1" class="form-control form-control-sm"
                       id="fit_weight_kg" name="weight_kg"
                       min="2" max="400"
                       value="<?php echo attr(fitFld('weight_kg', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-2">
                <label for="fit_bmi" class="form-label">
                    <?php echo xlt('BMI (kg/m²)'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo xlt('Calculated'); ?></span>
                </label>
                <input type="text" class="form-control form-control-sm bg-light"
                       id="fit_bmi" name="bmi"
                       value="<?php echo attr(fitFld('bmi', $study)); ?>"
                       readonly>
            </div>
            <div class="col-md-2">
                <label for="heart_rate_bpm" class="form-label"><?php echo xlt('Heart Rate (bpm)'); ?></label>
                <input type="number" class="form-control form-control-sm"
                       id="heart_rate_bpm" name="heart_rate_bpm"
                       min="20" max="300"
                       value="<?php echo attr(fitFld('heart_rate_bpm', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-2">
                <label for="bp_systolic_mmhg" class="form-label"><?php echo xlt('BP Systolic (mmHg)'); ?></label>
                <input type="number" class="form-control form-control-sm"
                       id="bp_systolic_mmhg" name="bp_systolic_mmhg"
                       min="40" max="300"
                       value="<?php echo attr(fitFld('bp_systolic_mmhg', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-2">
                <label for="bp_diastolic_mmhg" class="form-label"><?php echo xlt('BP Diastolic (mmHg)'); ?></label>
                <input type="number" class="form-control form-control-sm"
                       id="bp_diastolic_mmhg" name="bp_diastolic_mmhg"
                       min="20" max="200"
                       value="<?php echo attr(fitFld('bp_diastolic_mmhg', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-12 mt-2">
                <label for="exam_findings" class="form-label"><?php echo xlt('Physical Examination Findings'); ?></label>
                <textarea class="form-control form-control-sm"
                          id="exam_findings"
                          name="exam_findings"
                          rows="2"
                          placeholder="<?php echo xla('Cardiac auscultation, peripheral pulses, murmurs, edema'); ?>"
                          <?php echo $disabled; ?>><?php echo text(fitFld('exam_findings', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: ECG & Additional Studies                                       -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-wave-square text-primary me-1"></i>
        <?php echo xlt('Electrocardiogram & Additional Studies'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="ecg_result" class="form-label"><?php echo xlt('Resting ECG Result'); ?></label>
                <select class="form-select form-select-sm"
                        id="ecg_result"
                        name="ecg_result"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select ECG result'); ?> --</option>
                    <?php foreach ($ecgResults as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (fitFld('ecg_result', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label for="ecg_note" class="form-label"><?php echo xlt('ECG Summary / Findings'); ?></label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="ecg_note"
                       name="ecg_note"
                       value="<?php echo attr(fitFld('ecg_note', $study)); ?>"
                       placeholder="<?php echo xla('e.g. Sinus rhythm, normal intervals and repolarization'); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-12">
                <label for="additional_studies" class="form-label"><?php echo xlt('Additional Diagnostic Studies'); ?></label>
                <textarea class="form-control form-control-sm"
                          id="additional_studies"
                          name="additional_studies"
                          rows="2"
                          placeholder="<?php echo xla('Echocardiogram, stress test, Holter monitoring, laboratory tests (if performed)'); ?>"
                          <?php echo $disabled; ?>><?php echo text(fitFld('additional_studies', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Certificate Determination & Validity                           -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-certificate text-success me-1"></i>
        <?php echo xlt('Certificate Determination & Validity'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="determination" class="form-label required">
                    <?php echo xlt('Fitness Determination'); ?>
                    <span class="text-danger">*</span>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <select class="form-select form-select-sm"
                        id="determination"
                        name="determination"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select determination'); ?> --</option>
                    <?php foreach ($determinations as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (fitFld('determination', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="valid_until" class="form-label required">
                    <?php echo xlt('Valid Until (Expiration Date)'); ?>
                    <span class="text-danger">*</span>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="valid_until"
                       name="valid_until"
                       value="<?php echo attr($validUntilVal); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-12" id="restrictions_group">
                <label for="restrictions" class="form-label">
                    <?php echo xlt('Activity Restrictions'); ?>
                    <span class="text-danger small" id="restrictions_required_badge" style="display: none;">
                        * (<?php echo xlt('Required when Fit with Restrictions'); ?>)
                    </span>
                </label>
                <textarea class="form-control form-control-sm"
                          id="restrictions"
                          name="restrictions"
                          rows="2"
                          placeholder="<?php echo xla('Specify competitive or physical load restrictions if applicable'); ?>"
                          <?php echo $disabled; ?>><?php echo text(fitFld('restrictions', $study)); ?></textarea>
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
                          rows="2"
                          placeholder="<?php echo xla('Medical certificate conclusion printed on document'); ?>"
                          <?php echo $disabled; ?>><?php echo text(fitFld('conclusion', $study)); ?></textarea>
            </div>
            <div class="col-12">
                <label for="comments" class="form-label"><?php echo xlt('Comments / Recommendations'); ?></label>
                <textarea class="form-control form-control-sm"
                          id="comments"
                          name="comments"
                          rows="2"
                          placeholder="<?php echo xla('Additional health recommendations or periodic follow-up notes'); ?>"
                          <?php echo $disabled; ?>><?php echo text(fitFld('comments', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

