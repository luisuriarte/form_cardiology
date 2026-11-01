# Cardiology Container Form

> **OpenEMR compatibility:** 8.2.0 – 8.5.0
> **formdir:** `cardiology`
> **Location:** `interface/forms/cardiology/`

---

## Table of Contents

1. [Architecture overview](#1-architecture-overview)
2. [File map](#2-file-map)
3. [How it works](#3-how-it-works)
4. [How to add a new card](#4-how-to-add-a-new-card)
5. [File conventions for a card](#5-file-conventions-for-a-card)
6. [Card visibility rules (CardRegistry)](#6-card-visibility-rules-cardregistry)
7. [Soft-delete policy](#7-soft-delete-policy)
8. [OpenEMR delete hook limitation](#8-openemr-delete-hook-limitation)
9. [Manual test checklist](#9-manual-test-checklist)

---

## 1. Architecture overview

```
form_cardiology  (one row per encounter – the "container")
   │
   ├── form_card_echo           (N studies per encounter)
   └── form_card_stress_test    (N studies per encounter)
          └── form_card_stress_test_stage  (M stages per study)
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
interface/forms/cardiology/
├── info.txt                        OpenEMR form name ("Cardiology")
├── table.sql                       DDL for all tables + list_options seeds
├── new.php                         Entry point (new container)
├── view.php                        Reopen existing container
├── save.php                        Save reason/notes; create container lazily
├── report.php                      Encounter-summary snippet
├── config.php                      Admin: enable/disable cards per user
│
├── lib/
│   ├── CardiologyContainer.php     ensureContainer(), getContainer(), softDelete(), transactions, session helpers
│   ├── CardRegistry.php            getVisibleCards(), canWrite() with ACL + config filtering
│   └── CardInput.php               Strict typed validation & sanitization helper (decimal, int, text, dateTime, listOption, flag)
│
├── css/
│   └── cardiology.css              All styles (no inline styles in PHP)
│
├── js/
│   └── cardiology.js               All JS (no inline handlers in PHP)
│
├── templates/
│   ├── _header.php                 Patient + encounter header bar
│   ├── _reason_notes.php           Reason / notes textarea fields
│   └── _card_grid.php              Card tile grid (generic, driven by CardRegistry)
│
└── cards/
    ├── echo/
    │   ├── README.md               Card specification and schema documentation
    │   ├── new.php                 New echocardiogram study form
    │   ├── view.php                View / edit existing echocardiogram study
    │   ├── save.php                Hardened save handler with transaction & CardInput
    │   └── print.php               Printable / PDF report view
    └── stress_test/
        ├── README.md               Card specification and schema documentation
        ├── new.php                 New exercise stress test form
        ├── view.php                View / edit existing stress test & stage grid
        ├── save.php                Hardened atomic save handler for study and stages
        └── print.php               Printable / PDF report view with stage charts
```

---

## 3. How it works

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

## 4. How to add a new card

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
   interface/forms/cardiology/cards/my_card/
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

## 5. File conventions for a card

Every card lives in `cards/<code>/` and must provide:

| File | Receives (GET) | Must do |
|---|---|---|
| `new.php` | `pid`, `encounter`, `cardiology_id` | Render a blank study form |
| `view.php` | `pid`, `encounter`, `cardiology_id`, `id` | Render an existing study (read-only if `status = 'final'`) |
| `save.php` | POST from new/view | Verify CSRF; verify ACL; verify parent container is active; insert/update study row; redirect |
| `print.php` | `pid`, `encounter`, `cardiology_id`, `id` | Render a printable/PDF report; no edit controls |

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

## 5.1 Input validation helper (`CardInput`)

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

## 6. Card visibility rules (CardRegistry)

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

## 7. Soft-delete policy

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

## 8. OpenEMR delete hook limitation

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

## 9. Manual test checklist

Run these steps on a test installation before deploying to production.

### 9.1 Open form (new container)

- [ ] Open a patient encounter.
- [ ] Click **Cardiology** in the encounter form menu.
- [ ] Verify `new.php` renders without PHP errors.
- [ ] Verify both card tiles appear marked **"Not installed"** with the
      tooltip visible on hover.
- [ ] Verify the patient name and encounter number appear in the header.

### 9.2 Save reason / notes

- [ ] Type text in **Reason for Visit** and **Visit Notes**.
- [ ] Click **Save**.
- [ ] Verify `save.php` redirects to `view.php`.
- [ ] Verify the row appears in `form_cardiology` (`activity = 1`).
- [ ] Verify a row appears in `forms` (`formdir = 'cardiology'`, `deleted = 0`).
- [ ] Reload `view.php`; verify reason and notes are pre-filled.
- [ ] Verify the encounter summary shows the cardiology row.

### 9.3 Existing container redirect

- [ ] While still on the same encounter, open the form again via
      the encounter form menu (`new.php`).
- [ ] Verify you are redirected to `view.php` (not a new blank form).
- [ ] Verify no duplicate row in `form_cardiology`.

### 9.4 ACL denied

- [ ] Log in as a user whose ACL role does NOT include `patients / med`.
- [ ] Navigate directly to `new.php` or `view.php`.
- [ ] Verify the page displays `"Not authorized"` and stops.

### 9.5 Per-user card disabled

- [ ] Open `config.php` as an admin.
- [ ] Set scope to **Specific user**, choose your test user, and **uncheck** the
      `echo` card.
- [ ] Log in as that user and open a cardiology encounter.
- [ ] Verify the `echo` tile does **not** appear in the grid.
- [ ] Verify the `stress_test` tile still appears.

### 9.6 Global card disabled

- [ ] Open `config.php` as an admin.
- [ ] In `card_modules`, set `global_active = 0` for `echo` (direct SQL or
      via config screen once that column is exposed).
- [ ] Open a cardiology encounter as any user.
- [ ] Verify the `echo` tile does **not** appear.

### 9.7 CSRF failure

- [ ] Open the browser developer tools, copy the form HTML, and submit it with
      a modified or missing `csrf_token_form` value to `save.php`.
- [ ] Verify the request is rejected (OpenEMR CSRF error page or die()).
- [ ] Verify no row is inserted in `form_cardiology`.

### 9.8 Config page save

- [ ] Open `config.php` as an admin.
- [ ] Toggle a card active/inactive at the clinic scope and save.
- [ ] Verify a row is upserted in `card_config` with `user_id = 0`.
- [ ] Toggle back and verify the row is updated.

### 9.9 Non-admin cannot access config.php

- [ ] Log in as a non-admin user.
- [ ] Navigate directly to `config.php`.
- [ ] Verify the page displays `"Not authorized"` and stops.

### 9.10 Decimal with comma accepted
- [ ] In the echo or stress test form, enter a decimal with a comma (e.g. `55,5` in `height_cm` or `lvedd_mm`).
- [ ] Save the study.
- [ ] Verify the value is stored and rendered as a numeric decimal (`55.5` or `55.500`).

### 9.11 Non-numeric input rejected
- [ ] Enter non-numeric text (e.g. `abc` or `12x`) into a numeric field (e.g. `weight_kg`).
- [ ] Submit the form.
- [ ] Verify the transaction is rolled back and a clean error page displays `Validation Error` with the offending field name and a functional Back button.

### 9.12 Plausibility bounds rejected
- [ ] Enter a value exceeding plausibility limits (e.g. `height_cm = 300` or `lvef_pct = 95`).
- [ ] Submit the form.
- [ ] Verify the submission is rejected with an explicit out-of-range error message.

### 9.13 Invalid list option rejected
- [ ] Using browser dev tools or cURL, forge a POST with an invalid option for a list (e.g. `protocol = 'invalid_protocol'`).
- [ ] Verify the submission throws `\InvalidArgumentException` and displays the validation error page.

### 9.14 Post to finalized study rejected
- [ ] Finalize a study (`action = 'save_final'`).
- [ ] Send a POST request targeting the finalized study (`study_id = <finalizedId>`).
- [ ] Verify the transaction rolls back and the operation is rejected (`This study is finalized and cannot be edited.`).

### 9.15 Container patient / encounter mismatch rejected
- [ ] Send a forged POST where `cardiology_id` belongs to patient A, but `pid` in POST belongs to patient B.
- [ ] Verify the request is rejected with `The container does not match the patient or encounter.`.

### 9.16 Direct POST to disabled card rejected
- [ ] In `config.php`, disable the `echo` card for the test user.
- [ ] Attempt a direct POST to `cards/echo/save.php` as that user.
- [ ] Verify the request is rejected with `Not authorized` via `CardRegistry::canWrite()`.

### 9.17 Finalize without conclusion rejected
- [ ] Leave the **Conclusion** field empty and click **Finalize** (`save_final`).
- [ ] Verify the submission is stopped and `A conclusion is required to finalize the study.` is displayed.

### 9.18 Duke score protocol dependency
- [ ] In a stress test study with protocol `Bruce` and duration `600` seconds, verify Duke score is calculated and saved.
- [ ] Change protocol to `Naughton` or `Ramp` and save.
- [ ] Verify Duke score is stored as `NULL`.

### 9.19 Stage rows validation
- [ ] Submit a stage row with an invalid phase (not `rest`, `exercise`, or `recovery`). Verify rejection.
- [ ] Attempt to submit more than 100 stage rows in a single request. Verify rejection with `Maximum of 100 stage records exceeded.`.

