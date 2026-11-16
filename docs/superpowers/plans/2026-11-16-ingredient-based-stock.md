# Ingredient-Based Stock Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace hand-typed bottle quotas with per-period ingredient stock and per-size recipes, so orderable bottles are computed from remaining ingredients and every order consumes (and cancellation returns) ingredients.

**Architecture:** New tables `ingredients`, `product_recipes`, `po_period_ingredients`, `order_item_ingredients`. One `App\Services\StockService` owns all math (per-bottle usage, availability, reserve, release); cart, menu, checkout and admin cancel call it. `QuotaService` and the `quota`/`ordered_qty` columns are removed last, once nothing uses them.

**Tech Stack:** Laravel (PHP 8.4), Blade + Tailwind, PHPUnit feature tests on SQLite in-memory.

**Spec:** `docs/superpowers/specs/2026-11-16-ingredient-based-stock-design.md`

## Global Constraints

- Run PHP via `/c/xampp/php8/php` (the default `php` on PATH is 7.4 and fails). Test command: `/c/xampp/php8/php artisan test`.
- Amount columns are `decimal(10,2)`; units are only `g` or `ml`.
- At most one ingredient has `is_sugar = true`.
- A sweetness is "less sugar" when `mb_strtolower(trim($s)) === 'less sugar'`.
- Products without sizes use size key `'-'`; without sweetness options use `'-'` (matches the menu card / CartController convention).
- `config('yokancha.max_per_variant')` (20) cap stays, unchanged.
- Customer/admin copy is Indonesian, casual "aku/kamu", matching existing messages (uses "tidak", not "nggak").
- Lock `po_period_ingredients` rows with `lockForUpdate()` ordered by `id` inside a `DB::transaction`.

## Review Focus

1. Float error: stock 100 g with 2.5 g per bottle must give exactly 40, not 39 — Task 2 test `test_decimal_amounts_do_not_lose_a_bottle`.
2. Same ingredient across two cart lines of the same product (Manis normal + Less sugar) must be summed — Task 3 test `test_cart_sums_shared_ingredient_across_lines`.
3. A recipe ingredient with no stock row in the period means 0 available, not unlimited — Task 2 test `test_missing_stock_row_means_unavailable`.
4. Recipe edited after an order, then order cancelled: return the snapshot amount — Task 4 test `test_cancel_returns_snapshot_after_recipe_edit`.
5. Product without sizes (`'-'`) must still work end-to-end — Task 2 test `test_product_without_sizes_uses_dash_key`.

---

### Task 1: Schema and models

**Files:**
- Create: `database/migrations/2026_11_16_100000_create_ingredients_table.php`, `..._100001_create_product_recipes_table.php`, `..._100002_create_po_period_ingredients_table.php`, `..._100003_create_order_item_ingredients_table.php`
- Create: `app/Models/Ingredient.php`, `app/Models/ProductRecipe.php`, `app/Models/PoPeriodIngredient.php`, `app/Models/OrderItemIngredient.php`
- Modify: `app/Models/Product.php`, `app/Models/PoPeriod.php`, `app/Models/OrderItem.php` (relations)
- Create: `tests/Concerns/StocksMenu.php` (test helper trait)
- Test: `tests/Unit/ModelRelationsTest.php`

**Interfaces:**
- Produces tables per spec "Data Model". Uniques: `product_recipes(product_id,size,ingredient_id)`, `po_period_ingredients(po_period_id,ingredient_id)`. All FKs `cascadeOnDelete`. `used` default 0. `amount_less_sugar` nullable.
- Models: `Ingredient{name, unit, is_sugar}` (cast bool); `ProductRecipe{product_id,size,ingredient_id,amount,amount_less_sugar}`; `PoPeriodIngredient{po_period_id,ingredient_id,stock,used}` with `remaining(): float` = stock − used; `OrderItemIngredient{order_item_id,ingredient_id,amount}`. Decimal casts `'decimal:2'`.
- Relations: `Product::recipes(): HasMany`, `PoPeriod::ingredientStocks(): HasMany` (PoPeriodIngredient), `OrderItem::ingredientUsages(): HasMany`, each child `belongsTo` its parents and `ingredient()`.
- Trait `Tests\Concerns\StocksMenu`: `ingredient(string $name, string $unit = 'ml', bool $isSugar = false): Ingredient`, `stock(PoPeriod $period, Ingredient $i, float $stock, float $used = 0): PoPeriodIngredient`, `recipe(Product $p, string $size, Ingredient $i, float $amount, ?float $lessSugar = null): ProductRecipe`.

- [ ] **Step 1:** Add test `test_po_period_ingredient_remaining_is_stock_minus_used` (stock 2000, used 450 → `remaining()` `=== 1550.0`) and `test_product_has_recipes_per_size` (two recipe rows for sizes `250 ml` and `1 liter` → `$product->recipes()->where('size','1 liter')->count() === 1`), using the trait.
- [ ] **Step 2:** Run `/c/xampp/php8/php artisan test --filter=ModelRelationsTest` → FAIL (class not found).
- [ ] **Step 3:** Write migrations, models, relations, trait.
- [ ] **Step 4:** Run full suite → all PASS (71 old + new).
- [ ] **Step 5:** Commit `feat: add ingredient, recipe and period stock tables`.

### Task 2: StockService math

**Files:**
- Create: `app/Services/StockService.php`, `app/Exceptions/InsufficientStockException.php`
- Test: `tests/Unit/StockServiceTest.php`

**Interfaces:**
- Consumes: Task 1 models.
- Produces (`App\Services\StockService`):
  - `isLessSugar(string $sweetness): bool`
  - `perBottle(Product $product, string $size, string $sweetness): array<int,float>` — ingredient_id ⇒ amount per bottle; sugar row uses `amount_less_sugar ?? amount` when less sugar. Empty array = no recipe.
  - `remainingFor(int $poPeriodId): array<int,float>` — ingredient_id ⇒ stock − used.
  - `available(PoPeriodProduct $ppp, string $size, string $sweetness, array $alreadyNeeded = []): int` — bottles makeable from remaining minus `$alreadyNeeded` (ingredient_id ⇒ amount). 0 when no recipe or any recipe ingredient lacks a stock row. Not capped by max_per_variant (cart applies the cap).
  - `maxAvailable(PoPeriodProduct $ppp): int` — max of `available()` over the product's sizes × sweetness options (`'-'` when empty).
  - `need(array $lines): array<int,float>` — `$lines` items are `['po_period_product' => PoPeriodProduct, 'size' => string, 'sweetness' => string, 'qty' => int]` (same shape `CartService::items()` returns); sums qty × perBottle.
  - `reserve(int $poPeriodId, array $lines): array<int, array<int,float>>` — must run inside caller's transaction; locks stock rows, throws `InsufficientStockException` (public `string $productName`, the first line whose ingredient is short) if any need > remaining, else increments `used` and returns per-line-index usage (ingredient_id ⇒ qty × perBottle).
  - `release(Order $order): void` — locks the period's stock rows, decrements `used` by the order's `order_item_ingredients` sums, never below 0.
- Rounding: compute on integer hundredths (`(int) round($x * 100)`) to avoid float error; `available` = `intdiv(remainingHundredths, perBottleHundredths)` min over ingredients.

- [ ] **Step 1:** Write tests: `test_scarcest_ingredient_decides` (milk 2000/200 ⇒10, matcha 30/5 ⇒6 → 6); `test_less_sugar_uses_its_own_sugar_amount` (gula 100, normal 20 ⇒5, less 10 ⇒10); `test_less_sugar_falls_back_to_normal_amount_when_blank`; `test_decimal_amounts_do_not_lose_a_bottle` (100 / 2.5 → 40); `test_missing_stock_row_means_unavailable` (→0); `test_no_recipe_for_size_means_unavailable` (→0); `test_already_needed_reduces_available` (milk 2000, already 1000, 200/bottle → 5); `test_product_without_sizes_uses_dash_key`; `test_reserve_increments_used_and_returns_usage`; `test_reserve_throws_and_changes_nothing_when_short` (exception `productName` equals product name, `used` unchanged); `test_release_returns_snapshot_not_below_zero`.
- [ ] **Step 2:** Run `--filter=StockServiceTest` → FAIL.
- [ ] **Step 3:** Implement the service and exception.
- [ ] **Step 4:** Run full suite → PASS.
- [ ] **Step 5:** Commit `feat: add StockService for ingredient-based availability`.

### Task 3: Menu and cart use ingredient stock

**Files:**
- Modify: `app/Http/Controllers/MenuController.php`, `resources/views/menu/_card.blade.php`, `app/Http/Controllers/CartController.php`
- Test: `tests/Feature/MenuTest.php`, `tests/Feature/CartTest.php`

**Interfaces:**
- Consumes: `StockService::available`, `maxAvailable`, `need`.
- MenuController passes `$availability` = `array<ppp_id, array{max:int, sizes: array<string,int>}>` where `sizes[size]` = max over sweetness of `available()`. Card: `$remaining = $availability[$item->id]['max']`; sold out when 0; size radios for sizes with 0 get `disabled` and the first enabled size is pre-checked. Sr-only text keeps "Sisa {n} botol untuk batch ini.".
- CartController `assertVariantLimits(PoPeriodProduct $ppp, string $size, string $sweetness, int $lineQty, int $variantTotal, ?string $exceptLineKey)`: keeps the max_per_variant check on `$variantTotal`; then `$others = $stock->need(cart lines except the line keyed (ppp,size,sweetness) and except $exceptLineKey)`, `$canMake = $stock->available($ppp, $size, $sweetness, $others)`; if `$lineQty > $canMake`: message `"{$name} sudah habis untuk batch ini."` when 0 else `"Stok {$name} ukuran {$size} tinggal cukup buat {$canMake} botol lagi."`. `$lineQty` is the resulting qty of that cart line (existing + added, or the new update qty).
- Add to `CartService`: `lines(): array` returning raw session lines with keys (already available via session; add a public accessor) — builder's call on shape.

- [ ] **Step 1:** Convert existing MenuTest/CartTest setups to stock via the `StocksMenu` trait (keep `quota` in ppp creation for now; it is ignored). Add `test_card_shows_habis_when_an_ingredient_runs_out`, `test_size_without_enough_stock_is_disabled`, `test_cannot_add_more_than_ingredients_allow` (milk 1000/200 ⇒ adding 6 rejected with "tinggal cukup buat 5 botol lagi"), `test_cart_sums_shared_ingredient_across_lines` (same product, Manis normal 3 + Less sugar 3 with milk for 5 → second add rejected), `test_cart_checks_shared_ingredient_across_products`.
- [ ] **Step 2:** Run `--filter="MenuTest|CartTest"` → new tests FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Full suite → PASS.
- [ ] **Step 5:** Commit `feat: menu and cart availability from ingredient stock`.

### Task 4: Checkout reserves, cancel releases

**Files:**
- Modify: `app/Http/Controllers/CheckoutController.php`, `app/Http/Controllers/Admin/OrderController.php`
- Test: `tests/Feature/CheckoutTest.php`, `tests/Feature/Admin/OrderManagementTest.php`

**Interfaces:**
- Consumes: `StockService::reserve`, `release`, `InsufficientStockException`.
- Checkout `store`: inside the existing transaction call `reserve($poPeriodId, $lines)`; on exception throw `ValidationException` key `quota` (kept so the view still shows it) with `"Stok bahan buat {$e->productName} tidak cukup. Kurangi jumlahnya atau hapus dari keranjang."`; after creating each order item, create its `order_item_ingredients` rows from the returned usage for that line index.
- Cancel: replace the per-item `QuotaService::release` loop with `$stock->release($locked)`. Status message: `'Pesanan dibatalkan dan bahan dikembalikan ke stok.'`. Guards unchanged.

- [ ] **Step 1:** Convert existing tests to ingredient setup; assert `used` instead of `ordered_qty`. Add `test_checkout_writes_ingredient_snapshots`, `test_rejects_checkout_when_ingredients_ran_out_after_adding` (stock used raised between add and submit → no order, `used` unchanged), `test_cancel_returns_snapshot_after_recipe_edit` (order 2×200 ml milk, change recipe to 300, cancel → used back to 0), keep double-cancel and completed-order guard tests.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Full suite → PASS.
- [ ] **Step 5:** Commit `feat: checkout consumes and cancel returns ingredients`.

### Task 5: Remove bottle quota

**Files:**
- Create: `database/migrations/2026_11_16_100004_drop_quota_from_po_period_products.php`
- Delete: `app/Services/QuotaService.php`, `tests/Unit/QuotaServiceTest.php`
- Modify: `app/Models/PoPeriodProduct.php` (drop fillable/casts/`remaining()`), `app/Http/Controllers/Admin/ProductController.php` (remove `setQuota`), `routes/web.php` (remove `products.quota`), `resources/views/admin/products/index.blade.php` (remove quota form), `database/seeders/MenuDemoSeeder.php` (stop writing quota), all tests still passing `quota`/`ordered_qty`
- Test: `tests/Feature/Admin/ProductManagementTest.php`, `tests/Unit/ModelRelationsTest.php`

- [ ] **Step 1:** Remove the two quota tests from ProductManagementTest and the `remaining()` test from ModelRelationsTest; add `test_quota_route_is_gone` (POST `/admin/products/{id}/quota` as admin → 404 or 405).
- [ ] **Step 2:** Run → new test FAIL.
- [ ] **Step 3:** Migration drops `quota`, `ordered_qty`; remove code; `grep -rn "quota\|ordered_qty\|QuotaService" app resources routes database/seeders tests` returns only the `quota` validation error key in checkout and its view.
- [ ] **Step 4:** Full suite → PASS.
- [ ] **Step 5:** Commit `refactor: remove manual bottle quota`.

### Task 6: Admin ingredient list

**Files:**
- Create: `app/Http/Controllers/Admin/IngredientController.php`, `resources/views/admin/ingredients/index.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/admin.blade.php` (nav link "Bahan")
- Test: `tests/Feature/Admin/IngredientManagementTest.php`

**Interfaces:**
- Routes (admin group): `GET /admin/ingredients` → `admin.ingredients`; `POST /admin/ingredients` → `admin.ingredients.store`; `PUT /admin/ingredients/{ingredient}` → `admin.ingredients.update`.
- Validation: `name` required, max 255, unique (ignoring self); `unit` `in:g,ml`; `is_sugar` boolean. Saving with `is_sugar` true sets every other ingredient's `is_sugar` false in the same transaction. One page: table of ingredients with inline edit + add form.

- [ ] **Step 1:** Tests: `test_admin_can_create_ingredient`, `test_admin_can_update_ingredient`, `test_unit_must_be_g_or_ml`, `test_only_one_sugar_ingredient`, `test_guest_cannot_access_ingredients` (redirect to login).
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement following the existing admin page style (`admin/periods/index.blade.php`).
- [ ] **Step 4:** Full suite → PASS.
- [ ] **Step 5:** Commit `feat: admin ingredient list`.

### Task 7: Admin period stock and menus

**Files:**
- Modify: `app/Http/Controllers/Admin/PoPeriodController.php`, `routes/web.php`, `resources/views/admin/periods/index.blade.php` (link "Stok bahan" per period)
- Create: `resources/views/admin/periods/stock.blade.php`
- Test: `tests/Feature/Admin/PeriodStockTest.php`, `tests/Feature/Admin/PeriodManagementTest.php`

**Interfaces:**
- `store` now also creates a `po_period_products` row for every product.
- `GET /admin/periods/{period}/stock` → `admin.periods.stock`: one row per ingredient: stock input (`stock[ingredient_id]`), read-only terpakai and sisa with unit; product checkboxes `products[]` (checked = has ppp row). `?prefill=previous` fills inputs with the remaining of the most recent other period (by `created_at`); a link labeled "Isi dari sisa periode lalu" points there. Nothing is saved until submit.
- `PUT /admin/periods/{period}/stock` → `admin.periods.stock.update`: `stock.*` nullable numeric min 0 (blank = no row / delete row only if `used` is 0); reject `stock < used` with error on `stock.{id}`: `"Stok {$name} tidak boleh di bawah yang sudah terpakai ({$used} {$unit})."`; upsert rows; sync ppp rows to `products[]` (create missing, delete unchecked). Flash `'Stok bahan disimpan.'`.

- [ ] **Step 1:** Tests: `test_new_period_includes_all_products`, `test_admin_can_save_stock`, `test_topping_up_mid_period_raises_availability` (via `StockService::available`), `test_cannot_lower_stock_below_used`, `test_prefill_uses_previous_period_remaining` (prev stock 100 used 70 → input value 30 shown, no row written), `test_unchecked_product_leaves_batch`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Full suite → PASS.
- [ ] **Step 5:** Commit `feat: admin period ingredient stock`.

### Task 8: Recipe grid on product form

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductController.php`, `resources/views/admin/products/form.blade.php`
- Test: `tests/Feature/Admin/ProductManagementTest.php`

**Interfaces:**
- Form gets `$ingredients` (all) and `$recipes` (existing rows keyed `[size][ingredient_id]`). Grid: rows = ingredients (name + unit), columns = product sizes (`'-'` column labeled "Satu ukuran" when no sizes); inputs `recipe[size][ingredient_id]`; the sugar ingredient row has an extra input per size `recipe_less_sugar[size]` labeled "Less sugar". Blank = not used.
- On store/update (same transaction as product save): validate `recipe.*.*` and `recipe_less_sugar.*` nullable numeric min 0; delete the product's recipe rows, then insert non-blank, > 0 amounts only for sizes in the saved `size_options` (or `'-'`). New sizes typed in the same submit have no columns yet: the form note says "Simpan dulu, lalu isi resep untuk ukuran baru."

- [ ] **Step 1:** Tests: `test_admin_can_save_recipe_per_size`, `test_less_sugar_amount_saved_on_sugar_row`, `test_blank_amounts_are_not_saved`, `test_recipe_for_removed_size_is_deleted`, `test_edit_form_shows_existing_recipe`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Full suite → PASS.
- [ ] **Step 5:** Commit `feat: per-size recipe inputs on product form`.

### Task 9: Demo seeder

**Files:**
- Modify: `database/seeders/MenuDemoSeeder.php`
- Test: `tests/Feature/SmokeTest.php`

- [ ] **Step 1:** Test `test_menu_demo_seeder_makes_menu_orderable`: run seeder, GET `/` shows "Matcha Latte Klasik" and no card is "Habis".
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Seed ingredients Matcha Longji 100 g, Matcha Tokyo 100 g, Fresh Milk 2000 ml, Oatside 2000 ml, Cashew Milk 2000 ml, Gula cair 500 ml (`is_sugar`) via `updateOrCreate` by name; stock them in the open period; recipes for 250 ml: matcha 4 g (Murni & Maple: Longji, others: Tokyo), milk 200 ml (Oat: Oatside; Murni: none), gula 15 / less sugar 8 (Murni: none); 1 liter = 4× the 250 ml amounts. Re-run safe (don't reset `used`).
- [ ] **Step 4:** Full suite → PASS.
- [ ] **Step 5:** Commit `chore: seed demo ingredients and recipes`.
