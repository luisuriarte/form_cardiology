<?php

/**
 * Anticoagulation card – _form_fields.php
 *
 * Included by new.php and view.php.
 * Renders all form inputs for an anticoagulation study.
 *
 * Variables expected in scope:
 *   $study    ?array  Existing study row, or pre-filled defaults for a new study.
 *   $readOnly bool    True when status = 'final'.
 *   $indications, $drugs, $sampleTypes, $adherences array
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Anticoag
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

$disabled = $readOnly ? 'disabled' : '';

function anticoagFld(string $name, ?array $study, string $default = ''): string
{
    return htmlspecialchars((string)($study[$name] ?? $default), ENT_QUOTES, 'UTF-8');
}

function anticoagRangeLabel(?string $code): string
{
    return match ($code) {
        'in' => xlt('In range'),
        'below' => xlt('Below range'),
        'above' => xlt('Above range'),
        default => '',
    };
}

$studyDateVal = anticoagFld('study_date', $study, date('Y-m-d\TH:i'));
if (strpos($studyDateVal, ' ') !== false) {
    $studyDateVal = str_replace(' ', 'T', substr($studyDateVal, 0, 16));
}
?>

<!-- ======================================================================= -->
<!-- Section: Clinical Context & Treatment                                   -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-info-circle text-primary me-1"></i>
        <?php echo xlt('Clinical Context & Treatment'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="study_date" class="form-label required">
                    <?php echo xlt('Study Date & Time'); ?>
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
                <label for="indication" class="form-label">
                    <?php echo xlt('Indication'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="indication"
                        name="indication"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select indication'); ?> --</option>
                    <?php foreach ($indications as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (anticoagFld('indication', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="drug" class="form-label">
                    <?php echo xlt('Anticoagulant Drug'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="drug"
                        name="drug"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select drug'); ?> --</option>
                    <?php foreach ($drugs as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (anticoagFld('drug', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label for="sample_type" class="form-label">
                    <?php echo xlt('INR Sample Type'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="sample_type"
                        name="sample_type"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select sample type'); ?> --</option>
                    <?php foreach ($sampleTypes as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (anticoagFld('sample_type', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: INR & Target Range                                             -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-tint text-danger me-1"></i>
        <?php echo xlt('INR & Target Range'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="inr_value" class="form-label">
                    <?php echo xlt('INR Value'); ?>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="inr_value"
                       name="inr_value"
                       min="0.5"
                       max="10"
                       value="<?php echo attr(anticoagFld('inr_value', $study)); ?>"
                       placeholder="0.5 - 10"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="target_inr_low" class="form-label">
                    <?php echo xlt('Target INR Low'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="target_inr_low"
                       name="target_inr_low"
                       min="1"
                       max="4"
                       value="<?php echo attr(anticoagFld('target_inr_low', $study)); ?>"
                       placeholder="1.0 - 4.0"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="target_inr_high" class="form-label">
                    <?php echo xlt('Target INR High'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="target_inr_high"
                       name="target_inr_high"
                       min="1.5"
                       max="5"
                       value="<?php echo attr(anticoagFld('target_inr_high', $study)); ?>"
                       placeholder="1.5 - 5.0"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="in_range_display" class="form-label">
                    <?php echo xlt('INR Position'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo xlt('Calculated'); ?></span>
                </label>
                <input type="text"
                       class="form-control form-control-sm bg-light"
                       id="in_range_display"
                       value="<?php echo attr(anticoagRangeLabel($study['in_range'] ?? null)); ?>"
                       readonly>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Weekly Dose                                                    -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-pills text-primary me-1"></i>
        <?php echo xlt('Weekly Dose'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="current_weekly_dose_mg" class="form-label">
                    <?php echo xlt('Current Weekly Dose (mg)'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="current_weekly_dose_mg"
                       name="current_weekly_dose_mg"
                       min="0"
                       max="200"
                       value="<?php echo attr(anticoagFld('current_weekly_dose_mg', $study)); ?>"
                       placeholder="0 - 200"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="new_weekly_dose_mg" class="form-label">
                    <?php echo xlt('New Weekly Dose (mg)'); ?>
                </label>
                <input type="number"
                       step="0.01"
                       class="form-control form-control-sm"
                       id="new_weekly_dose_mg"
                       name="new_weekly_dose_mg"
                       min="0"
                       max="200"
                       value="<?php echo attr(anticoagFld('new_weekly_dose_mg', $study)); ?>"
                       placeholder="0 - 200"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="dose_change_display" class="form-label">
                    <?php echo xlt('Dose Change (%)'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo xlt('Calculated'); ?></span>
                </label>
                <input type="text"
                       class="form-control form-control-sm bg-light"
                       id="dose_change_display"
                       value="<?php echo anticoagFld('dose_change_pct', $study) !== '' ? attr(anticoagFld('dose_change_pct', $study) . ' %') : ''; ?>"
                       readonly>
            </div>
            <div class="col-md-3">
                <label for="dosing_schedule" class="form-label">
                    <?php echo xlt('Dosing Schedule'); ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="dosing_schedule"
                       name="dosing_schedule"
                       value="<?php echo attr(anticoagFld('dosing_schedule', $study)); ?>"
                       placeholder="<?php echo xla('e.g. Monday to Friday 5 mg, weekend 2.5 mg'); ?>"
                       <?php echo $disabled; ?>>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="missed_doses" class="form-label">
                    <?php echo xlt('Missed Doses'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="missed_doses"
                          name="missed_doses"
                          rows="2"
                          placeholder="<?php echo xla('Missed doses or adherence details reported by the patient'); ?>"
                          <?php echo $disabled; ?>><?php echo text(anticoagFld('missed_doses', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Events & Adherence                                             -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-exclamation-triangle text-warning me-1"></i>
        <?php echo xlt('Events & Adherence'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="bleeding_event"
                           name="bleeding_event"
                           value="1"
                           <?php echo (anticoagFld('bleeding_event', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="bleeding_event">
                        <?php echo xlt('Bleeding Event'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="thrombotic_event"
                           name="thrombotic_event"
                           value="1"
                           <?php echo (anticoagFld('thrombotic_event', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="thrombotic_event">
                        <?php echo xlt('Thrombotic Event'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-6">
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
                        <?php echo (anticoagFld('adherence', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="event_detail" class="form-label">
                    <?php echo xlt('Event Detail'); ?>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required when a bleeding or thrombotic event is recorded'); ?>)</span>
                </label>
                <textarea class="form-control form-control-sm"
                          id="event_detail"
                          name="event_detail"
                          rows="2"
                          placeholder="<?php echo xla('Description of the bleeding or thrombotic event'); ?>"
                          <?php echo $disabled; ?>><?php echo text(anticoagFld('event_detail', $study)); ?></textarea>
            </div>
            <div class="col-12">
                <label for="interacting_factors" class="form-label">
                    <?php echo xlt('Interacting Factors'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="interacting_factors"
                          name="interacting_factors"
                          rows="2"
                          placeholder="<?php echo xla('Diet, drugs, or clinical factors that may affect anticoagulation'); ?>"
                          <?php echo $disabled; ?>><?php echo text(anticoagFld('interacting_factors', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Follow-up, Plan & Conclusion                                   -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-calendar-check text-success me-1"></i>
        <?php echo xlt('Follow-up & Conclusion'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="next_control_date" class="form-label">
                    <?php echo xlt('Next Control Date'); ?>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="next_control_date"
                       name="next_control_date"
                       value="<?php echo attr(anticoagFld('next_control_date', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-8">
                <label for="plan" class="form-label">
                    <?php echo xlt('Plan'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="plan"
                          name="plan"
                          rows="2"
                          placeholder="<?php echo xla('Management plan for the next follow-up period'); ?>"
                          <?php echo $disabled; ?>><?php echo text(anticoagFld('plan', $study)); ?></textarea>
            </div>
        </div>
        <div class="row g-3 mt-1">
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
                          <?php echo $disabled; ?>><?php echo text(anticoagFld('conclusion', $study)); ?></textarea>
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
                          <?php echo $disabled; ?>><?php echo text(anticoagFld('comments', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>
