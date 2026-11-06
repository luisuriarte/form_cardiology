# Demo expected values

Reference for `tools/demo_data.sql`: the calculated values each save.php would
produce for the demo rows. Calculations were done by hand with the exact
formulas, rounding and NULL rules of the corresponding `save.php`.

Assumptions: `age_years` and stress-test age-derived values assume DOB
1960-01-01 (age 65 at every 2025 study date: max_predicted 155, target 132).
If the demo patient's real DOB differs, re-saving in the UI recomputes those
columns. Draft rows (`status = 'draft'`) have `finalized_at`/`finalized_by`
NULL and `comments` NULL, with the DEMO marker in `conclusion`.

## TTR (Rosendaal) — form_card_anticoag

`CardAnticoag::ttr()` uses final studies only, linear interpolation between
consecutive tests, target range of the earlier test, intervals over 56 days
skipped. Target range 2.0–3.0 for all:

- 2025-01-06 INR 2.0 → 2025-02-03 INR 3.0: 28 days, line stays inside
  [2.0, 3.0] → fraction 1.0 → 28.0 weighted days.
- 2025-02-03 INR 3.0 → 2025-03-03 INR 4.0: 28 days, starts at the top edge
  and leaves the range immediately → fraction 0 → 0.0 weighted days.
- 2025-03-03 INR 4.0 → 2025-03-31 INR 2.5: 28 days, crosses 3.0 after
  (4.0−3.0)/(4.0−2.5) = 2/3 of the interval → fraction 1/3 → 9.3 weighted days.
- The 2025-04-28 row is a draft and is excluded.

Step by step for the reference date 2025-03-03:

- 2025-01-06 (2.0) → 2025-02-03 (3.0): 28 days, always in range → 28.0 days.
- 2025-02-03 (3.0) → 2025-03-03 (4.0): 28 days, leaves immediately → 0.0 days.

Expected: `ttr_pct = 50.0`, `days_evaluated = 56.0`, `tests = 3`.

## Echo (form_card_echo)

| Study | bsa_m2 | mitral_e_a_ratio | e_e_prime_ratio | lv_mass_index_g_m2 |
|---|---|---|---|---|
| 2025-01-15 | 1.81 | 1.20 | 6.7 | 82.9 |
| 2025-04-15 | 1.88 | 1.22 | 5.5 | 85.1 |
| 2025-07-15 | 1.74 | 1.63 | 8.1 | 74.7 |
| 2025-10-15 (draft) | 1.92 | 0.91 | 5.6 | 88.5 |

## Stress test (form_card_stress_test)

Assumed age 65 → `max_predicted_hr_bpm = 155`, `target_hr_bpm = 132`.
Duke = minutes − 5·ST deviation − 4·angina (angina 0), protocol `bruce`.

| Study | peak_hr_pct_predicted | double_product | duke_score |
|---|---|---|---|
| 2025-01-15 | 96.8 | 28500 | 0.0 |
| 2025-04-15 | 95.5 | 27720 | 4.0 |
| 2025-07-15 | 98.1 | 28880 | 8.5 |
| 2025-10-15 (draft) | 93.5 | 26750 | -7.0 |

Stage rows (study 1 and 2): rest + 3 exercise + 3 recovery, see
`tools/demo_data.sql` for the exact values (elapsed 0/180/360/540/600/660/720 s).

## ECG (form_card_ecg) — QTc

RR = 60/HR; Bazett = QT/√RR; Fridericia = QT/∛RR, rounded to integers.

| HR | QT | qtc_bazett_ms | qtc_fridericia_ms |
|---|---|---|---|
| 72 | 360 | 394 | 383 |
| 68 | 380 | 405 | 396 |
| 75 | 350 | 391 | 377 |
| 70 | 370 | 400 | 390 |

## Fitness (form_card_fitness) — bmi

BMI = weight / (height_m)², 1 decimal: 24.2, 25.4, 23.0, 27.7 for the four
studies (H/W = 170/70, 172/75, 168/65, 170/80).

## Anticoagulation (form_card_anticoag) — in_range, dose_change_pct

`in_range`: 2.0→in, 3.0→in, 4.0→above, 2.5→in (draft).
`dose_change_pct` = (new−current)/current ×100, 2 decimals:
(35→35) 0.00, (35→40) 14.29, (40→35) -12.50, (35→30) -14.29 (draft).

## HF follow-up (form_card_hf_followup)

`weight_change_kg` (3 decimals) vs the previous study of the same patient:
NULL (first row, weight 70), then +2.000, -1.000, +3.000.
`nyha_numeric`: ii→2, ii→2, iii→3, ii→2.

## Holter (form_card_holter) — pvc_burden_pct

PVC/total ×100, 2 decimals: 1.20, 0.76, 1.53, 0.59 for the four studies.

## ABPM (form_card_abpm)

`valid_pct` (2 dp): 95.00, 94.92, 90.16, 97.50.
`pulse_pressure_24h` (2 dp): 48.00, 46.00, 49.00, 50.00.
`dip_sbp_pct` / `dip_dbp_pct` (2 dp): 11.59/13.64, 10.45/13.95, 10.00/13.33,
13.24/14.29.

## Preop risk (form_card_preop_risk) — Lee RCRI

`lee_score` = sum of the six flags; class I=0, II=1, III=2, IV≥3:
scores 0→I, 1→II, 2→III, 0→I for the four studies.

## Chagas (form_card_chagas)

No calculated columns. LVEF 48/46/48/48 (`lvef_pct` is a plain input for
this card), Kuschnir `i`, ABCD `b1`, chronic cardiac phase.
