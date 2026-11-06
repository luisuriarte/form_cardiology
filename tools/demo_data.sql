-- ============================================================
-- demo_data.sql -- fictitious demo studies for functional tests
--
-- Set the three user variables below to a real patient, encounter and
-- cardiology container, then run this script once. The guard stops
-- execution (SELECT with an error message, no INSERT) when any of
-- them is NULL or 0, or when the container does not belong to that
-- patient and encounter.
-- ============================================================
SET @demo_pid := NULL;            -- <-- set: patient_data.pid
SET @demo_encounter := NULL;      -- <-- set: encounter number
SET @demo_cardiology_id := NULL;  -- <-- set: form_cardiology.id

SET @demo_guard := (
  SELECT IF(@demo_pid IS NULL OR @demo_pid = 0 OR @demo_encounter IS NULL OR @demo_encounter = 0
         OR @demo_cardiology_id IS NULL OR @demo_cardiology_id = 0,
         'DEMO GUARD FAILED: set @demo_pid, @demo_encounter and @demo_cardiology_id first',
         NULL));
SELECT @demo_guard AS demo_guard_error;

SET @demo_container_ok := (
  SELECT COUNT(*) FROM `form_cardiology`
  WHERE id = @demo_cardiology_id AND pid = @demo_pid AND encounter = @demo_encounter AND activity = 1);
SELECT IF(@demo_container_ok = 0,
       'DEMO GUARD FAILED: container does not belong to this patient and encounter',
       NULL) AS demo_container_error;

-- ---------------- Echo ----------------
INSERT INTO `form_card_echo`
  (`cardiology_id`, `pid`, `encounter`, `user`, `activity`, `status`, `finalized_at`, `finalized_by`, `study_date`, `indication`, `height_cm`, `weight_kg`, `bsa_m2`, `heart_rate_bpm`, `bp_systolic_mmhg`, `bp_diastolic_mmhg`, `lvedd_mm`, `lvesd_mm`, `ivs_mm`, `pw_mm`, `lvedv_ml`, `lvesv_ml`, `lvef_pct`, `lvef_method`, `lv_mass_g`, `lv_mass_index_g_m2`, `wall_motion`, `wall_motion_detail`, `mitral_e_cm_s`, `mitral_a_cm_s`, `mitral_e_a_ratio`, `dec_time_ms`, `e_prime_septal_cm_s`, `e_prime_lateral_cm_s`, `e_e_prime_ratio`, `la_diameter_mm`, `la_volume_index_ml_m2`, `ra_area_cm2`, `rv_basal_diameter_mm`, `tapse_mm`, `rv_s_prime_cm_s`, `ivc_diameter_mm`, `rap_mmhg`, `tr_peak_velocity_m_s`, `pasp_mmhg`, `ao_root_mm`, `asc_aorta_mm`, `ao_regurg_grade`, `ao_stenosis_grade`, `ao_peak_velocity_m_s`, `ao_mean_gradient_mmhg`, `ao_valve_area_cm2`, `mv_regurg_grade`, `mv_stenosis_grade`, `mv_mean_gradient_mmhg`, `mv_valve_area_cm2`, `tv_regurg_grade`, `pv_regurg_grade`, `valve_findings`, `pericardial_effusion`, `conclusion`, `comments`)
VALUES
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-01-15 10:00:00', 'demo', '2025-01-15 10:00:00', 'DEMO indication', 170, 70, 1.81, 72, 120, 80, 48, 31, 10, 9, 120, 55, 58, 'simpson_biplane', 150, 82.9, 'normal', 'DEMO segmental detail', 60, 50, 1.20, 200, 8, 10, 6.7, 36, 28.5, 14.0, 38, 20, 12, 16, 5, 2.8, 28, 32, 36, 'none', 'none', 1.8, 12.5, 1.4, 'none', 'none', 3.5, 0.8, 'none', 'none', 'DEMO valve findings', 'none', 'DEMO echo conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-04-15 10:00:00', 'demo', '2025-04-15 10:00:00', 'DEMO indication', 172, 75, 1.88, 72, 120, 80, 50, 33, 11, 10, 130, 62, 52, 'simpson_biplane', 165, 87.8, 'normal', 'DEMO segmental detail', 55, 45, 1.22, 190, 9, 11, 5.5, 36, 28.5, 14.0, 38, 20, 12, 16, 5, 2.8, 30, 33, 36, 'none', 'none', 1.8, 12.5, 1.4, 'none', 'none', 3.5, 0.8, 'none', 'none', 'DEMO valve findings', 'none', 'DEMO echo conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-07-15 10:00:00', 'demo', '2025-07-15 10:00:00', 'DEMO indication', 168, 65, 1.74, 72, 120, 80, 46, 30, 9, 8, 115, 50, 57, 'simpson_biplane', 140, 80.5, 'normal', 'DEMO segmental detail', 65, 40, 1.63, 210, 7, 9, 8.1, 36, 28.5, 14.0, 38, 20, 12, 16, 5, 2.8, 26, 31, 36, 'none', 'none', 1.8, 12.5, 1.4, 'none', 'none', 3.5, 0.8, 'none', 'none', 'DEMO valve findings', 'none', 'DEMO echo conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'draft', NULL, NULL, '2025-10-15 10:00:00', 'DEMO indication', 170, 80, 1.92, 72, 120, 80, 52, 35, 12, 10, 140, 68, 55, 'simpson_biplane', 180, 93.8, 'normal', 'DEMO segmental detail', 50, 55, 0.91, 180, 8, 10, 5.6, 36, 28.5, 14.0, 38, 20, 12, 16, 5, 2.8, 32, 34, 36, 'none', 'none', 1.8, 12.5, 1.4, 'none', 'none', 3.5, 0.8, 'none', 'none', 'DEMO valve findings', 'none', 'DEMO echo conclusion', NULL);

SET @demo_echo_ids := (SELECT GROUP_CONCAT(id ORDER BY study_date) FROM `form_card_echo` WHERE pid = @demo_pid AND encounter = @demo_encounter AND conclusion LIKE '%DEMO%');

-- ---------------- Stress Test ----------------
INSERT INTO `form_card_stress_test`
  (`cardiology_id`, `pid`, `encounter`, `user`, `activity`, `status`, `finalized_at`, `finalized_by`, `study_date`, `indication`, `age_years`, `height_cm`, `weight_kg`, `on_beta_blocker`, `medications_note`, `protocol`, `rest_ecg_findings`, `rest_hr_bpm`, `rest_bp_systolic_mmhg`, `rest_bp_diastolic_mmhg`, `max_predicted_hr_bpm`, `target_hr_bpm`, `peak_hr_bpm`, `peak_hr_pct_predicted`, `peak_bp_systolic_mmhg`, `peak_bp_diastolic_mmhg`, `double_product`, `exercise_duration_s`, `mets_achieved`, `bp_response`, `hr_recovery_1min_bpm`, `symptom`, `angina_index`, `st_depression_mm`, `st_elevation_mm`, `st_leads`, `st_onset_hr_bpm`, `arrhythmias`, `stop_reason`, `duke_score`, `result`, `conclusion`, `comments`)
VALUES
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-01-15 10:00:00', 'demo', '2025-01-15 10:00:00', 'DEMO indication', 65, 170, 70, 0, 'DEMO meds', 'bruce', 'DEMO rest ecg', 68, 120, 80, 155, 132, 150, 96.8, 190, 95, 28500, 600, 8.0, 'normal', 12, 'none', 0, 2.0, 0.0, 'V4-V6', 140, 'DEMO arrhythmias', 'target_hr', 0.0, 'negative', 'DEMO stress conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-04-15 10:00:00', 'demo', '2025-04-15 10:00:00', 'DEMO indication', 65, 170, 70, 0, 'DEMO meds', 'bruce', 'DEMO rest ecg', 68, 120, 80, 155, 132, 148, 95.5, 190, 95, 28120, 540, 7.5, 'normal', 12, 'none', 0, 1.0, 0.0, 'V4-V6', 140, 'DEMO arrhythmias', 'target_hr', 4.0, 'negative', 'DEMO stress conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-07-15 10:00:00', 'demo', '2025-07-15 10:00:00', 'DEMO indication', 65, 170, 70, 0, 'DEMO meds', 'bruce', 'DEMO rest ecg', 68, 120, 80, 155, 132, 152, 98.1, 190, 95, 28880, 660, 9.0, 'normal', 12, 'none', 0, 0.5, 0.0, 'V4-V6', 140, 'DEMO arrhythmias', 'target_hr', 8.5, 'negative', 'DEMO stress conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'draft', NULL, NULL, '2025-10-15 10:00:00', 'DEMO indication', 65, 170, 70, 0, 'DEMO meds', 'bruce', 'DEMO rest ecg', 68, 120, 80, 155, 132, 145, 93.5, 185, 95, 26825, 480, 7.0, 'normal', 12, 'none', 0, 3.0, 0.0, 'V4-V6', 140, 'DEMO arrhythmias', 'target_hr', -7.0, 'negative', 'DEMO stress conclusion', NULL);

SET @st1 := (SELECT id FROM `form_card_stress_test` WHERE pid = @demo_pid AND encounter = @demo_encounter AND conclusion LIKE '%DEMO%' ORDER BY study_date ASC LIMIT 1);
SET @st2 := (SELECT id FROM `form_card_stress_test` WHERE pid = @demo_pid AND encounter = @demo_encounter AND conclusion LIKE '%DEMO%' ORDER BY study_date ASC LIMIT 1 OFFSET 1);

INSERT INTO `form_card_stress_test_stage`
  (`stress_test_id`, `phase`, `stage_no`, `elapsed_s`, `speed_kmh`, `grade_pct`, `mets`, `hr_bpm`, `bp_systolic_mmhg`, `bp_diastolic_mmhg`, `st_mm`, `symptom_note`)
VALUES
  (@st1, 'rest', 0, 0, 0.0, 0.0, 1.0, 68, 120, 80, 0.0, 'DEMO stage note'),
  (@st1, 'exercise', 1, 180, 3.2, 10.0, 3.5, 100, 140, 90, 0.5, 'DEMO stage note'),
  (@st1, 'exercise', 2, 360, 5.6, 14.0, 6.0, 130, 170, 95, 1.0, 'DEMO stage note'),
  (@st1, 'exercise', 3, 540, 6.4, 16.0, 8.0, 148, 190, 95, 2.0, 'DEMO stage note'),
  (@st1, 'recovery', 1, 600, 0.0, 0.0, 3.0, 128, 160, 90, 1.5, 'DEMO stage note'),
  (@st1, 'recovery', 2, 660, 0.0, 0.0, 2.0, 112, 140, 85, 1.0, 'DEMO stage note'),
  (@st1, 'recovery', 3, 720, 0.0, 0.0, 1.5, 100, 130, 82, 0.5, 'DEMO stage note');

INSERT INTO `form_card_stress_test_stage`
  (`stress_test_id`, `phase`, `stage_no`, `elapsed_s`, `speed_kmh`, `grade_pct`, `mets`, `hr_bpm`, `bp_systolic_mmhg`, `bp_diastolic_mmhg`, `st_mm`, `symptom_note`)
VALUES
  (@st2, 'rest', 0, 0, 0.0, 0.0, 1.0, 68, 120, 80, 0.0, 'DEMO stage note'),
  (@st2, 'exercise', 1, 180, 3.2, 10.0, 3.5, 100, 140, 90, 0.5, 'DEMO stage note'),
  (@st2, 'exercise', 2, 360, 5.6, 14.0, 6.0, 130, 170, 95, 1.0, 'DEMO stage note'),
  (@st2, 'exercise', 3, 540, 6.4, 16.0, 8.0, 148, 190, 95, 2.0, 'DEMO stage note'),
  (@st2, 'recovery', 1, 600, 0.0, 0.0, 3.0, 128, 160, 90, 1.5, 'DEMO stage note'),
  (@st2, 'recovery', 2, 660, 0.0, 0.0, 2.0, 112, 140, 85, 1.0, 'DEMO stage note'),
  (@st2, 'recovery', 3, 720, 0.0, 0.0, 1.5, 100, 130, 82, 0.5, 'DEMO stage note');


-- ---------------- ECG ----------------
INSERT INTO `form_card_ecg`
  (`cardiology_id`, `pid`, `encounter`, `user`, `activity`, `status`, `finalized_at`, `finalized_by`, `study_date`, `indication`, `heart_rate_bpm`, `rhythm`, `axis_deg`, `pr_ms`, `qrs_ms`, `qt_ms`, `qtc_bazett_ms`, `qtc_fridericia_ms`, `av_conduction`, `ventricular_conduction`, `hypertrophy`, `st_t_changes`, `st_t_detail`, `pathologic_q`, `q_leads`, `findings`, `conclusion`, `comments`)
VALUES
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-01-15 10:00:00', 'demo', '2025-01-15 10:00:00', 'DEMO indication', 72, 'sinus', 60, 160, 90, 360, 394, 383, 'normal', 'normal', 'none', 'none', 'DEMO st-t detail', 0, 'V5', 'DEMO findings', 'DEMO ecg conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-04-15 10:00:00', 'demo', '2025-04-15 10:00:00', 'DEMO indication', 68, 'sinus', 60, 160, 90, 380, 405, 396, 'normal', 'normal', 'none', 'none', 'DEMO st-t detail', 0, 'V5', 'DEMO findings', 'DEMO ecg conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-07-15 10:00:00', 'demo', '2025-07-15 10:00:00', 'DEMO indication', 75, 'sinus', 60, 160, 90, 350, 391, 377, 'normal', 'normal', 'none', 'none', 'DEMO st-t detail', 0, 'V5', 'DEMO findings', 'DEMO ecg conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'draft', NULL, NULL, '2025-10-15 10:00:00', 'DEMO indication', 70, 'sinus', 60, 160, 90, 370, 400, 390, 'normal', 'normal', 'none', 'none', 'DEMO st-t detail', 0, 'V5', 'DEMO findings', 'DEMO ecg conclusion', NULL);

-- ---------------- Preop Risk ----------------
INSERT INTO `form_card_preop_risk`
  (`cardiology_id`, `pid`, `encounter`, `user`, `activity`, `status`, `finalized_at`, `finalized_by`, `study_date`, `surgery_description`, `surgery_date`, `surgery_urgency`, `surgery_risk_class`, `height_cm`, `weight_kg`, `heart_rate_bpm`, `bp_systolic_mmhg`, `bp_diastolic_mmhg`, `functional_capacity`, `lee_high_risk_surgery`, `lee_ischemic_heart_disease`, `lee_heart_failure`, `lee_cerebrovascular_disease`, `lee_insulin_diabetes`, `lee_creatinine_gt_2`, `lee_score`, `lee_class`, `asa_class`, `lvef_pct`, `medications_note`, `ecg_summary`, `echo_summary`, `labs_note`, `recommendation`, `perioperative_recommendations`, `conclusion`, `comments`)
VALUES
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-01-15 10:00:00', 'demo', '2025-01-15 10:00:00', 'DEMO appendectomy', '2025-08-01', 'elective', 'low', 170, 70, 72, 120, 80, 'moderate_4_10_mets', 0, 0, 0, 0, 0, 0, 0, 'I', 'asa_2', 55, 'DEMO meds', 'DEMO ecg', 'DEMO echo', 'DEMO labs', 'proceed', 'DEMO periop plan', 'DEMO preop conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-04-15 10:00:00', 'demo', '2025-04-15 10:00:00', 'DEMO appendectomy', '2025-08-01', 'elective', 'low', 170, 70, 72, 120, 80, 'moderate_4_10_mets', 1, 0, 0, 0, 0, 0, 1, 'II', 'asa_2', 55, 'DEMO meds', 'DEMO ecg', 'DEMO echo', 'DEMO labs', 'proceed', 'DEMO periop plan', 'DEMO preop conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-07-15 10:00:00', 'demo', '2025-07-15 10:00:00', 'DEMO appendectomy', '2025-08-01', 'elective', 'low', 170, 70, 72, 120, 80, 'moderate_4_10_mets', 1, 1, 0, 0, 1, 0, 3, 'IV', 'asa_2', 55, 'DEMO meds', 'DEMO ecg', 'DEMO echo', 'DEMO labs', 'proceed', 'DEMO periop plan', 'DEMO preop conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'draft', NULL, NULL, '2025-10-15 10:00:00', 'DEMO appendectomy', '2025-08-01', 'elective', 'low', 170, 70, 72, 120, 80, 'moderate_4_10_mets', 0, 0, 0, 0, 0, 0, 0, 'I', 'asa_2', 55, 'DEMO meds', 'DEMO ecg', 'DEMO echo', 'DEMO labs', 'proceed', 'DEMO periop plan', 'DEMO preop conclusion', NULL);

-- ---------------- Fitness ----------------
INSERT INTO `form_card_fitness`
  (`cardiology_id`, `pid`, `encounter`, `user`, `activity`, `status`, `finalized_at`, `finalized_by`, `study_date`, `purpose`, `purpose_detail`, `symptoms_note`, `family_history_note`, `height_cm`, `weight_kg`, `heart_rate_bpm`, `bp_systolic_mmhg`, `bp_diastolic_mmhg`, `bmi`, `exam_findings`, `ecg_result`, `ecg_note`, `additional_studies`, `determination`, `restrictions`, `valid_until`, `conclusion`, `comments`)
VALUES
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-01-15 10:00:00', 'demo', '2025-01-15 10:00:00', 'gym', 'DEMO gym detail', 'DEMO symptoms', 'DEMO family history', 170, 70, 72, 120, 80, 24.2, 'DEMO exam findings', 'normal', 'DEMO ecg', 'DEMO additional studies', 'fit', NULL, '2026-09-15', 'DEMO fitness conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-04-15 10:00:00', 'demo', '2025-04-15 10:00:00', 'gym', 'DEMO gym detail', 'DEMO symptoms', 'DEMO family history', 172, 75, 72, 120, 80, 25.4, 'DEMO exam findings', 'normal', 'DEMO ecg', 'DEMO additional studies', 'fit', NULL, '2026-09-15', 'DEMO fitness conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-07-15 10:00:00', 'demo', '2025-07-15 10:00:00', 'gym', 'DEMO gym detail', 'DEMO symptoms', 'DEMO family history', 168, 65, 72, 120, 80, 23.0, 'DEMO exam findings', 'normal', 'DEMO ecg', 'DEMO additional studies', 'fit', NULL, '2026-09-15', 'DEMO fitness conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'draft', NULL, NULL, '2025-10-15 10:00:00', 'gym', 'DEMO gym detail', 'DEMO symptoms', 'DEMO family history', 170, 80, 72, 120, 80, 27.7, 'DEMO exam findings', 'normal', 'DEMO ecg', 'DEMO additional studies', 'fit', NULL, '2026-09-15', 'DEMO fitness conclusion', NULL);

-- ---------------- Anticoagulation (TTR series) ----------------
INSERT INTO `form_card_anticoag`
  (`cardiology_id`, `pid`, `encounter`, `user`, `activity`, `status`, `finalized_at`, `finalized_by`, `study_date`, `indication`, `drug`, `sample_type`, `inr_value`, `target_inr_low`, `target_inr_high`, `in_range`, `current_weekly_dose_mg`, `new_weekly_dose_mg`, `dose_change_pct`, `dosing_schedule`, `missed_doses`, `bleeding_event`, `thrombotic_event`, `event_detail`, `adherence`, `interacting_factors`, `next_control_date`, `plan`, `conclusion`, `comments`)
VALUES
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-01-06 10:00:00', 'demo', '2025-01-06 10:00:00', 'atrial_fibrillation', 'warfarin', 'point_of_care', 2.0, 2.0, 3.0, in, 35, 35, 0.00, 'DEMO schedule', 0, 0, 0, NULL, 'good', NULL, '2025-05-30', 'DEMO plan', 'DEMO anticoag conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-02-03 10:00:00', 'demo', '2025-02-03 10:00:00', 'atrial_fibrillation', 'warfarin', 'point_of_care', 3.0, 2.0, 3.0, in, 35, 40, 14.29, 'DEMO schedule', 0, 0, 0, NULL, 'good', NULL, '2025-05-30', 'DEMO plan', 'DEMO anticoag conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-03-03 10:00:00', 'demo', '2025-03-03 10:00:00', 'atrial_fibrillation', 'warfarin', 'point_of_care', 4.0, 2.0, 3.0, above, 35, 35, -12.50, 'DEMO schedule', 0, 0, 0, NULL, 'good', NULL, '2025-05-30', 'DEMO plan', 'DEMO anticoag conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'draft', NULL, NULL, '2025-04-28 10:00:00', 'atrial_fibrillation', 'warfarin', 'point_of_care', 2.5, 2.0, 3.0, in, 35, 30, -14.29, 'DEMO schedule', 0, 0, 0, NULL, 'good', NULL, '2025-05-30', 'DEMO plan', 'DEMO anticoag conclusion', NULL);

-- ---------------- Heart Failure Follow-up ----------------
INSERT INTO `form_card_hf_followup`
  (`cardiology_id`, `pid`, `encounter`, `user`, `activity`, `status`, `finalized_at`, `finalized_by`, `study_date`, `nyha_class`, `nyha_numeric`, `hf_type`, `etiology`, `hf_stage`, `weight_kg`, `dry_weight_kg`, `weight_change_kg`, `heart_rate_bpm`, `bp_systolic_mmhg`, `bp_diastolic_mmhg`, `spo2_pct`, `edema`, `jugular_distension`, `rales`, `orthopnea`, `pnd`, `congestion_note`, `lvef_pct`, `lvef_date`, `nt_probnp_pg_ml`, `bnp_pg_ml`, `creatinine_mg_dl`, `potassium_mmol_l`, `sodium_mmol_l`, `adherence`, `on_raas_inhibitor`, `on_beta_blocker`, `on_mra`, `on_sglt2_inhibitor`, `on_loop_diuretic`, `medications_note`, `hospitalizations_since_last`, `er_visits_since_last`, `education_given`, `plan`, `next_control_date`, `conclusion`, `comments`)
VALUES
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-01-15 10:00:00', 'demo', '2025-01-15 10:00:00', 'ii', 2, 'hfpef', 'hypertensive', 'stage_b', 70, 69, NULL, 72, 120, 80, 97, 'mild', 0, 'none', 0, 0, 'DEMO congestion', 48, '2024-12-20', 850, 120, 1.1, 4.2, 139, 'good', 1, 1, 0, 1, 1, 'DEMO medications', 0, 0, 1, 'DEMO plan', '2026-01-15', 'DEMO hf conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-04-15 10:00:00', 'demo', '2025-04-15 10:00:00', 'ii', 2, 'hfpef', 'hypertensive', 'stage_b', 72, 71, 2.000, 72, 120, 80, 97, 'mild', 0, 'none', 0, 0, 'DEMO congestion', 48, '2024-12-20', 850, 120, 1.1, 4.2, 139, 'good', 1, 1, 0, 1, 1, 'DEMO medications', 0, 0, 1, 'DEMO plan', '2026-01-15', 'DEMO hf conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-07-15 10:00:00', 'demo', '2025-07-15 10:00:00', 'iii', 3, 'hfpef', 'hypertensive', 'stage_b', 71, 70, -1.000, 72, 120, 80, 97, 'mild', 0, 'none', 0, 0, 'DEMO congestion', 48, '2024-12-20', 850, 120, 1.1, 4.2, 139, 'good', 1, 1, 0, 1, 1, 'DEMO medications', 0, 0, 1, 'DEMO plan', '2026-01-15', 'DEMO hf conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'draft', NULL, NULL, '2025-10-15 10:00:00', 'ii', 2, 'hfpef', 'hypertensive', 'stage_b', 74, 73, 3.000, 72, 120, 80, 97, 'mild', 0, 'none', 0, 0, 'DEMO congestion', 48, '2024-12-20', 850, 120, 1.1, 4.2, 139, 'good', 1, 1, 0, 1, 1, 'DEMO medications', 0, 0, 1, 'DEMO plan', '2026-01-15', 'DEMO hf conclusion', NULL);

-- ---------------- Holter ----------------
INSERT INTO `form_card_holter`
  (`cardiology_id`, `pid`, `encounter`, `user`, `activity`, `status`, `finalized_at`, `finalized_by`, `study_date`, `indication`, `recording_hours`, `total_beats`, `hr_min_bpm`, `hr_mean_bpm`, `hr_max_bpm`, `hr_min_time`, `hr_max_time`, `dominant_rhythm`, `af_present`, `af_burden_pct`, `paced_beats_pct`, `longest_pause_ms`, `pauses_gt_3s`, `pvc_total`, `pvc_burden_pct`, `pvc_couplets`, `nsvt_runs`, `longest_nsvt_beats`, `pac_total`, `svt_runs`, `longest_svt_beats`, `st_episodes`, `st_description`, `av_block_note`, `symptoms_reported`, `symptom_correlation`, `findings`, `conclusion`, `comments`)
VALUES
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-01-15 10:00:00', 'demo', '2025-01-15 10:00:00', 'DEMO palpitations', 24, 100000, 41, 68, 132, '06:12:00', '14:05:00', 'sinus', 1, 12.5, 8.0, 2400, 2, 1200, 1.20, 40, 1, 12, 300, 1, 15, 1, 'DEMO st', 'DEMO av block', 'DEMO symptoms', 'DEMO correlation', 'DEMO findings', 'DEMO holter conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-04-15 10:00:00', 'demo', '2025-04-15 10:00:00', 'DEMO palpitations', 24, 105000, 41, 68, 132, '06:12:00', '14:05:00', 'sinus', 1, 12.5, 8.0, 2400, 2, 800, 0.76, 40, 1, 12, 300, 1, 15, 1, 'DEMO st', 'DEMO av block', 'DEMO symptoms', 'DEMO correlation', 'DEMO findings', 'DEMO holter conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-07-15 10:00:00', 'demo', '2025-07-15 10:00:00', 'DEMO palpitations', 24, 98000, 41, 68, 132, '06:12:00', '14:05:00', 'sinus', 1, 12.5, 8.0, 2400, 2, 1500, 1.53, 40, 1, 12, 300, 1, 15, 1, 'DEMO st', 'DEMO av block', 'DEMO symptoms', 'DEMO correlation', 'DEMO findings', 'DEMO holter conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'draft', NULL, NULL, '2025-10-15 10:00:00', 'DEMO palpitations', 24, 102000, 41, 68, 132, '06:12:00', '14:05:00', 'sinus', 1, 12.5, 8.0, 2400, 2, 600, 0.59, 40, 1, 12, 300, 1, 15, 1, 'DEMO st', 'DEMO av block', 'DEMO symptoms', 'DEMO correlation', 'DEMO findings', 'DEMO holter conclusion', NULL);

-- ---------------- ABPM ----------------
INSERT INTO `form_card_abpm`
  (`cardiology_id`, `pid`, `encounter`, `user`, `activity`, `status`, `finalized_at`, `finalized_by`, `study_date`, `indication`, `device_model`, `recording_hours`, `readings_total`, `readings_valid`, `valid_pct`, `on_antihypertensives`, `medications_note`, `day_start_time`, `night_start_time`, `office_sbp_mmhg`, `office_dbp_mmhg`, `office_hr_bpm`, `sbp_24h`, `dbp_24h`, `hr_24h`, `sbp_day`, `dbp_day`, `hr_day`, `sbp_night`, `dbp_night`, `hr_night`, `sbp_max`, `dbp_max`, `hr_max`, `sbp_min`, `dbp_min`, `hr_min`, `pulse_pressure_24h`, `dip_sbp_pct`, `dip_dbp_pct`, `dipping_pattern`, `abpm_classification`, `findings`, `conclusion`, `comments`)
VALUES
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-01-15 10:00:00', 'demo', '2025-01-15 10:00:00', 'DEMO hypertension', 'DEMO device', 24, 120, 114, 95.00, 1, 'DEMO meds', '07:00:00', '22:00:00', 126, 82, 70, 132, 84, 68, 138, 88, 74, 122, 76, 55, 144, 94, 82, 114, 70, 48, 48.00, 11.59, 13.64, 'dipping', 'sustained_hypertension', 'DEMO findings', 'DEMO abpm conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-04-15 10:00:00', 'demo', '2025-04-15 10:00:00', 'DEMO hypertension', 'DEMO device', 24, 118, 112, 94.92, 1, 'DEMO meds', '07:00:00', '22:00:00', 126, 82, 70, 128, 82, 68, 134, 86, 74, 118, 74, 55, 140, 92, 82, 110, 68, 48, 46.00, 11.94, 13.95, 'dipping', 'sustained_hypertension', 'DEMO findings', 'DEMO abpm conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-07-15 10:00:00', 'demo', '2025-07-15 10:00:00', 'DEMO hypertension', 'DEMO device', 24, 122, 110, 90.16, 1, 'DEMO meds', '07:00:00', '22:00:00', 126, 82, 70, 135, 86, 68, 141, 90, 74, 125, 78, 55, 147, 96, 82, 117, 72, 48, 49.00, 11.35, 13.33, 'dipping', 'sustained_hypertension', 'DEMO findings', 'DEMO abpm conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'draft', NULL, NULL, '2025-10-15 10:00:00', 'DEMO hypertension', 'DEMO device', 24, 120, 117, 97.50, 1, 'DEMO meds', '07:00:00', '22:00:00', 126, 82, 70, 130, 80, 68, 136, 84, 74, 120, 72, 55, 142, 90, 82, 112, 66, 48, 50.00, 11.76, 14.29, 'dipping', 'sustained_hypertension', 'DEMO findings', 'DEMO abpm conclusion', NULL);

-- ---------------- Chagas ----------------
INSERT INTO `form_card_chagas`
  (`cardiology_id`, `pid`, `encounter`, `user`, `activity`, `status`, `finalized_at`, `finalized_by`, `study_date`, `disease_phase`, `origin_area`, `endemic_residence`, `epidemiological_history`, `transmission_route`, `serology_1_type`, `serology_1_result`, `serology_1_date`, `serology_2_type`, `serology_2_result`, `serology_2_date`, `serology_3_type`, `serology_3_result`, `serology_3_date`, `serology_status`, `serology_note`, `kuschnir_stage`, `abcd_stage`, `ecg_done`, `ecg_date`, `ecg_normal`, `ecg_rbbb`, `ecg_lafb`, `ecg_lpfb`, `ecg_av_block`, `ecg_q_waves`, `ecg_st_t_changes`, `ecg_low_voltage`, `ecg_arrhythmia`, `ecg_other`, `ecg_findings`, `chest_xray_done`, `chest_xray_date`, `chest_xray_cardiomegaly`, `chest_xray_findings`, `echo_done`, `echo_date`, `lvef_pct`, `lvedd_mm`, `echo_aneurysm`, `echo_thrombus`, `echo_wall_motion`, `echo_findings`, `heart_failure`, `nyha_class`, `hf_findings`, `cardiac_device`, `device_note`, `digestive_involvement`, `megaesophagus`, `megacolon`, `constipation`, `digestive_findings`, `treatment_status`, `treatment_drug`, `treatment_start_date`, `treatment_end_date`, `treatment_note`, `mandatory_report`, `report_date`, `public_health_note`, `family_screening_done`, `family_screening_note`, `plan`, `next_control_date`, `conclusion`, `comments`)
VALUES
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-01-15 10:00:00', 'demo', '2025-01-15 10:00:00', 'chronic_cardiac', 'DEMO origin', 1, 'DEMO epi', 'vector', 'elisa', 'positive', '2025-01-02', 'ifa', 'positive', '2025-01-09', 'clia', 'positive', '2025-01-16', 'positive', 'DEMO serology note', 'i', 'b1', 1, '2025-01-15', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 'DEMO ecg findings', 1, '2025-01-15', 0, 'DEMO chest findings', 1, '2025-01-15', 48, 40, 0, 0, 'normal', 'DEMO echo findings', 1, 'ii', 'DEMO hf findings', 'none', 'DEMO device note', 0, 0, 0, 0, 'DEMO digestive findings', 'active', 'benznidazole', '2025-02-01', NULL, 'DEMO treatment note', 0, NULL, 'DEMO public health note', 1, 'DEMO family note', 'DEMO plan', '2026-01-15', 'DEMO chagas conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-04-15 10:00:00', 'demo', '2025-04-15 10:00:00', 'chronic_cardiac', 'DEMO origin', 1, 'DEMO epi', 'vector', 'elisa', 'positive', '2025-01-02', 'ifa', 'positive', '2025-01-09', 'clia', 'positive', '2025-01-16', 'positive', 'DEMO serology note', 'i', 'b1', 1, '2025-01-15', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 'DEMO ecg findings', 1, '2025-01-15', 0, 'DEMO chest findings', 1, '2025-01-15', 48, 40, 0, 0, 'normal', 'DEMO echo findings', 1, 'ii', 'DEMO hf findings', 'none', 'DEMO device note', 0, 0, 0, 0, 'DEMO digestive findings', 'active', 'benznidazole', '2025-02-01', NULL, 'DEMO treatment note', 0, NULL, 'DEMO public health note', 1, 'DEMO family note', 'DEMO plan', '2026-01-15', 'DEMO chagas conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'final', '2025-07-15 10:00:00', 'demo', '2025-07-15 10:00:00', 'chronic_cardiac', 'DEMO origin', 1, 'DEMO epi', 'vector', 'elisa', 'positive', '2025-01-02', 'ifa', 'positive', '2025-01-09', 'clia', 'positive', '2025-01-16', 'positive', 'DEMO serology note', 'i', 'b1', 1, '2025-01-15', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 'DEMO ecg findings', 1, '2025-01-15', 0, 'DEMO chest findings', 1, '2025-01-15', 48, 40, 0, 0, 'normal', 'DEMO echo findings', 1, 'ii', 'DEMO hf findings', 'none', 'DEMO device note', 0, 0, 0, 0, 'DEMO digestive findings', 'active', 'benznidazole', '2025-02-01', NULL, 'DEMO treatment note', 0, NULL, 'DEMO public health note', 1, 'DEMO family note', 'DEMO plan', '2026-01-15', 'DEMO chagas conclusion', 'DEMO comments'),
  (@demo_cardiology_id, @demo_pid, @demo_encounter, 'demo', 1, 'draft', NULL, NULL, '2025-10-15 10:00:00', 'chronic_cardiac', 'DEMO origin', 1, 'DEMO epi', 'vector', 'elisa', 'positive', '2025-01-02', 'ifa', 'positive', '2025-01-09', 'clia', 'positive', '2025-01-16', 'positive', 'DEMO serology note', 'i', 'b1', 1, '2025-01-15', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 'DEMO ecg findings', 1, '2025-01-15', 0, 'DEMO chest findings', 1, '2025-01-15', 48, 40, 0, 0, 'normal', 'DEMO echo findings', 1, 'ii', 'DEMO hf findings', 'none', 'DEMO device note', 0, 0, 0, 0, 'DEMO digestive findings', 'active', 'benznidazole', '2025-02-01', NULL, 'DEMO treatment note', 0, NULL, 'DEMO public health note', 1, 'DEMO family note', 'DEMO plan', '2026-01-15', 'DEMO chagas conclusion', NULL);

SELECT 'demo_data complete' AS done;
