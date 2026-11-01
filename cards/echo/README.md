# Echo Card – Stub

**Card code:** `echo`
**Table:** `form_card_echo`
**Icon:** `fa-heartbeat`

This directory is a placeholder for the Doppler Echocardiogram card.

---

## Expected files

| File | Purpose |
|---|---|
| `new.php` | Create a new echo study for the current encounter. |
| `view.php` | View / edit an existing echo study. Final studies are read-only. |
| `save.php` | Handle POST from `new.php` / `view.php`. CSRF token required. |
| `print.php` | Render the PDF report for a single echo study. |

## URL parameters

Every child-card page receives these query-string parameters from the container:

| Parameter | Type | Description |
|---|---|---|
| `pid` | int | Patient id |
| `encounter` | int | Encounter number |
| `cardiology_id` | int | `form_cardiology.id` (the parent container) |
| `id` | int | `form_card_echo.id` — present only when editing or printing |

## Status workflow

| Status | Meaning |
|---|---|
| `draft` | Editable. Edit controls are visible. |
| `final` | Read-only. Only the **Open** (view) and **Print** buttons are shown. |

## Soft-delete policy

See [../../README.md](../../README.md) for full details.

A child study row must set `activity = 0` when deleted; it must never be
hard-deleted. `save.php` must check whether the parent container is active
(`form_cardiology.activity = 1`) before allowing a new study to be created or
an existing one to be edited.

## How to install this card

1. Copy this directory to `interface/forms/form_cardiology/cards/echo/`.
2. Create the four PHP files listed above.
3. The container will automatically detect the card as "installed" and enable
   the **New** button on the card tile.
4. No changes to the container code are needed.

