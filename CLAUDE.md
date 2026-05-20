# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Running the app

Served via Laravel Valet at **http://freemius.test** (parked at `/Users/bigrigmedia/Documents/sites`, TLD `.test`). No build, install, or test commands — it's a two-file PHP app (`api.php` + `index.php`) loaded directly by PHP via Valet.

`config.php` is gitignored and holds live credentials. Never commit it. `config.example.php` is the template.

## Architecture

Three-file app, no framework, no dependencies:

- **`config.php`** — returns an array with `api_base`, `product_id` (21348), `bearer`, `pk`, `sk`. Required by `api.php` and read inline by `index.php` for the product-ID badge.
- **`api.php`** — thin proxy in front of the Freemius REST API (`https://api.freemius.com/v1`). Dispatches on `?action=` via a single `switch`, calls `apiRequest()` (curl + Bearer header), echoes `{success, http_code, data}`. This file is the *only* place that talks to Freemius — the browser never sees the bearer token.
- **`index.php`** — single-page dashboard (Tailwind via CDN, vanilla JS, no bundler). Four tabs (users / licenses / subscriptions / installs) share one table, toolbar, paginator, confirm modal, and user-detail drawer. JS state lives in globals (`currentTab`, `currentOffset`); per-tab config lives in the `filters` and `headers` maps near the top of the script. All fetches go to `api.php?action=...`.

### Adding a new Freemius action

1. Add a `case` to the switch in `api.php` that builds the URL and calls `apiRequest($url, $bearer, $method)`.
2. If it's a list/filter/search, reuse the shared `$count` / `$offset` / `$filter` / `$search` locals already parsed at the top of `api.php`.
3. On the frontend, call it with `fetch('api.php?action=...')` and handle the `{success, data}` envelope. For destructive actions use `showConfirm(msg, fn)` + `apiAction(params, label)` — they already wire up the modal, status line, and auto-reload.

### Freemius API quirks worth knowing

- No DELETE endpoint for users via Bearer Token API — don't try to add a "delete user" action.
- `enriched=true` on `/licenses.json` embeds linked user/plan/subscription; `extended=true` on `/subscriptions.json` embeds plan name, install URL, user email. Both are already used.
- Pagination `count` is clamped 1–50 server-side; frontend pages at 25.
- Subscription cancel accepts optional `reason` / `reason_ids` query params.
