<?php

/**
 * Chagas Disease Follow-up card – _form_fields.php
 *
 * Included by new.php and view.php.
 * Renders all form inputs for a Chagas disease follow-up study.
 *
 * Variables expected in scope:
 *   $study    ?array  Existing study row, or pre-filled defaults for a new study.
 *   $readOnly bool    True when status = 'final'.
 *   $phases, $transmissionRoutes, $serologyStatuses, $serologyTestTypes,
 *   $serologyResults, $kuschnirStages, $abcdStages, $devices,
 *   $treatmentStatuses, $treatmentDrugs, $nyhaClasses, $wallMotionOptions array
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Chagas
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

$disabled = $readOnly ? 'disabled' : '';

function chagasFld(string $name, ?array $study, string $default = ''): string
{
    return htmlspecialchars((string)($study[$name] ?? $default), ENT_QUOTES, 'UTF-8');
}

function chagasDateValue(string $name, ?array $study): string
{
    $value = chagasFld($name, $study);
    if ($value === '') {
        return '';
    }
    return substr(str_replace(' ', 'T', $value), 0, 10);
}

function chagasSelect(
    string $name,
    ?array $study,
    array $options,
    string $placeholder,
    string $disabled
): void {
    ?>
    <select class="form-select form-select-sm"
            id="<?php echo attr($name); ?>"
            name="<?php echo attr($name); ?>"
            <?php echo $disabled; ?>>
        <option value="">-- <?php echo text($placeholder); ?> --</option>
        <?php foreach ($options as $opt) : ?>
        <option value="<?php echo attr($opt['option_id']); ?>"
            <?php echo (chagasFld($name, $study) === $opt['option_id']) ? 'selected' : ''; ?>>
            <?php echo text(xl_list_label($opt['title'])); ?>
        </option>
        <?php endforeach; ?>
    </select>
    <?php
}

$studyDateVal = chagasFld('study_date', $study, date('Y-m-d\TH:i'));
if (strpos($studyDateVal, ' ') !== false) {
    $studyDateVal = str_replace(' ', 'T', substr($studyDateVal, 0, 16));
}
?>

<!-- ======================================================================= -->
<!-- Section: Phase & Epidemiology                                           -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-bug text-danger me-1"></i>
        <?php echo xlt('Phase & Epidemiology'); ?>
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
                <label for="disease_phase" class="form-label">
                    <?php echo xlt('Disease Phase / Clinical Form'); ?>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <?php chagasSelect('disease_phase', $study, $phases, xlt('Select disease phase'), $disabled); ?>
            </div>
            <div class="col-md-4">
                <label for="transmission_route" class="form-label">
                    <?php echo xlt('Probable Transmission Route'); ?>
                </label>
                <?php chagasSelect('transmission_route', $study, $transmissionRoutes, xlt('Select transmission route'), $disabled); ?>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label for="origin_area" class="form-label">
                    <?php echo xlt('Country or Region of Origin'); ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       id="origin_area"
                       name="origin_area"
                       maxlength="255"
                       value="<?php echo attr(chagasFld('origin_area', $study)); ?>"
                       placeholder="<?php echo xla('Country or region of origin'); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-4">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="endemic_residence"
                           name="endemic_residence"
                           value="1"
                           <?php echo (chagasFld('endemic_residence', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="endemic_residence">
                        <?php echo xlt('Residence in an Endemic Area'); ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="epidemiological_history" class="form-label">
                    <?php echo xlt('Epidemiological History'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="epidemiological_history"
                          name="epidemiological_history"
                          rows="2"
                          placeholder="<?php echo xla('Epidemiological history and exposure details'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('epidemiological_history', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Serology                                                       -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-flask text-primary me-1"></i>
        <?php echo xlt('Serology'); ?>
    </div>
    <div class="card-body">
        <?php for ($i = 1; $i <= 3; $i++) : ?>
        <div class="row g-3 <?php echo $i > 1 ? 'mt-1' : ''; ?>">
            <div class="col-md-4">
                <label for="serology_<?php echo $i; ?>_type" class="form-label">
                    <?php echo xlt('Serology Test'); ?> <?php echo text((string)$i); ?>
                </label>
                <?php chagasSelect('serology_' . $i . '_type', $study, $serologyTestTypes, xlt('Select test type'), $disabled); ?>
            </div>
            <div class="col-md-4">
                <label for="serology_<?php echo $i; ?>_result" class="form-label">
                    <?php echo xlt('Serology Result'); ?> <?php echo text((string)$i); ?>
                </label>
                <?php chagasSelect('serology_' . $i . '_result', $study, $serologyResults, xlt('Select result'), $disabled); ?>
            </div>
            <div class="col-md-4">
                <label for="serology_<?php echo $i; ?>_date" class="form-label">
                    <?php echo xlt('Serology Date'); ?> <?php echo text((string)$i); ?>
                </label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="serology_<?php echo $i; ?>_date"
                       name="serology_<?php echo $i; ?>_date"
                       value="<?php echo attr(chagasDateValue('serology_' . $i . '_date', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
        </div>
        <?php endfor; ?>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label for="serology_status" class="form-label">
                    <?php echo xlt('Overall Serology Status'); ?>
                    <span class="small text-muted ms-1">(<?php echo xlt('Required to finalize'); ?>)</span>
                </label>
                <?php chagasSelect('serology_status', $study, $serologyStatuses, xlt('Select serology status'), $disabled); ?>
            </div>
            <div class="col-md-8">
                <label for="serology_note" class="form-label">
                    <?php echo xlt('Serology Details'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="serology_note"
                          name="serology_note"
                          rows="2"
                          placeholder="<?php echo xla('Serology laboratory details or comments'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('serology_note', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Stage                                                          -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-layer-group text-secondary me-1"></i>
        <?php echo xlt('Stage (physician-selected)'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="kuschnir_stage" class="form-label">
                    <?php echo xlt('Kuschnir Stage'); ?>
                </label>
                <?php chagasSelect('kuschnir_stage', $study, $kuschnirStages, xlt('Select Kuschnir stage'), $disabled); ?>
                <div class="form-text">
                    <?php echo xlt('Stages are selected by the physician according to the consensus applied; the criteria are not evaluated by this form.'); ?>
                </div>
            </div>
            <div class="col-md-6">
                <label for="abcd_stage" class="form-label">
                    <?php echo xlt('ABCD Stage'); ?>
                </label>
                <?php chagasSelect('abcd_stage', $study, $abcdStages, xlt('Select ABCD stage'), $disabled); ?>
                <div class="form-text">
                    <?php echo xlt('Stages are selected by the physician according to the consensus applied; the criteria are not evaluated by this form.'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Electrocardiogram                                              -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-heartbeat text-danger me-1"></i>
        <?php echo xlt('Electrocardiogram'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="ecg_done"
                           name="ecg_done"
                           value="1"
                           <?php echo (chagasFld('ecg_done', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="ecg_done">
                        <?php echo xlt('ECG Performed'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <label for="ecg_date" class="form-label">
                    <?php echo xlt('ECG Date'); ?>
                </label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="ecg_date"
                       name="ecg_date"
                       value="<?php echo attr(chagasDateValue('ecg_date', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="ecg_normal"
                           name="ecg_normal"
                           value="1"
                           <?php echo (chagasFld('ecg_normal', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="ecg_normal">
                        <?php echo xlt('ECG Normal'); ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ecg_rbbb" name="ecg_rbbb" value="1"
                        <?php echo (chagasFld('ecg_rbbb', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="ecg_rbbb"><?php echo xlt('Right Bundle Branch Block'); ?></label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ecg_lafb" name="ecg_lafb" value="1"
                        <?php echo (chagasFld('ecg_lafb', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="ecg_lafb"><?php echo xlt('Left Anterior Fascicular Block'); ?></label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ecg_lpfb" name="ecg_lpfb" value="1"
                        <?php echo (chagasFld('ecg_lpfb', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="ecg_lpfb"><?php echo xlt('Left Posterior Fascicular Block'); ?></label>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ecg_av_block" name="ecg_av_block" value="1"
                        <?php echo (chagasFld('ecg_av_block', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="ecg_av_block"><?php echo xlt('Atrioventricular Block'); ?></label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ecg_q_waves" name="ecg_q_waves" value="1"
                        <?php echo (chagasFld('ecg_q_waves', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="ecg_q_waves"><?php echo xlt('Pathologic Q Waves'); ?></label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ecg_st_t_changes" name="ecg_st_t_changes" value="1"
                        <?php echo (chagasFld('ecg_st_t_changes', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="ecg_st_t_changes"><?php echo xlt('ST-T Changes'); ?></label>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ecg_low_voltage" name="ecg_low_voltage" value="1"
                        <?php echo (chagasFld('ecg_low_voltage', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="ecg_low_voltage"><?php echo xlt('Low Voltage'); ?></label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ecg_arrhythmia" name="ecg_arrhythmia" value="1"
                        <?php echo (chagasFld('ecg_arrhythmia', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="ecg_arrhythmia"><?php echo xlt('Arrhythmia'); ?></label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ecg_other" name="ecg_other" value="1"
                        <?php echo (chagasFld('ecg_other', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="ecg_other"><?php echo xlt('Other ECG Finding'); ?></label>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="ecg_findings" class="form-label">
                    <?php echo xlt('ECG Findings'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="ecg_findings"
                          name="ecg_findings"
                          rows="2"
                          placeholder="<?php echo xla('Detailed ECG findings'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('ecg_findings', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Chest X-ray & Echocardiogram                                   -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-x-ray text-primary me-1"></i>
        <?php echo xlt('Chest X-ray & Echocardiogram'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="chest_xray_done"
                           name="chest_xray_done"
                           value="1"
                           <?php echo (chagasFld('chest_xray_done', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="chest_xray_done">
                        <?php echo xlt('Chest X-ray Performed'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <label for="chest_xray_date" class="form-label">
                    <?php echo xlt('Chest X-ray Date'); ?>
                </label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="chest_xray_date"
                       name="chest_xray_date"
                       value="<?php echo attr(chagasDateValue('chest_xray_date', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="chest_xray_cardiomegaly"
                           name="chest_xray_cardiomegaly"
                           value="1"
                           <?php echo (chagasFld('chest_xray_cardiomegaly', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="chest_xray_cardiomegaly">
                        <?php echo xlt('Cardiomegaly'); ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="chest_xray_findings" class="form-label">
                    <?php echo xlt('Chest X-ray Findings'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="chest_xray_findings"
                          name="chest_xray_findings"
                          rows="2"
                          placeholder="<?php echo xla('Detailed chest X-ray findings'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('chest_xray_findings', $study)); ?></textarea>
            </div>
        </div>
        <hr class="my-2">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="echo_done"
                           name="echo_done"
                           value="1"
                           <?php echo (chagasFld('echo_done', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="echo_done">
                        <?php echo xlt('Echocardiogram Performed'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <label for="echo_date" class="form-label">
                    <?php echo xlt('Echocardiogram Date'); ?>
                </label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="echo_date"
                       name="echo_date"
                       value="<?php echo attr(chagasDateValue('echo_date', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
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
                       value="<?php echo attr(chagasFld('lvef_pct', $study)); ?>"
                       placeholder="5 - 90"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <label for="lvedd_mm" class="form-label">
                    <?php echo xlt('LV End-Diastolic Diameter (mm)'); ?>
                </label>
                <input type="number"
                       step="0.1"
                       class="form-control form-control-sm"
                       id="lvedd_mm"
                       name="lvedd_mm"
                       min="20"
                       max="100"
                       value="<?php echo attr(chagasFld('lvedd_mm', $study)); ?>"
                       placeholder="20 - 100"
                       <?php echo $disabled; ?>>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="echo_aneurysm" name="echo_aneurysm" value="1"
                        <?php echo (chagasFld('echo_aneurysm', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="echo_aneurysm"><?php echo xlt('Left Ventricular Aneurysm'); ?></label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="echo_thrombus" name="echo_thrombus" value="1"
                        <?php echo (chagasFld('echo_thrombus', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="echo_thrombus"><?php echo xlt('Left Ventricular Thrombus'); ?></label>
                </div>
            </div>
            <div class="col-md-6">
                <label for="echo_wall_motion" class="form-label">
                    <?php echo xlt('Wall Motion'); ?>
                </label>
                <?php chagasSelect('echo_wall_motion', $study, $wallMotionOptions, xlt('Select wall motion'), $disabled); ?>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="echo_findings" class="form-label">
                    <?php echo xlt('Echocardiogram Findings'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="echo_findings"
                          name="echo_findings"
                          rows="2"
                          placeholder="<?php echo xla('Detailed echocardiogram findings'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('echo_findings', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Heart Failure & Functional Class                               -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-heart text-danger me-1"></i>
        <?php echo xlt('Heart Failure & Functional Class'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="heart_failure"
                           name="heart_failure"
                           value="1"
                           <?php echo (chagasFld('heart_failure', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="heart_failure">
                        <?php echo xlt('Heart Failure Present'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-4">
                <label for="nyha_class" class="form-label">
                    <?php echo xlt('NYHA Functional Class'); ?>
                    <span class="small text-muted ms-1">(<?php echo xlt('When heart failure is present'); ?>)</span>
                </label>
                <?php chagasSelect('nyha_class', $study, $nyhaClasses, xlt('Select NYHA class'), $disabled); ?>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="hf_findings" class="form-label">
                    <?php echo xlt('Heart Failure Findings'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="hf_findings"
                          name="hf_findings"
                          rows="2"
                          placeholder="<?php echo xla('Heart failure findings and functional details'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('hf_findings', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Cardiac Device                                                 -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-microchip text-secondary me-1"></i>
        <?php echo xlt('Cardiac Device'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="cardiac_device" class="form-label">
                    <?php echo xlt('Device'); ?>
                </label>
                <?php chagasSelect('cardiac_device', $study, $devices, xlt('Select device'), $disabled); ?>
            </div>
            <div class="col-md-8">
                <label for="device_note" class="form-label">
                    <?php echo xlt('Device Details'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="device_note"
                          name="device_note"
                          rows="2"
                          placeholder="<?php echo xla('Cardiac device details'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('device_note', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Digestive Involvement                                          -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-stomach text-warning me-1"></i>
        <?php echo xlt('Digestive Involvement'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="digestive_involvement"
                           name="digestive_involvement"
                           value="1"
                           <?php echo (chagasFld('digestive_involvement', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="digestive_involvement">
                        <?php echo xlt('Digestive Involvement Present'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="megaesophagus" name="megaesophagus" value="1"
                        <?php echo (chagasFld('megaesophagus', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="megaesophagus"><?php echo xlt('Megaesophagus'); ?></label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="megacolon" name="megacolon" value="1"
                        <?php echo (chagasFld('megacolon', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="megacolon"><?php echo xlt('Megacolon'); ?></label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="constipation" name="constipation" value="1"
                        <?php echo (chagasFld('constipation', $study) === '1') ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                    <label class="form-check-label" for="constipation"><?php echo xlt('Chronic Constipation'); ?></label>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="digestive_findings" class="form-label">
                    <?php echo xlt('Digestive Findings'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="digestive_findings"
                          name="digestive_findings"
                          rows="2"
                          placeholder="<?php echo xla('Digestive involvement findings'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('digestive_findings', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Etiologic Treatment                                            -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-pills text-primary me-1"></i>
        <?php echo xlt('Etiologic Treatment'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="treatment_status" class="form-label">
                    <?php echo xlt('Treatment Status'); ?>
                </label>
                <?php chagasSelect('treatment_status', $study, $treatmentStatuses, xlt('Select treatment status'), $disabled); ?>
            </div>
            <div class="col-md-4">
                <label for="treatment_drug" class="form-label">
                    <?php echo xlt('Treatment Drug'); ?>
                </label>
                <?php chagasSelect('treatment_drug', $study, $treatmentDrugs, xlt('Select treatment drug'), $disabled); ?>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label for="treatment_start_date" class="form-label">
                    <?php echo xlt('Treatment Start Date'); ?>
                </label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="treatment_start_date"
                       name="treatment_start_date"
                       value="<?php echo attr(chagasDateValue('treatment_start_date', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-6">
                <label for="treatment_end_date" class="form-label">
                    <?php echo xlt('Treatment End Date'); ?>
                </label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="treatment_end_date"
                       name="treatment_end_date"
                       value="<?php echo attr(chagasDateValue('treatment_end_date', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="treatment_note" class="form-label">
                    <?php echo xlt('Treatment Details'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="treatment_note"
                          name="treatment_note"
                          rows="2"
                          placeholder="<?php echo xla('Treatment tolerance, adherence and follow-up details'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('treatment_note', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Public Health & Family                                         -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-users text-success me-1"></i>
        <?php echo xlt('Public Health & Family'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="mandatory_report"
                           name="mandatory_report"
                           value="1"
                           <?php echo (chagasFld('mandatory_report', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="mandatory_report">
                        <?php echo xlt('Reported to Public Health'); ?>
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <label for="report_date" class="form-label">
                    <?php echo xlt('Reporting Date'); ?>
                </label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="report_date"
                       name="report_date"
                       value="<?php echo attr(chagasDateValue('report_date', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input"
                           type="checkbox"
                           id="family_screening_done"
                           name="family_screening_done"
                           value="1"
                           <?php echo (chagasFld('family_screening_done', $study) === '1') ? 'checked' : ''; ?>
                           <?php echo $disabled; ?>>
                    <label class="form-check-label fw-bold" for="family_screening_done">
                        <?php echo xlt('Family Screening Performed'); ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label for="public_health_note" class="form-label">
                    <?php echo xlt('Public Health Details'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="public_health_note"
                          name="public_health_note"
                          rows="2"
                          placeholder="<?php echo xla('Public health reporting details'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('public_health_note', $study)); ?></textarea>
            </div>
            <div class="col-md-6">
                <label for="family_screening_note" class="form-label">
                    <?php echo xlt('Family Screening Details'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="family_screening_note"
                          name="family_screening_note"
                          rows="2"
                          placeholder="<?php echo xla('Family screening details'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('family_screening_note', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Section: Plan, Conclusion & Comments                                    -->
<!-- ======================================================================= -->
<div class="card mb-3">
    <div class="card-header bg-light fw-bold py-2">
        <i class="fa fa-file-medical-alt text-success me-1"></i>
        <?php echo xlt('Plan, Conclusion & Comments'); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="next_control_date" class="form-label">
                    <?php echo xlt('Next Control Date'); ?>
                </label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="next_control_date"
                       name="next_control_date"
                       value="<?php echo attr(chagasDateValue('next_control_date', $study)); ?>"
                       <?php echo $disabled; ?>>
            </div>
            <div class="col-12">
                <label for="plan" class="form-label">
                    <?php echo xlt('Plan'); ?>
                </label>
                <textarea class="form-control form-control-sm"
                          id="plan"
                          name="plan"
                          rows="2"
                          placeholder="<?php echo xla('Management plan for the next follow-up period'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('plan', $study)); ?></textarea>
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
                          placeholder="<?php echo xla('Assessment conclusion printed in the report'); ?>"
                          <?php echo $disabled; ?>><?php echo text(chagasFld('conclusion', $study)); ?></textarea>
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
                          <?php echo $disabled; ?>><?php echo text(chagasFld('comments', $study)); ?></textarea>
            </div>
        </div>
    </div>
</div>
