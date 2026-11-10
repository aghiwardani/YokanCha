# YokanCha PO Ordering App — Design

## Intent & Why

YokanCha is a bottled artisan matcha brand (brand kit: Moku/Kyoto Modern
Warmth palette, "Yoka" mascot, tagline "Slow Down, Sip Quietly"). The
owner currently sells via manual pre-order (PO) rounds and wants a web
app so customers can browse the menu and place PO orders themselves,
instead of taking orders manually (e.g. over chat).

This is v1: customer-facing ordering only. No cashier/walk-in flow, no
payment gateway, no customer accounts — those are explicitly deferred.

## Goals

- Customer can browse the product catalog (matching the existing Figma
  landing page) and place an order during an open PO period.
- Owner (admin) can open/close PO periods on their own schedule, each
  with its own product list and per-product stock quota.
- Order payment is manual: customer transfers and marks/uploads proof;
  owner reviews orders and manually marks each as paid.
- Works well as a responsive site from both desktop and mobile browsers.

## Non-Goals (v1)

- No cashier / walk-in POS flow (explicitly "not yet" per owner).
- No payment gateway integration (manual transfer only).
- No customer accounts/login — identified by name + WhatsApp number only.
- No native mobile app — browser-only, responsive web.
- No multi-category product catalog yet (field exists, only one category
  used today; more categories are a future addition, not built out now).

## Anti-Goals

- Don't build a generic multi-tenant e-commerce platform — this is a
  single-brand PO ordering tool, keep it that simple.
- Don't block ordering behind account creation — the whole point of
  "name + WhatsApp only" is to keep checkout friction-free.

## Constraints

- Must run on XAMPP (Apache + MySQL) at `C:\xampp\htdocs\YokanCha`.
- Visual design must follow the existing Figma file "YokanCha Matcha –
  Web Pemesanan" (fileKey `Lq7F631fWludCz7LavzFby`) for already-designed
  screens (landing/menu page), and follow the same brand system (Moku
  Kyoto Modern Warmth palette, tokens below) for screens not yet
  designed in Figma (cart, checkout, order confirmation, order status
  lookup, admin panel).
- Brand tokens (from brand guide PDFs, already CSS-ready):
  - `--bg-primary: #F8F6F0` (Warm Oat Milk, 60%, main background)
  - `--bg-surface: #FFFFFF` (Pure Cream, cards/modals)
  - `--color-brand: #4A6B4F` (Matcha Sage, 30%, header/nav/brand elements)
  - `--color-cta: #C2783B` (Roasted Maple Amber, 10%, CTA buttons/accents)
  - `--text-primary: #222521` (Deep Roast Charcoal, typography/borders)
  - `--border-radius: 16px`
  - Hero headline convention: "Slow Down, Sip Quietly"; mascot is "Yoka".

## Decisions Already Made (by owner, in conversation)

- **Stack**: Laravel (PHP) + MySQL, Blade + Tailwind CSS, running on
  XAMPP. Chosen because the project already lives under
  `C:\xampp\htdocs`, and this avoids a separate build pipeline while
  still giving a modern, responsive UI.
- **Platform**: responsive web only, accessed via browser on both
  desktop and mobile. No native app.
- **Ordering model**: Pre-order (PO) by period/batch. Owner defines when
  a PO period opens and closes, on their own schedule (no fixed
  recurrence).
- **Stock**: each product has a limited quota *per PO period* (e.g.
  "this batch: 50 bottles of Maple Matcha Latte"). Once a product's
  quota for the current period is exhausted, it can no longer be
  ordered in that period.
- **Who orders**: customers only, self-service. No cashier/walk-in
  entry in v1 (owner: "belum ada, buatkan pemesanan oleh pelanggan dulu
  saja").
- **Customer identification**: name + WhatsApp number per order, no
  account/login system.
- **Payment**: manual transfer. Customer sees payment instructions
  (bank/e-wallet info) at checkout; owner reviews orders in the admin
  panel and manually marks each order's payment status (paid /
  unpaid) — owner: "nanti dari aku yang ceklis apa pesanan ini sudah
  bayar atau belum".
- **Categories**: a category field exists on products for future use,
  but only one category is active today (drinks). Not building
  category-based navigation/filtering yet.
- **Unpaid order / stock release**: quota is decremented at order
  placement (not at payment confirmation), to prevent overselling.
  There is no automatic expiry — if an order goes unpaid, the owner
  manually cancels it from the admin panel, which releases its quota
  back to the period. Orders therefore need a `cancelled` status in
  addition to the new → diproses → siap diambil → selesai flow.

## What Exists in Figma Today

File: "YokanCha Matcha – Web Pemesanan" — 3 pages: `Desktop 1440`,
`Mobile 390`, `Tokens & Komponen`.

The `Desktop 1440` page contains one full landing page design:
Header (brand + nav + cart button) → Hero (headline, CTA buttons, Yoka
mascot) → product ribbon → **Menu** section (product cards showing
photo, name, description, size options as chips, sweetness options as
chips, price, "add" button) → "Arti YokanCha" (brand story) → "Cara
Pesan" (how-to-order steps) → care/storage info → FAQ → Footer.

This covers the **landing/catalog browsing** experience. It does **not**
cover: cart contents, checkout form, order confirmation, order status
lookup, or any admin screen. `Mobile 390` presumably mirrors this
responsively; `Tokens & Komponen` holds shared components/styles to
reuse (buttons, chips, tags) when building the screens that don't exist
yet.

## Screens to Build

### Customer-facing
1. **Landing / Menu** — per Figma Desktop 1440 / Mobile 390. Shows
   current open PO period (or a "no open PO right now" state), product
   list with live remaining quota, size/sweetness selection, add to
   cart.
2. **Cart** — not in Figma; design to match brand tokens. List of
   selected items, quantity edit, remove, subtotal, proceed to
   checkout.
3. **Checkout** — not in Figma. Form for name + WhatsApp number,
   order summary, manual payment instructions (bank/e-wallet details),
   submit order.
4. **Order confirmation** — not in Figma. Shows order number/summary
   and payment instructions again after submit.
5. **Order status lookup** — not in Figma. Since there's no login,
   customer needs a way to check their order later — by order
   number and/or WhatsApp number — showing payment status and order
   status.

### Admin (owner only, simple authenticated panel)
6. **Login** — single admin user (or small fixed set), simple
   Laravel auth.
7. **PO periods** — create/open/close a period, set its date range.
8. **Products** — create/edit products (name, description, photo,
   size/sweetness options, price, category field), and set each
   product's stock quota for a given PO period.
9. **Orders list** — view incoming orders for a period, order detail
   (items, customer name/WhatsApp), toggle payment status
   (paid/unpaid), update order status (e.g. new → diproses → siap
   diambil → selesai).

## Data Model (sketch, left to builder to refine during planning)

- `po_periods`: id, name/label, opens_at, closes_at, status.
- `products`: id, category (free text/enum for now), name, description,
  photo, base price, size options, sweetness options.
- `po_period_products`: links product to a period with a `quota` and
  derived `remaining` (quota − ordered quantity in that period).
- `orders`: id, po_period_id, customer_name, customer_whatsapp,
  payment_status (unpaid/paid), order_status, created_at.
- `order_items`: order_id, product_id, size, sweetness, qty, unit_price.

## What's Left to the Builder

- Exact DB schema/migrations, Eloquent models, and validation rules.
- Routing structure and controller organization.
- How "remaining quota" is computed/locked under concurrent checkouts
  (basic reservation logic is fine for v1 scale).
- Exact Tailwind setup matching the brand tokens above.
- Detail layout of the 5 non-Figma customer screens and 4 admin
  screens, staying within the brand's visual language (rounded 16px
  corners, Matcha Sage headers, Maple Amber CTA buttons, Warm Oat
  background, Charcoal text).
- Admin auth mechanism specifics (Laravel Breeze/Fortify vs. hand-rolled
  — builder's call, keep it simple since it's a single owner user).
- Whether proof-of-payment upload is included now or deferred (owner
  only confirmed manual verification by the owner checking off paid
  status — an upload field is a reasonable small addition but not
  explicitly requested; builder may include a simple optional upload
  field as part of checkout, but it is not required for the owner's
  manual-check workflow to function).
