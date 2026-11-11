# YokanCha PO Ordering App

A spot-style pre-order (PO) ordering app for YokanCha drinks: customers browse an open
PO batch, add items to cart, and check out; admins manage PO periods, products/quota,
and orders.

## Requirements

- PHP 8.4+
- Composer
- Node.js + npm

> **Windows/XAMPP note:** this XAMPP install's `PATH` `php` may resolve to an older
> PHP (e.g. 7.4), which is too old to run this app. Use the bundled PHP 8 binary
> explicitly instead, e.g. `C:\xampp\php8\php.exe`, for every `artisan`/`php`/`composer`
> command below (`C:\xampp\php8\php.exe artisan migrate`, etc.).

## Setup

```bash
composer install
npm install && npm run build

cp .env.example .env   # if you don't already have a .env
php artisan key:generate

php artisan migrate
php artisan db:seed     # seeds a test user AND the admin account (see below)

php artisan serve
```

Tests run against an isolated in-memory SQLite database and do not require MySQL.

## Shop settings

Set these in `.env` (defaults in `config/yokancha.php` are placeholders):

| Key | Used for |
|---|---|
| `YOKANCHA_WHATSAPP` | Seller WhatsApp number (e.g. `6281234567890`) for the "Kirim lewat WhatsApp" button after checkout |
| `YOKANCHA_BANK_NAME`, `YOKANCHA_BANK_ACCOUNT`, `YOKANCHA_BANK_HOLDER` | Transfer instructions at checkout and on the confirmation page |
| `YOKANCHA_MAX_PER_VARIANT` | Max bottles of one variant per cart (default 20) |

`APP_TIMEZONE` should stay `Asia/Jakarta` so PO opening/closing times match WIB.

Run `php artisan config:clear` after changing `.env`.

## Demo menu

`php artisan db:seed --class=MenuDemoSeeder` adds the five drinks from the Figma mockup
(with per-size prices) to the currently open PO period, creating one if none is open.

## Admin account

`php artisan db:seed` wires in `AdminUserSeeder`, which creates (or reuses) a single
placeholder admin account:

- Email: `admin@yokancha.local`
- Password: `ChangeMe123!`

Log in at `/admin/login`. Only users with `is_admin = 1` can sign in to the admin panel;
other accounts (like the seeded `test@example.com`) are refused.

**Change this password after first login (or before deploying to production).** These
are placeholder credentials meant only to get the admin panel usable immediately after
setup.

If you only want the admin seeder (without the test user), run it on its own:

```bash
php artisan db:seed --class=AdminUserSeeder
```
