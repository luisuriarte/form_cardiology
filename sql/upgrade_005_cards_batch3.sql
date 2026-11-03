-- =============================================================================
-- Cardiology Form Module: Upgrade 005 - ABPM, Chagas Disease
-- Location: interface/forms/form_cardiology/sql/upgrade_005_cards_batch3.sql
--
-- Adds tables and catalog seeds for two new clinical cards:
--   1. abpm   -> Ambulatory Blood Pressure Monitoring (form_card_abpm)
--   2. chagas -> Chagas Disease Follow-up (form_card_chagas)
--
-- Safe to re-run on existing databases (idempotent DDL and seeds).
-- Compatible with OpenEMR 8.2.0 - 8.5.0 and MariaDB 10.x / MySQL 8.x.
-- Note: Do NOT put semicolons inside comments or COMMENT strings.
-- =============================================================================

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- 1. Register the two new modules in card_modules
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `card_modules`
  (`code`, `title`, `icon`, `table_name`, `acl_section`, `acl_value`, `card_type`, `doc_category_id`, `sort_order`, `global_active`) VALUES
  ('abpm',   'Ambulatory Blood Pressure Monitoring (ABPM)', 'fa-tachometer', 'form_card_abpm',   'patients', 'med', 'study', 22004, 90, 1),
  ('chagas', 'Chagas Disease Follow-up',                   'fa-bug',        'form_card_chagas', 'patients', 'med', 'study', 22007, 100, 1);

-- -----------------------------------------------------------------------------
-- 2. Register list IDs in list_options (list_id = 'lists')
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `activity`) VALUES
  ('lists', 'card_dipping_pattern',        'Cardiology - ABPM dipping pattern',       0, 0, 0, 1),
  ('lists', 'card_abpm_classification',    'Cardiology - ABPM classification',        0, 0, 0, 1),
  ('lists', 'card_chagas_phase',           'Cardiology - Chagas disease phase',       0, 0, 0, 1),
  ('lists', 'card_chagas_transmission',    'Cardiology - Chagas transmission route',  0, 0, 0, 1),
  ('lists', 'card_chagas_serology_status', 'Cardiology - Chagas serology status',     0, 0, 0, 1),
  ('lists', 'card_chagas_serology_test',   'Cardiology - Chagas serology test',       0, 0, 0, 1),
  ('lists', 'card_chagas_serology_result', 'Cardiology - Chagas serology result',     0, 0, 0, 1),
  ('lists', 'card_chagas_kuschnir',        'Cardiology - Chagas Kuschnir stage',      0, 0, 0, 1),
  ('lists', 'card_chagas_abcd',            'Cardiology - Chagas ABCD stage',          0, 0, 0, 1),
  ('lists', 'card_chagas_device',          'Cardiology - Chagas cardiac device',      0, 0, 0, 1),
  ('lists', 'card_chagas_treatment_status','Cardiology - Chagas treatment status',    0, 0, 0, 1),
  ('lists', 'card_chagas_treatment_drug',  'Cardiology - Chagas treatment drug',      0, 0, 0, 1),
  ('lists', 'card_nyha',                   'Cardiology - NYHA functional class',      0, 0, 0, 1),
  ('lists', 'card_wall_motion',            'Cardiology - Wall motion',                0, 0, 0, 1);

-- -----------------------------------------------------------------------------
-- 3. Options for ABPM card lists
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_dipping_pattern', 'dipping',         'Dipping pattern',          10, 1, 1),
  ('card_dipping_pattern', 'non_dipping',     'Non-dipping pattern',      20, 0, 1),
  ('card_dipping_pattern', 'reverse_dipping', 'Reverse dipping pattern',  30, 0, 1),
  ('card_dipping_pattern', 'extreme_dipping', 'Extreme dipping pattern',  40, 0, 1),
  ('card_dipping_pattern', 'not_classified',  'Not classified',           50, 0, 1),

  ('card_abpm_classification', 'normotensive',          'Normotensive profile',          10, 1, 1),
  ('card_abpm_classification', 'white_coat',            'White-coat profile',            20, 0, 1),
  ('card_abpm_classification', 'masked',                'Masked profile',                30, 0, 1),
  ('card_abpm_classification', 'sustained_hypertension','Sustained hypertension profile',40, 0, 1),
  ('card_abpm_classification', 'not_classified',        'Not classified',                50, 0, 1);

-- -----------------------------------------------------------------------------
-- 4. Options for Chagas Disease card lists
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_chagas_phase', 'acute',                  'Acute phase',                    10, 1, 1),
  ('card_chagas_phase', 'chronic_indeterminate',  'Chronic indeterminate form',     20, 0, 1),
  ('card_chagas_phase', 'chronic_cardiac',        'Chronic cardiac form',           30, 0, 1),
  ('card_chagas_phase', 'chronic_digestive',      'Chronic digestive form',         40, 0, 1),
  ('card_chagas_phase', 'chronic_cardiodigestive','Chronic cardiodigestive form',   50, 0, 1),
  ('card_chagas_phase', 'congenital',              'Congenital Chagas disease',      60, 0, 1),
  ('card_chagas_phase', 'reactivation',           'Reactivation',                   70, 0, 1),

  ('card_chagas_transmission', 'vector',       'Vector-borne',                  10, 1, 1),
  ('card_chagas_transmission', 'congenital',   'Mother-to-child (congenital)',  20, 0, 1),
  ('card_chagas_transmission', 'transfusion',  'Blood transfusion',             30, 0, 1),
  ('card_chagas_transmission', 'transplant',   'Organ transplant',              40, 0, 1),
  ('card_chagas_transmission', 'oral',         'Oral',                          50, 0, 1),
  ('card_chagas_transmission', 'accidental',   'Laboratory accident',           60, 0, 1),
  ('card_chagas_transmission', 'unknown',      'Unknown route',                 70, 0, 1),
  ('card_chagas_transmission', 'other',        'Other route',                   80, 0, 1),

  ('card_chagas_serology_status', 'positive',       'Positive',         10, 1, 1),
  ('card_chagas_serology_status', 'negative',       'Negative',         20, 0, 1),
  ('card_chagas_serology_status', 'indeterminate',  'Indeterminate',    30, 0, 1),
  ('card_chagas_serology_status', 'result_pending', 'Result pending',   40, 0, 1),
  ('card_chagas_serology_status', 'not_performed',  'Not performed',    50, 0, 1),

  ('card_chagas_serology_test', 'elisa', 'ELISA',                                      10, 1, 1),
  ('card_chagas_serology_test', 'ifa',   'Indirect immunofluorescence',                20, 0, 1),
  ('card_chagas_serology_test', 'iha',   'Indirect hemagglutination',                  30, 0, 1),
  ('card_chagas_serology_test', 'clia',  'Chemiluminescence',                          40, 0, 1),
  ('card_chagas_serology_test', 'rapid', 'Rapid immunochromatographic test',           50, 0, 1),
  ('card_chagas_serology_test', 'other', 'Other test',                                 60, 0, 1),

  ('card_chagas_serology_result', 'positive',     'Positive',       10, 1, 1),
  ('card_chagas_serology_result', 'negative',     'Negative',       20, 0, 1),
  ('card_chagas_serology_result', 'indeterminate','Indeterminate',  30, 0, 1),
  ('card_chagas_serology_result', 'inconclusive', 'Inconclusive',   40, 0, 1),

  ('card_chagas_kuschnir', '0',   'Kuschnir stage 0',   10, 1, 1),
  ('card_chagas_kuschnir', 'i',   'Kuschnir stage I',   20, 0, 1),
  ('card_chagas_kuschnir', 'ii',  'Kuschnir stage II',  30, 0, 1),
  ('card_chagas_kuschnir', 'iii', 'Kuschnir stage III', 40, 0, 1),

  ('card_chagas_abcd', 'a',  'ABCD stage A',  10, 1, 1),
  ('card_chagas_abcd', 'b1', 'ABCD stage B1', 20, 0, 1),
  ('card_chagas_abcd', 'b2', 'ABCD stage B2', 30, 0, 1),
  ('card_chagas_abcd', 'c',  'ABCD stage C',  40, 0, 1),
  ('card_chagas_abcd', 'd',  'ABCD stage D',  50, 0, 1),

  ('card_chagas_device', 'none',      'No device',                            10, 1, 1),
  ('card_chagas_device', 'pacemaker', 'Pacemaker',                            20, 0, 1),
  ('card_chagas_device', 'icd',       'Implantable cardioverter-defibrillator', 30, 0, 1),
  ('card_chagas_device', 'crt',       'Cardiac resynchronization therapy device', 40, 0, 1),
  ('card_chagas_device', 'crt_d',     'CRT-D device',                         50, 0, 1),
  ('card_chagas_device', 'other',     'Other device',                         60, 0, 1),

  ('card_chagas_treatment_status', 'not_started',  'Not started',           10, 1, 1),
  ('card_chagas_treatment_status', 'active',       'Active treatment',      20, 0, 1),
  ('card_chagas_treatment_status', 'completed',    'Completed treatment',   30, 0, 1),
  ('card_chagas_treatment_status', 'interrupted',  'Interrupted treatment', 40, 0, 1),
  ('card_chagas_treatment_status', 'not_indicated','Not indicated',         50, 0, 1),

  ('card_chagas_treatment_drug', 'benznidazole', 'Benznidazole', 10, 1, 1),
  ('card_chagas_treatment_drug', 'nifurtimox',   'Nifurtimox',   20, 0, 1),
  ('card_chagas_treatment_drug', 'other',        'Other drug',   30, 0, 1),

  ('card_nyha', 'i',   'Class I',   10, 1, 1),
  ('card_nyha', 'ii',  'Class II',  20, 0, 1),
  ('card_nyha', 'iii', 'Class III', 30, 0, 1),
  ('card_nyha', 'iv',  'Class IV',  40, 0, 1),

  ('card_wall_motion', 'normal',      'Normal',      10, 1, 1),
  ('card_wall_motion', 'hypokinetic', 'Hypokinetic', 20, 0, 1),
  ('card_wall_motion', 'akinetic',    'Akinetic',    30, 0, 1),
  ('card_wall_motion', 'dyskinetic',  'Dyskinetic',  40, 0, 1);

-- -----------------------------------------------------------------------------
-- 5. Table: form_card_abpm (Ambulatory Blood Pressure Monitoring)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `form_card_abpm` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the study',
  `cardiology_id` BIGINT(20) NOT NULL COMMENT 'Parent container (form_cardiology.id)',
  `pid` BIGINT(20) NOT NULL COMMENT 'Patient id (patient_data.pid)',
  `encounter` BIGINT(20) NOT NULL COMMENT 'Encounter number (form_encounter.encounter)',
  `study_date` DATETIME NOT NULL COMMENT 'Date and time the study was performed',
  `user` VARCHAR(255) DEFAULT NULL COMMENT 'Username of the physician who interpreted the study (users.username)',
  `status` VARCHAR(10) NOT NULL DEFAULT 'draft' COMMENT 'Workflow status: draft or final. Final studies are read-only',
  `finalized_at` DATETIME DEFAULT NULL COMMENT 'Date and time the study was finalized',
  `finalized_by` VARCHAR(255) DEFAULT NULL COMMENT 'Username of the user who finalized the study',
  `activity` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Soft delete flag (1 = active, 0 = deleted)',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Row last update timestamp',

  `indication` TEXT COMMENT 'Clinical indication for the ABPM study, free text',
  `device_model` VARCHAR(100) DEFAULT NULL COMMENT 'ABPM device model or manufacturer, free text',
  `recording_hours` SMALLINT DEFAULT NULL COMMENT 'Recording duration in hours (6 to 72)',
  `readings_total` SMALLINT DEFAULT NULL COMMENT 'Total number of blood pressure readings (1 to 500)',
  `readings_valid` SMALLINT DEFAULT NULL COMMENT 'Number of valid blood pressure readings (0 to 500)',
  `valid_pct` DECIMAL(5,2) DEFAULT NULL COMMENT 'Calculated percentage of valid readings (0 to 100)',
  `on_antihypertensives` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Patient was taking antihypertensive drugs during the recording (1 = yes, 0 = no)',
  `medications_note` TEXT COMMENT 'Current antihypertensive medications and doses, free text',
  `day_start_time` TIME DEFAULT NULL COMMENT 'Configured start time of the daytime period',
  `night_start_time` TIME DEFAULT NULL COMMENT 'Configured start time of the nighttime period',
  `office_sbp_mmhg` SMALLINT DEFAULT NULL COMMENT 'Office systolic blood pressure in mmHg (40 to 300)',
  `office_dbp_mmhg` SMALLINT DEFAULT NULL COMMENT 'Office diastolic blood pressure in mmHg (20 to 200)',
  `office_hr_bpm` SMALLINT DEFAULT NULL COMMENT 'Office heart rate in beats per minute (20 to 250)',
  `sbp_24h` SMALLINT DEFAULT NULL COMMENT 'Mean 24-hour systolic blood pressure in mmHg (40 to 300)',
  `dbp_24h` SMALLINT DEFAULT NULL COMMENT 'Mean 24-hour diastolic blood pressure in mmHg (20 to 200)',
  `hr_24h` SMALLINT DEFAULT NULL COMMENT 'Mean 24-hour heart rate in beats per minute (20 to 250)',
  `sbp_day` SMALLINT DEFAULT NULL COMMENT 'Mean daytime systolic blood pressure in mmHg (40 to 300)',
  `dbp_day` SMALLINT DEFAULT NULL COMMENT 'Mean daytime diastolic blood pressure in mmHg (20 to 200)',
  `hr_day` SMALLINT DEFAULT NULL COMMENT 'Mean daytime heart rate in beats per minute (20 to 250)',
  `sbp_night` SMALLINT DEFAULT NULL COMMENT 'Mean nighttime systolic blood pressure in mmHg (40 to 300)',
  `dbp_night` SMALLINT DEFAULT NULL COMMENT 'Mean nighttime diastolic blood pressure in mmHg (20 to 200)',
  `hr_night` SMALLINT DEFAULT NULL COMMENT 'Mean nighttime heart rate in beats per minute (20 to 250)',
  `sbp_max` SMALLINT DEFAULT NULL COMMENT 'Maximum systolic blood pressure in mmHg (40 to 300)',
  `dbp_max` SMALLINT DEFAULT NULL COMMENT 'Maximum diastolic blood pressure in mmHg (20 to 200)',
  `hr_max` SMALLINT DEFAULT NULL COMMENT 'Maximum heart rate in beats per minute (20 to 250)',
  `sbp_min` SMALLINT DEFAULT NULL COMMENT 'Minimum systolic blood pressure in mmHg (40 to 300)',
  `dbp_min` SMALLINT DEFAULT NULL COMMENT 'Minimum diastolic blood pressure in mmHg (20 to 200)',
  `hr_min` SMALLINT DEFAULT NULL COMMENT 'Minimum heart rate in beats per minute (20 to 250)',
  `pulse_pressure_24h` DECIMAL(6,2) DEFAULT NULL COMMENT 'Calculated 24-hour pulse pressure in mmHg (sbp_24h minus dbp_24h)',
  `dip_sbp_pct` DECIMAL(6,2) DEFAULT NULL COMMENT 'Calculated systolic dip percentage from daytime and nighttime means',
  `dip_dbp_pct` DECIMAL(6,2) DEFAULT NULL COMMENT 'Calculated diastolic dip percentage from daytime and nighttime means',
  `dipping_pattern` VARCHAR(32) DEFAULT NULL COMMENT 'Dipping pattern selected by the physician (list_options list_id card_dipping_pattern)',
  `abpm_classification` VARCHAR(32) DEFAULT NULL COMMENT 'ABPM profile classification selected by the physician (list_options list_id card_abpm_classification)',
  `findings` TEXT COMMENT 'Detailed ABPM findings, free text',
  `conclusion` TEXT COMMENT 'ABPM interpretation conclusion printed in the report',
  `comments` TEXT COMMENT 'Additional clinical recommendations or comments',

  PRIMARY KEY (`id`),
  KEY `idx_pid_study_date` (`pid`, `study_date`),
  KEY `idx_cardiology_id` (`cardiology_id`),
  KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Ambulatory blood pressure monitoring study (one row per study)';

-- -----------------------------------------------------------------------------
-- 6. Table: form_card_chagas (Chagas Disease Follow-up)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `form_card_chagas` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the study',
  `cardiology_id` BIGINT(20) NOT NULL COMMENT 'Parent container (form_cardiology.id)',
  `pid` BIGINT(20) NOT NULL COMMENT 'Patient id (patient_data.pid)',
  `encounter` BIGINT(20) NOT NULL COMMENT 'Encounter number (form_encounter.encounter)',
  `study_date` DATETIME NOT NULL COMMENT 'Date and time the study was performed',
  `user` VARCHAR(255) DEFAULT NULL COMMENT 'Username of the physician who performed the assessment (users.username)',
  `status` VARCHAR(10) NOT NULL DEFAULT 'draft' COMMENT 'Workflow status: draft or final. Final studies are read-only',
  `finalized_at` DATETIME DEFAULT NULL COMMENT 'Date and time the study was finalized',
  `finalized_by` VARCHAR(255) DEFAULT NULL COMMENT 'Username of the user who finalized the study',
  `activity` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Soft delete flag (1 = active, 0 = deleted)',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Row last update timestamp',

  `disease_phase` VARCHAR(32) DEFAULT NULL COMMENT 'Chagas disease phase or clinical form selected by the physician (list_options list_id card_chagas_phase)',
  `origin_area` VARCHAR(255) DEFAULT NULL COMMENT 'Country or region of origin, free text',
  `endemic_residence` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'History of residence in an endemic area (1 = yes, 0 = no)',
  `epidemiological_history` TEXT COMMENT 'Epidemiological history and exposure details, free text',
  `transmission_route` VARCHAR(32) DEFAULT NULL COMMENT 'Probable transmission route selected by the physician (list_options list_id card_chagas_transmission)',

  `serology_1_type` VARCHAR(32) DEFAULT NULL COMMENT 'First serology test type (list_options list_id card_chagas_serology_test)',
  `serology_1_result` VARCHAR(32) DEFAULT NULL COMMENT 'First serology test result (list_options list_id card_chagas_serology_result)',
  `serology_1_date` DATE DEFAULT NULL COMMENT 'First serology test date',
  `serology_2_type` VARCHAR(32) DEFAULT NULL COMMENT 'Second serology test type (list_options list_id card_chagas_serology_test)',
  `serology_2_result` VARCHAR(32) DEFAULT NULL COMMENT 'Second serology test result (list_options list_id card_chagas_serology_result)',
  `serology_2_date` DATE DEFAULT NULL COMMENT 'Second serology test date',
  `serology_3_type` VARCHAR(32) DEFAULT NULL COMMENT 'Third serology test type (list_options list_id card_chagas_serology_test)',
  `serology_3_result` VARCHAR(32) DEFAULT NULL COMMENT 'Third serology test result (list_options list_id card_chagas_serology_result)',
  `serology_3_date` DATE DEFAULT NULL COMMENT 'Third serology test date',
  `serology_status` VARCHAR(32) DEFAULT NULL COMMENT 'Overall serology status selected by the physician (list_options list_id card_chagas_serology_status)',
  `serology_note` TEXT COMMENT 'Serology laboratory details or comments, free text',

  `kuschnir_stage` VARCHAR(32) DEFAULT NULL COMMENT 'Kuschnir stage selected by the physician (list_options list_id card_chagas_kuschnir)',
  `abcd_stage` VARCHAR(32) DEFAULT NULL COMMENT 'ABCD stage selected by the physician (list_options list_id card_chagas_abcd)',

  `ecg_done` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Resting ECG performed (1 = yes, 0 = no)',
  `ecg_date` DATE DEFAULT NULL COMMENT 'Resting ECG date',
  `ecg_normal` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Resting ECG reported as normal (1 = yes, 0 = no)',
  `ecg_rbbb` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Right bundle branch block on ECG (1 = yes, 0 = no)',
  `ecg_lafb` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Left anterior fascicular block on ECG (1 = yes, 0 = no)',
  `ecg_lpfb` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Left posterior fascicular block on ECG (1 = yes, 0 = no)',
  `ecg_av_block` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Atrioventricular block on ECG (1 = yes, 0 = no)',
  `ecg_q_waves` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Pathologic Q waves on ECG (1 = yes, 0 = no)',
  `ecg_st_t_changes` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ST-T changes on ECG (1 = yes, 0 = no)',
  `ecg_low_voltage` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Low voltage on ECG (1 = yes, 0 = no)',
  `ecg_arrhythmia` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Arrhythmia on ECG (1 = yes, 0 = no)',
  `ecg_other` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Other ECG finding (1 = yes, 0 = no)',
  `ecg_findings` TEXT COMMENT 'Detailed ECG findings, free text',

  `chest_xray_done` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Chest X-ray performed (1 = yes, 0 = no)',
  `chest_xray_date` DATE DEFAULT NULL COMMENT 'Chest X-ray date',
  `chest_xray_cardiomegaly` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Cardiomegaly on chest X-ray (1 = yes, 0 = no)',
  `chest_xray_findings` TEXT COMMENT 'Detailed chest X-ray findings, free text',

  `echo_done` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Echocardiogram performed (1 = yes, 0 = no)',
  `echo_date` DATE DEFAULT NULL COMMENT 'Echocardiogram date',
  `lvef_pct` DECIMAL(4,1) DEFAULT NULL COMMENT 'Left ventricular ejection fraction percent (5 to 90)',
  `lvedd_mm` DECIMAL(5,1) DEFAULT NULL COMMENT 'Left ventricular end-diastolic diameter in mm (20 to 100)',
  `echo_aneurysm` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Left ventricular aneurysm on echocardiogram (1 = yes, 0 = no)',
  `echo_thrombus` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Left ventricular thrombus on echocardiogram (1 = yes, 0 = no)',
  `echo_wall_motion` VARCHAR(32) DEFAULT NULL COMMENT 'Echocardiographic wall motion (list_options list_id card_wall_motion)',
  `echo_findings` TEXT COMMENT 'Detailed echocardiogram findings, free text',

  `heart_failure` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Heart failure present (1 = yes, 0 = no)',
  `nyha_class` VARCHAR(32) DEFAULT NULL COMMENT 'NYHA functional class when heart failure is present (list_options list_id card_nyha)',
  `hf_findings` TEXT COMMENT 'Heart failure findings and functional details, free text',

  `cardiac_device` VARCHAR(32) DEFAULT NULL COMMENT 'Cardiac device selected by the physician (list_options list_id card_chagas_device)',
  `device_note` TEXT COMMENT 'Cardiac device details, free text',

  `digestive_involvement` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Digestive involvement present (1 = yes, 0 = no)',
  `megaesophagus` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Megaesophagus present (1 = yes, 0 = no)',
  `megacolon` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Megacolon present (1 = yes, 0 = no)',
  `constipation` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Chronic constipation reported (1 = yes, 0 = no)',
  `digestive_findings` TEXT COMMENT 'Digestive involvement findings, free text',

  `treatment_status` VARCHAR(32) DEFAULT NULL COMMENT 'Etiologic treatment status selected by the physician (list_options list_id card_chagas_treatment_status)',
  `treatment_drug` VARCHAR(32) DEFAULT NULL COMMENT 'Etiologic treatment drug selected by the physician (list_options list_id card_chagas_treatment_drug)',
  `treatment_start_date` DATE DEFAULT NULL COMMENT 'Etiologic treatment start date',
  `treatment_end_date` DATE DEFAULT NULL COMMENT 'Etiologic treatment end date, not before the start date',
  `treatment_note` TEXT COMMENT 'Treatment tolerance, adherence and follow-up details, free text without dose instructions',

  `mandatory_report` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Case reported to public health authorities (1 = yes, 0 = no)',
  `report_date` DATE DEFAULT NULL COMMENT 'Public health reporting date, required when mandatory_report is set',
  `public_health_note` TEXT COMMENT 'Public health reporting details, free text',
  `family_screening_done` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Family screening performed (1 = yes, 0 = no)',
  `family_screening_note` TEXT COMMENT 'Family screening details, free text',

  `plan` TEXT COMMENT 'Management plan for the next follow-up period',
  `next_control_date` DATE DEFAULT NULL COMMENT 'Date of the next planned follow-up',
  `conclusion` TEXT COMMENT 'Assessment conclusion printed in the report',
  `comments` TEXT COMMENT 'Additional clinical recommendations or comments',

  PRIMARY KEY (`id`),
  KEY `idx_pid_study_date` (`pid`, `study_date`),
  KEY `idx_cardiology_id` (`cardiology_id`),
  KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Chagas disease follow-up study (one row per study)';

COMMIT;
