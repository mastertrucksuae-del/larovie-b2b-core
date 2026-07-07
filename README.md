# Larovie B2B Wholesale Catalogue

A B2B wholesale product catalogue for `larovie.ae`. Products are imported from
Shopify, curated by an admin, and browsed by wholesale buyers as guests. Buyers
build a **request-for-quote** (RFQ) inquiry cart; the admin prices it, generates a
branded quote (PDF/CSV), and sends it over WhatsApp. No payments, no online ordering.

Built with **Laravel 13**, **Filament v5** (admin), **Livewire 3** (storefront),
**Tailwind v4**, and **MySQL/MariaDB**. Fully bilingual **EN / AR** with RTL.

---

## Requirements

- PHP 8.3+ (developed on 8.4)
- Composer 2
- Node 20+ / npm
- MySQL 8 or MariaDB 10.4+

## Setup

```bash
composer install
npm install
cp .env.example .env          # then fill in the values (see below)
php artisan key:generate

# Create the database, then:
php artisan migrate --seed    # schema + admin user + settings (+ sample data)

npm run build                 # or `npm run dev` while developing
php artisan storage:link      # for uploaded logos
php artisan serve
```

Default admin login (change in production): **admin@larovie.ae** / **password**
Admin panel: `/admin` · Storefront: `/`

### Configuration

Key `.env` values:

| Variable | Purpose |
| --- | --- |
| `DB_*` | MySQL connection (`DB_DATABASE=larovie`) |
| `SHOPIFY_SHOP_DOMAIN` | e.g. `your-store.myshopify.com` |
| `SHOPIFY_ADMIN_TOKEN` | Admin API token (fallback if key/secret set) |
| `SHOPIFY_API_KEY` / `SHOPIFY_API_SECRET` | Auto-refresh a short-lived token via the `client_credentials` grant |
| `SHOPIFY_API_VERSION` | e.g. `2025-10` |
| `QUEUE_CONNECTION` | `database` (the sync runs as a queued job) |

## Shopify sync

Products, variants, images, tags, vendor, type and status are imported via the
**GraphQL Admin API** with cursor pagination and cost-based rate-limit backoff.

- **Admin-owned fields are never overwritten** by a sync: product `is_visible`/`moq`,
  and variant `wholesale_price`/`moq`/`is_visible`.
- Products/variants that disappear from Shopify are **soft-archived** (`is_archived`),
  never deleted — this protects historical inquiry snapshots.

Run a sync:

```bash
php artisan shopify:sync           # synchronous, prints a summary
```

Or from the admin: **Products → Sync from Shopify** (dispatches a queued job — make
sure a worker is running: `php artisan queue:work`).

> **Managed-install token note:** the admin token is short-lived (~24h). With
> `SHOPIFY_API_KEY` + `SHOPIFY_API_SECRET` set, the client mints and caches a fresh
> token automatically (and refreshes on a 401), so no manual rotation is needed.

## The flow

1. **Admin** clicks *Sync from Shopify*, then marks products visible and sets
   wholesale prices / MOQ (Products resource, inline-editable).
2. **Buyer** browses the storefront (single page: live search, sort by
   brand/category/price/stock, infinite scroll), adds variants to an inquiry cart
   (session-based, MOQ-aware), and submits with contact details + WhatsApp flag.
   Line items are **snapshotted** at submission.
3. **Admin** opens the inquiry, fills unit prices (totals compute live, status
   auto-advances to *Prices filled*), then **Generate PDF quote** / **Export CSV**.
4. **Admin** clicks **Send quote via WhatsApp** — opens `wa.me` with a pre-filled
   message containing a **signed, expiring link** to the quote PDF, and sets the
   status to *Quote sent*.

Statuses: `new_inquiry → responding → prices_filled → quote_sent`.

## Quotes

- **PDF** — branded Blade template rendered with dompdf, stored on the `local` disk,
  served via a signed route (`quote.download`). Bilingual; the Arabic font (Cairo)
  is embedded from `storage/fonts/`.
- **CSV** — line items + customer/quote meta, UTF-8 BOM for Excel/Arabic.

> dompdf does not perform Arabic contextual shaping. For pixel-perfect Arabic/branding,
> swap to `spatie/laravel-pdf` (Browsershot) — `App\Services\Quote\QuoteService` is the
> only place to change.

## Bilingual / RTL

- Locale toggle in the header (`/locale/en`, `/locale/ar`), stored in session.
- `lang/en/shop.php` + `lang/ar/shop.php`; `dir="rtl"` and RTL-aware layout for Arabic.
- The buyer's locale is stored on the inquiry and used to render the quote.

## Testing

```bash
php artisan test
```

Feature coverage: inquiry submission + snapshotting, Shopify upsert preserving admin
fields + archival, quote total computation + signed PDF route, storefront
search/sort/infinite-scroll, phone normalization, and admin panel smoke tests.
Tests run on in-memory SQLite; the app runs on MySQL.

## Deploy notes

- Run `php artisan migrate --force` and `npm run build`.
- Run a queue worker (`php artisan queue:work`) for background syncs.
- Schedule `php artisan shopify:sync` if you want periodic imports.
- Set `APP_ENV=production`, `APP_DEBUG=false`, and a strong admin password.

## Project layout

```
app/
  Actions/CreateInquiry.php            # snapshotting RFQ from the cart
  Filament/Resources/Products/…        # curate visibility, MOQ, wholesale price
  Filament/Resources/Inquiries/…       # pipeline, line pricing, quote actions
  Filament/Pages/ManageSettings.php    # branding, quote config, WhatsApp templates
  Filament/Widgets/…                   # dashboard stats + recent inquiries
  Services/Shopify/…                   # GraphQL client (+ token refresh) & sync
  Services/Quote/QuoteService.php      # PDF + CSV generation
  Services/WhatsApp/WhatsAppLink.php   # wa.me link + signed quote URL
  Services/Cart/CartService.php        # session inquiry cart
  Support/{Money,PhoneNumber}.php
resources/views/
  components/⚡catalogue.blade.php       # storefront grid (search/sort/infinite scroll)
  components/⚡{product-inquiry,inquiry-cart,cart-counter}.blade.php
  quotes/pdf.blade.php                 # branded, bilingual quote
```
