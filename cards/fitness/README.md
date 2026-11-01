# Cardiology Fitness Certificate Card (`fitness`)

> **Location:** `interface/forms/form_cardiology/cards/fitness/`  
> **Table:** `form_card_fitness`  
> **Module Code:** `fitness`  
> **Sort Order:** 50  
> **Icon:** `fa-certificate`  
> **ACL:** `patients / med`

---

## Overview

The `fitness` card provides standardized evaluations for physical and cardiovascular activity clearance ("apto cardiológico"), including:
- Purpose categorization (recreational sport, competitive sport, gym, occupational/work, school, travel, other) and activity details.
- Cardiovascular symptoms review and family history of sudden cardiac death or premature CAD.
- Vitals and physical examination: Height, weight, calculated Body Mass Index (BMI), heart rate, systolic and diastolic blood pressure, and cardiovascular physical exam findings.
- Electrocardiogram results and complementary diagnostic testing summary (echocardiogram, stress test, Holter).
- Clear cardiovascular determination:
  - Fit without restrictions (`fit`)
  - Fit with restrictions (`fit_restrictions`)
  - Temporarily or permanently unfit (`not_fit`)
  - Clearance pending further evaluation (`pending_studies`)
- Restrictions specification, validity expiration date (defaults to study date + 12 months), and conclusion.
- Dedicated formal certificate print view including the neutral standard text:
  *"Based on the examination performed on <date>, the patient is considered <determination>."*
  along with validity date and physician license number block.

---

## File Structure

```
cards/fitness/
├── new.php            New certificate entry point (pre-fills valid_until with +12 months)
├── view.php           View / edit existing certificate (read-only if final)
├── _form_fields.php   Shared form field partial
├── save.php           Hardened save handler with CardInput & transactions
├── print.php          Printable / PDF certificate view
├── fitness.js         Client-side dynamic BMI calculation & restrictions highlight
└── README.md          This documentation file
```

---

## Plausibility Limits

Defined in `cards/fitness/save.php` (`RANGES` constant):
- `height_cm`: 50 – 250 cm
- `weight_kg`: 2 – 400 kg
- `heart_rate_bpm`: 20 – 300 bpm
- `bp_systolic_mmhg`: 40 – 300 mmHg
- `bp_diastolic_mmhg`: 20 – 200 mmHg

---

## Validation & Workflow Rules

1. Finalization (`action = 'save_final'`) strictly requires:
   - `determination` (non-empty)
   - `valid_until` (non-empty and strictly greater than the study date)
   - `restrictions` (mandatory when `determination === 'fit_restrictions'`)
   - `conclusion` (non-empty)
2. Finalized studies (`status = 'final'`) cannot be modified.
3. Comma decimals are automatically normalized to dots by `CardInput`.
4. Values outside plausibility limits or invalid catalog options throw an `\InvalidArgumentException` identifying the field name.

