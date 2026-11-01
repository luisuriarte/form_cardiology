<?php
/**
 * Echo card – _form_fields.php
 *
 * Shared form partial included by new.php and view.php.
 *
 * Expects in scope:
 *   $study          array|null  – existing row from form_card_echo, or null for new
 *   $valveGrades    array       – list_options for card_valve_grade
 *   $wallMotions    array       – list_options for card_wall_motion
 *   $effusionGrades array       – list_options for card_effusion_grade
 *   $efMethods      array       – list_options for card_ef_method
 *   $readOnly       bool        – true when status = 'final'
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Echo
 */

// Helper: get a value from $study or return default
$fv = static function (string $key, string $default = '') use ($study): string {
    if ($study === null) {
        return $default;
    }
    return (string)($study[$key] ?? $default);
};

// Helper: build <option> list for a <select>
$optList = static function (array $opts, string $selected, string $blank = '') : string {
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
?>

<?php if ($readOnly) : ?>
<div class="alert alert-info d-flex align-items-center mb-3" role="alert">
    <i class="fa fa-lock me-2" aria-hidden="true"></i>
    <?php echo xlt('This study is finalized and cannot be edited.'); ?>
</div>
<?php endif; ?>

<!-- Sección 1: Contexto clínico -->
<fieldset class="mb-4">
<legend class="cardiology-section-title">
    <i class="fa fa-info-circle me-1" aria-hidden="true"></i>
    <?php echo xlt('Clinical Context'); ?>
</legend>
<div class="row g-2">
    <div class="col-12 col-md-4">
        <label class="form-label" for="echo_study_date">
            <?php echo xlt('Study Date'); ?> <span class="text-danger">*</span>
        </label>
        <input type="datetime-local" id="echo_study_date" name="study_date"
               class="form-control form-control-sm"
               value="<?php echo attr(str_replace(' ', 'T', $fv('study_date'))); ?>"
               required <?php echo $dis; ?>>
    </div>
    <div class="col-12 col-md-8">
        <label class="form-label" for="echo_indication">
            <?php echo xlt('Indication'); ?>
        </label>
        <input type="text" id="echo_indication" name="indication"
               class="form-control form-control-sm"
               value="<?php echo attr($fv('indication')); ?>"
               placeholder="<?php echo xla('Indication for the study'); ?>"
               <?php echo $dis; ?>>
    </div>
</div>
<div class="row g-2 mt-1">
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_height_cm"><?php echo xlt('Height (cm)'); ?></label>
        <input type="number" id="echo_height_cm" name="height_cm"
               class="form-control form-control-sm" step="0.1" min="0" max="300"
               value="<?php echo attr($fv('height_cm')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_weight_kg"><?php echo xlt('Weight (kg)'); ?></label>
        <input type="number" id="echo_weight_kg" name="weight_kg"
               class="form-control form-control-sm" step="0.1" min="0" max="500"
               value="<?php echo attr($fv('weight_kg')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_bsa_m2"><?php echo xlt('BSA (m²)'); ?></label>
        <input type="text" id="echo_bsa_m2" name="bsa_m2"
               class="form-control form-control-sm bg-light"
               value="<?php echo attr($fv('bsa_m2')); ?>"
               readonly title="<?php echo xla('Calculated: Du Bois formula'); ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_heart_rate_bpm"><?php echo xlt('Heart Rate (bpm)'); ?></label>
        <input type="number" id="echo_heart_rate_bpm" name="heart_rate_bpm"
               class="form-control form-control-sm" min="0" max="300"
               value="<?php echo attr($fv('heart_rate_bpm')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_bp_systolic_mmhg"><?php echo xlt('BP Systolic (mmHg)'); ?></label>
        <input type="number" id="echo_bp_systolic_mmhg" name="bp_systolic_mmhg"
               class="form-control form-control-sm" min="0" max="300"
               value="<?php echo attr($fv('bp_systolic_mmhg')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_bp_diastolic_mmhg"><?php echo xlt('BP Diastolic (mmHg)'); ?></label>
        <input type="number" id="echo_bp_diastolic_mmhg" name="bp_diastolic_mmhg"
               class="form-control form-control-sm" min="0" max="200"
               value="<?php echo attr($fv('bp_diastolic_mmhg')); ?>" <?php echo $dis; ?>>
    </div>
</div>
</fieldset>

<!-- Sección 2: VI – Dimensiones y función sistólica -->
<fieldset class="mb-4">
<legend class="cardiology-section-title">
    <?php echo xlt('Left Ventricle – Dimensions & Systolic Function'); ?>
</legend>
<div class="row g-2">
    <?php
    $lv1Fields = [
        ['lvedd_mm',  'LVEDD (mm)',   '0','150','0.1'],
        ['lvesd_mm',  'LVESD (mm)',   '0','150','0.1'],
        ['ivs_mm',    'IVS (mm)',     '0','50', '0.1'],
        ['pw_mm',     'PW (mm)',      '0','50', '0.1'],
        ['lvedv_ml',  'LVEDV (mL)',   '0','999','0.1'],
        ['lvesv_ml',  'LVESV (mL)',   '0','999','0.1'],
        ['lvef_pct',  'LVEF (%)',     '0','100','0.1'],
        ['lv_mass_g', 'LV Mass (g)',  '0','999','0.1'],
    ];
    foreach ($lv1Fields as [$name, $label, $min, $max, $step]) : ?>
    <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label small" for="echo_<?php echo attr($name); ?>">
            <?php echo xlt($label); ?>
        </label>
        <input type="number" id="echo_<?php echo attr($name); ?>"
               name="<?php echo attr($name); ?>"
               class="form-control form-control-sm"
               step="<?php echo attr($step); ?>"
               min="<?php echo attr($min); ?>" max="<?php echo attr($max); ?>"
               value="<?php echo attr($fv($name)); ?>" <?php echo $dis; ?>>
    </div>
    <?php endforeach; ?>
    <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label small" for="echo_lv_mass_index_g_m2">
            <?php echo xlt('LV Mass Index (g/m²)'); ?>
        </label>
        <input type="text" id="echo_lv_mass_index_g_m2" name="lv_mass_index_g_m2"
               class="form-control form-control-sm bg-light"
               value="<?php echo attr($fv('lv_mass_index_g_m2')); ?>"
               readonly title="<?php echo xla('Calculated: LV Mass / BSA'); ?>">
    </div>
</div>
<div class="row g-2 mt-1">
    <div class="col-12 col-md-4">
        <label class="form-label small" for="echo_lvef_method"><?php echo xlt('LVEF Method'); ?></label>
        <select id="echo_lvef_method" name="lvef_method"
                class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($efMethods, $fv('lvef_method'), xlt('-- Select --')); ?>
        </select>
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label small" for="echo_wall_motion"><?php echo xlt('Global Wall Motion'); ?></label>
        <select id="echo_wall_motion" name="wall_motion"
                class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($wallMotions, $fv('wall_motion'), xlt('-- Select --')); ?>
        </select>
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label small" for="echo_wall_motion_detail">
            <?php echo xlt('Segmental Wall Motion Detail'); ?>
        </label>
        <input type="text" id="echo_wall_motion_detail" name="wall_motion_detail"
               class="form-control form-control-sm"
               value="<?php echo attr($fv('wall_motion_detail')); ?>"
               placeholder="<?php echo xla('e.g. Anterior hypokinesia'); ?>"
               <?php echo $dis; ?>>
    </div>
</div>
</fieldset>

<!-- Sección 3: VI – Función diastólica -->
<fieldset class="mb-4">
<legend class="cardiology-section-title">
    <?php echo xlt('Left Ventricle – Diastolic Function'); ?>
</legend>
<div class="row g-2">
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_mitral_e_cm_s"><?php echo xlt('Mitral E (cm/s)'); ?></label>
        <input type="number" id="echo_mitral_e_cm_s" name="mitral_e_cm_s"
               class="form-control form-control-sm" step="0.1" min="0" max="300"
               value="<?php echo attr($fv('mitral_e_cm_s')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_mitral_a_cm_s"><?php echo xlt('Mitral A (cm/s)'); ?></label>
        <input type="number" id="echo_mitral_a_cm_s" name="mitral_a_cm_s"
               class="form-control form-control-sm" step="0.1" min="0" max="300"
               value="<?php echo attr($fv('mitral_a_cm_s')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_mitral_e_a_ratio"><?php echo xlt('E/A Ratio'); ?></label>
        <input type="text" id="echo_mitral_e_a_ratio" name="mitral_e_a_ratio"
               class="form-control form-control-sm bg-light"
               value="<?php echo attr($fv('mitral_e_a_ratio')); ?>"
               readonly title="<?php echo xla('Calculated: E / A'); ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_dec_time_ms"><?php echo xlt('Dec. Time (ms)'); ?></label>
        <input type="number" id="echo_dec_time_ms" name="dec_time_ms"
               class="form-control form-control-sm" min="0" max="1000"
               value="<?php echo attr($fv('dec_time_ms')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_e_prime_septal_cm_s">
            <?php echo xlt("e' Septal (cm/s)"); ?>
        </label>
        <input type="number" id="echo_e_prime_septal_cm_s" name="e_prime_septal_cm_s"
               class="form-control form-control-sm" step="0.1" min="0" max="50"
               value="<?php echo attr($fv('e_prime_septal_cm_s')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_e_prime_lateral_cm_s">
            <?php echo xlt("e' Lateral (cm/s)"); ?>
        </label>
        <input type="number" id="echo_e_prime_lateral_cm_s" name="e_prime_lateral_cm_s"
               class="form-control form-control-sm" step="0.1" min="0" max="50"
               value="<?php echo attr($fv('e_prime_lateral_cm_s')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="echo_e_e_prime_ratio">
            <?php echo xlt("E/e' Ratio"); ?>
        </label>
        <input type="text" id="echo_e_e_prime_ratio" name="e_e_prime_ratio"
               class="form-control form-control-sm bg-light"
               value="<?php echo attr($fv('e_e_prime_ratio')); ?>"
               readonly title="<?php echo xla("Calculated: E / avg(e'sep, e'lat)"); ?>">
    </div>
</div>
</fieldset>

<!-- Sección 4: Aurículas -->
<fieldset class="mb-4">
<legend class="cardiology-section-title"><?php echo xlt('Atria'); ?></legend>
<div class="row g-2">
    <div class="col-6 col-md-3">
        <label class="form-label small" for="echo_la_diameter_mm">
            <?php echo xlt('LA Diameter (mm)'); ?>
        </label>
        <input type="number" id="echo_la_diameter_mm" name="la_diameter_mm"
               class="form-control form-control-sm" step="0.1" min="0" max="100"
               value="<?php echo attr($fv('la_diameter_mm')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small" for="echo_la_volume_index_ml_m2">
            <?php echo xlt('LA Volume Index (mL/m²)'); ?>
        </label>
        <input type="number" id="echo_la_volume_index_ml_m2" name="la_volume_index_ml_m2"
               class="form-control form-control-sm" step="0.1" min="0" max="200"
               value="<?php echo attr($fv('la_volume_index_ml_m2')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small" for="echo_ra_area_cm2">
            <?php echo xlt('RA Area (cm²)'); ?>
        </label>
        <input type="number" id="echo_ra_area_cm2" name="ra_area_cm2"
               class="form-control form-control-sm" step="0.1" min="0" max="100"
               value="<?php echo attr($fv('ra_area_cm2')); ?>" <?php echo $dis; ?>>
    </div>
</div>
</fieldset>

<!-- Sección 5: VD y presión pulmonar -->
<fieldset class="mb-4">
<legend class="cardiology-section-title">
    <?php echo xlt('Right Ventricle & Pulmonary Pressure'); ?>
</legend>
<div class="row g-2">
    <?php
    $rvFields = [
        ['rv_basal_diameter_mm', 'RV Basal Diam. (mm)', '0','100','0.1'],
        ['tapse_mm',             'TAPSE (mm)',           '0','50', '0.1'],
        ['rv_s_prime_cm_s',      "RV S' (cm/s)",        '0','50', '0.1'],
        ['ivc_diameter_mm',      'IVC Diameter (mm)',    '0','50', '0.1'],
        ['rap_mmhg',             'RAP (mmHg)',           '0','40', '1'],
        ['tr_peak_velocity_m_s', 'TR Peak Vel. (m/s)',   '0','10', '0.01'],
        ['pasp_mmhg',            'PASP (mmHg)',          '0','200','1'],
    ];
    foreach ($rvFields as [$name, $label, $min, $max, $step]) : ?>
    <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label small" for="echo_<?php echo attr($name); ?>">
            <?php echo xlt($label); ?>
        </label>
        <input type="number" id="echo_<?php echo attr($name); ?>"
               name="<?php echo attr($name); ?>"
               class="form-control form-control-sm"
               step="<?php echo attr($step); ?>"
               min="<?php echo attr($min); ?>" max="<?php echo attr($max); ?>"
               value="<?php echo attr($fv($name)); ?>" <?php echo $dis; ?>>
    </div>
    <?php endforeach; ?>
</div>
</fieldset>

<!-- Sección 6: Aorta -->
<fieldset class="mb-4">
<legend class="cardiology-section-title"><?php echo xlt('Aorta'); ?></legend>
<div class="row g-2">
    <div class="col-6 col-md-3">
        <label class="form-label small" for="echo_ao_root_mm"><?php echo xlt('Aortic Root (mm)'); ?></label>
        <input type="number" id="echo_ao_root_mm" name="ao_root_mm"
               class="form-control form-control-sm" step="0.1" min="0" max="100"
               value="<?php echo attr($fv('ao_root_mm')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small" for="echo_asc_aorta_mm"><?php echo xlt('Ascending Aorta (mm)'); ?></label>
        <input type="number" id="echo_asc_aorta_mm" name="asc_aorta_mm"
               class="form-control form-control-sm" step="0.1" min="0" max="100"
               value="<?php echo attr($fv('asc_aorta_mm')); ?>" <?php echo $dis; ?>>
    </div>
</div>
</fieldset>

<!-- Sección 7: Válvulas -->
<fieldset class="mb-4">
<legend class="cardiology-section-title"><?php echo xlt('Valves'); ?></legend>

<h6 class="text-muted small fw-bold mb-2"><?php echo xlt('Aortic Valve'); ?></h6>
<div class="row g-2 mb-3">
    <div class="col-6 col-md-2">
        <label class="form-label small"><?php echo xlt('Regurgitation'); ?></label>
        <select name="ao_regurg_grade" class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($valveGrades, $fv('ao_regurg_grade'), xlt('-- Select --')); ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small"><?php echo xlt('Stenosis'); ?></label>
        <select name="ao_stenosis_grade" class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($valveGrades, $fv('ao_stenosis_grade'), xlt('-- Select --')); ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small"><?php echo xlt('Peak Vel. (m/s)'); ?></label>
        <input type="number" name="ao_peak_velocity_m_s"
               class="form-control form-control-sm" step="0.01" min="0" max="10"
               value="<?php echo attr($fv('ao_peak_velocity_m_s')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small"><?php echo xlt('Mean Gradient (mmHg)'); ?></label>
        <input type="number" name="ao_mean_gradient_mmhg"
               class="form-control form-control-sm" step="0.1" min="0" max="200"
               value="<?php echo attr($fv('ao_mean_gradient_mmhg')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small"><?php echo xlt('Valve Area (cm²)'); ?></label>
        <input type="number" name="ao_valve_area_cm2"
               class="form-control form-control-sm" step="0.01" min="0" max="10"
               value="<?php echo attr($fv('ao_valve_area_cm2')); ?>" <?php echo $dis; ?>>
    </div>
</div>

<h6 class="text-muted small fw-bold mb-2"><?php echo xlt('Mitral Valve'); ?></h6>
<div class="row g-2 mb-3">
    <div class="col-6 col-md-2">
        <label class="form-label small"><?php echo xlt('Regurgitation'); ?></label>
        <select name="mv_regurg_grade" class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($valveGrades, $fv('mv_regurg_grade'), xlt('-- Select --')); ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small"><?php echo xlt('Stenosis'); ?></label>
        <select name="mv_stenosis_grade" class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($valveGrades, $fv('mv_stenosis_grade'), xlt('-- Select --')); ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small"><?php echo xlt('Mean Gradient (mmHg)'); ?></label>
        <input type="number" name="mv_mean_gradient_mmhg"
               class="form-control form-control-sm" step="0.1" min="0" max="50"
               value="<?php echo attr($fv('mv_mean_gradient_mmhg')); ?>" <?php echo $dis; ?>>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small"><?php echo xlt('Valve Area (cm²)'); ?></label>
        <input type="number" name="mv_valve_area_cm2"
               class="form-control form-control-sm" step="0.01" min="0" max="10"
               value="<?php echo attr($fv('mv_valve_area_cm2')); ?>" <?php echo $dis; ?>>
    </div>
</div>

<h6 class="text-muted small fw-bold mb-2"><?php echo xlt('Tricuspid & Pulmonary Valves'); ?></h6>
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <label class="form-label small"><?php echo xlt('Tricuspid Regurgitation'); ?></label>
        <select name="tv_regurg_grade" class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($valveGrades, $fv('tv_regurg_grade'), xlt('-- Select --')); ?>
        </select>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small"><?php echo xlt('Pulmonary Regurgitation'); ?></label>
        <select name="pv_regurg_grade" class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($valveGrades, $fv('pv_regurg_grade'), xlt('-- Select --')); ?>
        </select>
    </div>
</div>

<div class="row g-2">
    <div class="col-12">
        <label class="form-label small" for="echo_valve_findings">
            <?php echo xlt('Additional Valve Findings'); ?>
        </label>
        <textarea id="echo_valve_findings" name="valve_findings"
                  class="form-control form-control-sm" rows="2"
                  placeholder="<?php echo xla('Morphology, prostheses, vegetations, etc.'); ?>"
                  <?php echo $dis; ?>><?php echo text($fv('valve_findings')); ?></textarea>
    </div>
</div>
</fieldset>

<!-- Sección 8: Pericardio -->
<fieldset class="mb-4">
<legend class="cardiology-section-title"><?php echo xlt('Pericardium'); ?></legend>
<div class="row g-2">
    <div class="col-12 col-md-4">
        <label class="form-label small" for="echo_pericardial_effusion">
            <?php echo xlt('Pericardial Effusion'); ?>
        </label>
        <select id="echo_pericardial_effusion" name="pericardial_effusion"
                class="form-select form-select-sm" <?php echo $dis; ?>>
            <?php echo $optList($effusionGrades, $fv('pericardial_effusion'), xlt('-- Select --')); ?>
        </select>
    </div>
</div>
</fieldset>

<!-- Sección 9: Informe -->
<fieldset class="mb-4">
<legend class="cardiology-section-title"><?php echo xlt('Report'); ?></legend>
<div class="row g-2">
    <div class="col-12">
        <label class="form-label" for="echo_conclusion">
            <strong><?php echo xlt('Conclusion'); ?></strong>
        </label>
        <textarea id="echo_conclusion" name="conclusion" class="form-control" rows="4"
                  placeholder="<?php echo xla('Study conclusion for the PDF report'); ?>"
                  <?php echo $dis; ?>><?php echo text($fv('conclusion')); ?></textarea>
    </div>
    <div class="col-12 mt-2">
        <label class="form-label" for="echo_comments">
            <?php echo xlt('Comments'); ?>
        </label>
        <textarea id="echo_comments" name="comments" class="form-control" rows="2"
                  placeholder="<?php echo xla('Additional comments or recommendations'); ?>"
                  <?php echo $dis; ?>><?php echo text($fv('comments')); ?></textarea>
    </div>
</div>
</fieldset>
