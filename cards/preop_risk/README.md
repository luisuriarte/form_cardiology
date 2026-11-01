# Preoperative Cardiovascular Risk Assessment Card (`preop_risk`)

> **Location:** `interface/forms/form_cardiology/cards/preop_risk/`  
> **Table:** `form_card_preop_risk`  
> **Module Code:** `preop_risk`  
> **Sort Order:** 40  
> **Icon:** `fa-clipboard-check`  
> **ACL:** `patients / med`

---

## Overview

The `preop_risk` card provides structured preoperative cardiovascular risk assessment for non-cardiac surgery, including:
- Planned surgical procedure description, scheduled date, and urgency classification (elective, urgent, emergency).
- Inherent surgical risk class set by the evaluating physician.
- Functional capacity assessment (< 4 METs, 4-10 METs, > 10 METs) and ASA physical status classification.
- Revised Cardiac Risk Index (Lee RCRI) factoring 6 predictors:
  1. High-risk surgery (intraperitoneal, intrathoracic, or suprainguinal vascular).
  2. History of ischemic heart disease.
  3. History of congestive heart failure.
  4. History of cerebrovascular disease.
  5. Preoperative insulin therapy for diabetes mellitus.
  6. Preoperative serum creatinine > 2.0 mg/dL.
- Server-side calculated Lee RCRI score (0 to 6) and class (I, II, III, IV).
  *(Note: Specific event-rate percentages are intentionally omitted per system specification).*
- Complementary studies: preoperative ECG summary, echocardiogram findings, current medications, relevant laboratory values.
- Cardiovascular clearance recommendation (proceed, proceed with optimization, further testing, postpone), perioperative instructions, and conclusion.

---

## File Structure

```
cards/preop_risk/
├── new.php            New preoperative risk assessment entry point
├── view.php           View / edit existing assessment (read-only if final)
├── _form_fields.php   Shared form field partial
├── save.php           Hardened save handler with CardInput & transactions
├── print.php          Printable / PDF report view
├── preop_risk.js      Client-side dynamic Lee RCRI score & class calculation
└── README.md          This documentation file
```

---

## Plausibility Limits

Defined in `cards/preop_risk/save.php` (`RANGES` constant):
- `height_cm`: 50 – 250 cm
- `weight_kg`: 2 – 400 kg
- `heart_rate_bpm`: 20 – 300 bpm
- `bp_systolic_mmhg`: 40 – 300 mmHg
- `bp_diastolic_mmhg`: 20 – 200 mmHg
- `lvef_pct`: 5.0 – 90.0 %

---

## Validation & Workflow Rules

1. Finalization (`action = 'save_final'`) strictly requires both `recommendation` and `conclusion`.
2. Finalized studies (`status = 'final'`) cannot be modified.
3. Comma decimals are automatically normalized to dots by `CardInput`.
4. Values outside plausibility limits or invalid catalog options throw an `\InvalidArgumentException` identifying the field name.

