# Coupons CRUD

## Goal

A sixth tab that lets the dashboard list, create, edit, and delete coupons for the active product. Bulk delete reuses the existing infrastructure.

## Confirmed via API probing

- `GET /products/{pid}/coupons.json` → 200, returns `{coupons: [...]}`
- `POST /products/{pid}/coupons.json` → 201, returns the new coupon. Required: `code`, `discount`, `discount_type` (one of `percentage` / `dollar`). `title` is optional.
- `DELETE /products/{pid}/coupons/{id}.json` → 204
- `PUT /products/{pid}/coupons/{id}.json` — assumed to work by analogy with `/licenses`; if it doesn't, fall back to delete-then-recreate. Will verify during implementation.

Coupon object includes: `code`, `title`, `discount`, `discount_type`, `redemptions`, `redemptions_limit`, `start_date`, `end_date`, `has_renewals_discount`, `is_one_per_user`, `billing_cycles`, `plans` (array of plan_ids or null = all), `is_active`, `created`, `updated`.

## Changes

### `api.php`

Four new switch cases:

- `list_coupons` — `fetchList(...)` against `/products/{pid}/coupons.json`. Honor `count`/`offset`/`filter`.
- `create_coupon` — reads selected fields from `$_GET`, builds a body, POSTs.
- `update_coupon` — reads `?coupon_id=N`, PUTs only present fields (same pattern as `update_license`).
- `delete_coupon` — reads `?coupon_id=N`, DELETEs.

Mutable/creatable fields exposed via query params: `code`, `title`, `discount`, `discount_type`, `redemptions_limit`, `start_date`, `end_date`, `has_renewals_discount`, `is_one_per_user`. Null-coercion for nullable numeric/date fields uses the same `'null'` sentinel the license editor already uses.

### `index.php`

- Add `Coupons` tab button in the nav.
- New `+ New Coupon` button in the toolbar — `hidden` by default, revealed only when `currentTab === 'coupons'`.
- New Coupon Edit modal (same shape pattern as `editLicenseModal`):
  - Code, Title, Discount + Discount Type (% / $), Redemptions Limit
  - Start Date, End Date (both optional, native date inputs)
  - Checkboxes: Has Renewals Discount, One Per User
  - Cancel / Save buttons. Save = create (if no `editingCoupon`) or update.

### `dashboard.js`

- Add `'coupons'` to `filters` (just `All`), `headers`, `sortKeys`, `perPageByTab`.
- Add `'coupons'` to `bulkSelectableTabs` — coupons get checkboxes + the existing bulk-delete strip. Bulk delete dispatches `delete_coupon` based on tab.
- New `renderRow` case for coupons: `code` (mono), `title`, `discount` formatted as `X%` or `$X.XX`, `redemptions / limit`, `start–end` date range, active badge (computed client-side from dates + redemptions), and Edit / Delete buttons.
- `openNewCoupon()` — opens modal with `editingCoupon = null` and blank fields.
- `openEditCoupon(id)` — fetches `get_coupon` (or pulls from `lastItems`), populates modal, sets `editingCoupon`.
- `saveCoupon()` — branches on whether `editingCoupon` exists. Create path posts all set fields. Update path diffs and PUTs only changed fields.
- `deleteCoupon(id)` — confirm + apiAction.
- `switchTab` toggles visibility of the `+ New Coupon` button based on tab.
- `bulkDelete` is updated to dispatch the right action per tab — extracted into a small `tabDeleteAction(tab)` helper instead of the if/else.

### CLAUDE.md
Update the file list with the new tab and the modal. Update the "Adding a new Freemius action" recipe to note the per-tab nature.

## Active-status computation (client-side)

A coupon is considered active when:
- `redemptions_limit == null || (redemptions ?? 0) < redemptions_limit`
- AND (`start_date == null || start_date <= now`)
- AND (`end_date == null || end_date >= now`)

The Freemius `is_active` field looks unreliable (returns `null` in probes), so we compute. Render as `<span class="text-green-400">Active</span>` or `<span class="text-red-400">Expired/Used Up</span>`.

## Out of scope

- Restricting coupon to specific plans (the `plans` array). The UI gets crowded quickly with multi-select; defer until needed.
- Editing licenses-restriction or billing_cycles. Keep the modal lean.
- Importing/exporting coupons. Existing CSV export already covers list view.

## Verification

- Create coupon "TESTCRUD" with 25% discount → appears in list, active status green.
- Edit it to set end_date in the past → status flips to "Expired".
- Bulk-select two coupons → Delete Selected → both disappear.
- Single delete on the remaining → list empty.
