# 12-Lead Electrocardiogram Card (`ecg`)

> **Location:** `interface/forms/form_cardiology/cards/ecg/`  
> **Table:** `form_card_ecg`  
> **Module Code:** `ecg`  
> **Sort Order:** 30  
> **Icon:** `fa-wave-square`  
> **ACL:** `patients / med`

---

## Overview

The `ecg` card provides standard 12-lead electrocardiogram interpretation recording, including:
- Rhythm and heart rate.
- Frontal plane QRS electrical axis.
- Intervals: PR, QRS duration, QT interval.
- Server-side calculated corrected QT intervals:
  - **Bazett Formula:** $\text{QTc} = \frac{\text{QT}}{\sqrt{\text{RR}}}$
  - **Fridericia Formula:** $\text{QTc} = \frac{\text{QT}}{\sqrt[3]{\text{RR}}}$
- Atrioventricular and intraventricular conduction.
- Chamber hypertrophy.
- Repolarization abnormalities (ST-segment elevation/depression, T-wave inversion) and pathologic Q waves.
- Detailed electrocardiographic findings, conclusion, and clinical comments.

---

## File Structure

```
cards/ecg/
├── new.php            New ECG study entry point
├── view.php           View / edit existing ECG study (read-only if final)
├── _form_fields.php   Shared form field partial
├── save.php           Hardened save handler with CardInput & transactions
├── print.php          Printable / PDF report view
├── ecg.js             Client-side dynamic QTc calculation preview
└── README.md          This documentation file
```

---

## Plausibility Limits

Defined in `cards/ecg/save.php` (`RANGES` constant):
- `heart_rate_bpm`: 20 – 300 bpm
- `axis_deg`: -180 – 180°
- `pr_ms`: 60 – 400 ms
- `qrs_ms`: 40 – 300 ms
- `qt_ms`: 200 – 700 ms

---

## Validation & Workflow Rules

1. Finalization (`action = 'save_final'`) strictly requires a non-empty `conclusion`.
2. Finalized studies (`status = 'final'`) cannot be modified.
3. Comma decimals are automatically normalized to dots by `CardInput`.
4. Values outside plausibility limits or invalid catalog options throw an `\InvalidArgumentException` identifying the field name.

