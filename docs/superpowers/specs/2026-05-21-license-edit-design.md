# License edit UI

## Goal

Let the dashboard mutate existing licenses (change plan/pricing, extend or remove expiration, change activation quota) instead of just deleting or cancelling them. Replaces the originally planned "create license" feature, which is not supported by the bearer-scoped Freemius API (`POST /products/{pid}/licenses.json` → `not_implemented`).

## Constraint that shapes the design

- `POST /products/{pid}/licenses.json` is rejected with HTTP 400 / `not_implemented`. The bearer API does not create licenses — Freemius reserves that for payment flows.
- `PUT /products/{pid}/licenses/{id}.json` works and accepts mutable fields. An empty PUT body is a no-op that just returns the license, so we only need to send the fields that changed.

## Changes

### `api.php`

- Remove the `test_create_license` switch case and any related comment. It was probe code that never worked.
- Replace `make_license_lifetime` with a single `update_license` action:
  - Reads `?license_id=N` (required).
  - Accepts these mutable fields via query params (omitted → not sent): `expiration` (datetime string or the literal token `null`), `plan_id`, `pricing_id`, `quota` (int or `null` for unlimited), `is_whitelabeled` (0/1).
  - Builds a body containing only the present params, passing through `null` literally where the user asked for it (e.g. `expiration=null` to make a license lifetime).
  - PUTs to `/products/{pid}/licenses/{id}.json` and returns the response.
- Keep `get_license` and `list_pricing` (already exist; used by the modal).

### `index.php`

Add a hidden Edit License modal at the bottom of `<main>`, similar to the existing confirm modal but bigger. IDs / fields:

- `#editLicenseModal` (the wrapper)
- `#editLicenseInfo` — header showing id, user email, created date
- `#editLicensePlan` — `<select>`
- `#editLicensePricing` — `<select>` (chained to plan)
- `#editLicenseExpRadio` — two radios: `lifetime` / `date`
- `#editLicenseExpDate` — `<input type="date">` (disabled under `lifetime`)
- `#editLicenseQuota` — `<select>` (1, 5, 25, Unlimited)
- `#editLicenseWhitelabel` — `<input type="checkbox">`
- `#editLicenseCancelBtn`, `#editLicenseSaveBtn`

### `dashboard.js`

- New module-level `let editingLicense = null;` — the license object currently being edited.
- New `openEditLicense(licenseId)`:
  1. `fetch list_licenses` is too coarse — call `?action=get_license&license_id=...` to refetch the canonical record.
  2. Wait for `loadPlansForCurrentProduct()` if not already loaded.
  3. Populate the plan select from `plansCache`; preselect the current `plan_id`.
  4. Call `loadPricingForPlan(plan_id)` to populate pricing select; preselect the current `pricing_id`.
  5. Wire up plan-change handler so changing plan re-fetches pricing.
  6. Set expiration radio + date based on current `expiration`.
  7. Set quota and whitelabel.
  8. Open modal.
- New `loadPricingForPlan(planId)` — caches per-product as `pricingCache[productId][planId]` so re-opening the modal is instant.
- New `saveEditLicense()` — diff the form values against `editingLicense`, build a query string with only the changed fields, call `apiAction('action=update_license&license_id=...', 'License #X')` which already handles status + reload + product_id.
- New `makeLifetime(licenseId)` — shows confirm, then `apiAction('action=update_license&license_id=N&expiration=null', 'License #N')`. No modal needed — it's a one-click shortcut.
- Update the licenses `renderRow` case to add two new buttons before the existing Cancel Sub / Delete: **Edit** (blue) and **Make Lifetime** (yellow).

## Edge cases

- **Plan with one pricing** — pricing select still appears, just with one option. User can still pick a different plan and the pricing options will update.
- **License already lifetime** — Make Lifetime button still appears; clicking it confirms a no-op. Better to keep the button than to compute which rows should hide it.
- **Plan changed but pricing not** — saving must send both, since `pricing_id` from the old plan is invalid against the new plan. Force-send `pricing_id` whenever `plan_id` changes.
- **Empty PUT body** — if nothing changed, we still issue the PUT with an empty body. Freemius returns the unmodified license. The status line says "License #X — done" which is honest.
- **API errors** — `apiAction` already shows the Freemius error message in the status line.

## Out of scope

- Reset activations (separate endpoint, untested).
- Regenerate secret_key (risky — would break customer's installs).
- Bulk editing.

## Verification

After implementation:
- Open the Edit modal on license 1916971 (Xpress 2.0).
- Confirm plan, pricing, expiration, quota are pre-populated from the live license.
- Change plan → pricing select refreshes.
- Save with no changes → status shows "done", no error.
- Make Lifetime on a license whose expiration is `null` → confirm, click, status shows "done".
