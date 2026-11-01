# Stress Test Card – Stub

**Card code:** `stress_test`
**Tables:** `form_card_stress_test`, `form_card_stress_test_stage`
**Icon:** `fa-running`

This directory is a placeholder for the Exercise Stress Test card.

---

## Expected files

| File | Purpose |
|---|---|
| `new.php` | Create a new stress-test study for the current encounter. |
| `view.php` | View / edit an existing study. Final studies are read-only. |
| `save.php` | Handle POST from `new.php` / `view.php`. CSRF token required. |
| `print.php` | Render the PDF report for a single stress-test study. |

## URL parameters

Every child-card page receives these query-string parameters from the container:

| Parameter | Type | Description |
|---|---|---|
| `pid` | int | Patient id |
| `encounter` | int | Encounter number |
| `cardiology_id` | int | `form_cardiology.id` (the parent container) |
| `id` | int | `form_card_stress_test.id` — present only when editing or printing |

## Status workflow

| Status | Meaning |
|---|---|
| `draft` | Editable. Stage rows can be added / edited. |
| `final` | Read-only. Stage chart is visible; no edits allowed. |

## Stage rows (`form_card_stress_test_stage`)

Stage rows are linked to the study via `stress_test_id`.
When a study is soft-deleted (`activity = 0`), its stage rows should be
retained (they carry no PHI beyond the study context) but logically excluded
from queries by joining through the parent study's `activity` column.

## Soft-delete policy

See [../../README.md](../../README.md) for full details.

A child study row must set `activity = 0` when deleted. Stage rows are NOT
individually deleted; they are excluded via the parent study join.
`save.php` must check whether the parent container is active before creating
or editing a study.

## How to install this card

1. Copy this directory to `interface/forms/form_cardiology/cards/stress_test/`.
2. Create the four PHP files listed above.
3. The container will automatically detect the card as "installed" and enable
   the **New** button on the card tile.
4. No changes to the container code are needed.

