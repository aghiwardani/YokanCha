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

## Admin account

`php artisan db:seed` wires in `AdminUserSeeder`, which creates (or reuses) a single
placeholder admin account:

- Email: `admin@yokancha.local`
- Password: `ChangeMe123!`

Log in at `/admin/login`.

**Change this password after first login (or before deploying to production).** These
are placeholder credentials meant only to get the admin panel usable immediately after
setup.

If you only want the admin seeder (without the test user), run it on its own:

```bash
php artisan db:seed --class=AdminUserSeeder
```
