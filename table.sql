-- =============================================================================
-- Cardiology container form: base tables
-- Location: interface/forms/cardiology/table.sql
--
-- Naming convention:
--   form_cardiology  -> container row registered in `forms` (one per encounter)
--   form_card_*      -> clinical data of each card (study), tied to an encounter
--   card_*           -> catalogs and configuration (no patient data)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Container: one row per encounter. Child cards reference this row.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `form_cardiology` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'Primary key, also stored as forms.form_id',
  `date` DATETIME DEFAULT NULL COMMENT 'Date and time the container was created',
  `pid` BIGINT(20) NOT NULL COMMENT 'Patient id (patient_data.pid)',
  `encounter` BIGINT(20) NOT NULL COMMENT 'Encounter number (form_encounter.encounter)',
  `user` VARCHAR(255) DEFAULT NULL COMMENT 'Username of the author (users.username)',
  `groupname` VARCHAR(255) DEFAULT NULL COMMENT 'Authorization group of the author, standard OpenEMR form field',
  `authorized` TINYINT(4) DEFAULT 0 COMMENT 'Standard OpenEMR authorization flag (1 = authorized)',
  `activity` TINYINT(4) DEFAULT 1 COMMENT 'Soft delete flag (1 = active, 0 = deleted)',
  `reason` TEXT COMMENT 'Reason for the cardiology visit',
  `notes` TEXT COMMENT 'Free-text visit notes shared by all cards',
  PRIMARY KEY (`id`),
  KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Cardiology container form, one row per encounter';

-- -----------------------------------------------------------------------------
-- Catalog of available cards (modules shown in the container menu)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `card_modules` (
  `code` VARCHAR(32) NOT NULL COMMENT 'Unique module code (e.g. echo, stress_test)',
  `title` VARCHAR(100) NOT NULL COMMENT 'Display title of the card',
  `icon` VARCHAR(50) DEFAULT NULL COMMENT 'Font Awesome icon class shown on the card',
  `table_name` VARCHAR(64) NOT NULL COMMENT 'Table holding the data of this card (form_card_*)',
  `acl_section` VARCHAR(64) NOT NULL DEFAULT 'patients' COMMENT 'phpGACL section required to open the card',
  `acl_value` VARCHAR(64) NOT NULL DEFAULT 'med' COMMENT 'phpGACL value required to open the card',
  `sort_order` INT NOT NULL DEFAULT 100 COMMENT 'Display order in the menu (ascending)',
  `global_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Clinic-wide switch (1 = available, 0 = disabled for everyone)',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Catalog of cardiology cards (modules)';

-- -----------------------------------------------------------------------------
-- Per-user activation of cards
-- user_id = 0 is the clinic default, a row with a real user id overrides it
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `card_config` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `module_code` VARCHAR(32) NOT NULL COMMENT 'Card code (card_modules.code)',
  `user_id` BIGINT(20) NOT NULL DEFAULT 0 COMMENT 'users.id - 0 means clinic default',
  `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether the card is shown (1) or hidden (0) for this scope',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_module_user` (`module_code`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Per-user and per-clinic activation of cards';

-- -----------------------------------------------------------------------------
-- Seed: initial cards (echocardiogram and exercise stress test)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `card_modules`
  (`code`, `title`, `icon`, `table_name`, `sort_order`) VALUES
  ('echo',        'Doppler Echocardiogram', 'fa-heartbeat', 'form_card_echo',        10),
  ('stress_test', 'Exercise Stress Test',   'fa-running',   'form_card_stress_test', 20);


-- =============================================================================
-- Lists for the cardiology form (stored in list_options)
-- Each list needs a registration row (list_id = 'lists') plus its options.
-- Titles are stored in English and translated at display time with
-- xl_list_label().
-- =============================================================================

-- --- List registration -------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `activity`) VALUES
  ('lists', 'card_valve_grade',    'Cardiology - Valve regurgitation/stenosis grade', 0, 0, 0, 1),
  ('lists', 'card_wall_motion',    'Cardiology - Wall motion',                        0, 0, 0, 1),
  ('lists', 'card_effusion_grade', 'Cardiology - Pericardial effusion grade',         0, 0, 0, 1),
  ('lists', 'card_stress_protocol','Cardiology - Stress test protocol',               0, 0, 0, 1);

-- --- card_valve_grade: severity of valve disease -----------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_valve_grade', 'none',       'None',            10, 1, 1),
  ('card_valve_grade', 'trace',      'Trace',           20, 0, 1),
  ('card_valve_grade', 'mild',       'Mild',            30, 0, 1),
  ('card_valve_grade', 'moderate',   'Moderate',        40, 0, 1),
  ('card_valve_grade', 'mod_severe', 'Moderate-severe', 50, 0, 1),
  ('card_valve_grade', 'severe',     'Severe',          60, 0, 1);

-- --- card_wall_motion: segmental wall motion ---------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_wall_motion', 'normal',      'Normal',      10, 1, 1),
  ('card_wall_motion', 'hypokinetic', 'Hypokinetic', 20, 0, 1),
  ('card_wall_motion', 'akinetic',    'Akinetic',    30, 0, 1),
  ('card_wall_motion', 'dyskinetic',  'Dyskinetic',  40, 0, 1);

-- --- card_effusion_grade: pericardial effusion -------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_effusion_grade', 'none',     'None',     10, 1, 1),
  ('card_effusion_grade', 'trace',    'Trace',    20, 0, 1),
  ('card_effusion_grade', 'mild',     'Mild',     30, 0, 1),
  ('card_effusion_grade', 'moderate', 'Moderate', 40, 0, 1),
  ('card_effusion_grade', 'large',    'Large',    50, 0, 1);

-- --- card_stress_protocol: exercise stress test protocols --------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_stress_protocol', 'bruce',     'Bruce',          10, 1, 1),
  ('card_stress_protocol', 'mod_bruce', 'Modified Bruce', 20, 0, 1),
  ('card_stress_protocol', 'naughton',  'Naughton',       30, 0, 1),
  ('card_stress_protocol', 'ramp',      'Ramp',           40, 0, 1);

-- =============================================================================
-- Card: Doppler Echocardiogram
-- Location: interface/forms/cardiology/table.sql
--
-- One row per study. Several studies per encounter are allowed.
-- Numeric measurements are typed columns so evolution charts (LVEF, diameters,
-- gradients, PASP) can be built with a simple query by pid.
-- Derived values (BSA, E/A, E/e', indexed values) are filled by the application,
-- not by generated columns, to stay compatible with all MySQL/MariaDB versions.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `form_card_echo` (
  -- ---- Identification and workflow ------------------------------------------
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the study',
  `cardiology_id` BIGINT(20) NOT NULL COMMENT 'Parent container (form_cardiology.id)',
  `pid` BIGINT(20) NOT NULL COMMENT 'Patient id (patient_data.pid)',
  `encounter` BIGINT(20) NOT NULL COMMENT 'Encounter number (form_encounter.encounter)',
  `study_date` DATETIME NOT NULL COMMENT 'Date and time the study was performed',
  `user` VARCHAR(255) DEFAULT NULL COMMENT 'Username of the physician who performed the study (users.username)',
  `status` VARCHAR(10) NOT NULL DEFAULT 'draft' COMMENT 'Workflow status: draft or final. Final studies are read-only',
  `finalized_at` DATETIME DEFAULT NULL COMMENT 'Date and time the study was finalized',
  `finalized_by` VARCHAR(255) DEFAULT NULL COMMENT 'Username of the user who finalized the study',
  `activity` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Soft delete flag (1 = active, 0 = deleted)',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Row last update timestamp',

  -- ---- Clinical context -----------------------------------------------------
  `indication` TEXT COMMENT 'Indication for the study',
  `height_cm` DECIMAL(5,1) DEFAULT NULL COMMENT 'Patient height in centimeters at study time',
  `weight_kg` DECIMAL(5,1) DEFAULT NULL COMMENT 'Patient weight in kilograms at study time',
  `bsa_m2` DECIMAL(4,2) DEFAULT NULL COMMENT 'Body surface area in m2, calculated by the application',
  `heart_rate_bpm` SMALLINT DEFAULT NULL COMMENT 'Heart rate during the study, beats per minute',
  `bp_systolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Systolic blood pressure during the study, mmHg',
  `bp_diastolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Diastolic blood pressure during the study, mmHg',

  -- ---- Left ventricle: dimensions and systolic function ---------------------
  `lvedd_mm` DECIMAL(4,1) DEFAULT NULL COMMENT 'Left ventricular end-diastolic diameter, mm',
  `lvesd_mm` DECIMAL(4,1) DEFAULT NULL COMMENT 'Left ventricular end-systolic diameter, mm',
  `ivs_mm` DECIMAL(4,1) DEFAULT NULL COMMENT 'Interventricular septum thickness in diastole, mm',
  `pw_mm` DECIMAL(4,1) DEFAULT NULL COMMENT 'Posterior wall thickness in diastole, mm',
  `lvedv_ml` DECIMAL(5,1) DEFAULT NULL COMMENT 'Left ventricular end-diastolic volume, mL',
  `lvesv_ml` DECIMAL(5,1) DEFAULT NULL COMMENT 'Left ventricular end-systolic volume, mL',
  `lvef_pct` DECIMAL(4,1) DEFAULT NULL COMMENT 'Left ventricular ejection fraction, percent',
  `lvef_method` VARCHAR(32) DEFAULT NULL COMMENT 'LVEF measurement method (list_options list_id card_ef_method)',
  `lv_mass_g` DECIMAL(5,1) DEFAULT NULL COMMENT 'Left ventricular mass, grams',
  `lv_mass_index_g_m2` DECIMAL(5,1) DEFAULT NULL COMMENT 'Left ventricular mass indexed to BSA, g/m2, calculated by the application',
  `wall_motion` VARCHAR(32) DEFAULT NULL COMMENT 'Global wall motion (list_options list_id card_wall_motion)',
  `wall_motion_detail` TEXT COMMENT 'Segmental wall motion abnormalities, free text',

  -- ---- Left ventricle: diastolic function -----------------------------------
  `mitral_e_cm_s` DECIMAL(5,1) DEFAULT NULL COMMENT 'Mitral inflow E wave velocity, cm/s',
  `mitral_a_cm_s` DECIMAL(5,1) DEFAULT NULL COMMENT 'Mitral inflow A wave velocity, cm/s',
  `mitral_e_a_ratio` DECIMAL(4,2) DEFAULT NULL COMMENT 'E/A ratio, calculated by the application',
  `dec_time_ms` SMALLINT DEFAULT NULL COMMENT 'E wave deceleration time, ms',
  `e_prime_septal_cm_s` DECIMAL(4,1) DEFAULT NULL COMMENT 'Septal mitral annulus e prime (tissue Doppler), cm/s',
  `e_prime_lateral_cm_s` DECIMAL(4,1) DEFAULT NULL COMMENT 'Lateral mitral annulus e prime (tissue Doppler), cm/s',
  `e_e_prime_ratio` DECIMAL(4,1) DEFAULT NULL COMMENT 'E/e prime average ratio, calculated by the application',

  -- ---- Atria ----------------------------------------------------------------
  `la_diameter_mm` DECIMAL(4,1) DEFAULT NULL COMMENT 'Left atrium anteroposterior diameter, mm',
  `la_volume_index_ml_m2` DECIMAL(4,1) DEFAULT NULL COMMENT 'Left atrial volume indexed to BSA, mL/m2',
  `ra_area_cm2` DECIMAL(4,1) DEFAULT NULL COMMENT 'Right atrium area, cm2',

  -- ---- Right ventricle and pulmonary pressure -------------------------------
  `rv_basal_diameter_mm` DECIMAL(4,1) DEFAULT NULL COMMENT 'Right ventricle basal diameter, mm',
  `tapse_mm` DECIMAL(4,1) DEFAULT NULL COMMENT 'Tricuspid annular plane systolic excursion, mm',
  `rv_s_prime_cm_s` DECIMAL(4,1) DEFAULT NULL COMMENT 'Tricuspid annulus systolic velocity (S prime), cm/s',
  `ivc_diameter_mm` DECIMAL(4,1) DEFAULT NULL COMMENT 'Inferior vena cava diameter, mm',
  `rap_mmhg` SMALLINT DEFAULT NULL COMMENT 'Estimated right atrial pressure, mmHg',
  `tr_peak_velocity_m_s` DECIMAL(3,2) DEFAULT NULL COMMENT 'Tricuspid regurgitation peak velocity, m/s',
  `pasp_mmhg` SMALLINT DEFAULT NULL COMMENT 'Estimated pulmonary artery systolic pressure, mmHg',

  -- ---- Aorta ----------------------------------------------------------------
  `ao_root_mm` DECIMAL(4,1) DEFAULT NULL COMMENT 'Aortic root diameter at the sinuses of Valsalva, mm',
  `asc_aorta_mm` DECIMAL(4,1) DEFAULT NULL COMMENT 'Ascending aorta diameter, mm',

  -- ---- Valves ---------------------------------------------------------------
  `ao_regurg_grade` VARCHAR(32) DEFAULT NULL COMMENT 'Aortic regurgitation grade (list_options list_id card_valve_grade)',
  `ao_stenosis_grade` VARCHAR(32) DEFAULT NULL COMMENT 'Aortic stenosis grade (list_options list_id card_valve_grade)',
  `ao_peak_velocity_m_s` DECIMAL(3,2) DEFAULT NULL COMMENT 'Aortic valve peak velocity, m/s',
  `ao_mean_gradient_mmhg` DECIMAL(4,1) DEFAULT NULL COMMENT 'Aortic valve mean gradient, mmHg',
  `ao_valve_area_cm2` DECIMAL(3,2) DEFAULT NULL COMMENT 'Aortic valve area, cm2',
  `mv_regurg_grade` VARCHAR(32) DEFAULT NULL COMMENT 'Mitral regurgitation grade (list_options list_id card_valve_grade)',
  `mv_stenosis_grade` VARCHAR(32) DEFAULT NULL COMMENT 'Mitral stenosis grade (list_options list_id card_valve_grade)',
  `mv_mean_gradient_mmhg` DECIMAL(4,1) DEFAULT NULL COMMENT 'Mitral valve mean gradient, mmHg',
  `mv_valve_area_cm2` DECIMAL(3,2) DEFAULT NULL COMMENT 'Mitral valve area, cm2',
  `tv_regurg_grade` VARCHAR(32) DEFAULT NULL COMMENT 'Tricuspid regurgitation grade (list_options list_id card_valve_grade)',
  `pv_regurg_grade` VARCHAR(32) DEFAULT NULL COMMENT 'Pulmonary regurgitation grade (list_options list_id card_valve_grade)',
  `valve_findings` TEXT COMMENT 'Additional valve findings (morphology, prostheses, vegetations), free text',

  -- ---- Pericardium ----------------------------------------------------------
  `pericardial_effusion` VARCHAR(32) DEFAULT NULL COMMENT 'Pericardial effusion grade (list_options list_id card_effusion_grade)',

  -- ---- Report text ----------------------------------------------------------
  `conclusion` TEXT COMMENT 'Study conclusion printed in the PDF report',
  `comments` TEXT COMMENT 'Additional comments or recommendations',

  PRIMARY KEY (`id`),
  KEY `idx_pid_study_date` (`pid`, `study_date`),
  KEY `idx_cardiology_id` (`cardiology_id`),
  KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Doppler echocardiogram study (one row per study)';

-- -----------------------------------------------------------------------------
-- Extra list used by this card: LVEF measurement method
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `activity`) VALUES
  ('lists', 'card_ef_method', 'Cardiology - LVEF method', 0, 0, 0, 1);

INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_ef_method', 'simpson_biplane', 'Simpson biplane', 10, 1, 1),
  ('card_ef_method', 'teichholz',       'Teichholz',       20, 0, 1),
  ('card_ef_method', 'visual',          'Visual estimate', 30, 0, 1);

-- =============================================================================
-- Card: Exercise Stress Test (ergometry)
-- Location: interface/forms/cardiology/table.sql
--
-- Two tables:
--   form_card_stress_test        -> one row per study (summary and conclusion)
--   form_card_stress_test_stage  -> one row per protocol stage / recovery minute
--                                   (feeds the HR, BP and ST charts)
-- Derived values (age, predicted HR, % of predicted, double product, Duke score)
-- are filled by the application when saving.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `form_card_stress_test` (
  -- ---- Identification and workflow ------------------------------------------
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the study',
  `cardiology_id` BIGINT(20) NOT NULL COMMENT 'Parent container (form_cardiology.id)',
  `pid` BIGINT(20) NOT NULL COMMENT 'Patient id (patient_data.pid)',
  `encounter` BIGINT(20) NOT NULL COMMENT 'Encounter number (form_encounter.encounter)',
  `study_date` DATETIME NOT NULL COMMENT 'Date and time the study was performed',
  `user` VARCHAR(255) DEFAULT NULL COMMENT 'Username of the physician who supervised the study (users.username)',
  `status` VARCHAR(10) NOT NULL DEFAULT 'draft' COMMENT 'Workflow status: draft or final. Final studies are read-only',
  `finalized_at` DATETIME DEFAULT NULL COMMENT 'Date and time the study was finalized',
  `finalized_by` VARCHAR(255) DEFAULT NULL COMMENT 'Username of the user who finalized the study',
  `activity` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Soft delete flag (1 = active, 0 = deleted)',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Row last update timestamp',

  -- ---- Clinical context -----------------------------------------------------
  `indication` TEXT COMMENT 'Indication for the study',
  `age_years` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Patient age in years at study time, calculated by the application',
  `height_cm` DECIMAL(5,1) DEFAULT NULL COMMENT 'Patient height in centimeters',
  `weight_kg` DECIMAL(5,1) DEFAULT NULL COMMENT 'Patient weight in kilograms',
  `on_beta_blocker` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Patient was taking a beta blocker at study time (1 = yes)',
  `medications_note` TEXT COMMENT 'Relevant medications at study time, free text',
  `protocol` VARCHAR(32) DEFAULT NULL COMMENT 'Stress test protocol (list_options list_id card_stress_protocol)',
  `rest_ecg_findings` TEXT COMMENT 'Resting ECG findings before the test',

  -- ---- Baseline values ------------------------------------------------------
  `rest_hr_bpm` SMALLINT DEFAULT NULL COMMENT 'Resting heart rate, beats per minute',
  `rest_bp_systolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Resting systolic blood pressure, mmHg',
  `rest_bp_diastolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Resting diastolic blood pressure, mmHg',

  -- ---- Exercise performance -------------------------------------------------
  `max_predicted_hr_bpm` SMALLINT DEFAULT NULL COMMENT 'Maximum predicted heart rate (220 minus age), calculated by the application',
  `target_hr_bpm` SMALLINT DEFAULT NULL COMMENT 'Target heart rate (85 percent of maximum predicted), calculated by the application',
  `peak_hr_bpm` SMALLINT DEFAULT NULL COMMENT 'Peak heart rate reached, beats per minute',
  `peak_hr_pct_predicted` DECIMAL(4,1) DEFAULT NULL COMMENT 'Peak heart rate as percent of maximum predicted, calculated by the application',
  `peak_bp_systolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Peak systolic blood pressure during exercise, mmHg',
  `peak_bp_diastolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Diastolic blood pressure at peak exercise, mmHg',
  `double_product` INT DEFAULT NULL COMMENT 'Peak heart rate times peak systolic pressure, calculated by the application',
  `exercise_duration_s` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Total exercise duration in seconds',
  `mets_achieved` DECIMAL(4,1) DEFAULT NULL COMMENT 'Functional capacity reached, in METs',
  `bp_response` VARCHAR(32) DEFAULT NULL COMMENT 'Blood pressure response to exercise (list_options list_id card_bp_response)',
  `hr_recovery_1min_bpm` SMALLINT DEFAULT NULL COMMENT 'Heart rate drop at 1 minute of recovery, beats per minute',

  -- ---- Symptoms, ST segment and arrhythmias ---------------------------------
  `symptom` VARCHAR(32) DEFAULT NULL COMMENT 'Main symptom during the test (list_options list_id card_stress_symptom)',
  `angina_index` TINYINT DEFAULT NULL COMMENT 'Duke angina index: 0 = none, 1 = non-limiting angina, 2 = test-limiting angina',
  `st_depression_mm` DECIMAL(3,1) DEFAULT NULL COMMENT 'Maximum ST segment depression, mm',
  `st_elevation_mm` DECIMAL(3,1) DEFAULT NULL COMMENT 'Maximum ST segment elevation, mm',
  `st_leads` VARCHAR(64) DEFAULT NULL COMMENT 'Leads showing ST changes, free text (e.g. V4-V6)',
  `st_onset_hr_bpm` SMALLINT DEFAULT NULL COMMENT 'Heart rate at which ST changes started, beats per minute',
  `arrhythmias` TEXT COMMENT 'Arrhythmias observed during exercise or recovery, free text',

  -- ---- Result ---------------------------------------------------------------
  `stop_reason` VARCHAR(32) DEFAULT NULL COMMENT 'Reason the test was stopped (list_options list_id card_stop_reason)',
  `duke_score` DECIMAL(4,1) DEFAULT NULL COMMENT 'Duke treadmill score (minutes minus 5 x ST deviation mm minus 4 x angina index), calculated by the application',
  `result` VARCHAR(32) DEFAULT NULL COMMENT 'Overall test result (list_options list_id card_stress_result)',
  `conclusion` TEXT COMMENT 'Study conclusion printed in the PDF report',
  `comments` TEXT COMMENT 'Additional comments or recommendations',

  PRIMARY KEY (`id`),
  KEY `idx_pid_study_date` (`pid`, `study_date`),
  KEY `idx_cardiology_id` (`cardiology_id`),
  KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Exercise stress test study (one row per study)';

-- -----------------------------------------------------------------------------
-- Stage-by-stage records: feed the HR, BP and ST charts of the report
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `form_card_stress_test_stage` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `stress_test_id` BIGINT(20) NOT NULL COMMENT 'Parent study (form_card_stress_test.id)',
  `phase` VARCHAR(10) NOT NULL DEFAULT 'exercise' COMMENT 'Phase: rest, exercise or recovery',
  `stage_no` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Stage number within the phase (recovery: minute number)',
  `elapsed_s` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Seconds elapsed since the start of the test',
  `speed_kmh` DECIMAL(3,1) DEFAULT NULL COMMENT 'Treadmill speed in km/h (the application converts from mph when needed)',
  `grade_pct` DECIMAL(4,1) DEFAULT NULL COMMENT 'Treadmill grade, percent',
  `mets` DECIMAL(4,1) DEFAULT NULL COMMENT 'Estimated workload of the stage, METs',
  `hr_bpm` SMALLINT DEFAULT NULL COMMENT 'Heart rate at the end of the stage, beats per minute',
  `bp_systolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Systolic blood pressure at the end of the stage, mmHg',
  `bp_diastolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Diastolic blood pressure at the end of the stage, mmHg',
  `st_mm` DECIMAL(3,1) DEFAULT NULL COMMENT 'ST segment level at the end of the stage, mm (negative = depression)',
  `symptom_note` VARCHAR(255) DEFAULT NULL COMMENT 'Symptoms or findings noted in the stage, free text',
  PRIMARY KEY (`id`),
  KEY `idx_stress_test_id` (`stress_test_id`, `elapsed_s`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Stage-by-stage records of an exercise stress test';

-- -----------------------------------------------------------------------------
-- Lists used by this card (stored in list_options)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `activity`) VALUES
  ('lists', 'card_bp_response',    'Cardiology - Blood pressure response',  0, 0, 0, 1),
  ('lists', 'card_stress_symptom', 'Cardiology - Stress test symptom',      0, 0, 0, 1),
  ('lists', 'card_stop_reason',    'Cardiology - Stress test stop reason',  0, 0, 0, 1),
  ('lists', 'card_stress_result',  'Cardiology - Stress test result',       0, 0, 0, 1);

INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_bp_response', 'normal',       'Normal',                  10, 1, 1),
  ('card_bp_response', 'hypertensive', 'Hypertensive response',   20, 0, 1),
  ('card_bp_response', 'blunted',      'Blunted response',        30, 0, 1),
  ('card_bp_response', 'hypotensive',  'Hypotensive response',    40, 0, 1),

  ('card_stress_symptom', 'none',          'None',                  10, 1, 1),
  ('card_stress_symptom', 'typical_angina','Typical angina',        20, 0, 1),
  ('card_stress_symptom', 'atypical_pain', 'Atypical chest pain',   30, 0, 1),
  ('card_stress_symptom', 'dyspnea',       'Dyspnea',               40, 0, 1),
  ('card_stress_symptom', 'dizziness',     'Dizziness',             50, 0, 1),
  ('card_stress_symptom', 'fatigue',       'Fatigue',               60, 0, 1),
  ('card_stress_symptom', 'claudication',  'Leg claudication',      70, 0, 1),

  ('card_stop_reason', 'target_hr',    'Target heart rate reached',  10, 1, 1),
  ('card_stop_reason', 'fatigue',      'Fatigue or exhaustion',      20, 0, 1),
  ('card_stop_reason', 'symptoms',     'Symptoms',                   30, 0, 1),
  ('card_stop_reason', 'st_changes',   'ST segment changes',         40, 0, 1),
  ('card_stop_reason', 'arrhythmia',   'Arrhythmia',                 50, 0, 1),
  ('card_stop_reason', 'hypertension', 'Hypertensive response',      60, 0, 1),
  ('card_stop_reason', 'hypotension',  'Hypotensive response',       70, 0, 1),
  ('card_stop_reason', 'patient_req',  'Patient request',            80, 0, 1),

  ('card_stress_result', 'negative',     'Negative for ischemia',       10, 1, 1),
  ('card_stress_result', 'positive',      'Positive for ischemia',       20, 0, 1),
  ('card_stress_result', 'inconclusive',  'Inconclusive',                30, 0, 1),
  ('card_stress_result', 'submaximal',    'Submaximal (target not reached)', 40, 0, 1);
