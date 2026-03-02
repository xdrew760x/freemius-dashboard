# Freemius Dashboard

PHP dashboard for managing your Freemius account — list, search, and delete users, licenses, subscriptions, and installs via the Freemius API.

## Access

Served via Laravel Valet at **http://freemius.test**

## Files

| File | Purpose |
|---|---|
| `index.php` | Dashboard UI (Tailwind CSS, vanilla JS) |
| `api.php` | Backend API proxy to Freemius |
| `config.php` | API credentials and product ID |

## Features

- **Users** — List, search, filter (paid/paying/never paid/beta), view detail drawer with related data
- **Licenses** — List, search, filter (active/cancelled/expired/abandoned), delete license, cancel subscription
- **Subscriptions** — List, filter (active/cancelled), cancel active subscriptions
- **Installs** — List, delete installs
- Pagination (25 per page)
- Confirmation modal before destructive actions
- Dark theme

## API

- Base: `https://api.freemius.com/v1`
- Auth: Bearer token
- Product ID: 21348

## Config

Edit `config.php` to update credentials:

```php
return [
    'api_base'   => 'https://api.freemius.com/v1',
    'product_id' => 21348,
    'bearer'     => 'your_bearer_token',
    'pk'         => 'your_public_key',
    'sk'         => 'your_secret_key',
];
```
