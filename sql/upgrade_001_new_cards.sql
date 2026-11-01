-- =============================================================================
-- Cardiology Form Module: Upgrade 001 - Add ECG, Preop Risk, and Fitness Cards
-- Location: interface/forms/form_cardiology/sql/upgrade_001_new_cards.sql
--
-- Adds tables and catalog seeds for three new clinical cards:
--   1. ecg         -> 12-lead Electrocardiogram (form_card_ecg)
--   2. preop_risk  -> Preoperative Cardiovascular Risk (form_card_preop_risk)
--   3. fitness     -> Cardiology Fitness Certificate (form_card_fitness)
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
  (`code`, `title`, `icon`, `table_name`, `acl_section`, `acl_value`, `sort_order`, `global_active`) VALUES
  ('ecg',        '12-Lead ECG',                      'fa-wave-square',     'form_card_ecg',        'patients', 'med', 30, 1),
  ('preop_risk', 'Preoperative Cardiovascular Risk', 'fa-clipboard-check', 'form_card_preop_risk', 'patients', 'med', 40, 1),
  ('fitness',    'Cardiology Fitness Certificate',   'fa-certificate',     'form_card_fitness',    'patients', 'med', 50, 1);

-- -----------------------------------------------------------------------------
-- 2. Register list IDs in list_options (list_id = 'lists')
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `activity`) VALUES
  ('lists', 'card_ecg_rhythm',             'Cardiology - ECG rhythm',                     0, 0, 0, 1),
  ('lists', 'card_ecg_av_conduction',      'Cardiology - ECG AV conduction',              0, 0, 0, 1),
  ('lists', 'card_ecg_vent_conduction',    'Cardiology - ECG ventricular conduction',     0, 0, 0, 1),
  ('lists', 'card_ecg_hypertrophy',        'Cardiology - ECG chamber hypertrophy',        0, 0, 0, 1),
  ('lists', 'card_ecg_st_t',               'Cardiology - ECG ST-T changes',               0, 0, 0, 1),
  ('lists', 'card_surgery_urgency',        'Cardiology - Surgery urgency',                0, 0, 0, 1),
  ('lists', 'card_surgery_risk',           'Cardiology - Surgery risk class',             0, 0, 0, 1),
  ('lists', 'card_functional_capacity',    'Cardiology - Functional capacity',            0, 0, 0, 1),
  ('lists', 'card_asa_class',              'Cardiology - ASA physical status',            0, 0, 0, 1),
  ('lists', 'card_preop_recommendation',   'Cardiology - Preop recommendation',           0, 0, 0, 1),
  ('lists', 'card_fitness_purpose',        'Cardiology - Fitness purpose',                0, 0, 0, 1),
  ('lists', 'card_fitness_ecg',            'Cardiology - Fitness ECG result',             0, 0, 0, 1),
  ('lists', 'card_fitness_determination',  'Cardiology - Fitness determination',          0, 0, 0, 1);

-- -----------------------------------------------------------------------------
-- 3. Options for ECG card lists
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_ecg_rhythm', 'sinus',         'Sinus rhythm',        10, 1, 1),
  ('card_ecg_rhythm', 'sinus_brady',   'Sinus bradycardia',   20, 0, 1),
  ('card_ecg_rhythm', 'sinus_tachy',   'Sinus tachycardia',   30, 0, 1),
  ('card_ecg_rhythm', 'afib',          'Atrial fibrillation', 40, 0, 1),
  ('card_ecg_rhythm', 'aflutter',      'Atrial flutter',      50, 0, 1),
  ('card_ecg_rhythm', 'atrial_tachy',  'Atrial tachycardia',  60, 0, 1),
  ('card_ecg_rhythm', 'junctional',    'Junctional rhythm',   70, 0, 1),
  ('card_ecg_rhythm', 'paced',         'Paced rhythm',        80, 0, 1),
  ('card_ecg_rhythm', 'other',         'Other rhythm',        90, 0, 1),

  ('card_ecg_av_conduction', 'normal',       'Normal AV conduction',               10, 1, 1),
  ('card_ecg_av_conduction', 'first_degree', 'First-degree AV block',              20, 0, 1),
  ('card_ecg_av_conduction', 'mobitz_1',     'Second-degree AV block Mobitz I',    30, 0, 1),
  ('card_ecg_av_conduction', 'mobitz_2',     'Second-degree AV block Mobitz II',   40, 0, 1),
  ('card_ecg_av_conduction', 'third_degree', 'Third-degree (complete) AV block',   50, 0, 1),

  ('card_ecg_vent_conduction', 'normal',       'Normal intraventricular conduction',             10, 1, 1),
  ('card_ecg_vent_conduction', 'rbbb',         'Right bundle branch block (RBBB)',               20, 0, 1),
  ('card_ecg_vent_conduction', 'lbbb',         'Left bundle branch block (LBBB)',                30, 0, 1),
  ('card_ecg_vent_conduction', 'lafb',         'Left anterior fascicular block (LAFB)',          40, 0, 1),
  ('card_ecg_vent_conduction', 'lpfb',         'Left posterior fascicular block (LPFB)',         50, 0, 1),
  ('card_ecg_vent_conduction', 'bifascicular', 'Bifascicular block',                             60, 0, 1),
  ('card_ecg_vent_conduction', 'nonspecific',  'Nonspecific intraventricular conduction delay',  70, 0, 1),

  ('card_ecg_hypertrophy', 'none',          'No hypertrophy',                    10, 1, 1),
  ('card_ecg_hypertrophy', 'lvh',           'Left ventricular hypertrophy (LVH)',20, 0, 1),
  ('card_ecg_hypertrophy', 'rvh',           'Right ventricular hypertrophy (RVH)',30, 0, 1),
  ('card_ecg_hypertrophy', 'biventricular', 'Biventricular hypertrophy',         40, 0, 1),

  ('card_ecg_st_t', 'none',         'Normal repolarization',           10, 1, 1),
  ('card_ecg_st_t', 'st_depression','ST-segment depression',           20, 0, 1),
  ('card_ecg_st_t', 'st_elevation', 'ST-segment elevation',            30, 0, 1),
  ('card_ecg_st_t', 't_inversion',  'T-wave inversion',                40, 0, 1),
  ('card_ecg_st_t', 'nonspecific',  'Nonspecific ST-T abnormalities',  50, 0, 1);

-- -----------------------------------------------------------------------------
-- 4. Options for Preoperative Risk card lists
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_surgery_urgency', 'elective',  'Elective',  10, 1, 1),
  ('card_surgery_urgency', 'urgent',    'Urgent',    20, 0, 1),
  ('card_surgery_urgency', 'emergency', 'Emergency', 30, 0, 1),

  ('card_surgery_risk', 'low',          'Low risk (< 1%)',          10, 1, 1),
  ('card_surgery_risk', 'intermediate', 'Intermediate risk (1-5%)', 20, 0, 1),
  ('card_surgery_risk', 'high',         'High risk (> 5%)',         30, 0, 1),

  ('card_functional_capacity', 'unknown',           'Unknown / Not assessed', 10, 1, 1),
  ('card_functional_capacity', 'poor_lt4_mets',     'Poor (< 4 METs)',        20, 0, 1),
  ('card_functional_capacity', 'moderate_4_10_mets', 'Moderate (4-10 METs)',   30, 0, 1),
  ('card_functional_capacity', 'good_gt10_mets',    'Good (> 10 METs)',       40, 0, 1),

  ('card_asa_class', 'asa_1', 'ASA I - Normal healthy patient',                                         10, 1, 1),
  ('card_asa_class', 'asa_2', 'ASA II - Patient with mild systemic disease',                             20, 0, 1),
  ('card_asa_class', 'asa_3', 'ASA III - Patient with severe systemic disease',                         30, 0, 1),
  ('card_asa_class', 'asa_4', 'ASA IV - Severe systemic disease that is a constant threat to life',     40, 0, 1),
  ('card_asa_class', 'asa_5', 'ASA V - Moribund patient not expected to survive without operation',     50, 0, 1),
  ('card_asa_class', 'asa_6', 'ASA VI - Brain-dead patient for organ donation',                         60, 0, 1),

  ('card_preop_recommendation', 'proceed',          'Proceed with surgery as planned',                 10, 1, 1),
  ('card_preop_recommendation', 'proceed_optimize', 'Proceed with medical optimization',              20, 0, 1),
  ('card_preop_recommendation', 'further_testing',  'Further cardiovascular evaluation recommended',   30, 0, 1),
  ('card_preop_recommendation', 'postpone',         'Postpone elective surgery',                       40, 0, 1);

-- -----------------------------------------------------------------------------
-- 5. Options for Fitness Certificate card lists
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `list_options`
  (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('card_fitness_purpose', 'sport_recreational', 'Recreational sports',                      10, 1, 1),
  ('card_fitness_purpose', 'sport_competitive',  'Competitive sports',                       20, 0, 1),
  ('card_fitness_purpose', 'gym',                'Gym / Fitness center',                     30, 0, 1),
  ('card_fitness_purpose', 'work',               'Occupational / Work clearance',            40, 0, 1),
  ('card_fitness_purpose', 'school',             'School / University physical education',   50, 0, 1),
  ('card_fitness_purpose', 'travel',             'High altitude / Adventure travel',         60, 0, 1),
  ('card_fitness_purpose', 'other',              'Other activity',                           70, 0, 1),

  ('card_fitness_ecg', 'normal',   'Normal ECG',    10, 1, 1),
  ('card_fitness_ecg', 'abnormal', 'Abnormal ECG',  20, 0, 1),
  ('card_fitness_ecg', 'not_done', 'Not performed', 30, 0, 1),

  ('card_fitness_determination', 'fit',              'Fit without restrictions',                 10, 1, 1),
  ('card_fitness_determination', 'fit_restrictions', 'Fit with restrictions',                    20, 0, 1),
  ('card_fitness_determination', 'not_fit',          'Temporarily or permanently unfit',         30, 0, 1),
  ('card_fitness_determination', 'pending_studies',  'Clearance pending further evaluation',     40, 0, 1);

-- -----------------------------------------------------------------------------
-- 6. Table: form_card_ecg (12-Lead Electrocardiogram)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `form_card_ecg` (
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

  `indication` TEXT COMMENT 'Clinical indication for the electrocardiogram',
  `heart_rate_bpm` SMALLINT DEFAULT NULL COMMENT 'Heart rate in beats per minute',
  `rhythm` VARCHAR(32) DEFAULT NULL COMMENT 'Cardiac rhythm (list_options list_id card_ecg_rhythm)',
  `axis_deg` SMALLINT DEFAULT NULL COMMENT 'Frontal QRS electrical axis in degrees (-180 to 180)',
  `pr_ms` SMALLINT DEFAULT NULL COMMENT 'PR interval in milliseconds (60 to 400)',
  `qrs_ms` SMALLINT DEFAULT NULL COMMENT 'QRS duration in milliseconds (40 to 300)',
  `qt_ms` SMALLINT DEFAULT NULL COMMENT 'QT interval in milliseconds (200 to 700)',
  `qtc_bazett_ms` SMALLINT DEFAULT NULL COMMENT 'Corrected QT interval by Bazett formula in ms, calculated server-side',
  `qtc_fridericia_ms` SMALLINT DEFAULT NULL COMMENT 'Corrected QT interval by Fridericia formula in ms, calculated server-side',
  `av_conduction` VARCHAR(32) DEFAULT NULL COMMENT 'Atrioventricular conduction (list_options list_id card_ecg_av_conduction)',
  `ventricular_conduction` VARCHAR(32) DEFAULT NULL COMMENT 'Intraventricular conduction (list_options list_id card_ecg_vent_conduction)',
  `hypertrophy` VARCHAR(32) DEFAULT NULL COMMENT 'Chamber hypertrophy (list_options list_id card_ecg_hypertrophy)',
  `st_t_changes` VARCHAR(32) DEFAULT NULL COMMENT 'Repolarization changes (list_options list_id card_ecg_st_t)',
  `st_t_detail` TEXT COMMENT 'Detailed ST-T segment abnormalities description, free text',
  `pathologic_q` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Presence of pathologic Q waves (1 = yes, 0 = no)',
  `q_leads` VARCHAR(64) DEFAULT NULL COMMENT 'Leads showing pathologic Q waves, free text',
  `findings` TEXT COMMENT 'Additional electrocardiographic findings, free text',
  `conclusion` TEXT COMMENT 'Electrocardiographic interpretation conclusion printed in report',
  `comments` TEXT COMMENT 'Additional clinical recommendations or comments',

  PRIMARY KEY (`id`),
  KEY `idx_pid_study_date` (`pid`, `study_date`),
  KEY `idx_cardiology_id` (`cardiology_id`),
  KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='12-lead electrocardiogram report study (one row per study)';

-- -----------------------------------------------------------------------------
-- 7. Table: form_card_preop_risk (Preoperative Cardiovascular Risk Assessment)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `form_card_preop_risk` (
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

  `surgery_description` VARCHAR(255) DEFAULT NULL COMMENT 'Description of planned surgery or procedure',
  `surgery_date` DATE DEFAULT NULL COMMENT 'Scheduled surgery date',
  `surgery_urgency` VARCHAR(32) DEFAULT NULL COMMENT 'Surgical urgency (list_options list_id card_surgery_urgency)',
  `surgery_risk_class` VARCHAR(32) DEFAULT NULL COMMENT 'Inherent surgical risk class set by physician (list_options list_id card_surgery_risk)',
  `height_cm` DECIMAL(5,1) DEFAULT NULL COMMENT 'Patient height in centimeters',
  `weight_kg` DECIMAL(5,1) DEFAULT NULL COMMENT 'Patient weight in kilograms',
  `heart_rate_bpm` SMALLINT DEFAULT NULL COMMENT 'Heart rate in beats per minute',
  `bp_systolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Systolic blood pressure in mmHg',
  `bp_diastolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Diastolic blood pressure in mmHg',
  `functional_capacity` VARCHAR(32) DEFAULT NULL COMMENT 'Functional capacity (list_options list_id card_functional_capacity)',
  `lee_high_risk_surgery` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Lee RCRI: High-risk surgery (1 = yes, 0 = no)',
  `lee_ischemic_heart_disease` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Lee RCRI: History of ischemic heart disease (1 = yes, 0 = no)',
  `lee_heart_failure` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Lee RCRI: History of congestive heart failure (1 = yes, 0 = no)',
  `lee_cerebrovascular_disease` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Lee RCRI: History of cerebrovascular disease (1 = yes, 0 = no)',
  `lee_insulin_diabetes` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Lee RCRI: Preoperative insulin therapy for diabetes (1 = yes, 0 = no)',
  `lee_creatinine_gt_2` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Lee RCRI: Preoperative serum creatinine above 2.0 mg/dL (1 = yes, 0 = no)',
  `lee_score` TINYINT DEFAULT NULL COMMENT 'Lee RCRI score: sum of the six risk factors (0 to 6), calculated server-side',
  `lee_class` VARCHAR(4) DEFAULT NULL COMMENT 'Lee RCRI class: I, II, III, or IV, calculated server-side',
  `asa_class` VARCHAR(16) DEFAULT NULL COMMENT 'ASA physical status classification (list_options list_id card_asa_class)',
  `lvef_pct` DECIMAL(4,1) DEFAULT NULL COMMENT 'Left ventricular ejection fraction percent (5 to 90)',
  `medications_note` TEXT COMMENT 'Current cardiovascular and perioperative medications, free text',
  `ecg_summary` TEXT COMMENT 'Preoperative ECG findings summary, free text',
  `echo_summary` TEXT COMMENT 'Echocardiogram findings summary if performed, free text',
  `labs_note` TEXT COMMENT 'Relevant laboratory values, free text',
  `recommendation` VARCHAR(32) DEFAULT NULL COMMENT 'Cardiovascular clearance recommendation (list_options list_id card_preop_recommendation)',
  `perioperative_recommendations` TEXT COMMENT 'Specific perioperative management instructions',
  `conclusion` TEXT COMMENT 'Final cardiovascular risk assessment summary for report',
  `comments` TEXT COMMENT 'Additional clinical recommendations or comments',

  PRIMARY KEY (`id`),
  KEY `idx_pid_study_date` (`pid`, `study_date`),
  KEY `idx_cardiology_id` (`cardiology_id`),
  KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Preoperative cardiovascular risk assessment study (one row per study)';

-- -----------------------------------------------------------------------------
-- 8. Table: form_card_fitness (Cardiology Fitness Certificate)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `form_card_fitness` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the study',
  `cardiology_id` BIGINT(20) NOT NULL COMMENT 'Parent container (form_cardiology.id)',
  `pid` BIGINT(20) NOT NULL COMMENT 'Patient id (patient_data.pid)',
  `encounter` BIGINT(20) NOT NULL COMMENT 'Encounter number (form_encounter.encounter)',
  `study_date` DATETIME NOT NULL COMMENT 'Date and time the study was performed',
  `user` VARCHAR(255) DEFAULT NULL COMMENT 'Username of the physician who performed the evaluation (users.username)',
  `status` VARCHAR(10) NOT NULL DEFAULT 'draft' COMMENT 'Workflow status: draft or final. Final studies are read-only',
  `finalized_at` DATETIME DEFAULT NULL COMMENT 'Date and time the study was finalized',
  `finalized_by` VARCHAR(255) DEFAULT NULL COMMENT 'Username of the user who finalized the study',
  `activity` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Soft delete flag (1 = active, 0 = deleted)',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Row last update timestamp',

  `purpose` VARCHAR(32) DEFAULT NULL COMMENT 'Purpose of the fitness evaluation (list_options list_id card_fitness_purpose)',
  `purpose_detail` VARCHAR(255) DEFAULT NULL COMMENT 'Specific sport, job or activity details',
  `symptoms_note` TEXT COMMENT 'Reported cardiovascular symptoms or negative findings, free text',
  `family_history_note` TEXT COMMENT 'Family history of premature CAD, sudden cardiac death, or cardiomyopathy',
  `height_cm` DECIMAL(5,1) DEFAULT NULL COMMENT 'Patient height in centimeters',
  `weight_kg` DECIMAL(5,1) DEFAULT NULL COMMENT 'Patient weight in kilograms',
  `heart_rate_bpm` SMALLINT DEFAULT NULL COMMENT 'Resting heart rate in beats per minute',
  `bp_systolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Resting systolic blood pressure in mmHg',
  `bp_diastolic_mmhg` SMALLINT DEFAULT NULL COMMENT 'Resting diastolic blood pressure in mmHg',
  `bmi` DECIMAL(4,1) DEFAULT NULL COMMENT 'Body mass index in kg/m2, calculated server-side',
  `exam_findings` TEXT COMMENT 'Cardiovascular physical examination findings, free text',
  `ecg_result` VARCHAR(32) DEFAULT NULL COMMENT 'Resting 12-lead ECG evaluation result (list_options list_id card_fitness_ecg)',
  `ecg_note` VARCHAR(255) DEFAULT NULL COMMENT 'Resting ECG findings summary',
  `additional_studies` TEXT COMMENT 'Additional testing performed (echocardiogram, stress test, Holter), free text',
  `determination` VARCHAR(32) DEFAULT NULL COMMENT 'Cardiovascular fitness determination (list_options list_id card_fitness_determination)',
  `restrictions` TEXT COMMENT 'Specific physical activity or competitive restrictions when applicable',
  `valid_until` DATE DEFAULT NULL COMMENT 'Certificate expiration date',
  `conclusion` TEXT COMMENT 'Medical certificate conclusion printed on document',
  `comments` TEXT COMMENT 'Additional clinical recommendations or comments',

  PRIMARY KEY (`id`),
  KEY `idx_pid_study_date` (`pid`, `study_date`),
  KEY `idx_cardiology_id` (`cardiology_id`),
  KEY `idx_pid_encounter` (`pid`, `encounter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Cardiology fitness certificate study (one row per study)';

COMMIT;

