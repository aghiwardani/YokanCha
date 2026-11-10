# YokanCha PO Ordering App Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Laravel web app so YokanCha customers can browse the matcha menu and place pre-order (PO) batch orders themselves, and the owner can manage PO periods, products/quota, and orders (incl. manually marking payment) from an admin panel.

**Architecture:** Laravel (Blade + Tailwind, no SPA framework) on XAMPP/MySQL. Customers are unauthenticated (name + WhatsApp per order, no accounts); a single admin guard protects `/admin/*`. Stock quota is tracked per `(product, po_period)` pair and decremented at order placement; admins can cancel an order to release its quota.

**Tech Stack:** PHP 8.2+, Laravel 11, MySQL (via XAMPP), Blade, Tailwind CSS (Vite), Laravel's built-in session auth for the single admin guard.

**Spec:** `docs/superpowers/specs/2026-11-10-po-ordering-app-design.md`

## Global Constraints

- Runs on XAMPP at `C:\xampp\htdocs\YokanCha`; must work with Apache + MySQL as shipped with XAMPP.
- Brand tokens (Tailwind theme values), from the spec: `bg-primary #F8F6F0`, `bg-surface #FFFFFF`, `color-brand #4A6B4F`, `color-cta #C2783B`, `text-primary #222521`, `border-radius 16px`.
- No payment gateway, no customer accounts, no cashier/walk-in flow, no multi-category navigation — v1 only.
- Customer identification is name + WhatsApp number, no login.
- Quota is decremented at order placement, not at payment confirmation; released only via admin cancellation (no auto-expiry).
- Order statuses: `new`, `diproses`, `siap diambil`, `selesai`, `cancelled`. Payment status: `unpaid`, `paid`.

## Review Focus

- **Ordering when no PO period is open** — spec implies ordering only happens "during an open PO period"; landing page must show a clear closed/no-active-period state and block checkout, not a confusing empty menu.
- **Ordering past a product's remaining quota** — concurrent/last-minute orders must not push remaining quota negative; checkout must re-validate quota at submission time, not just at add-to-cart time.
- **Cancelling an already-cancelled or completed order** — admin cancel action must not double-release quota if clicked twice or on a non-cancellable order.
- **Order status lookup with no match** — customer looking up by order number/WhatsApp with a typo or no orders should get a clear "not found" result, not an error page.
- **Admin routes without login** — every `/admin/*` route must redirect to admin login when unauthenticated, including deep links typed directly.

---

## File Structure

```
app/Models/PoPeriod.php
app/Models/Product.php
app/Models/PoPeriodProduct.php
app/Models/Order.php
app/Models/OrderItem.php
app/Models/Admin.php                         (or use default User model as admin)
app/Http/Controllers/MenuController.php       (customer landing/menu)
app/Http/Controllers/CartController.php       (session cart)
app/Http/Controllers/CheckoutController.php   (checkout + order creation)
app/Http/Controllers/OrderLookupController.php
app/Http/Controllers/Auth/AdminLoginController.php
app/Http/Controllers/Admin/PoPeriodController.php
app/Http/Controllers/Admin/ProductController.php
app/Http/Controllers/Admin/OrderController.php
app/Services/CartService.php                  (session cart read/write helpers)
app/Services/QuotaService.php                 (remaining-quota + reserve/release logic)
database/migrations/*_create_po_periods_table.php
database/migrations/*_create_products_table.php
database/migrations/*_create_po_period_products_table.php
database/migrations/*_create_orders_table.php
database/migrations/*_create_order_items_table.php
resources/views/layouts/app.blade.php          (customer layout, brand tokens)
resources/views/layouts/admin.blade.php
resources/views/menu/index.blade.php
resources/views/cart/show.blade.php
resources/views/checkout/show.blade.php
resources/views/checkout/confirmation.blade.php
resources/views/orders/lookup.blade.php
resources/views/auth/admin-login.blade.php
resources/views/admin/periods/index.blade.php
resources/views/admin/products/index.blade.php
resources/views/admin/products/form.blade.php
resources/views/admin/orders/index.blade.php
resources/views/admin/orders/show.blade.php
resources/css/app.css                          (Tailwind entry + theme tokens)
routes/web.php
tests/Feature/MenuTest.php
tests/Feature/CartTest.php
tests/Feature/CheckoutTest.php
tests/Feature/OrderLookupTest.php
tests/Feature/Admin/PeriodManagementTest.php
tests/Feature/Admin/ProductManagementTest.php
tests/Feature/Admin/OrderManagementTest.php
tests/Unit/QuotaServiceTest.php
```

---

### Task 1: Laravel project scaffold + Tailwind brand theme

**Files:**
- Create: new Laravel project at `C:\xampp\htdocs\YokanCha` (via `laravel new` or `composer create-project`)
- Modify: `resources/css/app.css`, `tailwind.config.js`
- Create: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/SmokeTest.php`

**Interfaces:**
- Produces: `layouts.app` Blade layout with brand CSS variables (`--bg-primary`, `--bg-surface`, `--color-brand`, `--color-cta`, `--text-primary`, `--radius`) available to all customer views; Tailwind config exposing `brand`, `cta`, `oat`, `charcoal` color tokens and `rounded-brand` (16px) utility.

- [ ] **Step 1: Scaffold a fresh Laravel 11 project in-place**, with MySQL configured in `.env` to match XAMPP defaults (host `127.0.0.1`, db `yokancha`, user `root`, empty password), and Vite + Tailwind installed.
- [ ] **Step 2: Create the `yokancha` database** in MySQL (via XAMPP phpMyAdmin or `mysql` CLI) and confirm `php artisan migrate` runs against it with no errors (default Laravel migrations only, at this point).
- [ ] **Step 3: Add brand tokens to `tailwind.config.js`** theme.extend.colors (`brand: '#4A6B4F'`, `cta: '#C2783B'`, `oat: '#F8F6F0'`, `surface: '#FFFFFF'`, `charcoal: '#222521'`) and `borderRadius.brand = '16px'`.
- [ ] **Step 4: Build `resources/views/layouts/app.blade.php`** with `<html>`/`<head>` boilerplate, Vite directives, `bg-oat text-charcoal` body classes, and a `@yield('content')`/slot for page content.
- [ ] **Step 5: Write a smoke test** `tests/Feature/SmokeTest.php::test_homepage_returns_ok` hitting `GET /` (a placeholder route returning a view that extends `layouts.app`) and asserting 200.
- [ ] **Step 6: Run `php artisan test --filter SmokeTest`** — Expected: PASS.
- [ ] **Step 7: Commit.**

```bash
git add -A
git commit -m "feat: scaffold Laravel project with brand Tailwind theme"
```

---

### Task 2: Core data model — migrations and Eloquent models

**Files:**
- Create: `database/migrations/*_create_po_periods_table.php`
- Create: `database/migrations/*_create_products_table.php`
- Create: `database/migrations/*_create_po_period_products_table.php`
- Create: `database/migrations/*_create_orders_table.php`
- Create: `database/migrations/*_create_order_items_table.php`
- Create: `app/Models/PoPeriod.php`, `app/Models/Product.php`, `app/Models/PoPeriodProduct.php`, `app/Models/Order.php`, `app/Models/OrderItem.php`
- Test: `tests/Unit/ModelRelationsTest.php`

**Interfaces:**
- Produces:
  - `PoPeriod`: columns `id, name, opens_at (datetime), closes_at (datetime), status (enum: draft|open|closed)`. Method `isOpen(): bool` (true when `status === 'open'` and now between opens_at/closes_at, if both set).
  - `Product`: columns `id, category (string, default 'minuman'), name, description, photo_path (nullable string), base_price (integer, rupiah), size_options (json array of strings), sweetness_options (json array of strings)`.
  - `PoPeriodProduct`: columns `id, po_period_id, product_id, quota (int), ordered_qty (int, default 0)`. Method `remaining(): int` = `quota - ordered_qty`.
  - `Order`: columns `id, po_period_id, customer_name, customer_whatsapp, payment_status (enum: unpaid|paid, default unpaid), order_status (enum: new|diproses|siap_diambil|selesai|cancelled, default new), order_number (unique string)`.
  - `OrderItem`: columns `id, order_id, product_id, size, sweetness, qty, unit_price`.
  - Relations: `PoPeriod hasMany PoPeriodProduct, Order`; `Product hasMany PoPeriodProduct`; `Order hasMany OrderItem`; `OrderItem belongsTo Product, Order`.

- [ ] **Step 1: Write migrations** for all 5 tables per the columns above, with foreign keys (`po_period_id`, `product_id`, `order_id`) and `order_number` unique index.
- [ ] **Step 2: Write the 5 Eloquent models** with `$fillable`, `$casts` (json columns, enums as string casts), and the relations listed above.
- [ ] **Step 3: Write `PoPeriodProduct::remaining()`** and `PoPeriod::isOpen()` per the signatures above.
- [ ] **Step 4: Write `tests/Unit/ModelRelationsTest.php`** covering: `test_po_period_product_remaining_is_quota_minus_ordered`, `test_po_period_is_open_only_within_window_and_status_open`, `test_order_has_many_order_items`.
- [ ] **Step 5: Run `php artisan test --filter ModelRelationsTest`** — Expected: FAIL (classes/methods don't exist yet) before implementation, then PASS after Steps 1–3.
- [ ] **Step 6: Run migrations** (`php artisan migrate:fresh`) and re-run the test suite — Expected: PASS.
- [ ] **Step 7: Commit.**

```bash
git add -A
git commit -m "feat: add PO period, product, and order data model"
```

---

### Task 3: QuotaService — reserve and release stock

**Files:**
- Create: `app/Services/QuotaService.php`
- Test: `tests/Unit/QuotaServiceTest.php`

**Interfaces:**
- Consumes: `PoPeriodProduct::remaining()` (Task 2).
- Produces:
  - `QuotaService::reserve(PoPeriodProduct $ppp, int $qty): bool` — atomically increments `ordered_qty` by `qty` only if `remaining() >= qty`; returns `true` on success, `false` if insufficient (no partial reservation). Use a DB transaction + row lock (`lockForUpdate`) to avoid race conditions.
  - `QuotaService::release(PoPeriodProduct $ppp, int $qty): void` — decrements `ordered_qty` by `qty` (floored at 0).

- [ ] **Step 1: Write `tests/Unit/QuotaServiceTest.php::test_reserve_succeeds_and_decrements_remaining`** — given a `PoPeriodProduct` with quota 10, ordered_qty 0, `reserve($ppp, 3)` returns true and `remaining()` becomes 7.
- [ ] **Step 2: Write `test_reserve_fails_when_insufficient_remaining`** — quota 2, ordered_qty 0, `reserve($ppp, 3)` returns false and `ordered_qty` is unchanged.
- [ ] **Step 3: Write `test_release_decrements_ordered_qty_not_below_zero`** — ordered_qty 2, `release($ppp, 5)` leaves `ordered_qty` at 0, not negative.
- [ ] **Step 4: Run tests** — Expected: FAIL (`QuotaService` doesn't exist).
- [ ] **Step 5: Implement `QuotaService`** per the signatures above.
- [ ] **Step 6: Run tests** — Expected: PASS.
- [ ] **Step 7: Commit.**

```bash
git add -A
git commit -m "feat: add QuotaService for stock reservation"
```

---

### Task 4: Customer menu page (open PO period + products)

**Files:**
- Create: `app/Http/Controllers/MenuController.php`
- Create: `resources/views/menu/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/MenuTest.php`

**Interfaces:**
- Consumes: `PoPeriod::isOpen()`, `PoPeriodProduct::remaining()` (Task 2).
- Produces: `GET /` → `MenuController::index()`, renders `menu.index` with `$period` (nullable `PoPeriod`) and `$items` (collection of `PoPeriodProduct` with `product` eager-loaded, only for the current open period, each exposing `remaining()`).

- [ ] **Step 1: Write `tests/Feature/MenuTest.php::test_shows_products_for_open_period_with_remaining_quota`** — seed an open `PoPeriod` with a `PoPeriodProduct` (quota 5, ordered_qty 2), hit `GET /`, assert 200 and the product name + "3" (remaining) appear in the response body.
- [ ] **Step 2: Write `test_shows_closed_state_when_no_open_period`** — no open period exists, hit `GET /`, assert 200 and a "no open PO" message appears (e.g. "Belum ada periode PO yang dibuka").
- [ ] **Step 3: Run tests** — Expected: FAIL (route/controller/view don't exist).
- [ ] **Step 4: Implement `MenuController::index()`** querying the currently open `PoPeriod` (status `open` and within window) and its `PoPeriodProduct`s with `product` eager-loaded.
- [ ] **Step 5: Build `resources/views/menu/index.blade.php`** extending `layouts.app`, matching the Figma Desktop 1440 "Menu" section structure (card per product: photo/name/description/size chips/sweetness chips/price/add-to-cart button posting to the cart route from Task 5), plus the no-open-period empty state.
- [ ] **Step 6: Run tests** — Expected: PASS.
- [ ] **Step 7: Commit.**

```bash
git add -A
git commit -m "feat: add customer menu page for open PO period"
```

---

### Task 5: Session cart

**Files:**
- Create: `app/Services/CartService.php`
- Create: `app/Http/Controllers/CartController.php`
- Create: `resources/views/cart/show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CartTest.php`

**Interfaces:**
- Consumes: `Product`, `PoPeriodProduct::remaining()` (Task 2).
- Produces:
  - `CartService::add(int $poPeriodProductId, int $qty, string $size, string $sweetness): void` — stores line items in `session('cart')` as an array keyed by a composite line key (`poPeriodProductId|size|sweetness`), merging quantity if the same combination is added again.
  - `CartService::items(): array` — returns cart lines with resolved `Product`/`PoPeriodProduct` and `line_total`.
  - `CartService::remove(string $lineKey): void`, `CartService::clear(): void`.
  - Routes: `POST /cart` (add), `GET /cart` (show), `DELETE /cart/{lineKey}` (remove).

- [ ] **Step 1: Write `tests/Feature/CartTest.php::test_add_to_cart_then_view_shows_item_and_subtotal`** — `POST /cart` with a valid `po_period_product_id`, `qty`, `size`, `sweetness`, then `GET /cart` asserts the product name, quantity, and subtotal appear.
- [ ] **Step 2: Write `test_adding_same_variant_twice_merges_quantity`** — two `POST /cart` calls with the same product/size/sweetness, assert the cart has one line with summed quantity.
- [ ] **Step 3: Write `test_remove_line_from_cart`** — add then `DELETE /cart/{lineKey}`, assert `GET /cart` no longer shows it.
- [ ] **Step 4: Run tests** — Expected: FAIL.
- [ ] **Step 5: Implement `CartService`** per the signatures above, backed by `session()`.
- [ ] **Step 6: Implement `CartController`** (`add`, `show`, `remove` actions) and routes.
- [ ] **Step 7: Build `resources/views/cart/show.blade.php`** (brand-styled, not from Figma — per spec) listing lines, quantity, remove button, subtotal, and a "Checkout" CTA linking to Task 6's checkout route.
- [ ] **Step 8: Run tests** — Expected: PASS.
- [ ] **Step 9: Commit.**

```bash
git add -A
git commit -m "feat: add session-based cart"
```

---

### Task 6: Checkout + order creation

**Files:**
- Create: `app/Http/Controllers/CheckoutController.php`
- Create: `resources/views/checkout/show.blade.php`, `resources/views/checkout/confirmation.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CheckoutTest.php`

**Interfaces:**
- Consumes: `CartService::items()`/`clear()` (Task 5), `QuotaService::reserve()` (Task 3).
- Produces: `GET /checkout` (form: name, WhatsApp number), `POST /checkout` → creates `Order` + `OrderItem`s, calls `QuotaService::reserve()` per line inside a DB transaction, generates a unique `order_number` (e.g. `YC-{period_id}-{6-char random}`), clears the cart, redirects to `GET /checkout/confirmation/{order_number}`.

- [ ] **Step 1: Write `tests/Feature/CheckoutTest.php::test_submits_order_with_sufficient_quota`** — seed cart session with a line whose `PoPeriodProduct` has quota 5, `POST /checkout` with name + WhatsApp, assert redirect to confirmation, an `Order` row exists with those `OrderItem`s, and `ordered_qty` increased accordingly.
- [ ] **Step 2: Write `test_rejects_checkout_when_quota_insufficient_at_submit_time`** — seed cart with qty exceeding remaining quota (simulating a race since add-to-cart), `POST /checkout`, assert the order is NOT created, no quota change, and the response shows an error telling the customer which item is out of stock.
- [ ] **Step 3: Write `test_requires_name_and_whatsapp_number`** — `POST /checkout` missing `customer_whatsapp`, assert validation error, no order created.
- [ ] **Step 4: Run tests** — Expected: FAIL.
- [ ] **Step 5: Implement `CheckoutController::show()`** rendering the cart summary + name/WhatsApp form (view not from Figma, brand-styled per spec).
- [ ] **Step 6: Implement `CheckoutController::store()`**: validate input, wrap in `DB::transaction`, call `QuotaService::reserve()` per cart line (roll back and return a validation error naming the out-of-stock item if any `reserve()` call returns false), create `Order`/`OrderItem`s, clear cart, redirect.
- [ ] **Step 7: Build `resources/views/checkout/confirmation.blade.php`** showing order number, items, total, and static manual-payment instructions (bank/e-wallet text — placeholder content, owner fills in real account details later).
- [ ] **Step 8: Run tests** — Expected: PASS.
- [ ] **Step 9: Commit.**

```bash
git add -A
git commit -m "feat: add checkout flow with quota-safe order creation"
```

---

### Task 7: Order status lookup

**Files:**
- Create: `app/Http/Controllers/OrderLookupController.php`
- Create: `resources/views/orders/lookup.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OrderLookupTest.php`

**Interfaces:**
- Consumes: `Order` (Task 2).
- Produces: `GET /orders/lookup` (form), `POST /orders/lookup` with `order_number` + `customer_whatsapp` → shows the matching order's items, `payment_status`, `order_status`, or a "not found" message.

- [ ] **Step 1: Write `tests/Feature/OrderLookupTest.php::test_finds_order_by_number_and_whatsapp`** — seed an order, `POST /orders/lookup` with matching values, assert the response shows its status.
- [ ] **Step 2: Write `test_shows_not_found_for_no_match`** — `POST /orders/lookup` with a non-existent order number, assert a friendly "not found" message (200, not 404/500).
- [ ] **Step 3: Run tests** — Expected: FAIL.
- [ ] **Step 4: Implement `OrderLookupController`** and view.
- [ ] **Step 5: Run tests** — Expected: PASS.
- [ ] **Step 6: Commit.**

```bash
git add -A
git commit -m "feat: add order status lookup by order number and WhatsApp"
```

---

### Task 8: Admin authentication

**Files:**
- Create: `app/Http/Controllers/Auth/AdminLoginController.php`
- Create: `resources/views/auth/admin-login.blade.php`
- Modify: `routes/web.php`, `config/auth.php` (admin guard if separate from default `users` table is not needed — reuse default `users` table/guard for the single owner account)
- Test: `tests/Feature/Admin/AdminAuthTest.php`

**Interfaces:**
- Produces: `GET /admin/login`, `POST /admin/login`, `POST /admin/logout`; an `admin` middleware alias (route group `->middleware('auth')` is sufficient since only admin users exist) protecting all `/admin/*` routes defined in Tasks 9–11.

- [ ] **Step 1: Write `tests/Feature/Admin/AdminAuthTest.php::test_guest_redirected_from_admin_dashboard`** — `GET /admin/periods` without auth, assert redirect to `/admin/login`.
- [ ] **Step 2: Write `test_login_with_valid_credentials_grants_access`** — seed a `User`, `POST /admin/login` with correct credentials, then `GET /admin/periods` returns 200.
- [ ] **Step 3: Run tests** — Expected: FAIL.
- [ ] **Step 4: Implement `AdminLoginController`** using Laravel's built-in `Auth::attempt()` against the default `users` table, and a route group in `routes/web.php` wrapping all `/admin/*` routes (periods, products, orders — Tasks 9–11) in `middleware(['auth'])`.
- [ ] **Step 5: Run tests** — Expected: PASS.
- [ ] **Step 6: Seed the owner's admin user** via a `database/seeders/AdminUserSeeder.php` (placeholder email/password, documented in the seeder's comment for the owner to change).
- [ ] **Step 7: Commit.**

```bash
git add -A
git commit -m "feat: add admin login guarding /admin routes"
```

---

### Task 9: Admin — PO period management

**Files:**
- Create: `app/Http/Controllers/Admin/PoPeriodController.php`
- Create: `resources/views/admin/periods/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/PeriodManagementTest.php`

**Interfaces:**
- Consumes: `PoPeriod` (Task 2), admin `auth` middleware (Task 8).
- Produces: `GET /admin/periods`, `POST /admin/periods` (create, status `draft`), `POST /admin/periods/{period}/open`, `POST /admin/periods/{period}/close`.

- [ ] **Step 1: Write `tests/Feature/Admin/PeriodManagementTest.php::test_admin_can_create_period`** — authenticated admin `POST /admin/periods` with name/opens_at/closes_at, assert a `PoPeriod` row exists with status `draft`.
- [ ] **Step 2: Write `test_admin_can_open_and_close_period`** — `POST /admin/periods/{id}/open` sets status `open`; `POST /admin/periods/{id}/close` sets status `closed`.
- [ ] **Step 3: Run tests** — Expected: FAIL.
- [ ] **Step 4: Implement `PoPeriodController`** (`index`, `store`, `open`, `close`) and routes under the `/admin` auth group.
- [ ] **Step 5: Build `resources/views/admin/periods/index.blade.php`** listing periods with status and open/close buttons, and a create form.
- [ ] **Step 6: Run tests** — Expected: PASS.
- [ ] **Step 7: Commit.**

```bash
git add -A
git commit -m "feat: add admin PO period management"
```

---

### Task 10: Admin — product management + per-period quota

**Files:**
- Create: `app/Http/Controllers/Admin/ProductController.php`
- Create: `resources/views/admin/products/index.blade.php`, `resources/views/admin/products/form.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/ProductManagementTest.php`

**Interfaces:**
- Consumes: `Product`, `PoPeriodProduct` (Task 2), admin `auth` middleware (Task 8).
- Produces: `GET /admin/products`, `GET /admin/products/create`, `POST /admin/products`, `GET /admin/products/{product}/edit`, `PUT /admin/products/{product}`, and `POST /admin/products/{product}/quota` (upserts a `PoPeriodProduct` for a given `po_period_id` + `quota`).

- [ ] **Step 1: Write `tests/Feature/Admin/ProductManagementTest.php::test_admin_can_create_product`** — `POST /admin/products` with name/description/base_price/size_options/sweetness_options, assert a `Product` row exists.
- [ ] **Step 2: Write `test_admin_can_set_quota_for_period`** — given an existing `Product` and `PoPeriod`, `POST /admin/products/{id}/quota` with `po_period_id` + `quota`, assert a `PoPeriodProduct` row exists with that quota.
- [ ] **Step 3: Write `test_admin_can_edit_product`** — `PUT /admin/products/{id}` changes name, assert persisted.
- [ ] **Step 4: Run tests** — Expected: FAIL.
- [ ] **Step 5: Implement `ProductController`** (`index`, `create`, `store`, `edit`, `update`, `setQuota`) and routes.
- [ ] **Step 6: Build the two admin product views** (list with edit links + quota-per-period inline form; create/edit form with size/sweetness as repeatable text inputs).
- [ ] **Step 7: Run tests** — Expected: PASS.
- [ ] **Step 8: Commit.**

```bash
git add -A
git commit -m "feat: add admin product and per-period quota management"
```

---

### Task 11: Admin — order management (payment status, order status, cancel)

**Files:**
- Create: `app/Http/Controllers/Admin/OrderController.php`
- Create: `resources/views/admin/orders/index.blade.php`, `resources/views/admin/orders/show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/OrderManagementTest.php`

**Interfaces:**
- Consumes: `Order`, `OrderItem` (Task 2), `QuotaService::release()` (Task 3), admin `auth` middleware (Task 8).
- Produces: `GET /admin/orders`, `GET /admin/orders/{order}`, `POST /admin/orders/{order}/mark-paid`, `POST /admin/orders/{order}/status` (body: `order_status`), `POST /admin/orders/{order}/cancel` (sets `order_status = cancelled`, calls `QuotaService::release()` for each item's `PoPeriodProduct`, no-op if already `cancelled`).

- [ ] **Step 1: Write `tests/Feature/Admin/OrderManagementTest.php::test_admin_can_mark_order_paid`** — `POST /admin/orders/{id}/mark-paid`, assert `payment_status` becomes `paid`.
- [ ] **Step 2: Write `test_admin_can_update_order_status`** — `POST /admin/orders/{id}/status` with `order_status=diproses`, assert persisted.
- [ ] **Step 3: Write `test_cancel_releases_quota`** — order with reserved quota (ordered_qty reflecting it), `POST /admin/orders/{id}/cancel`, assert `order_status` is `cancelled` and the related `PoPeriodProduct.ordered_qty` decreased by the order's quantity.
- [ ] **Step 4: Write `test_cancelling_already_cancelled_order_does_not_double_release_quota`** — cancel twice, assert `ordered_qty` only decreased once (second call is a no-op, e.g. returns early or a validation message).
- [ ] **Step 5: Run tests** — Expected: FAIL.
- [ ] **Step 6: Implement `OrderController`** (`index`, `show`, `markPaid`, `updateStatus`, `cancel`) with the cancel guard from Step 4.
- [ ] **Step 7: Build admin order list/detail views** (filter by period, show payment/status badges and action buttons).
- [ ] **Step 8: Run tests** — Expected: PASS.
- [ ] **Step 9: Commit.**

```bash
git add -A
git commit -m "feat: add admin order management with quota-safe cancellation"
```

---

## Self-Review Notes

- **Spec coverage:** all 9 screens (menu, cart, checkout, confirmation, order lookup, admin login, periods, products, orders) have an owning task; manual payment instructions covered in Task 6; quota-per-period covered in Tasks 2/3/10; cancellation/quota release covered in Task 11.
- **Review Focus coverage:** closed-period state → Task 4 Step 2; quota race at checkout → Task 6 Step 2 (and `QuotaService.reserve` row-locking in Task 3); double-cancel → Task 11 Step 4; order lookup not-found → Task 7 Step 2; admin route guarding → Task 8 Step 1.
- Proof-of-payment upload and WhatsApp auto-notifications are explicitly out of scope per the spec and not included here; can be added as a follow-up plan if the owner wants them later.
