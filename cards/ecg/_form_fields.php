<?php

/**
 * ECG card – _form_fields.php
 *
 * Included by new.php and view.php.
 * Renders all form inputs for the 12-lead ECG study.
 *
 * Variables expected in scope:
 *   $study    ?array  Existing study row, or null for a new study.
 *   $readOnly bool    True when status = 'final'.
 *   $rhythms, $avConductions, $ventConductions, $hypertrophies, $stTChanges array
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\ECG
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

$disabled = $readOnly ? 'disabled' : '';

function ecgFld(string $name, ?array $study, string $default = ''): string
{
    return htmlspecialchars((string)($study[$name] ?? $default), ENT_QUOTES, 'UTF-8');
}

$studyDateVal = ecgFld('study_date', $study, date('Y-m-d\TH:i'));
if (strpos($studyDateVal, ' ') !== false) {
    $studyDateVal = str_replace(' ', 'T', substr($studyDateVal, 0, 16));
}
?>

<!-- ======================================================================= -->
<!-- Section: Identification & Clinical Context                              -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-info-circle text-primary me-1"></i>
        <?php echo xlt('Clinical Context'); ?>
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
            <div class="col-md-8">
                <label for="indication" class="form-label">
                    <?php echo xlt('Indication'); ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="indication"
                       name="indication"
                       value="<?php echo attr(ecgFld('indication', $study)); ?>"
                       placeholder="<?php echo xla('e.g. Chest pain, palpitations, pre-op evaluation'); ?>"
                       <?php echo $disabled; ?>>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Rhythm & Intervals                                            -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-heartbeat text-danger me-1"></i>
        <?php echo xlt('Rhythm & Intervals'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="ecg_heart_rate" class="form-label">
                    <?php echo xlt('Heart Rate (bpm)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="ecg_heart_rate"
                       name="heart_rate_bpm"
                       min="20"
                       max="300"
                       value="<?php echo attr(ecgFld('heart_rate_bpm', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="rhythm" class="form-label">
                    <?php echo xlt('Rhythm'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="rhythm"
                        name="rhythm"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select rhythm'); ?> --</option>
                    <?php foreach ($rhythms as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (ecgFld('rhythm', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="axis_deg" class="form-label">
                    <?php echo xlt('Electrical Axis (°)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="axis_deg"
                       name="axis_deg"
                       min="-180"
                       max="180"
                       value="<?php echo attr(ecgFld('axis_deg', $study)); ?>"
                       placeholder="-180 to 180"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="pr_ms" class="form-label">
                    <?php echo xlt('PR Interval (ms)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="pr_ms"
                       name="pr_ms"
                       min="60"
                       max="400"
                       value="<?php echo attr(ecgFld('pr_ms', $study)); ?>"
                       placeholder="60 - 400"
                       <?php echo $disabled; ?>>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label for="qrs_ms" class="form-label">
                    <?php echo xlt('QRS Duration (ms)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="qrs_ms"
                       name="qrs_ms"
                       min="40"
                       max="300"
                       value="<?php echo attr(ecgFld('qrs_ms', $study)); ?>"
                       placeholder="40 - 300"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="ecg_qt" class="form-label">
                    <?php echo xlt('QT Interval (ms)'); ?>
                </label>
                <input type="number"
                       class="form-control form-control-sm"
                       id="ecg_qt"
                       name="qt_ms"
                       min="200"
                       max="700"
                       value="<?php echo attr(ecgFld('qt_ms', $study)); ?>"
                       placeholder="200 - 700"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="ecg_qtc_bazett" class="form-label">
                    <?php echo xlt('QTc Bazett (ms)'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo xlt('Calculated'); ?></span>
                </label>
                <input type="text"
                       class="form-control form-control-sm bg-light"
                       id="ecg_qtc_bazett"
                       name="qtc_bazett_ms"
                       value="<?php echo attr(ecgFld('qtc_bazett_ms', $study)); ?>"
                       readonly>
            </div>
            <div class="col-md-3">
                <label for="ecg_qtc_fridericia" class="form-label">
                    <?php echo xlt('QTc Fridericia (ms)'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo xlt('Calculated'); ?></span>
                </label>
                <input type="text"
                       class="form-control form-control-sm bg-light"
                       id="ecg_qtc_fridericia"
                       name="qtc_fridericia_ms"
                       value="<?php echo attr(ecgFld('qtc_fridericia_ms', $study)); ?>"
                       readonly>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Conduction, Hypertrophy & Repolarization                      -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-wave-square text-primary me-1"></i>
        <?php echo xlt('Conduction & Morphology'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="av_conduction" class="form-label">
                    <?php echo xlt('AV Conduction'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="av_conduction"
                        name="av_conduction"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select AV conduction'); ?> --</option>
                    <?php foreach ($avConductions as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (ecgFld('av_conduction', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="ventricular_conduction" class="form-label">
                    <?php echo xlt('Ventricular Conduction'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="ventricular_conduction"
                        name="ventricular_conduction"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select intraventricular conduction'); ?> --</option>
                    <?php foreach ($ventConductions as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (ecgFld('ventricular_conduction', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="hypertrophy" class="form-label">
                    <?php echo xlt('Chamber Hypertrophy'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="hypertrophy"
                        name="hypertrophy"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select hypertrophy'); ?> --</option>
                    <?php foreach ($hypertrophies as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (ecgFld('hypertrophy', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label for="st_t_changes" class="form-label">
                    <?php echo xlt('ST-T Segment Changes'); ?>
                </label>
                <select class="form-select form-select-sm"
                        id="st_t_changes"
                        name="st_t_changes"
                        <?php echo $disabled; ?>>
                    <option value="">-- <?php echo xlt('Select ST-T changes'); ?> --</option>
                    <?php foreach ($stTChanges as $opt) : ?>
                    <option value="<?php echo attr($opt['option_id']); ?>"
                        <?php echo (ecgFld('st_t_changes', $study) === $opt['option_id']) ? 'selected' : ''; ?>>
                        <?php echo text(xl_list_label($opt['title'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label for="st_t_detail" class="form-label">
                    <?php echo xlt('ST-T Changes Detail'); ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="st_t_detail"
                       name="st_t_detail"
                       value="<?php echo attr(ecgFld('st_t_detail', $study)); ?>"
                       placeholder="<?php echo xla('e.g. 1mm elevation in V2-V4, T inversion in DII, DIII, aVF'); ?>"
                       <?php echo $disabled; ?>>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="pathologic_q"
                           name="pathologic_q"
                           value="1"
                           <?php echo (ecgFld('pathologic_q', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="pathologic_q">
                        <?php echo xlt('Pathologic Q Waves Present'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-8">
                <label for="q_leads" class="form-label">
                    <?php echo xlt('Pathologic Q Leads'); ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="q_leads"
                       name="q_leads"
                       value="<?php echo attr(ecgFld('q_leads', $study)); ?>"
                       placeholder="<?php echo xla('e.g. V1-V3 or II, III, aVF'); ?>"
                       <?php echo $disabled; ?>>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Findings, Conclusion & Comments                               -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-file-medical-alt text-success me-1"></i>
        <?php echo xlt('Interpretation & Conclusion'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label for="findings" class="form-label">
                    <?php echo xlt('Electrocardiographic Findings'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="findings"
                          name="findings"
                          rows="3"
                          placeholder="<?php echo xla('Detailed electrocardiographic findings'); ?>"
                          <?php echo $disabled; ?>><?php echo text(ecgFld('findings', $study)); ?></textarea>
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
                          placeholder="<?php echo xla('Study conclusion printed in the PDF report'); ?>"
                          <?php echo $disabled; ?>><?php echo text(ecgFld('conclusion', $study)); ?></textarea>
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
                          <?php echo $disabled; ?>><?php echo text(ecgFld('comments', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

