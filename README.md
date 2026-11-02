# Cardiology Container Form

> **OpenEMR compatibility:** 8.2.0 – 8.5.0
> **formdir:** `form_cardiology`
> **Location:** `interface/forms/form_cardiology/`

> [!NOTE]
> **Formdir & Report Naming:** The folder name inside `interface/forms/` is the registered `formdir` (`form_cardiology`). OpenEMR automatically derives the encounter report function name from this formdir using the pattern `<formdir>_report()`, which in this module is `form_cardiology_report()`.

---

## Table of Contents

1. [Architecture overview](#1-architecture-overview)
2. [File map](#2-file-map)
3. [Database installation & upgrade](#3-database-installation--upgrade)
4. [How it works](#4-how-it-works)
5. [Clinical cards specification](#5-clinical-cards-specification)
6. [Cardiology charts component](#6-cardiology-charts-component)
7. [How to add a new card](#7-how-to-add-a-new-card)
8. [File conventions for a card](#8-file-conventions-for-a-card)
9. [Card visibility rules (CardRegistry)](#9-card-visibility-rules-cardregistry)
10. [Soft-delete policy](#10-soft-delete-policy)
11. [OpenEMR delete hook limitation](#11-openemr-delete-hook-limitation)
12. [Manual test checklist](#12-manual-test-checklist)
13. [Document attachments architecture](#13-document-attachments-architecture-card_documents)
14. [Reusable document upload dialog](#14-reusable-document-upload-dialog)

---

## 1. Architecture overview

```
form_cardiology  (one row per encounter – the "container")
   │
   ├── form_card_echo           (N studies per encounter: Doppler Echocardiogram)
   ├── form_card_stress_test    (N studies per encounter: Exercise Stress Test)
   │      └── form_card_stress_test_stage  (M stages per study)
   ├── form_card_ecg            (N studies per encounter: 12-Lead ECG)
   ├── form_card_preop_risk     (N studies per encounter: Preoperative Cardiovascular Risk)
   └── form_card_fitness        (N studies per encounter: Cardiology Fitness Certificate)
```

**One container per encounter.**
The container row is created *lazily*—only when the user first saves the
`reason`/`notes` fields or when the first child-card study is created.
The container is then registered in the OpenEMR `forms` table via `addForm()`.

**The container is generic.**
It never hard-codes the list of cards. The card grid is built at runtime from
`card_modules`, filtered through `card_config` and ACL.

---

## 2. File map

```
interface/forms/form_cardiology/
├── info.txt                        OpenEMR form name ("Cardiology")
├── table.sql                       DDL for all tables + list_options seeds (fresh install)
├── lang_custom.sql                 Spanish (Argentina) translations for all strings and lists
├── new.php                         Entry point (new container)
├── view.php                        Reopen existing container
├── save.php                        Save reason/notes; create container lazily
├── report.php                      Encounter-summary generic report (form_cardiology_report)
├── config.php                      Admin: enable/disable cards per user
│
├── sql/
│   └── upgrade_001_new_cards.sql   Idempotent migration for existing installs (ECG, Preop, Fitness)
│
├── lib/
│   ├── CardiologyContainer.php     ensureContainer(), getContainer(), softDelete(), transactions, session helpers
│   ├── CardRegistry.php            getVisibleCards(), canWrite() with ACL + config filtering
│   ├── CardInput.php               Strict typed validation & sanitization helper (decimal, int, text, dateTime, listOption, flag)
│   └── CardCharts.php              Reusable charting engine (longitudinal evolution and stage series)
│
├── css/
│   └── cardiology.css              All styles (no inline styles in PHP, includes chart cards & print rules)
│
├── js/
│   ├── cardiology.js               All container JS (no inline handlers in PHP)
│   └── cardiology-charts.js        Chart.js driver (color-blind safe, theme-adaptive, collapse-aware)
│
├── templates/
│   ├── _header.php                 Patient + encounter header bar
│   ├── _reason_notes.php           Reason / notes textarea fields
│   ├── _card_grid.php              Card tile grid (generic, driven by CardRegistry)
│   └── _charts.php                 Generic chart component (collapsible for view, open for print)
│
└── cards/
    ├── echo/
    │   ├── README.md               Card specification and schema documentation
    │   ├── charts.php              Longitudinal evolution chart definitions (LVEF, LV diameters, PASP, aorta)
    │   ├── new.php                 New echocardiogram study form
    │   ├── view.php                View / edit existing echocardiogram study (with evolution charts)
    │   ├── save.php                Hardened save handler with transaction & CardInput
    │   └── print.php               Printable / PDF report view (with static evolution charts)
    ├── stress_test/
    │   ├── README.md               Card specification and schema documentation
    │   ├── charts.php              Evolution & stage chart definitions (HR, METs, duration, Duke, stages)
    │   ├── new.php                 New exercise stress test form
    │   ├── view.php                View / edit existing stress test & stage grid (with stage & evolution charts)
    │   ├── save.php                Hardened atomic save handler for study and stages
    │   └── print.php               Printable / PDF report view with stage charts & evolution
    ├── ecg/
    │   ├── README.md               Card specification and schema documentation
    │   ├── charts.php              Longitudinal evolution chart definitions (HR, QTc, intervals)
    │   ├── new.php                 New 12-lead ECG study form
    │   ├── view.php                View / edit existing ECG study (with evolution charts)
    │   ├── save.php                Hardened save handler with QTc calculations
    │   ├── print.php               Printable / PDF report view (with static evolution charts)
    │   └── ecg.js                  Real-time client-side calculation of QTc Bazett & Fridericia
    ├── preop_risk/
    │   ├── README.md               Card specification and schema documentation
    │   ├── new.php                 New preoperative cardiovascular risk assessment form
    │   ├── view.php                View / edit existing preoperative assessment
    │   ├── save.php                Hardened save handler with Lee RCRI score/class
    │   ├── print.php               Printable / PDF report view
    │   └── preop_risk.js           Real-time client-side calculation of Lee RCRI score & class
    └── fitness/
        ├── README.md               Card specification and schema documentation
        ├── new.php                 New fitness certificate form (valid_until pre-filled +12m)
        ├── view.php                View / edit existing certificate
        ├── save.php                Hardened save handler with BMI calculation & validity rules
        ├── print.php               Printable / PDF certificate view with physician license block
        └── fitness.js              Real-time client-side calculation of BMI & UI state
```

---

## 3. Database installation & upgrade

### 3.1 Fresh installation
Run in order on the OpenEMR database:
```bash
# 1. Base container + all 5 cards schema and list_options seeds
mysql -u <user> -p <database> < table.sql

# 2. Spanish (Argentina) translations
mysql -u <user> -p <database> < lang_custom.sql
```

### 3.2 Upgrading an existing installation (`upgrade_001_new_cards.sql`)
If the container and initial cards (`echo`, `stress_test`) are already installed, apply the idempotent upgrade script to add the three new cards (`ecg`, `preop_risk`, `fitness`):
```bash
# 1. Add ECG, Preoperative Risk, and Fitness tables, modules, and list_options
mysql -u <user> -p <database> < sql/upgrade_001_new_cards.sql

# 2. Update translations
mysql -u <user> -p <database> < lang_custom.sql
```
> [!NOTE]
> `sql/upgrade_001_new_cards.sql` is completely idempotent: it uses `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE`, making it safe to re-run without risking data loss or primary key conflicts.

---

## 4. How it works

### Opening a form

1. The encounter form menu calls `new.php`.
2. `new.php` checks for an existing active container via
   `CardiologyContainer::getContainer($pid, $encounter)`.
   - **Found** → redirect to `view.php?id=<containerId>`.
   - **Not found** → render the empty form (no container row yet).

### Saving reason / notes

3. The user fills in `reason` and/or `notes` and clicks **Save**.
4. `save.php` verifies the CSRF token and ACL.
5. If `cardiology_id` in POST is 0 → calls
   `CardiologyContainer::ensureContainer()` which:
   - Inserts a row into `form_cardiology`.
   - Calls `addForm()` to register in the `forms` table.
6. If `cardiology_id > 0` → calls `updateReasonNotes()`.
7. Redirects to `view.php?id=<containerId>`.

### Opening a child card

8. The user clicks **New** on a card tile.
9. If no container exists yet (new form, unsaved), clicking **New** submits
   the container form first (the button is a `type="submit"` inside the form),
   so `save.php` runs and creates the container before the card is opened.
10. If a container already exists, the **New** link goes directly to
    `cards/<code>/new.php?pid=…&encounter=…&cardiology_id=…`.

---

## 5. Clinical cards specification

The module bundles 5 clinical cards designed to cover core cardiovascular outpatient workflows:

| Code | Title | Table | Key Features & Calculations | Finalize Requirement |
|---|---|---|---|---|
| `echo` | Doppler Echocardiogram | `form_card_echo` | M-mode/2D chambers, Doppler gradients & velocities, valvulopathies, wall motion, LVEF | `conclusion` |
| `stress_test` | Exercise Stress Test | `form_card_stress_test`, `form_card_stress_test_stage` | Multi-stage protocols (Bruce, modified Bruce, Naughton, Ramp, etc.), METs, Duke treadmill score (for Bruce protocol), stage charts | `conclusion` |
| `ecg` | 12-Lead Electrocardiogram | `form_card_ecg` | Rhythm, conduction intervals (PR, QRS, QT), electrical axis, repolarization findings. Formulas: QTc Bazett = `QT / sqrt(60/HR)`, QTc Fridericia = `QT / cbrt(60/HR)`. Real-time calculation in `ecg.js`. | `conclusion` |
| `preop_risk` | Preoperative Cardiovascular Risk | `form_card_preop_risk` | Lee Revised Cardiac Risk Index (RCRI) with 6 clinical predictors (high-risk surgery, ischemic heart disease, congestive heart failure, cerebrovascular disease, insulin-dependent diabetes, creatinine > 2.0 mg/dL). Calculates score 0–6 and Lee Class I–IV. Neutral risk assessment with no hardcoded event-rate percentages. Real-time calculation in `preop_risk.js`. | `recommendation` AND `conclusion` |
| `fitness` | Cardiology Fitness Certificate | `form_card_fitness` | Anthropometry (weight, height, BSA, calculated BMI = `kg/m²`), cardiovascular exam, ECG/stress test summaries, fitness determination (`fit`, `fit_restrictions`, `unfit`, `temporary_unfit`), sports/activities, clinical restrictions (mandatory if `fit_restrictions`), validity date `valid_until` (pre-filled +12m, must exceed `study_date`). Certificate print renders neutral declaration: `"Based on the examination performed on <date>, the patient is considered <determination>."` and displays the physician's `state_license_number`. Real-time BMI calculation in `fitness.js`. | `determination`, `valid_until`, `conclusion`, and `restrictions` (if `fit_restrictions`) |

---

## 6. Cardiology charts component

The module features a generic, config-driven charting engine powered by OpenEMR's bundled **Chart.js** library (version 4.5.1, loaded via OpenEMR asset key `'chart'` through `Header::setupHeader('chart')` / `Header::setupAssets(['chart'])`).

### 6.1 Capabilities
- **Longitudinal evolution:** Plots historical trends across all previous final studies (and the current study even if draft) for the active patient (up to 60 studies), ordered chronologically.
- **Within-study stage trends:** For multi-stage procedures like exercise stress tests, plots continuous stage-by-stage trends (HR, BP, ST level) indexed by elapsed time (`mm:ss`).
- **Interactive UI & PDF Print:** Rendered inside a collapsible "Evolution" accordion on `view.php` (responsive, theme-adaptive with accessible color-blind palettes) and printed statically with animations disabled on `print.php`.

### 6.2 Configuration format (`charts.php`)
Each card defines its charts by creating a `charts.php` file inside its card folder returning an array:

```php
<?php

declare(strict_types=1);

return [
    'longitudinal' => [
        [
            'id'    => 'lvef',
            'title' => 'Left ventricular ejection fraction',
            'unit'  => '%',
            'series' => [
                ['field' => 'lvef_pct', 'label' => 'LVEF'],
            ],
            // Optional: 'y_min' => 0, 'y_max' => 100
        ],
        [
            'id'    => 'lv_diameters',
            'title' => 'Left ventricular diameters',
            'unit'  => 'mm',
            'series' => [
                ['field' => 'lvedd_mm', 'label' => 'LVEDD'],
                ['field' => 'lvesd_mm', 'label' => 'LVESD'],
            ],
        ],
    ],
    // Optional within-study stage charts (e.g. stress test):
    'study' => [
        'stage_table' => 'form_card_stress_test_stage',
        'foreign_key' => 'stress_test_id',
        'x_field'     => 'elapsed_s',
        'charts'      => [
            [
                'id'    => 'stage_hr',
                'title' => 'Heart rate during the test',
                'unit'  => 'bpm',
                'series' => [
                    ['field' => 'hr_bpm', 'label' => 'HR'],
                ],
            ],
        ],
    ],
];
```

### 6.3 Adding charts to a new card in 3 steps
1. **Create `cards/<code>/charts.php`:** Define the longitudinal metrics and series fields.
2. **Include in `cards/<code>/view.php`:** Add `Header::setupHeader('chart');` in `<head>`, and right after `_form_fields.php`:
   ```php
   <?php
   $cardCode  = 'my_card';
   $chartMode = 'view';
   require __DIR__ . '/../../templates/_charts.php';
   ?>
   ```
3. **Include in `cards/<code>/print.php`:** Add `\OpenEMR\Core\Header::setupAssets(['chart'])` in `<head>`, and right before conclusion:
   ```php
   <?php
   $cardCode  = 'my_card';
   $chartMode = 'print';
   require __DIR__ . '/../../templates/_charts.php';
   ?>
   ```

---

## 7. How to add a new card

Adding a new card requires **no changes to the container code**.

### Steps

1. **Register the card in the database** (only once, via migration or manual SQL):

   ```sql
   INSERT IGNORE INTO card_modules
     (code, title, icon, table_name, acl_section, acl_value, sort_order)
   VALUES
     ('my_card', 'My New Card', 'fa-plus', 'form_card_my_card', 'patients', 'med', 30);
   ```

2. **Create the card folder** and its required PHP files:

   ```
   interface/forms/form_cardiology/cards/my_card/
   ├── new.php
   ├── view.php
   ├── save.php
   └── print.php
   ```

3. **Create the card table** (add to `table.sql` or a dedicated migration):

   ```sql
   CREATE TABLE IF NOT EXISTS form_card_my_card (
     id            BIGINT NOT NULL AUTO_INCREMENT,
     cardiology_id BIGINT NOT NULL,
     pid           BIGINT NOT NULL,
     encounter     BIGINT NOT NULL,
     study_date    DATETIME NOT NULL,
     user          VARCHAR(255) DEFAULT NULL,
     status        VARCHAR(10) NOT NULL DEFAULT 'draft',
     finalized_at  DATETIME DEFAULT NULL,
     finalized_by  VARCHAR(255) DEFAULT NULL,
     activity      TINYINT NOT NULL DEFAULT 1,
     -- ... card-specific columns ...
     PRIMARY KEY (id),
     KEY idx_cardiology_id (cardiology_id),
     KEY idx_pid_encounter (pid, encounter)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```

4. **Done.** The container will detect the new folder and show the tile.
   No container PHP files need to be touched.

---

## 8. File conventions for a card

Every card lives in `cards/<code>/` and must provide:

| File | Receives (GET) | Must do |
|---|---|---|
| `new.php` | `pid`, `encounter`, `cardiology_id` | Render a blank study form |
| `view.php` | `pid`, `encounter`, `cardiology_id`, `id` | Render an existing study (read-only if `status = 'final'`) |
| `save.php` | POST from new/view | Verify CSRF; verify ACL; verify parent container is active; insert/update study row; redirect |
| `print.php` | `pid`, `encounter`, `cardiology_id`, `id` | Render a printable/PDF report; no edit controls |
| `charts.php` | (Config array) | Return array of longitudinal and study chart metrics |

**Mandatory checks in every card `save.php`:**

1. **CSRF Protection:** Verify token via version-tolerant `CardiologyContainer::verifyCsrfToken()`.
2. **Card-level Write ACL:** Check user permissions and card activation via `(new CardRegistry())->canWrite($code, $userId)`.
3. **Container Status & Ownership:** Verify container exists, is active (`activity = 1`), and strictly matches the request's `$pid` and `$encounter`.
4. **Session Consistency:** Verify `$pid > 0`, `$encounter > 0`, and match session state (`CardiologyContainer::getSessionPid()` and `getSessionEncounter()`).
5. **Database Transaction:** All operations wrapped in `CardiologyContainer::startTransaction()` / `commitTransaction()` / `rollbackTransaction()`.
6. **Concurrency & Finalized Lock:** If updating, use `SELECT id, status FROM <table> WHERE id = ? AND pid = ? AND cardiology_id = ? AND activity = 1 FOR UPDATE`. If `status === 'final'`, abort with an error.
7. **Strict Input Sanitization:** Validate and sanitize every input field through `CardInput` static methods against the card's `RANGES` bounds. Throw `\InvalidArgumentException` identifying the faulty field on error.
8. **Finalize Gate:** If `$action === 'save_final'`, enforce that `conclusion` is non-empty.
9. **Single `$data` Array:** Use a single associative array (`column => value`) for INSERT. For UPDATE, strip identity columns (`cardiology_id`, `pid`, `encounter`, `user`, `activity`, `created_at`) and append `WHERE id = ? AND pid = ? AND cardiology_id = ? AND status <> 'final'`.
10. **Multi-Row Validation (e.g., Stages):** Enforce stage limit (<= 100 rows), whitelist phase (`rest`, `exercise`, `recovery`), scope all stage inserts/updates/deletions strictly by the parent `stress_test_id`.

```php
// 1. CSRF
if (!CardiologyContainer::verifyCsrfToken()) {
    CsrfUtils::csrfNotVerified();
}

// 2. ACL and card activation
$userId = (int)($_SESSION['authUserID'] ?? 0);
if (!(new CardRegistry())->canWrite('echo', $userId)) {
    die(xlt('Not authorized'));
}

// 3. Container ownership and session match
$container = CardiologyContainer::getContainerById((int)$_POST['cardiology_id']);
if ($container === null || (int)$container['pid'] !== $pid || (int)$container['encounter'] !== $encounter) {
    die(xlt('The container does not match the patient or encounter.'));
}
if ($pid <= 0 || $pid !== CardiologyContainer::getSessionPid() || $encounter !== CardiologyContainer::getSessionEncounter()) {
    die(xlt('Session mismatch. Please reload and try again.'));
}
```

---

## 8.1 Input validation helper (`CardInput`)

Located at `lib/CardInput.php` (namespace `OpenEMR\Forms\Cardiology`, `declare(strict_types=1)`).  
All methods throw `\InvalidArgumentException` with the field name in the message when validation fails, enabling clear feedback to the clinician.

| Method | Behavior |
|---|---|
| `decimal(mixed $v, string $field, ?float $min, ?float $max): ?string` | Trims value; converts decimal commas to dots (`55,5` → `55.5`); validates with `is_numeric()`; enforces min/max plausibility bounds; formats as `number_format($f, 3, '.', '')`. Returns `null` if empty. |
| `int(mixed $v, string $field, ?int $min, ?int $max): ?int` | Trims value; validates using `filter_var(FILTER_VALIDATE_INT)`; enforces min/max range. Returns `null` if empty. |
| `text(mixed $v): ?string` | Trims string; returns `null` if empty. |
| `dateTime(mixed $v, string $field): string` | Validates date/time against `Y-m-d\TH:i`, `Y-m-d H:i:s`, and `Y-m-d H:i` formats; outputs standardized `Y-m-d H:i:s`. Throws if empty or invalid. |
| `listOption(string $listId, mixed $v, string $field): ?string` | Checks that the option ID exists in OpenEMR's `list_options` for the given `$listId` with `activity = 1`. Returns `null` if empty; throws if invalid. |
| `flag(mixed $v): int` | Converts checkbox / boolean input to `1` or `0`. |

---

## 9. Card visibility rules (CardRegistry)

`CardRegistry::getVisibleCards(int $userId)` returns a card when **all** of:

| # | Rule |
|---|---|
| 1 | `card_modules.global_active = 1` |
| 2 | Effective activation in `card_config` is active (user-row wins over clinic-default row; if neither exists → active by default) |
| 3 | `AclMain::aclCheckCore(acl_section, acl_value)` passes |

Cards are ordered by `card_modules.sort_order ASC`.

A card that passes all three rules but whose folder or required files are
missing is shown as a **disabled tile** with the tooltip _"Not installed"_.

---

## 10. Soft-delete policy

### Container (`form_cardiology`)

- Set `activity = 0`. Never hard-delete.
- Mirror the deletion in the `forms` table by calling `formDisappear()` (see
  `CardiologyContainer::softDelete()`).
- The `forms` table entry is then `deleted = 1`, which hides the form from the
  encounter summary.

### Child studies (`form_card_*`)

- Set `activity = 0`. Never hard-delete.
- Child studies are **not** automatically deactivated when the container is
  soft-deleted. The data remains intact.
- Every child-card `save.php` **must** check `form_cardiology.activity = 1`
  before allowing writes. This prevents new data from being added to a deleted
  container.
- `view.php` and `print.php` may still open an existing study whose container
  is soft-deleted, for audit / review purposes.

### Stage rows (`form_card_stress_test_stage`)

- Stage rows have no `activity` column; they are logically excluded by joining
  through the parent study's `activity` column.
- They are never independently deleted.

---

## 11. OpenEMR delete hook limitation

OpenEMR does not provide a formal cascade-delete hook for custom forms.
When a user deletes the encounter or the patient, the `forms` row is marked
`deleted = 1`, but the `form_cardiology` and `form_card_*` rows are **not**
automatically touched.

**Implication:** If a cleanup process relies on `forms.deleted`, it must also
apply `activity = 0` to `form_cardiology` and, optionally, to the child tables.
A future maintenance script or event listener may handle this; until then,
orphaned container rows are harmless (they are filtered out by `activity = 1`
queries) but they do occupy storage.

---

## 12. Manual test checklist

Run these steps on a test installation before deploying to production.

### 12.1 Open form (new container)

- [ ] Open a patient encounter.
- [ ] Click **Cardiology** in the encounter form menu.
- [ ] Verify `new.php` renders without PHP errors.
- [ ] Verify both card tiles appear marked **"Not installed"** with the
      tooltip visible on hover if files are missing.
- [ ] Verify the patient name and encounter number appear in the header.

### 12.2 Save reason / notes

- [ ] Type text in **Reason for Visit** and **Visit Notes**.
- [ ] Click **Save**.
- [ ] Verify `save.php` redirects to `view.php`.
- [ ] Verify the row appears in `form_cardiology` (`activity = 1`).
- [ ] Verify a row appears in `forms` (`formdir = 'form_cardiology'`, `deleted = 0`).
- [ ] Reload `view.php`; verify reason and notes are pre-filled.
- [ ] Verify the encounter summary shows the cardiology row.

### 12.3 Existing container redirect

- [ ] While still on the same encounter, open the form again via
      the encounter form menu (`new.php`).
- [ ] Verify you are redirected to `view.php` (not a new blank form).
- [ ] Verify no duplicate row in `form_cardiology`.

### 12.4 ACL denied

- [ ] Log in as a user whose ACL role does NOT include `patients / med`.
- [ ] Navigate directly to `new.php` or `view.php`.
- [ ] Verify the page displays `"Not authorized"` and stops.

### 12.5 Per-user card disabled

- [ ] Open `config.php` as an admin.
- [ ] Set scope to **Specific user**, choose your test user, and **uncheck** the
      `echo` card.
- [ ] Log in as that user and open a cardiology encounter.
- [ ] Verify the `echo` tile does **not** appear in the grid.
- [ ] Verify the `stress_test` tile still appears.

### 12.6 Global card disabled

- [ ] Open `config.php` as an admin.
- [ ] In `card_modules`, set `global_active = 0` for `echo` (direct SQL or
      via config screen once that column is exposed).
- [ ] Open a cardiology encounter as any user.
- [ ] Verify the `echo` tile does **not** appear.

### 12.7 CSRF failure

- [ ] Open the browser developer tools, copy the form HTML, and submit it with
      a modified or missing `csrf_token_form` value to `save.php`.
- [ ] Verify the request is rejected (OpenEMR CSRF error page or die()).
- [ ] Verify no row is inserted in `form_cardiology`.

### 12.8 Config page save

- [ ] Open `config.php` as an admin.
- [ ] Toggle a card active/inactive at the clinic scope and save.
- [ ] Verify a row is upserted in `card_config` with `user_id = 0`.
- [ ] Toggle back and verify the row is updated.

### 12.9 Non-admin cannot access config.php

- [ ] Log in as a non-admin user.
- [ ] Navigate directly to `config.php`.
- [ ] Verify the page displays `"Not authorized"` and stops.

### 12.10 Decimal with comma accepted
- [ ] In the echo or stress test form, enter a decimal with a comma (e.g. `55,5` in `height_cm` or `lvedd_mm`).
- [ ] Save the study.
- [ ] Verify the value is stored and rendered as a numeric decimal (`55.5` or `55.500`).

### 12.11 Non-numeric input rejected
- [ ] Enter non-numeric text (e.g. `abc` or `12x`) into a numeric field (e.g. `weight_kg`).
- [ ] Submit the form.
- [ ] Verify the transaction is rolled back and a clean error page displays `Validation Error` with the offending field name and a functional Back button.

### 12.12 Plausibility bounds rejected
- [ ] Enter a value exceeding plausibility limits (e.g. `height_cm = 300` or `lvef_pct = 95`).
- [ ] Submit the form.
- [ ] Verify the submission is rejected with an explicit out-of-range error message.

### 12.13 Invalid list option rejected
- [ ] Using browser dev tools or cURL, forge a POST with an invalid option for a list (e.g. `protocol = 'invalid_protocol'`).
- [ ] Verify the submission throws `\InvalidArgumentException` and displays the validation error page.

### 12.14 Post to finalized study rejected
- [ ] Finalize a study (`action = 'save_final'`).
- [ ] Send a POST request targeting the finalized study (`study_id = <finalizedId>`).
- [ ] Verify the transaction rolls back and the operation is rejected (`This study is finalized and cannot be edited.`).

### 12.15 Container patient / encounter mismatch rejected
- [ ] Send a forged POST where `cardiology_id` belongs to patient A, but `pid` in POST belongs to patient B.
- [ ] Verify the request is rejected with `The container does not match the patient or encounter.`.

### 12.16 Direct POST to disabled card rejected
- [ ] In `config.php`, disable the `echo` card for the test user.
- [ ] Attempt a direct POST to `cards/echo/save.php` as that user.
- [ ] Verify the request is rejected with `Not authorized` via `CardRegistry::canWrite()`.

### 12.17 Finalize without conclusion rejected
- [ ] Leave the **Conclusion** field empty and click **Finalize** (`save_final`).
- [ ] Verify the submission is stopped and `A conclusion is required to finalize the study.` is displayed.

### 12.18 Duke score protocol dependency
- [ ] In a stress test study with protocol `Bruce` and duration `600` seconds, verify Duke score is calculated and saved.
- [ ] Change protocol to `Naughton` or `Ramp` and save.
- [ ] Verify Duke score is stored as `NULL`.

### 12.19 Stage rows validation
- [ ] Submit a stage row with an invalid phase (not `rest`, `exercise`, or `recovery`). Verify rejection.
- [ ] Attempt to submit more than 100 stage rows in a single request. Verify rejection with `Maximum of 100 stage records exceeded.`.

### 12.20 ECG: Draft and finalization
- [ ] In an active encounter, open the **12-Lead ECG** card (`ecg`).
- [ ] Enter heart rate `75`, PR `160`, QRS `90`, QT `380`.
- [ ] Verify real-time client preview displays QTc Bazett `425 ms` and QTc Fridericia `409 ms`.
- [ ] Click **Save draft**. Verify the study is saved with `status = 'draft'` and editable.
- [ ] Click **Finalize** without entering a conclusion. Verify save is rejected with `A conclusion is required to finalize the study.`.
- [ ] Fill in `conclusion` and click **Finalize**. Verify `status = 'final'`, finalized timestamp and user are populated, and the form switches to read-only.
- [ ] Attempt a direct POST to edit the finalized ECG study. Verify the update is rejected with `This study is finalized and cannot be edited.`.
- [ ] Click **Print**. Verify the printable/PDF view renders correctly with patient header, rhythm findings, calculated QTc values, and conclusion.

### 12.21 Preoperative Risk: RCRI score calculation and finalize rules
- [ ] Open the **Preoperative Cardiovascular Risk** card (`preop_risk`).
- [ ] Check `High-risk surgery` and `Ischemic heart disease`.
- [ ] Verify real-time UI updates to Lee RCRI Score `2` and Class `III`.
- [ ] Click **Save draft**. Verify study is saved as draft with score `2` and class `III`.
- [ ] Click **Finalize** with `conclusion` filled but `recommendation` empty. Verify save is rejected with `A recommendation and a conclusion are required to finalize the study.`.
- [ ] Fill in `recommendation` and click **Finalize**. Verify study switches to `final` and becomes read-only.
- [ ] Verify the print view displays surgical procedure, all 6 risk factors with indicators, calculated Lee RCRI score & class (without hardcoded event percentages), recommendation, and conclusion.

### 12.22 Fitness: Validity period, restrictions and certificate print
- [ ] Open the **Cardiology Fitness Certificate** card (`fitness`).
- [ ] Verify `valid_until` is pre-filled with the date 12 months after today.
- [ ] Enter height `175` cm and weight `70` kg. Verify real-time BMI displays `22.86`.
- [ ] Set `determination` to `fit_restrictions` and leave `restrictions` empty. Click **Finalize**.
- [ ] Verify save is rejected with `Specific restrictions must be documented when patient is fit with restrictions.`.
- [ ] Set `valid_until` to a date prior to `study_date`. Click **Finalize**.
- [ ] Verify save is rejected with `The certificate validity date must be strictly after the study date.`.
- [ ] Enter valid restrictions and a future validity date, then click **Finalize**. Verify study is saved as `final`.
- [ ] Click **Print**. Verify the certificate displays the neutral legal statement: `"Based on the examination performed on <date>, the patient is considered <determination>."`, along with sports activities, validity expiration date, and the physician's license number (or signature line if not configured).

### 12.23 Charts: Chronological order across patient studies
- [ ] Open a patient with multiple historical finalized studies for `echo` or `ecg`.
- [ ] In `view.php`, expand the **Evolution** section.
- [ ] Verify all historical points appear sorted chronologically from left to right by study date.

### 12.24 Charts: Gaps on missing / NULL values
- [ ] Create a study where a metric (such as `pasp_mmhg` or `duke_score`) is left empty/NULL.
- [ ] Open the evolution chart.
- [ ] Verify that the line series shows a visible gap at that date instead of connecting to zero.

### 12.25 Charts: Draft and open study styling
- [ ] Save an `ecg` or `echo` study as **Draft**.
- [ ] Expand the **Evolution** accordion.
- [ ] Verify the current open study point is rendered with an enlarged marker radius and hollow fill (white center).
- [ ] Hover over the point and verify the tooltip displays `(Draft) [Current Study]`.

### 12.26 Charts: Empty state and patient privacy
- [ ] Open a patient who has no previous cardiology studies.
- [ ] In `view.php`, expand **Evolution**.
- [ ] Verify the chart containers display the clean translated message `"No data to plot"` instead of empty or broken canvases.
- [ ] Switch to a different patient; verify that no data points from the previous patient are visible.

### 12.27 Charts: Stress test stage-by-stage trends
- [ ] Open an exercise stress test study with multiple stages recorded.
- [ ] In `view.php`, verify that stage trend charts appear first, followed by longitudinal charts.
- [ ] Verify the x-axis displays elapsed exercise time in `mm:ss` format and tooltips reflect the stage phase (`Rest`, `Exercise`, `Recovery`) and stage number.

### 12.28 Charts: PDF print rendering & theme adaptability
- [ ] Click **Print PDF** on an echo, stress test, or ECG study.
- [ ] Verify the printable page renders all applicable charts statically (without animations).
- [ ] Click the **Print** button and verify the browser print dialog previews the charts sharply.
- [ ] In the main view, toggle dark/light theme in OpenEMR and verify text and grid colors adapt cleanly.

### 12.29 Documents: Upload PDF to draft study
- [ ] In a draft echo study `view.php`, click **Upload documents** in the Attached Documents section.
- [ ] The reusable upload dialog opens. Drag a PDF report onto the drop zone (or click it and choose the file).
- [ ] Verify the queue shows the file name, size, and type, and that **Remove** works before upload.
- [ ] Click **Upload** and verify per-file progress, then success.
- [ ] Verify the attachments table and count badge refresh without reloading the page.
- [ ] Open the patient's main OpenEMR **Documents** tab and verify the file appears under `Cardiology > Echocardiogram`.

### 12.30 Documents: Attach existing patient document
- [ ] In a draft or final study, click **Attach Existing**.
- [ ] Select an existing unlinked document of the same patient from the dropdown and click **Attach**.
- [ ] Verify the document is linked to the study and appears in the table.

### 12.31 Documents: Cross-patient linking rejected
- [ ] Send a crafted POST to `documents_api.php` attempting to link a document belonging to patient B into a study of patient A.
- [ ] Verify the API returns HTTP 400 with `Document does not belong to the selected patient. Cross-patient linking is prohibited.`.

### 12.32 Documents: Status rules (draft vs final)
- [ ] In a draft study, click **Unlink** on an attachment and confirm. Verify the attachment is unlinked.
- [ ] Finalize the study.
- [ ] Verify that Unlink buttons are no longer rendered in the UI.
- [ ] Send a forged POST attempting to unlink an attachment from the finalized study. Verify the API rejects it with HTTP 422 (`Documents cannot be unlinked from a finalized study.`).
- [ ] Attach a new document or existing document to the finalized study. Verify it succeeds and displays the badge `Added after finalization`.

### 12.33 Documents: ACL permissions check
- [ ] Log in as a user without `patients/docs` permission.
- [ ] Open a study's `view.php`. Verify the Documents section displays an alert stating documents access is not permitted, and no upload/link actions are visible.
- [ ] Attempt a direct POST to `documents_api.php`. Verify the request is rejected.

### 12.34 Documents: Disallowed extension and spoofed MIME rejected
- [ ] Attempt to upload an `.exe` or `.txt` file. Verify immediate rejection.
- [ ] Rename an `.exe` file to `.pdf` and attempt upload. Verify the server-side MIME check rejects it with `File content type ... does not match allowed extension`.
- [ ] Attempt to upload a file larger than the configured upload ceiling (e.g. > 50 MB). Verify rejection.

### 12.35 Documents: CSRF and duplicate protection
- [ ] Submit an API request with an invalid or missing CSRF token. Verify HTTP 403 CSRF rejection.
- [ ] Attempt to attach the same document twice to the same study. Verify the API returns a friendly message: `This document is already attached to this study.`.

### 12.36 Documents: Category fallback and missing category error
- [ ] Set `card_modules.doc_category_id = NULL` for a card. Attach a document and verify it is filed under fallback category `22007 Other Cardiology Documents`.
- [ ] If both card category and fallback 22007 are missing from `categories`, verify an actionable error is shown instructing the administrator to run the upgrade script and rebuild the category tree.

### 12.37 Documents: Audit logging
- [ ] Upload a document, link an existing document, and unlink an attachment.
- [ ] Check OpenEMR's `log` table / Event Log. Verify audit records for `doc_upload`, `doc_attach_existing`, and `doc_unlink` containing user, card code, study ID, document ID, and category ID (with zero patient clinical data in comments).

### 12.38 Documents: Print PDF attachments list
- [ ] Add attachments to a study and click **Print PDF**.
- [ ] Verify the report renders the **Attached Documents** table listing Document Title, Category, and Date without file contents.
- [ ] For a study without attachments, verify the Attached Documents section is not printed.

### 12.39 Documents: Container tile counts
- [ ] Return to the encounter's Cardiology container form (`view.php`).
- [ ] Verify that each study item in the card tiles displays a badge with a paperclip icon and the count of attached documents next to the status badge, loaded with a single grouped query (no N+1).

### 12.40 Documents: Tree rebuild integrity
- [ ] As super administrator, navigate to `tools/rebuild_category_tree.php`.
- [ ] Confirm and execute the tree rebuild.
- [ ] Verify that all existing OpenEMR categories retain their original IDs, parents, and document links, and the new Cardiology branch is fully browsable in the Documents tree.

### 12.41 Documents: Reusable upload dialog and security matrix
- [ ] **Single PDF:** open the dialog from a draft study, select one PDF, upload it, and verify the table refreshes and the new row is linked.
- [ ] **Mixed allowed/disallowed:** queue one PDF, one JPG, and one `.exe`. Verify the `.exe` is rejected in the dialog and the allowed files can still be uploaded.
- [ ] **Drag and drop:** drag several allowed files onto the drop zone. Verify they are queued, removable before upload, and uploaded sequentially.
- [ ] **Cancel:** start a multi-file upload and click **Cancel upload** (or press Escape). Verify the active request stops and the dialog stays open with a cancelled summary.
- [ ] **Retry:** force one file to fail (for example, make the upload endpoint return an error for that file), then click **Retry failed files** or the per-file retry icon. Verify only failed files are retried and successful files are not duplicated.
- [ ] **Oversize:** attempt a file larger than the limit reported by the dialog. Verify the client rejects it and the server also rejects it when the check is bypassed.
- [ ] **Spoofed extension:** rename an executable to `.pdf` and upload it. Verify the server's real MIME check rejects the file.
- [ ] **Wrong pid:** craft a POST with `pid` different from the active patient session. Verify HTTP 403 and no document is created.
- [ ] **Foreign category:** craft a POST with a valid `category_id` whose `aco_spec` the user cannot access. Verify rejection and no document is created.
- [ ] **Other patient's study:** craft a POST with another patient's `study_id`. Verify rejection and no link is created.
- [ ] **Missing `patients/docs`:** log in without `patients/docs`. Verify the Documents section shows the permission alert, the upload button is absent, and direct POSTs are rejected.
- [ ] **CSRF failure:** submit the upload POST with a missing or invalid `csrf_token_form`. Verify HTTP 403 and no file is stored.
- [ ] **Final study attachment:** upload a document to a finalized study. Verify the attachment succeeds, is linked, and shows **Added after finalization** after the table refresh.
- [ ] **List refresh:** upload two files, then verify only the attachment table and count badge refresh (the page does not reload) and the translated counts are shown.
- [ ] **Dialog and Bootstrap fallback:** open the dialog via `dlgopen()` and verify origin/source validation. Temporarily undefine `dlgopen` and verify the Bootstrap 4/5 iframe modal fallback opens, receives the result, and closes.
- [ ] **OpenEMR 8.2.0 and 8.5.0:** repeat the single-PDF, partial-result, cancel, and Escape checks on both versions. Verify no version-number checks are used and the same code path runs.
- [ ] **Escape and Close:** press Escape before uploading (dialog closes), during upload (upload is cancelled), and click both the header X and the bottom **Close** button. Verify the opener always removes its `message` listener.

---

## 13. Document attachments architecture (`card_documents`)

The module integrates clinical studies with OpenEMR's native Documents repository through a dedicated join table and category branch.

### 13.1 Database schema
- **Join Table:** `card_documents`
  - `id BIGINT AUTO_INCREMENT PRIMARY KEY`
  - `card_code VARCHAR(32)`: Code of the card module (e.g. `echo`, `stress_test`, `ecg`, `preop_risk`, `fitness`).
  - `study_id BIGINT`: ID of the study in the corresponding `form_card_*` table.
  - `pid BIGINT`: Patient ID (`patient_data.pid`), strictly validated against the active session.
  - `document_id BIGINT`: ID in OpenEMR's core `documents` table.
  - `category_id BIGINT`: Category ID assigned at time of linking.
  - `created_by VARCHAR(255)`: Username of the user who attached the file.
  - `created_at DATETIME`: Attachment timestamp.
  - `activity TINYINT`: Soft-delete flag (`1` = active link, `0` = unlinked).
  - Indexes: `UNIQUE KEY (card_code, study_id, document_id)`, `KEY (pid, card_code, study_id)`, `KEY (document_id)`.

### 13.2 Cardiology category branch
Categories seeded under the OpenEMR root document category (ID `1`):
| Category ID | Category Name | Parent ID | Description |
|---|---|---|---|
| **22000** | Cardiology | 1 | Root branch for all cardiology documentation |
| **22001** | Echocardiogram | 22000 | Doppler, transthoracic and transesophageal echocardiograms |
| **22002** | Stress Test | 22000 | Ergometry and treadmill stress test reports |
| **22003** | ECG | 22000 | Resting 12-lead electrocardiograms |
| **22004** | Holter / ABPM | 22000 | 24-48h ECG Holter and ambulatory blood pressure monitoring |
| **22005** | Preoperative Evaluation | 22000 | Perioperative cardiac clearance and surgical risk evaluations |
| **22006** | Fitness Certificates | 22000 | Sports, employment, and academic physical fitness clearances |
| **22007** | Other Cardiology Documents | 22000 | Default fallback for unmapped cards or general cardiology documents |

### 13.3 Recommended upgrade procedure
1. **Database Backup:** Back up `categories`, `categories_seq`, and `categories_to_documents`.
2. **Run Upgrade SQL:** Execute `sql/upgrade_003_documents.sql` in MariaDB/MySQL.
3. **Rebuild Category Tree:** Log in as a super administrator (`admin/super`) and open `tools/rebuild_category_tree.php`. Click **Rebuild Document Categories Tree** to recompute the nested-set preorder boundaries (`lft`, `rght`) across all categories.

---

## 14. Reusable document upload dialog

The Attached Documents section no longer contains an inline upload form. All
five study views include `templates/_documents.php`, which opens the reusable
dialog implemented by `documents_upload.php` through the public `CardUpload`
JavaScript API.

```
templates/_documents.php  →  js/cardiology-upload.js  →  documents_upload.php
                                        ↑                        │
                                        └── postMessage result ──┘
```

### 14.1 Files

| File | Responsibility |
|---|---|
| `documents_upload.php` | GET renders the dialog; POST validates and stores files, returning the documented JSON result. |
| `js/cardiology-upload.js` | Public `CardUpload.open()` API, `dlgopen()`/Bootstrap modal hosting, origin/source checks, listener cleanup, `onDone`/`onClose`/`callBack`. |
| `js/cardiology-upload-dialog.js` | Drag/drop, file picker, queue, remove-before-upload, per-file XHR progress, retry, cancel, summary, Escape/Close. |
| `lib/CardDocuments.php` | `resolveUploadContext()`, `validateCategoryId()`, and the single `storeUploadedFile()` path. |
| `documents_api.php` | Non-upload actions only: `list`, `attach_existing`, `unlink`, `list_candidates`. |

### 14.2 Deliberate differences from the odontogram uploader

The odontogram was used as the UX reference. The following differences are
intentional and required by this contract:

| Odontogram behavior | Cardiology upload dialog | Why |
|---|---|---|
| `window.open()` popup sized 1000×600 | `dlgopen()` modal, or a Bootstrap 4/5 iframe modal fallback | Keeps OpenEMR's native dialog chrome, stacking, resize, and theme. |
| Single fixed `odon_doc` input | Queue with multiple files (server limit, maximum 10) | Attachments commonly arrive as several files. |
| Normal form POST that reloads the opener | `XMLHttpRequest` + JSON + `postMessage` | Avoids losing page state and allows progress, retry, and partial results. |
| No per-file progress | Per-file upload progress bar and status | Required for large PDFs and slow clinical networks. |
| No retry | Retry failed files only | Successful files are never uploaded twice. |
| No cancel control | Cancel aborts the active request | Prevents long-running uploads when the user changes their mind. |
| Free category tree in the dialog | `category_id` parameter or the card/fallback category | The API owns category validation and ACL, not the browser. |
| No CSRF token | CSRF checked on every POST | Mandatory for state-changing requests. |
| Validation only in the page | Extension allowlist, real MIME check, double-extension check, size limit, patient/category/study checks | Browser checks are advisory; the server is authoritative. |
| Direct `Document::createDocument()` plus `UPDATE documents` | One `CardDocuments::storeUploadedFile()` method | Same logic for standalone, draft, and final uploads. |
| Page reload alert | Origin/source-checked `postMessage`, optional opener `callBack` | Documented, reusable integration contract. |

### 14.3 HTTP contract

`GET documents_upload.php`

| Parameter | Required | Validation |
|---|---|---|
| `pid` | Yes | Must equal `CardiologyContainer::getSessionPid()`. |
| `category_id` | No | Must exist in `categories`; the category `aco_spec` is checked with core ACL. When omitted, the card category or fallback `22007` is used. |
| `card_code` | Pair | Must be supplied together with `study_id`. |
| `study_id` | Pair | Must belong to `pid`, be active, and pass `CardRegistry::canWrite()`. |
| `multiple` | No | `1` (default) or `0`. |

`GET` returns the HTML dialog page. `POST` accepts `multipart/form-data`:

| Field | Required | Notes |
|---|---|---|
| `csrf_token_form` | Yes | Also accepted as `X-CSRF-Token`. |
| `pid` | Yes | Same session check as GET. |
| `files[]` | Yes | One or more files, maximum `CardDocuments::MAX_FILES_PER_REQUEST` (10). |
| `category_id`, `card_code`, `study_id`, `multiple` | No | Same validation as GET. |

The POST response is always JSON:

```json
{
  "source": "cardiology-upload",
  "status": "ok",
  "documents": [
    {
      "id": 1234,
      "name": "echocardiogram-report.pdf",
      "category_id": 22001,
      "linked": true
    }
  ],
  "errors": []
}
```

`status` is one of:

- `ok` — every file was stored (and linked when `card_code` + `study_id` were supplied).
- `partial` — at least one file was stored and at least one failed.
- `error` — no file was stored; inspect `errors`.

Each error is `{ "name": "file.pdf", "message": "translated message" }`.

HTTP status codes: `200` for `ok`/`partial`/`error` results produced after a
file was inspected, `400` for invalid parameters or no files, `403` for CSRF,
session, or ACL failures, `422` for service-level failures, `500` for unexpected
server errors. The dialog displays the message in `errors` regardless of code.

No callback function name, JavaScript snippet, table name, column name, or
foreign-reference identifier is accepted from the request.

### 14.4 `postMessage` contract

After a completed batch the dialog sends exactly (automatically on full success,
or when the user closes the dialog after a partial/error result):

```js
window.parent.postMessage({
  source: 'cardiology-upload',
  status: 'ok' | 'partial' | 'error',
  documents: [{ id, name, category_id, linked }],
  errors: [{ name, message }]
}, window.location.origin);
```

The receiver must check both `event.origin` (exact `window.location.origin`) and
`event.source` (the dialog iframe content window). `CardUpload.open()` does this
and also supports an opener-side callback:

```js
function onChange(payload) {
  if (!payload || !payload.documents) return;
  console.log(payload.documents.length, payload.errors.length);
}

CardUpload.open({
  pid: 42,
  cardCode: 'echo',
  studyId: 7,
  multiple: 1,
  callBack: 'onChange',
  onDone: onChange,
  onClose: function () { /* runs after the dialog is removed */ }
});
```

If `postMessage` is unavailable, the dialog keeps its final summary visible and
offers a **Close** button instead of silently failing.

### 14.5 Required Header asset and embedding example

`dlgopen()` comes from OpenEMR's `dialog` asset. Include it (and jQuery and
Bootstrap) when embedding the uploader in another script:

```php
<?php
use OpenEMR\Core\Header;

require_once __DIR__ . '/lib/CardiologyContainer.php';

$uploadUrl = \OpenEMR\Forms\Cardiology\CardiologyContainer::getFormRootUrl() . '/documents_upload.php';
$csrfToken = \OpenEMR\Forms\Cardiology\CardiologyContainer::collectCsrfToken();
?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(['dialog', 'jquery', 'bootstrap']); ?>
</head>
<body>
    <button type="button" id="open-upload"
            data-pid="<?php echo attr((string)$pid); ?>"
            data-category-id="22001"
            data-card-code="echo"
            data-study-id="<?php echo attr((string)$studyId); ?>"
            data-upload-url="<?php echo attr($uploadUrl); ?>"
            data-csrf-token="<?php echo attr($csrfToken); ?>"
            data-multiple="1">
        <?php echo xlt('Upload documents'); ?>
    </button>

    <script src="<?php echo attr($GLOBALS['webroot']); ?>/interface/forms/form_cardiology/js/cardiology-upload.js"></script>
    <script>
    document.getElementById('open-upload').addEventListener('click', function () {
        var button = this;
        window.CardUpload.open({
            pid: button.getAttribute('data-pid'),
            categoryId: button.getAttribute('data-category-id'),
            cardCode: button.getAttribute('data-card-code'),
            studyId: button.getAttribute('data-study-id'),
            url: button.getAttribute('data-upload-url'),
            multiple: button.getAttribute('data-multiple') === '1' ? 1 : 0,
            onDone: function (payload) {
                if (!payload || !payload.documents) return;
                console.log('Stored:', payload.documents.length);
                console.log('Failed:', payload.errors.length);
            }
        });
    });
    </script>
</body>
</html>
```

The dialog reads its limits from `documents_upload.php`; do not hard-code a size
or file-count limit in the embedding page. The visible allowed types, maximum
size, and maximum files per request are generated server-side.

### 14.6 Server-side security notes

- Every POST verifies CSRF through `CardiologyContainer::verifyCsrfToken()`.
- `pid` must match the active patient session; other patients' studies cannot be
  linked.
- `patients/docs` is required, and linked uploads additionally require
  `CardRegistry::canWrite()` for the card.
- A supplied `category_id` must exist and its `aco_spec` must pass core ACL.
- Only `pdf`, `jpg`, `jpeg`, `png`, `tif`, and `tiff` are accepted.
- The extension is checked first, then the real content type is detected with
  fileinfo. Double extensions that hide executables are rejected.
- The effective limit is the smallest of the module ceiling (50 MB),
  `upload_max_filesize`, and `post_max_size`; the request is limited to 10
  files. The dialog displays the effective limit returned by the server.
- Display names are sanitized (path components, control characters, excessive
  length) before they reach `Document::createDocument()`.
- Documents are created only through OpenEMR's core `Document` class under the
  validated category. All SQL uses prepared statements and no table or column
  name comes from the request.
- Every stored/linked file is audited (`doc_upload`) with card, study, document,
  category, and size metadata, never clinical content.
- The response is JSON-encoded and all dialog output is escaped.

### 14.7 Compatibility with OpenEMR 8.2.0 – 8.5.0

Feature detection is used; there are no version-number checks.

- **`dlgopen`** exists in both 8.2.0 and 8.5.0. 8.5.0 drops `resolvePromiseOn`
  from its defaults, so this module never passes or relies on it. If `dlgopen`
  is absent, the Bootstrap iframe modal is used.
- **`Header::setupHeader()`** accepts asset keys in both versions. `dialog`,
  `jquery`, `bootstrap`, and `utility` are autoloaded in the OpenEMR layouts
  used by this form. Request `dialog` explicitly when embedding elsewhere.
- **Bootstrap / jQuery:** both 8.2.0 and 8.5.0 ship Bootstrap 4.6.2 and jQuery
  3.7.1; the markup also tolerates Bootstrap 5 class names.
- **`Document::createDocument()`** has the same signature in both versions,
  including `foreign_reference_id` and `foreign_reference_table`, and
  `get_id()` exists in both.
- **`CsrfUtils`** requires a session argument in both versions. This module
  routes all CSRF calls through `CardiologyContainer`, which reflects over the
  method signature so both argument orders work.
- **`AclMain`** exposes the same ACL methods; 8.5.0 mainly adds return types.
- **Session accessors** are resolved through `CardiologyContainer::getSessionPid()`,
  which uses core session utilities when available and falls back to
  `$_SESSION` / `$GLOBALS`.
- **Core document retrieval URL** stays
  `<webroot>/controller.php?document&retrieve&patient_id=…&as_file=false&document_id=…`
  in both versions.

### 14.8 Cross-repository caution

This dialog is internally reusable, but it is not a stable long-term shared
library for unrelated repositories. Its formdir, category IDs, ACL expectations,
Root URL, and `card_documents` schema are owned by `form_cardiology`. If another
module needs the same UX, copy the contract and adapt the paths and category
resolution there rather than pointing two repositories at one moving file.


