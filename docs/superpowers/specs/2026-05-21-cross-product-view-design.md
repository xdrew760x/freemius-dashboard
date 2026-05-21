# Cross-product merged view

## Goal

A synthetic "All Products" option in the header dropdown that pulls each tab's data from every configured product and merges it into one table, with a new Product column showing which one each row came from. Read-only — action buttons are hidden because they'd need per-row credential routing, which adds significant complexity for marginal value at this scale.

## Why read-only

The dashboard currently has < 25 rows total across all 3 products on every tab. Mutations are rare. Building per-row credential dispatch on top of the existing `?product_id=N` resolver model would require touching every action handler and threading the row's product ID through bulk-delete + license-edit + coupon-edit flows. Not worth it for a glance-at-everything view.

## Changes

### `api.php`

No backend changes. The list endpoints already accept `product_id=N`; the merge happens in JS with N parallel fetches.

### `dashboard.js`

- Treat `currentProductId === 'all'` as the synthetic mode. `init()` and `changeProduct()` accept the string `'all'`.
- `loadCurrentTab()` branches:
  - If specific product → existing single-fetch path.
  - If `'all'` → `Promise.all(products.map(p => fetch(... product_id=p.id)))`, merge each collection key, tag every item with `_product_id` and `_product_label`, then pass through the normal renderTable path. Skip the offset/count chunking — fetch one page per product at the current per-page count.
- New `productLookup` map (id → label) populated when product list loads, so we can resolve row tags without extra fetches.
- `renderRowWithBulk` is wrapped to also prepend a Product column cell when in all mode (separate from the bulk checkbox prepend). Header gets a synthetic "Product" column too.
- Hide read-only-incompatible UI in all mode:
  - Bulk strip stays hidden (selection isn't tracked across products anyway since loadCurrentTab clears it).
  - Hide the per-row action buttons via CSS class. Easiest: `body.classList.toggle('all-products-mode', currentProductId === 'all')` and add a small CSS rule `body.all-products-mode #tableBody td:last-child { visibility: hidden; }`. Tab-specific create buttons (`+ New Coupon`) also hide.
  - Hide installs total badge in all mode (it's per-product).
  - Hide IP filter (it's per-product cache).
- Plan-name resolution: needs to load plans from every product when in all mode. New `loadAllPlans()` calls `loadPlansForCurrentProduct` for each. Done once on entering all mode.

### `index.php`

- Add `<option value="all">All Products</option>` to the productSelect at init time (prepended to the list before the per-product options).
- Add a `<style>` rule for `.all-products-mode` to hide row-action cells.

## Out of scope

- Cross-product pagination. Each product returns up to one page; we accept that.
- Cross-product totals (e.g. summed installs). The current Total Sites badge hides in all mode.
- Cross-product search. The search box still posts to each product; results are merged.

## Verification

- Pick "All Products" → users tab shows N+M+0 rows total tagged by product.
- Switch to licenses → same: tagged rows.
- Switch back to a specific product → row tags gone, action buttons return.
- Refresh → localStorage remembers "all" and reloads in that mode.
