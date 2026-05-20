# Multi-product support for the Freemius dashboard

## Goal

Let one dashboard instance switch between multiple Freemius products (each with its own credentials) via a header dropdown, instead of being hardcoded to a single product through `config.php`.

Initial products:
- `21348` — Smart Listing Pro (existing)
- `27881` — Xpress 2.0 (new)

## Constraint that shapes the design

The Freemius bearer token in `config.php` is **plugin-scoped**: it only authenticates requests for the product it was issued for. Listing endpoints (`/v1/products.json`, `/v1/plugins.json`, `/v1/developers/me/products.json`) all return errors with this token. Therefore each product must carry its own `bearer`, `pk`, and `sk` in config — we cannot share one credential set across products.

## Changes

### 1. `config.php`

Flatten replaced with a `products` array. `api_base` stays top-level. No `product_id` key remains.

```php
return [
    'api_base' => 'https://api.freemius.com/v1',
    'products' => [
        [
            'id'     => 21348,
            'label'  => 'Smart Listing Pro',
            'bearer' => '...',
            'pk'     => '...',
            'sk'     => '...',
        ],
        [
            'id'     => 27881,
            'label'  => 'Xpress 2.0',
            'bearer' => '...',
            'pk'     => '...',
            'sk'     => '...',
        ],
    ],
];
```

`config.example.php` mirrors the new shape.

### 2. `api.php`

- Replace the top-of-file `$pid = $config['product_id']; $bearer = $config['bearer'];` with a resolver:
  - Read `?product_id=N` from the request (GET or POST).
  - Find the matching entry in `$config['products']`.
  - On no match (or missing param), fall back to `$config['products'][0]` so the API behaves sensibly during the transition / when called directly.
  - Assign `$pid` and `$bearer` from the chosen entry. Existing endpoint code keeps using `$pid` / `$bearer` and is unchanged.
- Add `case 'list_products'`: returns `{success: true, data: [{id, label}, ...]}` projected from `$config['products']` — never returns credentials.

### 3. `index.php`

- Replace the static product-ID badge with a `<select id="productSelect">`. The dropdown sits in the existing header bar.
- New JS module-level state: `let currentProductId = null;`
- On page load:
  1. `fetch('api.php?action=list_products')` to populate the dropdown.
  2. Read `localStorage.getItem('freemius.productId')`. If set and present in the list, select it. Otherwise select the first option.
  3. Assign `currentProductId`, then call the existing init that loads the default tab.
- On dropdown change:
  1. `localStorage.setItem('freemius.productId', value)`.
  2. Reset `currentOffset = 0`, `lastItems = []`, `installsTotalCache = null`. Clear `ipCache` (`Object.keys(ipCache).forEach(k => delete ipCache[k])`).
  3. Call `loadCurrentTab()`.
- Every `fetch('api.php?action=...')` call site appends `&product_id=${currentProductId}` (every existing call already starts with `?action=...`, so `&` is always the right separator). Affected: `loadCurrentTab`, `apiAction`, `refreshInstallsTotal`, the user-drawer fetches, and the POST to `resolve_ips` (append product_id to the URL even though the endpoint ignores it — keeps every call site uniform).

## Out of scope (YAGNI)

- Per-product page-size memory. `perPageByTab` stays global across products.
- Encoding product in the URL hash. localStorage only.
- Combined / merged view across products.
- Caching the product list. It's a config read on the server; refetching is cheap.

## Migration

`config.php` is gitignored, so the user updates it manually. The implementation step will produce a one-time diff snippet showing the old → new shape with the new Xpress 2.0 values pre-filled, which the user pastes in.

## Verification

After implementation, with the new `config.php` shape in place:
- Dashboard loads, dropdown shows both products.
- Selecting either product reloads the current tab against the right credentials. Counts, results, and the product-specific badges/totals are correct for the selection.
- Refresh persists selection.
- All four tabs (users / licenses / subscriptions / installs) work for both products.
- User-drawer fetches work for the active product.
