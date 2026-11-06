-- ============================================================
-- demo_cleanup.sql -- remove ONLY demo rows (DEMO marker + pid)
--
-- Uses the same @demo_pid / @demo_encounter guards as demo_data.sql.
-- Deletes stage rows that belong to demo stress studies first, then
-- study rows whose conclusion or comments contain the DEMO marker.
-- Never touches anything else. Prints deleted rows per table.
-- ============================================================

SET @demo_pid := NULL;            -- <-- set: same pid used for demo_data.sql
SET @demo_encounter := NULL;      -- <-- set: same encounter

SET @demo_guard := (
  SELECT IF(@demo_pid IS NULL OR @demo_pid = 0 OR @demo_encounter IS NULL OR @demo_encounter = 0,
         'DEMO GUARD FAILED: set @demo_pid and @demo_encounter first',
         NULL));
SELECT @demo_guard AS demo_guard_error;

START TRANSACTION;

DELETE s FROM `form_card_stress_test_stage` s
INNER JOIN `form_card_stress_test` t ON t.id = s.stress_test_id
WHERE t.pid = @demo_pid AND t.encounter = @demo_encounter
  AND (t.conclusion LIKE '%DEMO%' OR t.comments LIKE '%DEMO%');
SELECT ROW_COUNT() AS deleted_stress_stages;

DELETE FROM `form_card_echo`
WHERE pid = @demo_pid AND encounter = @demo_encounter
  AND (conclusion LIKE '%DEMO%' OR comments LIKE '%DEMO%');
SELECT ROW_COUNT() AS deleted_echo;

DELETE FROM `form_card_stress_test`
WHERE pid = @demo_pid AND encounter = @demo_encounter
  AND (conclusion LIKE '%DEMO%' OR comments LIKE '%DEMO%');
SELECT ROW_COUNT() AS deleted_stress_test;

DELETE FROM `form_card_ecg`
WHERE pid = @demo_pid AND encounter = @demo_encounter
  AND (conclusion LIKE '%DEMO%' OR comments LIKE '%DEMO%');
SELECT ROW_COUNT() AS deleted_ecg;

DELETE FROM `form_card_preop_risk`
WHERE pid = @demo_pid AND encounter = @demo_encounter
  AND (conclusion LIKE '%DEMO%' OR comments LIKE '%DEMO%');
SELECT ROW_COUNT() AS deleted_preop_risk;

DELETE FROM `form_card_fitness`
WHERE pid = @demo_pid AND encounter = @demo_encounter
  AND (conclusion LIKE '%DEMO%' OR comments LIKE '%DEMO%');
SELECT ROW_COUNT() AS deleted_fitness;

DELETE FROM `form_card_anticoag`
WHERE pid = @demo_pid AND encounter = @demo_encounter
  AND (conclusion LIKE '%DEMO%' OR comments LIKE '%DEMO%');
SELECT ROW_COUNT() AS deleted_anticoag;

DELETE FROM `form_card_hf_followup`
WHERE pid = @demo_pid AND encounter = @demo_encounter
  AND (conclusion LIKE '%DEMO%' OR comments LIKE '%DEMO%');
SELECT ROW_COUNT() AS deleted_hf_followup;

DELETE FROM `form_card_holter`
WHERE pid = @demo_pid AND encounter = @demo_encounter
  AND (conclusion LIKE '%DEMO%' OR comments LIKE '%DEMO%');
SELECT ROW_COUNT() AS deleted_holter;

DELETE FROM `form_card_abpm`
WHERE pid = @demo_pid AND encounter = @demo_encounter
  AND (conclusion LIKE '%DEMO%' OR comments LIKE '%DEMO%');
SELECT ROW_COUNT() AS deleted_abpm;

DELETE FROM `form_card_chagas`
WHERE pid = @demo_pid AND encounter = @demo_encounter
  AND (conclusion LIKE '%DEMO%' OR comments LIKE '%DEMO%');
SELECT ROW_COUNT() AS deleted_chagas;

COMMIT;

SELECT 'demo_cleanup complete' AS done;
