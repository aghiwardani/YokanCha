# Ingredient-Based Stock per PO Period — Design

## Intent & Why

Today each PO period has a hand-typed bottle quota per menu
(`po_period_products.quota`, e.g. "Maple Matcha Latte: 30"). In reality
the owner opens a batch with a fixed pile of ingredients — e.g. Matcha
Longji 100 g, Matcha Tokyo 100 g, Fresh Milk 2 L, Oatside 2 L, Cashew
Milk 2 L, Gula cair 500 ml — and many menus draw from the same pile.
A typed bottle quota can't express that "Klasik and Maple both use
fresh milk, so selling more Klasik leaves less Maple".

Owner's words: *"inti nya banyak nya pesanan dan stok itu sudah saling
terkoneksi"* — orders and stock must be connected. Every order consumes
ingredients; what's left determines what can still be ordered.

## Goals

- Each period has its own ingredient stock (amount per ingredient).
- Each menu has a recipe **per size** (250 ml and 1 liter have separate
  amounts), entered by the owner in admin.
- The sugar ingredient has a separate amount for "Less sugar" (owner
  confirmed). The recipe is written once per size; only the sugar line
  carries the extra "less sugar" amount.
- Orderable bottles per menu/size/sweetness are computed from remaining
  ingredient stock; the scarcest ingredient decides. A size that can't
  be made shows as habis; a menu with no makeable size shows as habis.
- Placing an order deducts ingredients atomically (shared pool across
  all menus). Cancelling an order returns exactly what it took.
- The cart/checkout check the whole cart together: 3 Klasik + 2 Maple is
  accepted only if total fresh milk covers all 5 bottles.

## Non-Goals

- No purchasing/cost tracking, suppliers, or HPP calculation.
- No waste/spoilage tracking, no unit conversion (g ↔ kg etc.).
- No automatic carry-over of leftovers between periods (see below).

## Anti-Goals

- The owner must never have to type a bottle quota again — if stock
  needs manual bottle numbers to work, the feature failed.
- A recipe edit mid-period must not corrupt stock: cancelling an old
  order must return what that order actually consumed, not what the
  current recipe says.
- Stock must never go negative under concurrent checkouts.

## Decisions

Owner decided:
- Ingredients are entered per period.
- Recipe inputs live on each menu, one set per size.
- Less sugar has its own sugar amount.

My calls (owner can override):
- **Ingredient master list is permanent** (`ingredients`: name, unit
  `g` | `ml`, `is_sugar` flag). Created once, reused every period. Only
  the stock amount is per period.
- **Exactly one ingredient may be flagged `is_sugar`.** Its recipe row
  has an extra `amount_less_sugar`. A sweetness option counts as "less
  sugar" when its label is `Less sugar` (case-insensitive); everything
  else uses the normal amount. If a product has no less-sugar amount,
  the normal amount is used.
- **New period starts at zero stock**, with a "Isi dari sisa periode
  lalu" button that prefills the form with the previous period's
  remaining amounts (owner still saves explicitly).
- **Stock can be topped up mid-period** (edit the period's stock
  amount; available bottles rise immediately). Lowering below what's
  already used is rejected.
- **The `max_per_variant` cap (20) stays** as an upper bound per order
  line group, as today. Effective limit = min(ingredient-based, 20).
- **Remove the manual bottle quota** (`quota`, `ordered_qty`,
  `setQuota` route/UI, `QuotaService`). `po_period_products` stays as
  "menu ini ikut batch ini" so the owner can still exclude a menu from a
  batch; the cart keeps keying lines by it.
- **Amounts are decimals** (`decimal(10,2)`), since recipes like 2.5 g
  matcha are normal.
- **Products with no recipe for a size are not orderable in that
  size** (prevents selling something that consumes nothing).

## Data Model

- `ingredients`: id, name, unit (`g`|`ml`), is_sugar (bool), timestamps.
- `product_recipes`: id, product_id, size (string, matches
  `products.size_options`), ingredient_id, amount decimal,
  amount_less_sugar decimal nullable (only meaningful for the sugar
  ingredient). Unique (product_id, size, ingredient_id).
- `po_period_ingredients`: id, po_period_id, ingredient_id, stock
  decimal, used decimal default 0. Unique (po_period_id,
  ingredient_id). Remaining = stock − used.
- `order_item_ingredients`: id, order_item_id, ingredient_id, amount
  decimal — snapshot of what that line consumed (qty × per-bottle
  amount at order time). Cancellation releases these rows, not the
  current recipe.
- `po_period_products`: drop `quota` and `ordered_qty`.

## Behaviour

**Per-bottle usage** for (product, size, sweetness) = recipe rows for
that product+size; sugar row uses `amount_less_sugar` when sweetness is
less sugar.

**Available bottles** for (period, product, size, sweetness) =
floor(min over recipe ingredients of remaining / per-bottle amount);
0 if any recipe ingredient has no stock row in the period; 0 if no
recipe exists for that size. Capped at `max_per_variant`.

**Menu page** shows sisa per size (the menu card already shows stock;
show the most constrained/"normal" figure or per size — builder's
call, keep the existing card style). Menu fully unavailable → existing
habis treatment.

**Cart add/update** validates the whole cart's combined ingredient need
against remaining stock (plus the existing per-variant cap). Error copy
stays casual "aku/kamu" style, e.g. "Stok bahan buat Maple Matcha Latte
nggak cukup. Kurangi jumlahnya dulu ya."

**Checkout** (replaces `QuotaService::reserve`): in one transaction,
lock all `po_period_ingredients` rows for the period
(`lockForUpdate`, ordered by id to avoid deadlocks), sum the cart's need
per ingredient, reject if any exceeds remaining, else increment `used`
and write `order_item_ingredients` snapshots.

**Cancel** (admin): same locking; decrement `used` by the snapshot
amounts (never below 0). Existing guards (no double cancel, cancelled
can't be revived) stay.

## Admin UI

- **Bahan** page: list/create/edit ingredients (name, unit, gula flag).
- **Period page**: per period, a stock form listing every ingredient
  with stock input, plus read-only "terpakai" and "sisa"; "Isi dari
  sisa periode lalu" button; which menus are included in this batch
  (checkboxes, default all).
- **Product form**: a recipe grid — rows = ingredients, columns = the
  product's sizes; sugar row has an extra less-sugar input per size.
  Blank = not used.
- Products index: remove the quota form.

## Migration of Existing Data

Existing dev/demo data only. Drop quota columns; `MenuDemoSeeder`
seeds the six ingredients above with stock and plausible recipes for
the five menus. Past orders without snapshots are simply not
releasable (cancel releases nothing for them) — acceptable.

## Testing

Feature tests for: availability math (scarcest ingredient wins, shared
ingredient across menus, less-sugar amount), checkout deducts and
snapshots, concurrent-ish oversell rejected, cancel returns snapshot
even after recipe edit, stock lowering below used rejected, menu shows
habis when an ingredient runs out. Replace `QuotaServiceTest`.

## Left to the Builder

Service naming/structure, exact admin layout within the existing admin
style, how per-size stock is displayed on the menu card.
