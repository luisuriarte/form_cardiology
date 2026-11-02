-- =============================================================================
-- Cardiology Form Module: Upgrade 004 - Anticoagulation, Heart Failure, Holter
-- Location: interface/forms/form_cardiology/sql/upgrade_004_cards_batch2.sql
--
-- Adds tables and catalog seeds for three new clinical cards:
--   1. anticoag   -> Anticoagulation Monitoring (form_card_anticoag)
--   2. hf_followup -> Heart Failure Follow-up (form_card_hf_followup)
--   3. holter     -> Holter Monitor Study (form_card_holter)
--
-- Safe to re-run on existing databases (idempotent DDL and seeds).
-- Compatible with OpenEMR 8.2.0 - 8.5.0 and MariaDB 10.x / MySQL 8.x.
-- Note: Do NOT put semicolons inside comments or COMMENT strings.
-- =============================================================================

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- 1. Register the three new modules in card_modules
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `card_modules`
  (`code`, `title`, `icon`, `table_name`, `acl_section`, `acl_value`, `card_type`, `doc_category_id`, `sort_order`, `global_active`) VALUES
  ('anticoag',   'Anticoagulation Monitoring', 'fa-tint',  'form_card_anticoag',   'patients', 'med', 'study', 22007, 60, 1),
  ('hf_followup', 'Heart Failure Follow-up',   'fa-heart', 'form_card_hf_followup', 'patients', 'med', 'study', 22007, 70, 1),
  ('holter',     'Holter Monitor Study',       'fa-clock', 'form_card_holter',      'patients', 'med', 'study', 22004, 80, 1);

-- -----------------------------------------------------------------------------
-- 2. Register list IDs in list_options (list_id = 'lists')
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `activity`) VALUES
  ('lists', 'card_anticoag_indication', 'Cardiology - Anticoagulation indication', 0, 0, 0, 1),
  ('lists', 'card_anticoag_drug',       'Cardiology - Anticoagulant drug',         0, 0, 0, 1),
  ('lists', 'card_inr_sample',          'Cardiology - INR sample type',            0, 0, 0, 1),
  ('lists', 'card_adherence',           'Cardiology - Treatment adherence',        0, 0, 0, 1),
  ('lists', 'card_nyha',                'Cardiology - NYHA functional class',      0, 0, 0, 1),
  ('lists', 'card_hf_type',             'Cardiology - Heart failure type',         0, 0, 0, 1),
  ('lists', 'card_hf_etiology',         'Cardiology - Heart failure etiology',     0, 0, 0, 1),
  ('lists', 'card_hf_stage',            'Cardiology - Heart failure stage',        0, 0, 0, 1),
  ('lists', 'card_edema_grade',         'Cardiology - Edema grade',                0, 0, 0, 1);

-- -----------------------------------------------------------------------------
-- 3. Options for Anticoagulation card lists
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_anticoag_indication', 'atrial_fibrillation',  'Atrial fibrillation',              10, 1, 1),
  ('card_anticoag_indication', 'mechanical_valve',     'Mechanical heart valve',           20, 0, 1),
  ('card_anticoag_indication', 'bioprosthetic_valve',  'Bioprosthetic heart valve',        30, 0, 1),
  ('card_anticoag_indication', 'venous_thromboembolism', 'Venous thromboembolism',         40, 0, 1),
  ('card_anticoag_indication', 'lv_thrombus',          'Left ventricular thrombus',        50, 0, 1),
  ('card_anticoag_indication', 'other',                'Other indication',                 60, 0, 1),

  ('card_anticoag_drug', 'acenocoumarol', 'Acenocoumarol',                  10, 1, 1),
  ('card_anticoag_drug', 'warfarin',      'Warfarin',                       20, 0, 1),
  ('card_anticoag_drug', 'other_vka',     'Other vitamin K antagonist',     30, 0, 1),

  ('card_inr_sample', 'venous_lab',   'Venous sample (laboratory)', 10, 1, 1),
  ('card_inr_sample', 'point_of_care', 'Point-of-care',             20, 0, 1),

  ('card_adherence', 'good',    'Good',    10, 1, 1),
  ('card_adherence', 'partial', 'Partial', 20, 0, 1),
  ('card_adherence', 'poor',    'Poor',    30, 0, 1);

-- -----------------------------------------------------------------------------
-- 4. Options for Heart Failure Follow-up card lists
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_nyha', 'i',   'Class I',   10, 1, 1),
  ('card_nyha', 'ii',  'Class II',  20, 0, 1),
  ('card_nyha', 'iii', 'Class III', 30, 0, 1),
  ('card_nyha', 'iv',  'Class IV',  40, 0, 1),

  ('card_hf_type', 'hfref',       'Reduced ejection fraction',         10, 1, 1),
  ('card_hf_type', 'hfmref',      'Mildly reduced ejection fraction',  20, 0, 1),
  ('card_hf_type', 'hfpef',       'Preserved ejection fraction',       30, 0, 1),
  ('card_hf_type', 'unspecified', 'Unspecified',                       40, 0, 1),

  ('card_hf_etiology', 'ischemic',          'Ischemic',              10, 1, 1),
  ('card_hf_etiology', 'hypertensive',      'Hypertensive',          20, 0, 1),
  ('card_hf_etiology', 'chagas',            'Chagas cardiomyopathy', 30, 0, 1),
  ('card_hf_etiology', 'valvular',          'Valvular',              40, 0, 1),
  ('card_hf_etiology', 'dilated_idiopathic', 'Dilated idiopathic',   50, 0, 1),
  ('card_hf_etiology', 'other',             'Other',                 60, 0, 1),

  ('card_hf_stage', 'stage_a', 'Stage A', 10, 1, 1),
  ('card_hf_stage', 'stage_b', 'Stage B', 20, 0, 1),
  ('card_hf_stage', 'stage_c', 'Stage C', 30, 0, 1),
  ('card_hf_stage', 'stage_d', 'Stage D', 40, 0, 1),

  ('card_edema_grade', 'none',     'None',     10, 1, 1),
  ('card_edema_grade', 'mild',     'Mild',     20, 0, 1),
  ('card_edema_grade', 'moderate', 'Moderate', 30, 0, 1),
  ('card_edema_grade', 'severe',   'Severe',   40, 0, 1);

-- -----------------------------------------------------------------------------
-- 5. Table: form_card_anticoag (Anticoagulation Monitoring)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `form_card_anticoag` (
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

  `indication` VARCHAR(32) DEFAULT NULL COMMENT 'Indication for anticoagulation (list_options list_id card_anticoag_indication)',
  `drug` VARCHAR(32) DEFAULT NULL COMMENT 'Vitamin K antagonist in use (list_options list_id card_anticoag_drug)',
  `sample_type` VARCHAR(32) DEFAULT NULL COMMENT 'INR sample type (list_options list_id card_inr_sample)',
  `inr_value` DECIMAL(5,2) DEFAULT NULL COMMENT 'Measured INR value (0.5 to 10)',
  `target_inr_low` DECIMAL(5,2) DEFAULT NULL COMMENT 'Lower bound of the target INR range (1.0 to 4.0)',
  `target_inr_high` DECIMAL(5,2) DEFAULT NULL COMMENT 'Upper bound of the target INR range (1.5 to 5.0)',
  `in_range` VARCHAR(8) DEFAULT NULL COMMENT 'Calculated INR position: in, below, or above the target range',
  `current_weekly_dose_mg` DECIMAL(7,2) DEFAULT NULL COMMENT 'Current weekly anticoagulant dose in mg (0 to 200)',
  `new_weekly_dose_mg` DECIMAL(7,2) DEFAULT NULL COMMENT 'New weekly anticoagulant dose in mg (0 to 200)',
  `dose_change_pct` DECIMAL(10,2) DEFAULT NULL COMMENT 'Calculated weekly dose change as percent of current dose',
  `dosing_schedule` VARCHAR(255) DEFAULT NULL COMMENT 'Weekly dosing schedule or administration days, free text',
  `missed_doses` TEXT COMMENT 'Missed doses or adherence details reported by the patient, free text',
  `bleeding_event` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Bleeding event since the previous control (1 = yes, 0 = no)',
  `thrombotic_event` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Thrombotic event since the previous control (1 = yes, 0 = no)',
  `event_detail` TEXT COMMENT 'Description of the bleeding or thrombotic event',
  `adherence` VARCHAR(32) DEFAULT NULL COMMENT 'Reported treatment adherence (list_options list_id card_adherence)',
  `interacting_factors` TEXT COMMENT 'Diet, drugs, or clinical factors that may affect anticoagulation, free text',
  `next_control_date` DATE DEFAULT NULL COMMENT 'Date of the next planned INR control',
  `plan` TEXT COMMENT 'Management plan for the next follow-up period',
  `conclusion` TEXT COMMENT 'Assessment conclusion printed in the report',
  `comments` TEXT COMMENT 'Additional clinical recommendations or comments',

  PRIMARY KEY (`id`),
  KEY `idx_pid_study_date` (`pid`, `study_date`),
  KEY `idx_cardiology_id` (`cardiology_id`),
  KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Anticoagulation monitoring study (one row per study)';

-- -----------------------------------------------------------------------------
-- 6. Table: form_card_hf_followup (Heart Failure Follow-up)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `form_card_hf_followup` (
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

  `nyha_class` VARCHAR(32) DEFAULT NULL COMMENT 'NYHA functional class (list_options list_id card_nyha)',
  `nyha_numeric` TINYINT DEFAULT NULL COMMENT 'Calculated NYHA class number (1 to 4)',
  `hf_type` VARCHAR(32) DEFAULT NULL COMMENT 'Heart failure type by ejection fraction (list_options list_id card_hf_type)',
  `etiology` VARCHAR(32) DEFAULT NULL COMMENT 'Heart failure etiology (list_options list_id card_hf_etiology)',
  `hf_stage` VARCHAR(32) DEFAULT NULL COMMENT 'Heart failure stage (list_options list_id card_hf_stage)',
  `weight_kg` DECIMAL(6,3) DEFAULT NULL COMMENT 'Current body weight in kilograms (20 to 300)',
  `dry_weight_kg` DECIMAL(6,3) DEFAULT NULL COMMENT 'Estimated dry weight in kilograms (20 to 300)',
  `weight_change_kg` DECIMAL(6,3) DEFAULT NULL COMMENT 'Calculated weight change versus the previous final study in kilograms',
  `heart_rate_bpm` SMALLINT DEFAULT NULL COMMENT 'Heart rate in beats per minute (20 to 250)',
  `bp_systolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Systolic blood pressure in mmHg (40 to 300)',
  `bp_diastolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Diastolic blood pressure in mmHg (20 to 200)',
  `spo2_pct` DECIMAL(5,2) DEFAULT NULL COMMENT 'Peripheral oxygen saturation percent (50 to 100)',
  `edema` VARCHAR(32) DEFAULT NULL COMMENT 'Peripheral edema grade (list_options list_id card_edema_grade)',
  `jugular_distension` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Jugular venous distension present (1 = yes, 0 = no)',
  `rales` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Pulmonary rales present (1 = yes, 0 = no)',
  `orthopnea` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Orthopnea present (1 = yes, 0 = no)',
  `pnd` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Paroxysmal nocturnal dyspnea present (1 = yes, 0 = no)',
  `congestion_note` TEXT COMMENT 'Congestion findings and physical examination details, free text',
  `lvef_pct` DECIMAL(4,1) DEFAULT NULL COMMENT 'Left ventricular ejection fraction percent (5 to 90)',
  `lvef_date` DATE DEFAULT NULL COMMENT 'Date of the LVEF measurement, not in the future',
  `nt_probnp_pg_ml` DECIMAL(9,2) DEFAULT NULL COMMENT 'NT-proBNP concentration in pg/mL (0 to 70000)',
  `bnp_pg_ml` DECIMAL(9,2) DEFAULT NULL COMMENT 'BNP concentration in pg/mL (0 to 5000)',
  `creatinine_mg_dl` DECIMAL(5,2) DEFAULT NULL COMMENT 'Serum creatinine in mg/dL (0.1 to 15)',
  `potassium_mmol_l` DECIMAL(4,2) DEFAULT NULL COMMENT 'Serum potassium in mmol/L (1.5 to 8)',
  `sodium_mmol_l` DECIMAL(5,1) DEFAULT NULL COMMENT 'Serum sodium in mmol/L (100 to 170)',
  `adherence` VARCHAR(32) DEFAULT NULL COMMENT 'Reported treatment adherence (list_options list_id card_adherence)',
  `on_raas_inhibitor` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Patient currently takes a RAAS inhibitor (1 = yes, 0 = no)',
  `on_beta_blocker` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Patient currently takes a beta blocker (1 = yes, 0 = no)',
  `on_mra` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Patient currently takes a mineralocorticoid receptor antagonist (1 = yes, 0 = no)',
  `on_sglt2_inhibitor` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Patient currently takes an SGLT2 inhibitor (1 = yes, 0 = no)',
  `on_loop_diuretic` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Patient currently takes a loop diuretic (1 = yes, 0 = no)',
  `medications_note` TEXT COMMENT 'Current cardiovascular medications and doses, free text',
  `hospitalizations_since_last` SMALLINT DEFAULT NULL COMMENT 'Heart failure hospitalizations since the previous study (0 to 50)',
  `er_visits_since_last` SMALLINT DEFAULT NULL COMMENT 'Emergency department visits since the previous study (0 to 50)',
  `education_given` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Patient education provided during the visit (1 = yes, 0 = no)',
  `plan` TEXT COMMENT 'Management plan for the next follow-up period',
  `next_control_date` DATE DEFAULT NULL COMMENT 'Date of the next planned follow-up',
  `conclusion` TEXT COMMENT 'Assessment conclusion printed in the report',
  `comments` TEXT COMMENT 'Additional clinical recommendations or comments',

  PRIMARY KEY (`id`),
  KEY `idx_pid_study_date` (`pid`, `study_date`),
  KEY `idx_cardiology_id` (`cardiology_id`),
  KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Heart failure follow-up study (one row per study)';

-- -----------------------------------------------------------------------------
-- 7. Table: form_card_holter (Holter Monitor Study)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `form_card_holter` (
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

  `indication` TEXT COMMENT 'Clinical indication for the Holter study, free text',
  `recording_hours` SMALLINT DEFAULT NULL COMMENT 'Recording duration in hours (1 to 168)',
  `total_beats` INT DEFAULT NULL COMMENT 'Total beats analyzed (100 to 2000000)',
  `hr_min_bpm` SMALLINT DEFAULT NULL COMMENT 'Minimum heart rate in beats per minute (20 to 200)',
  `hr_mean_bpm` SMALLINT DEFAULT NULL COMMENT 'Mean heart rate in beats per minute (30 to 220)',
  `hr_max_bpm` SMALLINT DEFAULT NULL COMMENT 'Maximum heart rate in beats per minute (40 to 300)',
  `hr_min_time` TIME DEFAULT NULL COMMENT 'Time of the minimum heart rate',
  `hr_max_time` TIME DEFAULT NULL COMMENT 'Time of the maximum heart rate',
  `dominant_rhythm` VARCHAR(32) DEFAULT NULL COMMENT 'Dominant rhythm (list_options list_id card_ecg_rhythm)',
  `af_present` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Atrial fibrillation present (1 = yes, 0 = no)',
  `af_burden_pct` DECIMAL(5,2) DEFAULT NULL COMMENT 'Atrial fibrillation burden percent (0 to 100)',
  `paced_beats_pct` DECIMAL(5,2) DEFAULT NULL COMMENT 'Paced beats percent (0 to 100)',
  `longest_pause_ms` INT DEFAULT NULL COMMENT 'Longest pause in milliseconds (0 to 30000)',
  `pauses_gt_3s` INT DEFAULT NULL COMMENT 'Number of pauses longer than 3 seconds (0 to 1000)',
  `pvc_total` INT DEFAULT NULL COMMENT 'Total premature ventricular complexes (0 to 500000)',
  `pvc_burden_pct` DECIMAL(5,2) DEFAULT NULL COMMENT 'Calculated PVC burden percent of total beats (0 to 100)',
  `pvc_couplets` INT DEFAULT NULL COMMENT 'Number of PVC couplets (0 to 100000)',
  `nsvt_runs` INT DEFAULT NULL COMMENT 'Number of non-sustained ventricular tachycardia runs (0 to 10000)',
  `longest_nsvt_beats` INT DEFAULT NULL COMMENT 'Longest non-sustained ventricular tachycardia run in beats (0 to 1000)',
  `pac_total` INT DEFAULT NULL COMMENT 'Total premature atrial complexes (0 to 500000)',
  `svt_runs` INT DEFAULT NULL COMMENT 'Number of supraventricular tachycardia runs (0 to 10000)',
  `longest_svt_beats` INT DEFAULT NULL COMMENT 'Longest supraventricular tachycardia run in beats (0 to 10000)',
  `st_episodes` INT DEFAULT NULL COMMENT 'Number of ST-segment deviation episodes (0 to 1000)',
  `st_description` TEXT COMMENT 'ST-segment episode description, free text',
  `av_block_note` TEXT COMMENT 'Atrioventricular block findings, free text',
  `symptoms_reported` TEXT COMMENT 'Symptoms reported by the patient during the recording, free text',
  `symptom_correlation` TEXT COMMENT 'Symptom to rhythm correlation findings, free text',
  `findings` TEXT COMMENT 'Detailed Holter findings, free text',
  `conclusion` TEXT COMMENT 'Holter interpretation conclusion printed in the report',
  `comments` TEXT COMMENT 'Additional clinical recommendations or comments',

  PRIMARY KEY (`id`),
  KEY `idx_pid_study_date` (`pid`, `study_date`),
  KEY `idx_cardiology_id` (`cardiology_id`),
  KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Holter monitor study (one row per study)';

COMMIT;
