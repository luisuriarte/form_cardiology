<?php

/**
 * Echo card – _form_fields.php
 *
 * Shared partial included by new.php and view.php.
 * Renders all Doppler Echocardiogram study fields grouped in Bootstrap fieldsets.
 *
 * Expected scope variables:
 *   $study          array|null  null = new study, array = existing row
 *   $valveGrades    array       list_options rows for card_valve_grade
 *   $wallMotions    array       list_options rows for card_wall_motion
 *   $effusionGrades array       list_options rows for card_effusion_grade
 *   $efMethods      array       list_options rows for card_ef_method
 *   $readOnly       bool        true = study is finalized (all inputs disabled)
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Echo
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Defensive defaults so this partial is safe when variables are unset.
$study          = $study          ?? null;
$valveGrades    = $valveGrades    ?? [];
$wallMotions    = $wallMotions    ?? [];
$effusionGrades = $effusionGrades ?? [];
$efMethods      = $efMethods      ?? [];
$readOnly       = $readOnly       ?? false;

$dis = $readOnly ? ' disabled' : '';   // attribute string for disabled inputs

// Helper: retrieve a stored value or empty string.
$v = static function (string $key) use ($study): string {
    return (string)($study[$key] ?? '');
};

// Helper: render <option> tags for a list_options array.
$renderOptions = static function (array $list, string $selected) use ($readOnly): void {
    echo '<option value="">' . xlt('-- Select --') . '</option>';
    foreach ($list as $row) {
        $optId    = (string)($row['option_id'] ?? '');
        $optTitle = (string)($row['title']     ?? '');
        $sel = ($selected === $optId) ? ' selected' : '';
        echo '<option value="' . attr($optId) . '"' . $sel . '>'
            . xlt($optTitle)
            . '</option>';
    }
};
?>

<?php if ($readOnly): ?>
    <div class="alert alert-secondary d-flex align-items-center mb-3" role="alert">
        <i class="fa fa-lock me-2" aria-hidden="true"></i>
        <strong><?php echo xlt('Final'); ?></strong>&nbsp;<?php echo xlt('This study is finalized and cannot be edited.'); ?>
    </div>
<?php endif; ?>

<!-- ===================================================================== -->
<!-- Section 1 – Clinical context                                           -->
<!-- ===================================================================== -->
<fieldset class="card mb-3">
    <legend class="card-header h6 mb-0">
        <i class="fa fa-calendar me-1" aria-hidden="true"></i>
        <?php echo xlt('Clinical Context'); ?>
    </legend>
    <div class="card-body">
        <div class="row">
            <!-- Study date -->
            <div class="col-md-4 mb-3">
                <label class="form-label" for="study_date">
                    <?php echo xlt('Study Date / Time'); ?>
                    <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <input type="datetime-local"
                       id="study_date"
                       name="study_date"
                       class="form-control"
                       value="<?php echo attr($v('study_date')); ?>"
                       required<?php echo $dis; ?>>
            </div>
            <!-- Indication -->
            <div class="col-md-8 mb-3">
                <label class="form-label" for="indication">
                    <?php echo xlt('Indication'); ?>
                </label>
                <textarea id="indication"
                          name="indication"
                          class="form-control"
                          rows="2"<?php echo $dis; ?>><?php echo text($v('indication')); ?></textarea>
            </div>
        </div>

        <div class="row">
            <!-- Height -->
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="height_cm">
                    <?php echo xlt('Height (cm)'); ?>
                </label>
                <input type="number"
                       id="height_cm"
                       name="height_cm"
                       class="form-control"
                       step="0.1" min="0"
                       value="<?php echo attr($v('height_cm')); ?>"<?php echo $dis; ?>>
            </div>
            <!-- Weight -->
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="weight_kg">
                    <?php echo xlt('Weight (kg)'); ?>
                </label>
                <input type="number"
                       id="weight_kg"
                       name="weight_kg"
                       class="form-control"
                       step="0.1" min="0"
                       value="<?php echo attr($v('weight_kg')); ?>"<?php echo $dis; ?>>
            </div>
            <!-- BSA (calculated) -->
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="bsa_m2">
                    <?php echo xlt('BSA (m²)'); ?>
                    <small class="text-muted"><?php echo xlt('[calculated]'); ?></small>
                </label>
                <input type="text"
                       id="bsa_m2"
                       name="bsa_m2"
                       class="form-control"
                       value="<?php echo attr($v('bsa_m2')); ?>"
                       readonly
                       tabindex="-1">
            </div>
            <!-- Heart rate -->
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="heart_rate_bpm">
                    <?php echo xlt('Heart Rate (bpm)'); ?>
                </label>
                <input type="number"
                       id="heart_rate_bpm"
                       name="heart_rate_bpm"
                       class="form-control"
                       step="1" min="0"
                       value="<?php echo attr($v('heart_rate_bpm')); ?>"<?php echo $dis; ?>>
            </div>
        </div>

        <div class="row">
            <!-- BP systolic -->
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="bp_systolic_mmhg">
                    <?php echo xlt('BP Systolic (mmHg)'); ?>
                </label>
                <input type="number"
                       id="bp_systolic_mmhg"
                       name="bp_systolic_mmhg"
                       class="form-control"
                       step="1" min="0"
                       value="<?php echo attr($v('bp_systolic_mmhg')); ?>"<?php echo $dis; ?>>
            </div>
            <!-- BP diastolic -->
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="bp_diastolic_mmhg">
                    <?php echo xlt('BP Diastolic (mmHg)'); ?>
                </label>
                <input type="number"
                       id="bp_diastolic_mmhg"
                       name="bp_diastolic_mmhg"
                       class="form-control"
                       step="1" min="0"
                       value="<?php echo attr($v('bp_diastolic_mmhg')); ?>"<?php echo $dis; ?>>
            </div>
        </div>
    </div>
</fieldset>

<!-- ===================================================================== -->
<!-- Section 2 – Left Ventricle: dimensions and systolic function           -->
<!-- ===================================================================== -->
<fieldset class="card mb-3">
    <legend class="card-header h6 mb-0">
        <i class="fa fa-heartbeat me-1" aria-hidden="true"></i>
        <?php echo xlt('Left Ventricle – Dimensions & Systolic Function'); ?>
    </legend>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="lvedd_mm"><?php echo xlt('LVEDD (mm)'); ?></label>
                <input type="number" id="lvedd_mm" name="lvedd_mm"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('lvedd_mm')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="lvesd_mm"><?php echo xlt('LVESD (mm)'); ?></label>
                <input type="number" id="lvesd_mm" name="lvesd_mm"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('lvesd_mm')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="ivs_mm"><?php echo xlt('IVS (mm)'); ?></label>
                <input type="number" id="ivs_mm" name="ivs_mm"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('ivs_mm')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="pw_mm"><?php echo xlt('PW (mm)'); ?></label>
                <input type="number" id="pw_mm" name="pw_mm"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('pw_mm')); ?>"<?php echo $dis; ?>>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="lvedv_ml"><?php echo xlt('LVEDV (mL)'); ?></label>
                <input type="number" id="lvedv_ml" name="lvedv_ml"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('lvedv_ml')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="lvesv_ml"><?php echo xlt('LVESV (mL)'); ?></label>
                <input type="number" id="lvesv_ml" name="lvesv_ml"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('lvesv_ml')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="lvef_pct"><?php echo xlt('LVEF (%)'); ?></label>
                <input type="number" id="lvef_pct" name="lvef_pct"
                       class="form-control" step="0.1" min="0" max="100"
                       value="<?php echo attr($v('lvef_pct')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="lvef_method"><?php echo xlt('LVEF Method'); ?></label>
                <select id="lvef_method" name="lvef_method"
                        class="form-control"<?php echo $dis; ?>>
                    <?php $renderOptions($efMethods, $v('lvef_method')); ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="lv_mass_g"><?php echo xlt('LV Mass (g)'); ?></label>
                <input type="number" id="lv_mass_g" name="lv_mass_g"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('lv_mass_g')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="lv_mass_index_g_m2">
                    <?php echo xlt('LV Mass Index (g/m²)'); ?>
                    <small class="text-muted"><?php echo xlt('[calculated]'); ?></small>
                </label>
                <input type="text" id="lv_mass_index_g_m2" name="lv_mass_index_g_m2"
                       class="form-control"
                       value="<?php echo attr($v('lv_mass_index_g_m2')); ?>"
                       readonly tabindex="-1">
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="wall_motion"><?php echo xlt('Wall Motion'); ?></label>
                <select id="wall_motion" name="wall_motion"
                        class="form-control"<?php echo $dis; ?>>
                    <?php $renderOptions($wallMotions, $v('wall_motion')); ?>
                </select>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="wall_motion_detail"><?php echo xlt('Wall Motion Detail'); ?></label>
                <textarea id="wall_motion_detail" name="wall_motion_detail"
                          class="form-control" rows="2"<?php echo $dis; ?>><?php echo text($v('wall_motion_detail')); ?></textarea>
            </div>
        </div>
    </div>
</fieldset>

<!-- ===================================================================== -->
<!-- Section 3 – LV Diastolic Function                                     -->
<!-- ===================================================================== -->
<fieldset class="card mb-3">
    <legend class="card-header h6 mb-0">
        <i class="fa fa-wave-square me-1" aria-hidden="true"></i>
        <?php echo xlt('LV Diastolic Function'); ?>
    </legend>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="mitral_e_cm_s"><?php echo xlt('Mitral E (cm/s)'); ?></label>
                <input type="number" id="mitral_e_cm_s" name="mitral_e_cm_s"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('mitral_e_cm_s')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="mitral_a_cm_s"><?php echo xlt('Mitral A (cm/s)'); ?></label>
                <input type="number" id="mitral_a_cm_s" name="mitral_a_cm_s"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('mitral_a_cm_s')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="mitral_e_a_ratio">
                    <?php echo xlt('E/A Ratio'); ?>
                    <small class="text-muted"><?php echo xlt('[calculated]'); ?></small>
                </label>
                <input type="text" id="mitral_e_a_ratio" name="mitral_e_a_ratio"
                       class="form-control"
                       value="<?php echo attr($v('mitral_e_a_ratio')); ?>"
                       readonly tabindex="-1">
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="dec_time_ms"><?php echo xlt('Deceleration Time (ms)'); ?></label>
                <input type="number" id="dec_time_ms" name="dec_time_ms"
                       class="form-control" step="1" min="0"
                       value="<?php echo attr($v('dec_time_ms')); ?>"<?php echo $dis; ?>>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="e_prime_septal_cm_s"><?php echo xlt("e' Septal (cm/s)"); ?></label>
                <input type="number" id="e_prime_septal_cm_s" name="e_prime_septal_cm_s"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('e_prime_septal_cm_s')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="e_prime_lateral_cm_s"><?php echo xlt("e' Lateral (cm/s)"); ?></label>
                <input type="number" id="e_prime_lateral_cm_s" name="e_prime_lateral_cm_s"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('e_prime_lateral_cm_s')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="e_e_prime_ratio">
                    <?php echo xlt("E/e' Ratio"); ?>
                    <small class="text-muted"><?php echo xlt('[calculated]'); ?></small>
                </label>
                <input type="text" id="e_e_prime_ratio" name="e_e_prime_ratio"
                       class="form-control"
                       value="<?php echo attr($v('e_e_prime_ratio')); ?>"
                       readonly tabindex="-1">
            </div>
        </div>
    </div>
</fieldset>

<!-- ===================================================================== -->
<!-- Section 4 – Atria                                                     -->
<!-- ===================================================================== -->
<fieldset class="card mb-3">
    <legend class="card-header h6 mb-0">
        <i class="fa fa-circle me-1" aria-hidden="true"></i>
        <?php echo xlt('Atria'); ?>
    </legend>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-4 mb-3">
                <label class="form-label" for="la_diameter_mm"><?php echo xlt('LA Diameter (mm)'); ?></label>
                <input type="number" id="la_diameter_mm" name="la_diameter_mm"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('la_diameter_mm')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-4 mb-3">
                <label class="form-label" for="la_volume_index_ml_m2"><?php echo xlt('LA Volume Index (mL/m²)'); ?></label>
                <input type="number" id="la_volume_index_ml_m2" name="la_volume_index_ml_m2"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('la_volume_index_ml_m2')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-4 mb-3">
                <label class="form-label" for="ra_area_cm2"><?php echo xlt('RA Area (cm²)'); ?></label>
                <input type="number" id="ra_area_cm2" name="ra_area_cm2"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('ra_area_cm2')); ?>"<?php echo $dis; ?>>
            </div>
        </div>
    </div>
</fieldset>

<!-- ===================================================================== -->
<!-- Section 5 – Right Ventricle & Pulmonary Pressure                     -->
<!-- ===================================================================== -->
<fieldset class="card mb-3">
    <legend class="card-header h6 mb-0">
        <i class="fa fa-lungs me-1" aria-hidden="true"></i>
        <?php echo xlt('Right Ventricle & Pulmonary Pressure'); ?>
    </legend>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="rv_basal_diameter_mm"><?php echo xlt('RV Basal Diameter (mm)'); ?></label>
                <input type="number" id="rv_basal_diameter_mm" name="rv_basal_diameter_mm"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('rv_basal_diameter_mm')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="tapse_mm"><?php echo xlt('TAPSE (mm)'); ?></label>
                <input type="number" id="tapse_mm" name="tapse_mm"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('tapse_mm')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="rv_s_prime_cm_s"><?php echo xlt("RV S' (cm/s)"); ?></label>
                <input type="number" id="rv_s_prime_cm_s" name="rv_s_prime_cm_s"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('rv_s_prime_cm_s')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="ivc_diameter_mm"><?php echo xlt('IVC Diameter (mm)'); ?></label>
                <input type="number" id="ivc_diameter_mm" name="ivc_diameter_mm"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('ivc_diameter_mm')); ?>"<?php echo $dis; ?>>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="rap_mmhg"><?php echo xlt('RAP (mmHg)'); ?></label>
                <input type="number" id="rap_mmhg" name="rap_mmhg"
                       class="form-control" step="1" min="0"
                       value="<?php echo attr($v('rap_mmhg')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="tr_peak_velocity_m_s"><?php echo xlt('TR Peak Velocity (m/s)'); ?></label>
                <input type="number" id="tr_peak_velocity_m_s" name="tr_peak_velocity_m_s"
                       class="form-control" step="0.01" min="0"
                       value="<?php echo attr($v('tr_peak_velocity_m_s')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="pasp_mmhg"><?php echo xlt('PASP (mmHg)'); ?></label>
                <input type="number" id="pasp_mmhg" name="pasp_mmhg"
                       class="form-control" step="1" min="0"
                       value="<?php echo attr($v('pasp_mmhg')); ?>"<?php echo $dis; ?>>
            </div>
        </div>
    </div>
</fieldset>

<!-- ===================================================================== -->
<!-- Section 6 – Aorta                                                     -->
<!-- ===================================================================== -->
<fieldset class="card mb-3">
    <legend class="card-header h6 mb-0">
        <i class="fa fa-arrows-alt-v me-1" aria-hidden="true"></i>
        <?php echo xlt('Aorta'); ?>
    </legend>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-4 mb-3">
                <label class="form-label" for="ao_root_mm"><?php echo xlt('Aortic Root (mm)'); ?></label>
                <input type="number" id="ao_root_mm" name="ao_root_mm"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('ao_root_mm')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-4 mb-3">
                <label class="form-label" for="asc_aorta_mm"><?php echo xlt('Ascending Aorta (mm)'); ?></label>
                <input type="number" id="asc_aorta_mm" name="asc_aorta_mm"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('asc_aorta_mm')); ?>"<?php echo $dis; ?>>
            </div>
        </div>
    </div>
</fieldset>

<!-- ===================================================================== -->
<!-- Section 7 – Valves                                                    -->
<!-- ===================================================================== -->
<fieldset class="card mb-3">
    <legend class="card-header h6 mb-0">
        <i class="fa fa-stethoscope me-1" aria-hidden="true"></i>
        <?php echo xlt('Valves'); ?>
    </legend>
    <div class="card-body">

        <!-- Aortic valve -->
        <h6 class="text-uppercase text-muted mb-2"><?php echo xlt('Aortic Valve'); ?></h6>
        <div class="row">
            <div class="col-sm-2 mb-3">
                <label class="form-label" for="ao_regurg_grade"><?php echo xlt('Regurgitation'); ?></label>
                <select id="ao_regurg_grade" name="ao_regurg_grade"
                        class="form-control"<?php echo $dis; ?>>
                    <?php $renderOptions($valveGrades, $v('ao_regurg_grade')); ?>
                </select>
            </div>
            <div class="col-sm-2 mb-3">
                <label class="form-label" for="ao_stenosis_grade"><?php echo xlt('Stenosis'); ?></label>
                <select id="ao_stenosis_grade" name="ao_stenosis_grade"
                        class="form-control"<?php echo $dis; ?>>
                    <?php $renderOptions($valveGrades, $v('ao_stenosis_grade')); ?>
                </select>
            </div>
            <div class="col-sm-2 mb-3">
                <label class="form-label" for="ao_peak_velocity_m_s"><?php echo xlt('Peak Vel. (m/s)'); ?></label>
                <input type="number" id="ao_peak_velocity_m_s" name="ao_peak_velocity_m_s"
                       class="form-control" step="0.01" min="0"
                       value="<?php echo attr($v('ao_peak_velocity_m_s')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="ao_mean_gradient_mmhg"><?php echo xlt('Mean Gradient (mmHg)'); ?></label>
                <input type="number" id="ao_mean_gradient_mmhg" name="ao_mean_gradient_mmhg"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('ao_mean_gradient_mmhg')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="ao_valve_area_cm2"><?php echo xlt('Valve Area (cm²)'); ?></label>
                <input type="number" id="ao_valve_area_cm2" name="ao_valve_area_cm2"
                       class="form-control" step="0.01" min="0"
                       value="<?php echo attr($v('ao_valve_area_cm2')); ?>"<?php echo $dis; ?>>
            </div>
        </div>

        <hr>

        <!-- Mitral valve -->
        <h6 class="text-uppercase text-muted mb-2"><?php echo xlt('Mitral Valve'); ?></h6>
        <div class="row">
            <div class="col-sm-2 mb-3">
                <label class="form-label" for="mv_regurg_grade"><?php echo xlt('Regurgitation'); ?></label>
                <select id="mv_regurg_grade" name="mv_regurg_grade"
                        class="form-control"<?php echo $dis; ?>>
                    <?php $renderOptions($valveGrades, $v('mv_regurg_grade')); ?>
                </select>
            </div>
            <div class="col-sm-2 mb-3">
                <label class="form-label" for="mv_stenosis_grade"><?php echo xlt('Stenosis'); ?></label>
                <select id="mv_stenosis_grade" name="mv_stenosis_grade"
                        class="form-control"<?php echo $dis; ?>>
                    <?php $renderOptions($valveGrades, $v('mv_stenosis_grade')); ?>
                </select>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="mv_mean_gradient_mmhg"><?php echo xlt('Mean Gradient (mmHg)'); ?></label>
                <input type="number" id="mv_mean_gradient_mmhg" name="mv_mean_gradient_mmhg"
                       class="form-control" step="0.1" min="0"
                       value="<?php echo attr($v('mv_mean_gradient_mmhg')); ?>"<?php echo $dis; ?>>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="mv_valve_area_cm2"><?php echo xlt('Valve Area (cm²)'); ?></label>
                <input type="number" id="mv_valve_area_cm2" name="mv_valve_area_cm2"
                       class="form-control" step="0.01" min="0"
                       value="<?php echo attr($v('mv_valve_area_cm2')); ?>"<?php echo $dis; ?>>
            </div>
        </div>

        <hr>

        <!-- Tricuspid & Pulmonary valves -->
        <div class="row">
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="tv_regurg_grade">
                    <?php echo xlt('Tricuspid Regurgitation'); ?>
                </label>
                <select id="tv_regurg_grade" name="tv_regurg_grade"
                        class="form-control"<?php echo $dis; ?>>
                    <?php $renderOptions($valveGrades, $v('tv_regurg_grade')); ?>
                </select>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="pv_regurg_grade">
                    <?php echo xlt('Pulmonary Regurgitation'); ?>
                </label>
                <select id="pv_regurg_grade" name="pv_regurg_grade"
                        class="form-control"<?php echo $dis; ?>>
                    <?php $renderOptions($valveGrades, $v('pv_regurg_grade')); ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mb-3">
                <label class="form-label" for="valve_findings"><?php echo xlt('Valve Findings'); ?></label>
                <textarea id="valve_findings" name="valve_findings"
                          class="form-control" rows="3"<?php echo $dis; ?>><?php echo text($v('valve_findings')); ?></textarea>
            </div>
        </div>
    </div>
</fieldset>

<!-- ===================================================================== -->
<!-- Section 8 – Pericardium                                               -->
<!-- ===================================================================== -->
<fieldset class="card mb-3">
    <legend class="card-header h6 mb-0">
        <i class="fa fa-shield-alt me-1" aria-hidden="true"></i>
        <?php echo xlt('Pericardium'); ?>
    </legend>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-4 mb-3">
                <label class="form-label" for="pericardial_effusion"><?php echo xlt('Pericardial Effusion'); ?></label>
                <select id="pericardial_effusion" name="pericardial_effusion"
                        class="form-control"<?php echo $dis; ?>>
                    <?php $renderOptions($effusionGrades, $v('pericardial_effusion')); ?>
                </select>
            </div>
        </div>
    </div>
</fieldset>

<!-- ===================================================================== -->
<!-- Section 9 – Report                                                    -->
<!-- ===================================================================== -->
<fieldset class="card mb-3">
    <legend class="card-header h6 mb-0">
        <i class="fa fa-file-medical-alt me-1" aria-hidden="true"></i>
        <?php echo xlt('Report'); ?>
    </legend>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label fw-bold" for="conclusion"><?php echo xlt('Conclusion'); ?></label>
            <textarea id="conclusion" name="conclusion"
                      class="form-control" rows="4"<?php echo $dis; ?>><?php echo text($v('conclusion')); ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label" for="comments"><?php echo xlt('Comments / Recommendations'); ?></label>
            <textarea id="comments" name="comments"
                      class="form-control" rows="3"<?php echo $dis; ?>><?php echo text($v('comments')); ?></textarea>
        </div>
    </div>
</fieldset>
