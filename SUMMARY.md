# Larovie B2B Wholesale Catalogue — Session Log

## 2026-07-09
**Topics:** Added a "Purchase order (PDF)" header action on the inquiry edit page — the
same document as the customer quote but retitled "Purchase Order" with the customer
"Prepared for" block omitted (no name). Added the product featured image as the first
column of the line-item table on both the customer quote and the supplier PO. Images are
inlined as base64 data URIs in `QuoteService` (local public-disk overrides read off disk,
remote Shopify CDN URLs fetched via HTTP with an 8s timeout) since DomPDF has remote
fetching disabled; failures degrade to no image.
**Decisions:** Parametrized the single `quotes/pdf.blade.php` view with `$isPurchaseOrder`
+ `$images` rather than duplicating the template. The PO streams as an inline download and
is not persisted (no new DB column). Faked HTTP in the PDF tests (factory uses a live
picsum URL) so the suite stays offline; added a PO test asserting the customer name is
absent.
**Files modified:** app/Services/Quote/QuoteService.php, resources/views/quotes/pdf.blade.php,
app/Filament/Resources/Inquiries/Pages/EditInquiry.php, tests/Feature/QuoteTest.php

**Tweak (same day):** On the inquiry edit form, the line-item SKU was tacked onto the end of
the product/variant name and ran straight into it ("Default TitleLAR167"). Moved the SKU to
the front of the row as a distinct monospace badge (InquiryForm `itemsRepeater` product
placeholder).

**Fix (same day):** Production hit "Malformed UTF-8 characters" on the Livewire update when
opening the PO action. Cause: `purchaseOrderResponse()` returned a plain `Response` with a
binary PDF body, which Livewire tried to json_encode into its payload. Fixed by returning a
`StreamedResponse` via `response()->streamDownload()` (same pattern as the CSV export) so
Livewire handles it as a file download. Updated the PO test to capture streamed output and
assert a valid `%PDF` header.

## 2026-07-07
**Topics:** Built the Larovie B2B wholesale catalogue MVP end-to-end from the build prompt.
Scaffolded a fresh Laravel 13 app (Filament v5, Livewire 3, Tailwind v4) on MySQL/MariaDB
(XAMPP). Implemented the full data model (products, product_variants, inquiries,
inquiry_items, settings), Shopify GraphQL import with admin-field preservation + soft
archival, the Filament Products & Inquiries resources (status pipeline, live line-item
pricing, dashboard widgets), session-based inquiry cart, RFQ submission with snapshotting +
honeypot + rate limiting, branded bilingual quote PDF (dompdf + embedded Cairo font) and
CSV export via signed routes, WhatsApp chat/send-quote buttons, a Settings page, and full
EN/AR + RTL localization. Later reworked the storefront into a single Livewire page with
live search, sort (brand/category/price/stock), and infinite scroll (24 at a time).

**Decisions:**
- Environment ships Filament **v5.6** and Laravel **13** (newer than the prompt's Filament v3);
  built against v5 APIs (schemas under `Filament\Schemas`, actions under `Filament\Actions`,
  resources use `Schemas/`, `Tables/` sub-namespaces).
- Shopify managed-install token is short-lived; `ShopifyClient` auto-mints/caches a token via
  the `client_credentials` grant using API key/secret and refreshes on 401.
- Products come from Shopify only (no manual create/delete in admin); admin edits just
  visibility/MOQ/wholesale price. Missing products are soft-archived, never deleted.
- Inquiry line items are snapshotted at submission (title/variant/sku/image/qty) so quotes
  stay accurate after re-sync/archival.
- Storefront cart is session-based (no login); MOQ clamping enforced.
- dompdf embeds Cairo for Arabic but doesn't do contextual shaping — noted `spatie/laravel-pdf`
  as the upgrade path in `QuoteService`.

**Live verification:** Real Shopify sync imported **496 products / 539 variants** (403 active
made visible). Storefront + admin panels render; **29 feature/unit tests pass**.

**Admin login:** admin@larovie.ae / password (change before production).

### Design pass + brand metaobject (same day)
- Applied real **larovie.ae branding**: deep plum/aubergine (#3E2340) + rose accent + warm ivory,
  Playfair Display + Inter + Tajawal (self-hosted via Vite/bunny), official logo assets, dark footer
  with taglines. Redesigned catalogue, cards, product detail, cart, confirmation.
- **Inquiry side drawer**: slides in (Alpine) on cart click / after add-to-inquiry, with "View full
  inquiry" → full `/cart` page.
- **Quick-add** icon button on cards (always visible); single-variant adds instantly + opens drawer,
  multi-variant navigates to product page.
- **Storefront brands now come from the Shopify "Brands" metaobject** (type `brand`), mapped via each
  brand's `featured_products` list (the `vendor` field was inconsistent). Added `products.brand`
  column, `buildBrandMap()` in the sync, `effective_brand = brand ?: vendor`. Catalogue groups/sorts/
  searches by effective brand; default sort leads with the biggest brand (Medicube, 76). 411/496
  products mapped to 42 brands.
- Dominant search bar; bigger logo (h-16/h-20); admin inquiry line-items given full width (was cramped).
- **Fixed a real bug**: `ShopifyClient` sent `variables: []` (JSON array) which Shopify rejects when
  empty — now casts to object. Live re-sync OK.

### Bundles excluded + admin inquiry polish
- **Catalogue shows solo products only.** No native Shopify bundle flag exists (the `app_bundles`
  metaobject is just 3 curated offers), so bundles are detected heuristically (`App\Support\BundleDetector`:
  title/type/tags contain bundle|kit|duo|trio|set|routine|pack|Npcs|N-piece|N-step). Added an
  `is_bundle` column: auto-set on import, **preserved on re-sync, admin-overridable** (toggle column +
  filter + form toggle in the Products resource). `publiclyVisible` scope now also excludes bundles.
  Backfilled existing → 120 bundles hidden, 330 solo products visible.
- **Admin inquiry line-items redesigned**: full-width section, product name on its own row, then
  Quantity / Unit price / Line total as three equal columns (no more cramping).

### Extra costs, status pipeline, brand counts
- **Additional charges** on inquiries (`inquiry_charges` table, `is_billable` flag). Admin adds ad-hoc
  costs (shipping, handling, parking…); each has an "On quote" toggle. Billable → added to the quote
  total + shown on PDF/CSV; internal → tracked for cost/margin only, off the customer quote.
  `Inquiry::recalculateTotals()` = items subtotal + billable charges; helpers
  `billableChargesTotal()` / `internalChargesTotal()`. Live totals summary in the form.
- **Odoo-style status pipeline** replaced the status dropdown: a colored, clickable stage bar
  (`resources/views/filament/inquiry/status-pipeline.blade.php`, arrow segments, one colour per stage —
  amber/sky/violet/emerald). Clicking a stage commits immediately via `EditInquiry::setStatus()`.
- **Per-brand product counts**: a badge next to each brand group header in the catalogue
  (`Catalogue::brandCounts()`, respects active search/category filters).
- **Charges support Fixed or Percentage** (per row, defaults to Fixed). Percentage resolves against
  the products subtotal (`InquiryCharge::resolve($base)`); label shows the % e.g. "Shipping (5%)".
  Form amount field flips prefix (AED) ↔ suffix (%) by type; totals/PDF/CSV use the resolved value.
- **53 tests pass.**

**Files modified:** Full greenfield build — migrations & models under `app/Models` +
`database/migrations`; `app/Services/{Shopify,Quote,WhatsApp,Cart}`; `app/Support`;
`app/Actions/CreateInquiry`; `app/Http/Controllers/{Catalogue,Inquiry,Locale,Quote}Controller`;
`app/Http/Middleware/SetLocale`; `app/Filament/**` (resources, pages, widgets);
`app/Console/Commands/ShopifySyncCommand`; storefront views under `resources/views/**`
(incl. ⚡ Livewire SFCs); `lang/{en,ar}/shop.php`; `config/shopify.php`; seeders/factories;
tests under `tests/**`; `.env`, `.env.example`, `README.md`.

## 2026-07-07 (brand navigation slider)
**Topics:** Added a brand-navigation slider to the top of the wholesale catalogue + collapsible per-brand sections (reference: qogita.com/brands). Also fixed an unrelated cPanel DNS issue (CNAME vs existing A record for www.wholesale.larovie.com — kept the A record, dropped the CNAME).
**Decisions:**
- Brand data has no dedicated model; grouping uses the existing `effective_brand` (brand → vendor fallback) on `products`.
- New `brandNav()` computed mirrors the product grid's brand ordering (count desc, alpha, "Other" last) and exposes each brand's product count + absolute start index.
- New `goToBrand(int $index)` Livewire action forces brand sort, bumps `perPage` to load enough pages for the target brand (works with infinite scroll), then dispatches a `brand-jump` browser event; the matching `<section>` expands and smooth-scrolls into view.
- Slider is a sticky (`top-24`) horizontally-scrollable chip strip; active chip is highlighted via a throttled scroll listener and auto-centered in the strip. RTL-aware (chevron `rtl:-scale-x-100`), bilingual (`shop.brands` added to en/ar).
- Per-brand sections use Alpine `x-data="{ open }"` + `x-collapse` (confirmed bundled in Livewire dist) with a rotating chevron and `aria-expanded`/`aria-controls`.
- Added a `no-scrollbar` Tailwind v4 `@utility`.
**Files modified:** `resources/views/components/⚡catalogue.blade.php`, `resources/css/app.css`, `lang/en/shop.php`, `lang/ar/shop.php`. Assets rebuilt (`npm run build`), views compile clean.

## 2026-07-07 (brand logos + only_full_group_by hotfix)
**Topics:** Admin-managed brand logos, surfaced in the catalogue brand slider + section headers. Also hotfixed a production 500 on the catalogue.
**Production hotfix:** `brandNav()`'s grouped query ordered by the raw `coalesce(nullif(brand,""),vendor)` expression, which Forge's MySQL rejected under `ONLY_FULL_GROUP_BY` (error 1055). Changed ORDER BY to use the grouped select aliases (`c desc`, `b is null, b asc`). Local MySQL 8 tolerated the old form (resolves expression equivalence); Forge's stricter MySQL did not — alias ordering is portable to both. Verified locally (only_full_group_by is ON).
**Feature — brand logos:**
- New `brands` table (migration `2026_01_06_000001_create_brands_table.php`): `name` (unique) + `logo_path`.
- New `App\Models\Brand`: `logo_url` accessor (`Storage::disk('public')`), `syncFromProducts()` (upserts a row per distinct effective brand, preserves existing logos), `logoUrlMap()`.
- New Filament resource `App\Filament\Resources\Brands\*` (Resource/Schemas/BrandForm/Tables/BrandsTable/Pages) mirroring the Products resource conventions (Filament v5). Logo `FileUpload` → `disk('public')` `directory('brands')`. ListBrands has an "Import brands from products" header action. Table shows logo, name, live product count.
- `ProductSyncService::sync()` now calls `Brand::syncFromProducts()` after archiving, so the brands list stays in step with each Shopify sync (logos preserved).
- Catalogue `⚡catalogue.blade.php`: `brandNav()` attaches `logo` via `Brand::logoUrlMap()`; slider chips show a small circular logo; section headers show a wordmark logo (h-9). Graceful fallback to text-only when no logo.
**Deploy note:** run `php artisan migrate` and ensure `storage:link` on the server (public disk) or logos 404.
**Files:** migration, `app/Models/Brand.php`, `app/Filament/Resources/Brands/**` (7 files), `app/Services/Shopify/ProductSyncService.php`, `resources/views/components/⚡catalogue.blade.php`. Assets rebuilt; brand routes registered; views compile clean.

## 2026-07-07 (brand logos — bigger, unified, name-hidden)
**Topics:** User: logos not visible, make them big/consistent, hide name when a logo exists. Checked the live domain (wholesale.larovie.com) with the gstack headless browser.
**Findings on live site:** logos DID load (no mixed-content/404) but were tiny 24px circles crammed next to the name, and source logos vary wildly in shape (200x200, 240x120, 160x160...), so the strip looked inconsistent.
**Changes (all in `resources/views/components/⚡catalogue.blade.php`):**
- Slider chips → uniform white tiles (h-16 × w-32, rounded-xl, border), logo `object-contain` centered, count as a small plum corner badge. Name shown only as a fallback when no logo. Active tile = plum border + ring.
- Section headers → same white framed tile (h-16, logo max-h-11), name hidden (kept as `sr-only`) when a logo exists; falls back to the serif `<h2>` otherwise.
- Fixed the jump-to-brand landing: the active-chip auto-centering was calling `scrollIntoView` which interrupted the page's smooth scroll (landed ~500px short). Now it scrolls only the horizontal track (`$refs.track.scrollBy`), never the window.
- Fixed active-chip lag after a jump: set `active` immediately on `brand-jump`, suppress scroll-sync during the jump (`jumping` flag), and finalize on the `scrollend` event (1500ms timeout fallback for browsers without scrollend). Aligned the active-detection line (212px) with the section landing point (`scroll-mt-52` = 208px).
**Verification:** previewed locally with 7 REAL production logos pulled down (had to swap the `public/storage` symlink for a real copy — PHP's built-in `artisan serve` 403s on symlinks on Windows; nginx on prod is fine). Confirmed via headless browser: tiles uniform, jump lands at 208px for near/mid brands, active chip matches the clicked brand every time, collapse toggle works. Screenshots taken. Cleaned up: symlink restored, test logo data/files removed.
**Deploy:** push the blade change; Forge deploy rebuilds assets (`public/build` is gitignored → `npm run build` on server generates the new Tailwind classes). No migration needed this round.

## 2026-07-15
**Topics:** Reviewed the "Larovie CTO Portal Upgrade Brief" (P0→P2 for wholesale.larovie.com) and gave a CTO-level assessment. No code changed — advisory only.
**Assessment given:**
- Strong brief; sequencing is its best feature ("no outreach without attribution" before flyer spend; trust items 1–4 before removing `noindex` in item 9; hold P2 until real funnel data).
- Flagged the biggest hidden-effort risk: task 8's **WhatsApp/push alerts** depend on a Meta/BSP approval flow (Twilio/360dialog) that won't reliably fit a one-week P0 — recommended splitting into **email + dashboard alert now / WhatsApp fast-follow**.
- Called out 3 **ops dependencies** that must exist before the sprint clock starts (else "one week" slips): verified WhatsApp Business number (task 1), founder-approved authenticity copy (task 3, pharma claims), legal entity/licence details (task 2).
- Decide the **inquiry-cart persistence mechanism** now (localStorage pre-KYC, account-bound post-KYC task 10) to avoid building twice (task 11).
- Add a **privacy/data-handling note** alongside task 10 (trade-licence uploads + PII).
- Mapped tasks to the existing Laravel 13 / Filament v5 / Livewire 3 stack: tasks 5–7 (UTM, coupon, timestamps) ≈ inquiry-record columns + a Filament dashboard widget; task 8 email = Laravel notification; task 10 KYC = a Filament resource with pending/approved/rejected states; task 9 hreflang builds on the existing EN/AR locale.
**Decisions:** Approve essentially as written, with task 8 split and the ops dependencies confirmed before starting. Offered to turn P0 into a concrete implementation plan against the codebase.
**Files modified:** none (SUMMARY.md log entry only).

## 2026-07-15 (CTO Portal Upgrade — full P0→P2 implementation)
**Topics:** Implemented the entire "Larovie CTO Portal Upgrade Brief" (P0→P2) in dependency-ordered waves. Shared-file-heavy work (storefront layout, Setting model, inquiries schema, routes, lang files, Filament) was done directly rather than via parallel agents to avoid merge conflicts; an Explore-agent codebase map and a code-reviewer agent bookended the build.

**Wave 1 — Data foundation:** 4 migrations. `settings` += company_whatsapp, legal_entity_name, trade_licence_number, google_maps_embed, contact_hours, authenticity_statement_{en,ar}, notification_email, ga4_measurement_id, search_indexing_enabled. `inquiries` += utm_{source,medium,campaign,term,content}, landing_page, referrer, referral_code, quote_sent_at, order_confirmed_at (+ indexes). New `business_accounts` table. New `notifications` table. Models: Inquiry gained STATUS_ORDER_CONFIRMED + stampPipeline()/responseMinutes(); new `BusinessAccount` (Authenticatable, hashed cast); Setting cast for search_indexing_enabled.

**Wave 2 — Trust & Contact (P0 #1-4):** Redesigned header (nav w/ icons, tap-to-call `tel:`, WhatsApp `wa.me` w/ pre-filled msg via inline `#25D366` so it renders without a rebuild, mobile menu) + trust-rich footer (legal identity: entity/address/licence/TRN, contact links). New `App\Support\Contact` helper. Contact page (`/contact`) w/ map embed + inquiry form; Authenticity page (`/authenticity`) + catalogue strip component. `CreateInquiry` refactored to accept optional cart + attribution (spine for both cart RFQ and contact form). All EN/AR copy.

**Wave 3 — Measurement (P0 #5-8):** `CaptureAttribution` middleware (first-touch UTM→session) + `App\Support\Attribution`; referral_code field on cart + contact forms; attribution persisted onto inquiry (verified: flyer QR `?utm_source=flyer` → tagged inquiry). Pipeline timestamps stamped in EditInquiry. `PipelineMetrics` dashboard widget (median response time vs 4h SLA, quote→order conversion, inquiry→quote rate). Notifications: admin email (`AdminNewInquiryNotification`) + instant in-panel push (`notifyNow`, since `QUEUE_CONNECTION=database` was silently queuing Filament DB notifications) + buyer acknowledgement email (`BuyerInquiryReceivedNotification`, EN/AR), all wrapped in try/catch so delivery never breaks submission. `->databaseNotifications()` enabled. GA4 slot (renders only when measurement id set). Attribution/timeline panel + Source column/filter in Filament.

**Wave 4 — Discoverability (P1 #9):** Conditional noindex — `search_indexing_enabled` gate (OFF by default). When ON: index/follow, canonical, hreflang en/ar/x-default (via new `?hl=` param honoured by SetLocale), Organization + Product JSON-LD, dynamic `/sitemap.xml` (333 URLs, 404 when off) + dynamic `/robots.txt` (Disallow when off, else Allow + Sitemap directive). Removed static public/robots.txt. Per-page meta descriptions. Verified full OFF↔ON flip.

**Wave 5 — KYC (P1 #10):** `business` auth guard + provider in config/auth.php (isolated from admin `web` guard). Public `/register` (company, contact person, trade-licence upload, KYC) → pending; `/login` + `/account` status page (auth:business); `BusinessAccountController`. Filament BusinessAccounts resource (list w/ tabs + pending nav badge, review form, **Approve/Reject** header actions stamping approved_at/reviewed_by). Verified register→login→account. NOTE: inquiring is intentionally NOT gated by KYC — approval is the *future* gate for pricing visibility only.

**Wave 6 — Polish (P1 #11 + P2):** Cart now durable — session + 30-day cookie that rehydrates a fresh session (survives browser restart/expiry). Admin CSV export of all inquiry line-items by brand & SKU (`LineItemExport`, UTF-8 BOM) as a ListInquiries header action. EN/AR key parity confirmed (138 each). RTL-safe components.

**Wave 7 — Verify:** `npm run build` clean; **57 tests / 143 assertions pass**. code-reviewer agent pass → applied 5 fixes: (HIGH) cart `clear()` now sets `[]` not forget (prevents same-request stale-cookie rehydrate); (MED) 150-line cart cap; (MED) KYC trade-licence moved to **private** disk + admin-guarded download route `/admin/business-accounts/{account}/licence`; (MED) dropped `JSON_UNESCAPED_SLASHES` from JSON-LD (prevents `</script>` breakout); (LOW) status Select disabled so approve/reject actions are the only audited path. Re-ran tests: still 57 green.

**Not done (external / data tasks, flagged to founder):** Google Search Console + Google Business Profile registration (external); founder sign-off on authenticity copy (defaults are safe/truthful); real WhatsApp Business API for outbound (email + in-panel push cover P0 #8 now — WhatsApp outbound is the fast-follow); per-SKU MOQ accuracy (needs real data); go-live: set the new Settings fields + flip `search_indexing_enabled` ON after founder review.

**Deploy notes:** run `php artisan migrate`; ensure `storage:link`; set MAIL_* for real email (currently `MAIL_MAILER=log`); a queue worker is recommended (admin email uses sync send; in-panel push uses notifyNow so it works without a worker). Populate the new Settings (WhatsApp number, legal entity, trade licence, map embed, notification email, GA4 id) before enabling indexing.

**Files:** 4 migrations; models Inquiry/BusinessAccount/Setting; middleware CaptureAttribution + SetLocale; Support Contact/Attribution; Actions/CreateInquiry; controllers Page/BusinessAccount/Sitemap + Inquiry; Services Cart + Export/LineItemExport; Notifications (2); config/auth.php; bootstrap/app.php; AdminPanelProvider; Filament InquiryForm/InquiriesTable/EditInquiry/ListInquiries + PipelineMetrics widget + BusinessAccounts resource (5 files); routes/web.php; storefront layout + catalogue index/show + cart + pages/{contact,authenticity} + auth/{register,login} + account/dashboard + components/authenticity-strip + sitemap; lang/{en,ar}/shop.php. Assets rebuilt.

**Hotfix (same day):** `/admin/business-accounts` 500'd — `ListBusinessAccounts` imported a non-existent `Filament\Resources\Components\Tab`; switched to `Filament\Schemas\Components\Tabs\Tab` (same class ListInquiries uses). Verified page now 302→login instead of 500. Also floated the dashboard stat widgets to the top via `$sort` (InquiryStats −6, PipelineMetrics −5, LatestInquiries −4) so the metrics sit above the account/welcome widget.

**Arabic polish + Cairo font (same day):** Rewrote `lang/ar/shop.php` into natural Gulf-business Arabic (better phrasing, correct pharma term "أرقام تشغيل" for batch codes, proper dual/plural forms for product count, "تواصل معنا"/"تذكّرني" UI terms), then stripped ALL diacritics for clean modern UI copy (verified zero tashkeel; 138/138 EN parity). Switched the Arabic UI font from the never-loaded Tajawal to **Cairo via Google Fonts CDN**: `--font-arabic` now `'Cairo', 'Inter', …` in app.css, and the storefront `<head>` conditionally (`@if $locale === 'ar'`) adds preconnect + the Cairo stylesheet link. Rebuilt assets; verified the CDN link renders on AR pages and the compiled CSS uses Cairo. (User also tweaked authentic_title→"ضمان المصادر الاصلية" and authentic_badge on the authenticity page.)

---

## 2026-08-10
**Topics:** SEO go-live (the site was invisible to Google) + a Core Web Vitals pass, prompted by three findings: `noindex, nofollow` on every page, `/sitemap.xml` 404, and `robots.txt` serving `Disallow: /`.

**Root cause:** all three were the same switch. The `search_indexing_enabled` gate built in Wave 4 shipped defaulting to OFF and was never flipped after go-live. A second, hidden bug sat underneath it: `Setting::create([])` does not read back DB-level column defaults, so even with the column defaulting to true a fresh install would have read `null` (falsy) and served `noindex` anyway.

**Decisions:**
- Flip indexing ON in a migration rather than leaving it as founder action — the trust/contact pages it was gating on are live, so the gate had outlived its purpose. It stays togglable in Admin → Settings for pulling the site out of search.
- Belt-and-braces the default: column default *and* `$attributes` on the model.
- Self-host **Cairo** (founder's confirmed choice) via the Vite fonts plugin instead of reverting to the bundled Tajawal — keeps the design decision from 2026-07 while dropping the render-blocking Google Fonts CDN.
- Font preloading deliberately left OFF: Bunny splits each weight into ~7 unicode-range subsets and `rel=preload` ignores unicode-range, so preloading would pull ~250KB of subsets the page never renders. Inlined `@font-face` + `display:swap` is strictly better here.
- Publish wholesale prices as `AggregateOffer` `lowPrice` (a lower bound), not fixed offers — pricing is quote-based, so a fixed `Offer` would be untrue.

**Discovered along the way:** the layout never called `@fonts`, so Playfair/Inter/Tajawal were bundled but never emitted — the storefront had been rendering in system fallbacks, and the CDN link was the only thing making Cairo work on AR pages.

**Also:** dropped the `⚡` prefix from the five Livewire component filenames at the user's request (Livewire 4 strips it as a cosmetic marker via `Finder::ZAP`, so component names are unchanged — verified in vendor before renaming).

**Files modified:** `database/migrations/2026_08_10_000001_enable_search_indexing.php` (new); `app/Models/Setting.php`; `app/Http/Controllers/SitemapController.php`; `app/Support/Img.php` (new); `app/Support/Money.php`; `app/Services/Shopify/ProductSyncService.php`; `app/Filament/Pages/ManageSettings.php`; `resources/views/layouts/storefront.blade.php`; `resources/views/catalogue/{show,partials/product-card}.blade.php`; `resources/views/components/catalogue.blade.php`; `resources/views/sitemap.blade.php`; `resources/css/app.css`; `vite.config.js`; `lang/{en,ar}/shop.php`; `app/Http/Middleware/SecurityHeaders.php` + `bootstrap/app.php` (new); `deploy/README.md` + `deploy/nginx/larovie-performance.conf` (new); `tests/Feature/{SeoTest,FontLoadingTest,SecurityHeadersTest}.php` (new); 5 Livewire component files renamed.

**Correction (same session):** production runs **Laravel Forge → nginx**, not Apache. The `public/.htaccess` compression/cache blocks I had added were inert there, so `.htaccess` was reverted to the Laravel default and the work was split by layer: security headers moved into `SecurityHeaders` middleware (server-agnostic, and survives a Forge nginx template reset), while gzip/brotli and static `Cache-Control` — which nginx serves without ever touching PHP — moved to `deploy/nginx/larovie-performance.conf` for pasting into Forge → Site → Nginx Configuration.

**Verify:** `npm run build` clean; **67 tests / 189 assertions pass** (was 57/143 — 10 new SEO, font and header tests).

**Deploy notes (required — none of this is live until deployed).** Full runbook in [deploy/README.md](deploy/README.md):
1. `php artisan migrate --force` — this is what flips indexing on.
2. `npm ci && npm run build` — `public/build` is gitignored, so the font + CSS changes only exist after a rebuild **on the server**.
3. `php artisan view:clear` (plus `config:cache`/`route:cache`).
4. Confirm `APP_URL=https://wholesale.larovie.com` in the production `.env` — canonical, hreflang and the sitemap's `Sitemap:` directive are all generated from it.
5. Paste `deploy/nginx/larovie-performance.conf` into Forge → Site → Nginx Configuration (needs re-applying after any Forge template reset).
6. Then verify: `/robots.txt` shows `Allow: /`, `/sitemap.xml` returns 200 XML, and the homepage `<meta name="robots">` reads `index, follow`.
7. Still founder action (external): submit the sitemap in Google Search Console and register the Google Business Profile.

**Not verifiable from here:** whether the brotli nginx module is installed on the Forge box (the `brotli` lines in the conf are commented out for that reason — gzip covers the audit either way). Actual PageSpeed scores also depend on hosting TTFB, which no code change addresses.

**PageSpeed follow-up (same day, post-deploy).** Deploy confirmed live: `robots.txt` serves `Allow: /`, `/sitemap.xml` returns 200 XML, `<meta name="robots">` reads `index, follow`, and the `SecurityHeaders` middleware output is present on the wire. Google's keyless PSI API was over its daily quota, so the site was measured directly with curl instead of reading the report.

**What the measurements showed is already fine:** HTML 22KB gzipped, app.css 14KB gzipped, product images 3.1KB each **served as WebP by the Shopify CDN** (the `Img::srcset` work is doing its job — `width=400` confirmed on the wire, 24 cards with srcset, all 77 images carrying intrinsic dimensions).

**What was actually broken:**
1. **No `Cache-Control` on any static asset** — `/build/*.css`, `/storage/*.webp`, `/images/*.png` all returned with the header entirely absent, so every repeat visit re-downloaded ~320KB of brand logos plus the stylesheet. The nginx config had never been applied. This is the one genuinely failing audit and it needs manual application in the Forge panel — Forge does not read config from the repo.
2. **Header logo was a 898×898 PNG (35,568 bytes) for a mark that renders at 80px.** Replaced with pre-scaled WebP: 3,910 bytes header, 2,160 bytes footer.
3. **A bug introduced in 85cc005:** those two `<img>` tags were given `width="240" height="80"` / `width="168" height="56"` while the sources are 1:1 and 1.15:1. The wrong aspect ratio made the browser reserve the wrong box — *causing* the layout shift the attributes were added to prevent. Now matched to the real assets, and omitted entirely for admin-uploaded logos whose proportions are unknown.

**nginx conf rewritten** against the real Forge server block (landed in `552fd17`, whose message covers only the logo work — noted here for the record). Two corrections once the actual config was known: dropped the `\.(css|js|…)$` regex location, which would also have captured `/livewire-*/livewire.js` — a dynamic PHP route that sets its own `Cache-Control`, so the block would have emitted two conflicting `Cache-Control` headers on that response; and re-declared `X-Content-Type-Options` inside each location, because nginx `add_header` does not merge with parent scope and would otherwise discard the server-level security headers. Cache lifetimes are graded by how each filename is generated: immutable for content-hashed Vite output, 30d for ULID-named uploads, 7d for repo images.

**Still outstanding:** paste `deploy/nginx/larovie-performance.conf` into Forge → Site → Nginx Configuration (the only remaining verified failure); brotli left commented out because the module is not installed by default and an uncommented `brotli on;` makes nginx refuse to reload.

**Accessibility + font-format round (same day).** Worked from the actual Lighthouse mobile report this time. All four scored a11y failures fixed:

| Failure | Cause | Fix |
|---|---|---|
| Buttons without accessible name | The inquiry/cart button's label is `hidden` below `sm`, which removes it from the accessibility tree — on mobile it was an icon with no name | `aria-label` with the item count; count badge `aria-hidden` |
| Select without label | Category filter had no name (sort already had one) | `aria-label` on the select **and** the search input — a placeholder is not an accessible name |
| Contrast | `rose-accent` #b76e79 = **3.80:1** on white, **3.54:1** on ivory (needs 4.5:1) | Small text → `rose-deep` #9c5763 (5.31 / 4.94) |
| Heading order | Brand sections emitted `<h2>` **only when the brand had no logo**, so logo'd brands jumped h1 → h3 | Every section carries a heading |

**Decision — contrast was not a global token change.** On the plum-950 footer the ratios invert: `rose-accent` passes at 4.71 and `rose-deep` would *fail* at 3.38. So the swap is per-background — the footer link and icon deliberately keep `rose-accent`. Ratios were computed, not eyeballed.

**Also fixed the heading markup, not just the level:** the old code put `<h2>` *inside* `<button>`, which is invalid (`<button>` takes phrasing content only). The `<h2>` now wraps the disclosure button. Brand logo `alt` dropped to `""` since the brand name is the heading text — it was duplicate announcement.

**Font bug — legacy WOFF was overriding WOFF2.** Bunny emits two `@font-face` rules per variant (WOFF2 then WOFF) with identical family, weight, style **and** `unicode-range`. Per the CSS Fonts spec the later matching rule wins, so browsers downloaded the legacy format and the WOFF2 was never used — the report's network tree showed `inter-400.woff` at 30.26 KiB. A `woff2Only()` build plugin in `vite.config.js` strips those rules. Side benefits: the `@font-face` CSS inlined into every page halves (Inter 16,489 → 8,248 bytes) and 824 KiB of now-unreferenced `.woff` files stop being deployed.

**Deliberately not acted on:**
- *Missing source maps* — marked **Unscored** in the report and refers to Livewire's own vendor bundle (`/livewire-*/livewire.js`), which we don't build. No score impact.
- *Render-blocking CSS (~460ms)* — clearing this audit fully means inlining the ~70KB raw / 14KB gzipped Tailwind bundle into every response, which trades away cross-page caching on a catalogue where buyers open many product pages. Left as a founder call; the cache policy below recovers most of it for repeat visits.

**Verify:** 72 tests / 231 assertions pass. New `AccessibilityTest` covers button naming, control labelling and heading order, plus a generic sweep asserting *any* icon-only button has a name.

**Still outstanding (unchanged):** the nginx config has not been applied — `/build/*`, `/storage/*` and `/images/*` still return no `Cache-Control` at all.
