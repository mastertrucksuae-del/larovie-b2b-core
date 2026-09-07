# Larovie B2B Wholesale Catalogue — Session Log

## 2026-09-07
**Topics:** Ported the designer's Lovable prototype (larovie-wholesale-hub.lovable.app) into
the real Laravel app as a proper bilingual homepage, made the login/registration paths
unmissable, and repaired a XAMPP/MariaDB startup failure that was blocking all local work.

**XAMPP fix (root cause):** MariaDB aborted with `Can't open and lock privilege tables:
Incorrect file format 'proxies_priv'`. On 2026-08-17 03:46:38 something wrote InnoDB log
content over two `mysql` system tables — `proxies_priv.MAI` (5,242,880 bytes, byte-identical
in size to `ib_logfile1`) and `db.MAD` (9.6 MB). The loud "InnoDB may be corrupt / LSN in the
future" messages were a red herring. Restored both from `D:\xampp\mysql\backup\mysql\`;
damaged originals kept as `.corrupt-*`, full cold backup at `D:\xampp-mysql-backup-20260907-105738`.
Data intact: 496 products / 539 variants / 403 visible. `mysql.gtid_slave_pos` is still missing
from its engine — harmless (replication only), left alone.

**Decisions:**
- `/` is now the marketing homepage (`HomeController`); the catalogue moved to `/catalogue`,
  keeping the `catalogue.index` route name so all 14 existing links followed automatically.
  Safe on SEO because indexing is still switched off in Settings.
- Adopted the designer's type system app-wide: Cormorant Garamond + Manrope (Latin),
  Alexandria + IBM Plex Sans Arabic (Arabic), self-hosted via the existing bunny/Vite setup.
  Palette shifted to the prototype's two real differences — primary `#241327` (was `#3e2340`)
  and accent `#c75f7b` (was `#b76e79`). Also defined `--color-plum-700`, which 13 existing
  classes referenced but the theme never declared.
- **No category taxonomy exists in the data**: `product_type` is empty for 263/330 visible
  products and holds a unique marketing sentence for the rest, `tags` covers only 67, and
  Shopify collections are never imported. Added `App\Support\Category` deriving 8 categories
  from title keywords — documented as a stopgap; the durable fix is importing collections.
- Price gating: guests see "Log in to view wholesale price" instead of a number, matching the
  designer's intent and giving registration a concrete payoff. Applied on the homepage only —
  extending it catalogue-wide is a business call, not a silent change.
- Stock badge threshold set to 3, not the usual 10: real inventory averages 3.6 units a
  variant, so a threshold of 10 badged literally every card "Limited stock".
- Hero art is a 2x2 mosaic of real catalogue imagery — the storefront ships no lifestyle
  photography and the prototype's stock photo is not ours to take.

**Auth visibility:** header now carries a "Sign in" link plus a filled "Open a business
account" button for guests, and an account menu (company name / My account / Sign out) when
signed in — mirrored in the mobile panel, with an icon-only account button below 640px so the
path is never buried in the hamburger.

**Tests:** 86 passing / 284 assertions (was 76 with 2 failing). Updated `FontLoadingTest` for
the new families — including a per-locale isolation check written against the real emitted
`font-family: "X"` shape, since the first version could only ever have passed vacuously — and
added `HomePageTest` (10 cases: routing, featured-product sourcing, visibility filtering,
the price gate both signed-in and out, header auth states, RTL).

**Still needs founder action:** trade licence number is unset in Settings, so the licence trust
item is hidden and "Registered in Dubai" falls back to copy without a number; MOQ is unset on
482/496 products so every card reads "Minimum order: 1 unit"; search indexing remains off.

**Files modified:** routes/web.php, app/Http/Controllers/HomeController.php (new),
app/Support/Category.php (new), app/Http/Controllers/SitemapController.php,
resources/views/home.blade.php (new), resources/views/home/partials/featured-card.blade.php (new),
resources/views/components/home-quick-add.blade.php (new),
resources/views/layouts/storefront.blade.php, resources/css/app.css, vite.config.js,
lang/en/shop.php, lang/ar/shop.php, tests/Feature/FontLoadingTest.php,
tests/Feature/HomePageTest.php (new)

### Homepage made editable + sortable from the dashboard (same day)
- **Translation audit:** EN/AR now at 215/215 key parity with no value identical across the
  two files. One genuinely hardcoded string was left in the storefront ("WhatsApp" in the
  header) — now `shop.whatsapp` / "واتساب". Two tests lock this in permanently: every editable
  key must exist in both files with different values, and the two files must not drift.
- **Editable copy:** new `homepage_content` JSON column on settings holding
  `{"en": {...}, "ar": {...}}` overrides, resolved through `App\Support\HomeContent`. An
  override wins; anything blank falls back to the shipped translation, so clearing a field in
  the dashboard restores the default instead of emptying the section. One JSON column rather
  than ~120 `_en`/`_ar` columns, which would need a migration per line of copy. 59 keys × 2
  languages = 118 fields, generated in Filament from `HomeContent::GROUPS` so the form can
  never drift from what the page renders. Placeholders (`:number`) and plural forms
  (`singular|plural`) survive overrides — `HomeContent::choice()` runs an overridden string
  through the translator's own message selector.
- **Also editable:** hero image upload (replaces the auto product collage), featured-product
  count (clamped 4–24), per-section visibility toggles, and SEO title/meta description.
- **Sortable sections:** new `homepage_section_order` column plus a drag-to-reorder control.
  The six middle sections were extracted into `resources/views/home/sections/*.blade.php` and
  are now rendered by a loop over `HomeContent::sectionOrder()`. Hero, trust strip and closing
  CTA stay fixed — a toggle that can blank the whole page is a support ticket waiting to
  happen. The stored order is treated as a preference, not a spec: unknown keys are dropped,
  duplicates collapse, and any section missing from it still renders appended in shipped
  order, so adding a seventh section later cannot make it invisible on existing installs.
- **Bug found and fixed in the test harness:** `Setting::current()` memoises the settings row
  in a static. RefreshDatabase rolls the row back between tests but the stale model survived
  in memory, so one test's settings silently applied to the next — this produced three
  confusing failures. Now reset in `Tests\TestCase::setUp()`, which protects every test.
- **phpunit memory_limit raised to 512M:** rendering ~120 Livewire form fields in one pass
  exhausted the stock 128M and aborted the suite.
- **Heads-up:** running `php artisan migrate` applied the previously pending
  `2026_08_10_000001_enable_search_indexing`, so `search_indexing_enabled` is now ON in the
  local database. Local only — check the intended value before production.
- **Tests:** 102 passing / 628 assertions (was 86 / 284).
- **Files added:** app/Support/HomeContent.php, resources/views/home/sections/{categories,
  brands,featured,why,steps,types}.blade.php, tests/Feature/HomepageContentTest.php, two
  migrations (homepage content + section order).
- **Files modified:** app/Filament/Pages/ManageSettings.php, app/Models/Setting.php,
  app/Http/Controllers/HomeController.php, resources/views/home.blade.php,
  resources/views/home/partials/featured-card.blade.php,
  resources/views/components/home-quick-add.blade.php,
  resources/views/layouts/storefront.blade.php, lang/en/shop.php, lang/ar/shop.php,
  tests/TestCase.php, phpunit.xml


### Account view page, review toggle, and product-page internal linking (same day)
- **Business account view page** (`/admin/business-accounts/{id}`) — a read-only profile built
  as a Filament infolist: application status with the full audit trail (registered, approved,
  reviewed by, notes), applicant details with click-to-mail/call, verification (licence number
  + document), then the management side: inquiries, confirmed orders, conversion %, open
  inquiries, confirmed value, quoted value, average order, last activity, a pipeline
  breakdown, most-requested products, and the last 10 inquiries linking through to each.
  Sections with no data hide themselves rather than showing empty headings. The table's row
  click now opens this; Edit is kept for notes only. Approve/Reject moved into a shared
  `ReviewsBusinessAccount` trait so the view and edit pages stamp the same audit trail.
- **`require_account_review` setting** (Settings → Business accounts). ON (the default, and
  what already shipped) queues registrations as pending. OFF approves them on registration,
  stamps `approved_at`, and shows the applicant a different message
  (`register_approved` vs `register_received`).
- **Bug found and fixed — pending accounts could see wholesale prices.** `login()` never
  checked status, so the price gate added earlier only tested "is signed in". An unreviewed
  or rejected applicant could log in and read wholesale pricing. The gate is now
  `canSeeWholesalePrices()` (i.e. approved), and a signed-in-but-unapproved buyer sees
  "Pricing unlocks once your account is approved" rather than a nonsensical "log in" prompt.
- **Inquiries now link to accounts.** New `inquiries.business_account_id`, set by
  `CreateInquiry` from the signed-in guard. Reporting reads through
  `BusinessAccount::matchedInquiries()`, which also matches historical guest rows on email
  (unique on the accounts table) — without that, a long-standing buyer who only just
  registered would read as brand new. `AccountInsights` memoises per account so the view
  costs one set of queries rather than one per field.
- **Product page internal linking** — four rails: often requested together, similar products,
  more from the same brand, recently viewed. These are the storefront's *only* crawlable path
  between product pages, since the catalogue is an infinite scroll that crawlers do not run;
  a product page went from 0 to ~12 internal links. Similar is matched on the derived
  category (`Category::keysFor`) and biased away from the product's own brand, falling back
  to same-brand when the category is thin. "Often requested together" is ranked by real
  co-occurrence across inquiries. Recently viewed is session-backed, matching the cart.
- **Caveat on "often requested together":** every one of the 10 `inquiry_items` rows in the
  local database has a NULL `product_variant_id`, so the rail is always empty here and could
  not be validated against real data. New cart submissions do set it, and the ranking query
  is covered by a test that builds the co-occurrence explicitly. Worth re-checking once real
  cart-based inquiries exist.
- **Tests:** 129 passing / 698 assertions (was 102 / 628). Two of my own test bugs found and
  fixed along the way: the cart session key is `inquiry_cart`, not `cart`, and `round()`
  returns a float where the conversion percentage should be an int.
- **Files added:** app/Support/{AccountInsights,ProductRecommendations,RecentlyViewed}.php,
  app/Filament/Resources/BusinessAccounts/{Pages/ViewBusinessAccount,Schemas/BusinessAccountInfolist,Concerns/ReviewsBusinessAccount}.php,
  resources/views/catalogue/partials/product-rail.blade.php,
  tests/Feature/{BusinessAccountReviewTest,ProductLinkingTest}.php, two migrations
  (require_account_review, inquiries.business_account_id).
- **Files modified:** app/Models/{Setting,Inquiry,BusinessAccount}.php,
  app/Http/Controllers/{BusinessAccountController,CatalogueController}.php,
  app/Actions/CreateInquiry.php, app/Support/{Category,HomeContent}.php,
  app/Filament/Pages/ManageSettings.php,
  app/Filament/Resources/BusinessAccounts/{BusinessAccountResource,Tables/BusinessAccountsTable,Pages/EditBusinessAccount}.php,
  resources/views/catalogue/show.blade.php,
  resources/views/home/partials/featured-card.blade.php, lang/en/shop.php, lang/ar/shop.php,
  tests/TestCase.php

### Pricing opened up, ordering gated, WhatsApp band, business types (same day)
- **Prices are public again.** The earlier login-to-see-price gate was removed at the
  founder's call: hiding pricing costs traffic, and the gate that matters is on ordering.
  `canSeeWholesalePrices()` is gone; the dead `login_to_view_price` /
  `price_pending_approval` copy was deleted rather than left to rot.
- **Ordering is gated instead.** New `EnsureCanSubmitInquiry` middleware (`can-order`) on
  `POST /inquiry`, so the guard cannot be bypassed by posting straight at the route. Guests
  are sent to sign in with `url.intended` set to the cart, so they come back to their basket.
  The rule is `canSubmitInquiry()`: never for a rejected account, and otherwise approval is
  required only while manual review is switched on — so turning review off retrospectively
  frees accounts that were queued under the old setting instead of stranding them.
  The cart shows a sign-in / open-account panel (or an "awaiting approval" panel) in place of
  the form, and the form is now prefilled from the signed-in account.
- **WhatsApp is a real homepage section now**, not just a header/footer link: a sortable,
  toggleable band with its own editable title/body/button in both languages. It renders only
  when a WhatsApp or fallback phone number is configured.
- **Business types are managed in the dashboard** (Admin → Business types): manual CRUD with
  drag-to-reorder, English + optional Arabic (falls back to English), and a visibility toggle.
  Seeded with the six that were hard-coded in the language files, so the page was unchanged
  the moment it ran. The homepage section reads the table and falls back to the shipped list
  if it is ever emptied, so it can never render as a bare heading.

### "Reordered a category and it vanished" — made the admin explain itself (same day)
Reordering was reported as not reaching the storefront. Reordering itself works —
reproduced locally, a category dragged to the top tiles first. But **three
separate rules can silently drop a category**, and all three produce the same
symptom, so there was no way to tell which one had been hit:

1. switched off (reordering does not switch a category on)
2. no *live* products inside it — visible products, not just products
3. hand-picked categories set in Settings, which override the ordering entirely
   (and a fourth: only the first 8 tile)

Nothing in the admin surfaced any of it. Two fixes:

- **The product count was actively misleading.** The list counted every related
  product, so a category could read "85" and still tile to nothing because none
  were visible. It now counts publicly visible products only — the number that
  actually decides — and turns red at zero.
- **New "On homepage" badge** naming the exact reason: Switched off / No live
  products / Not picked in Settings / Beyond the first 8 / Showing.

To keep that badge honest it asks the homepage rather than re-deriving the rules:
the decision moved into `Category::forHomepage()`, used by both the controller and
the admin, so the badge cannot drift from what the storefront renders.

- **Tests:** 197 passing / 1,060 assertions (was 193). New cases cover reordering,
  the limit, the all-hidden-products case, and picks overriding order.
- **Files modified:** app/Models/Category.php, app/Http/Controllers/HomeController.php,
  app/Filament/Resources/Categories/Tables/CategoriesTable.php, tests/Feature/CategoryTest.php

### Inline visibility toggle + category artwork (same day)
- **"Shown" is now an inline toggle** on Categories and Business types, matching
  the Products table which already worked that way. Switching a category on is
  the most common action on that screen; opening an edit page to flip one boolean
  was friction for no gain.
- **Categories borrow artwork from a product when the collection has none.**
  Order of preference: admin override, then the Shopify collection image, then a
  product from inside the category. Plenty of Shopify collections carry no image,
  and without this both the homepage tile and the admin list render an empty grey
  box. The lookup reuses an eager-loaded relation where the caller provided one,
  so a list does not fire a query per row.
- **Regression I introduced, now fixed.** Making the public disk serve
  root-relative URLs (to end the CORS/host mismatch) broke every admin image
  column. Filament's `ImageColumn` only passes a value through when it validates
  as an absolute URL and otherwise prefixes the disk again, turning
  `/storage/x.webp` into `/storage//storage/x.webp`. Added `Img::absolute()` and
  applied it to all four columns — categories, products, variants and brands.
  Absolute CDN URLs pass through untouched, so the storefront keeps its
  same-origin relative URLs.

- **Tests:** 193 passing / 1,053 assertions (was 187). New cases cover the
  artwork preference chain and the absolutiser in both directions.
- **Files modified:** app/Models/Category.php, app/Support/Img.php,
  app/Filament/Resources/{Categories,BusinessTypes,Products,Brands}/**,
  tests/Feature/{CategoryTest,ImageProcessingTest}.php

### Featured categories picker (same day)
Settings -> Homepage now has a **Featured categories** picker beside the existing
product and brand ones, so all three homepage strips are chosen the same way.

- Empty means "choose automatically" — the visible categories in the order set on
  the Categories page — so the section keeps working before anyone touches it.
- A hand-picked list is honoured **in full and in the order chosen**; the
  category limit only governs the automatic selection.
- Stored as ids rather than names: unlike brands, a category is a real record, so
  a rename cannot break the pick.
- Only categories switched on under Admin -> Categories can be picked, and one
  hidden after being picked drops off the homepage rather than 404-ing a tile.

- **Tests:** 187 passing / 1,043 assertions (was 182).
- **Files added:** migration 2026_09_07_000009_add_homepage_featured_categories_to_settings_table
- **Files modified:** app/Models/Setting.php, app/Support/HomeContent.php,
  app/Http/Controllers/HomeController.php, app/Filament/Pages/ManageSettings.php,
  tests/Feature/CategoryTest.php

### Categories management + licence number removed from public copy (same day)
**Categories are now real, and manageable.** This had been flagged as outstanding
several times without ever being built — the admin genuinely had no way to manage
them. Now:
- `categories` table + `category_product` pivot, imported from Shopify collections
  by `ProductSyncService::syncCollections()`. Same ownership split as products:
  Shopify owns title/handle/image and refreshes them each import; the admin owns
  visibility, ordering, the Arabic name and an image override, and a sync never
  touches those. A collection removed upstream is **archived, not deleted**, so a
  mistake in Shopify cannot take an admin's settings and translations with it.
- Imported categories arrive **hidden** — an import must never silently publish a
  new section of the storefront. The nav badge counts unreviewed ones.
- Admin → Categories: list with drag-reorder, visibility toggle, Arabic name,
  WebP image override, and an "Import from Shopify" action. No manual creation,
  for the same reason products have none.
- The homepage prefers imported visible categories and falls back to the
  keyword-guessed `App\Support\DerivedCategories` (renamed from `Support\Category`
  to end the clash with the new model) when nothing has been imported, so the
  section still works on a store that has not synced.
- Tiles link to `/catalogue?category=<id>`, and the catalogue filters on real
  membership rather than a text search.

**Trade licence number removed from all public copy.** The number now appears
only where it is the *applicant's own* (registration form, their account page,
the admin's KYC view). Larovie's own licence number is gone from the trust strip,
the "Registered in Dubai" card, the footer and the contact page, replaced by a
plain statement of registration. A test asserts the number never renders on
public pages.

**Two bugs of my own, caught by the suite:**
- The rename left `Category::withCounts()` in `HomeController::index()` pointing
  at the new Eloquent model, which has no such method. A string replacement had
  silently no-opped because the earlier sed had already changed the import.
- Editing the trust strip removed an `@if` but left its `@endif`, unbalancing the
  template (4 vs 5). Caught before it shipped.

- **Tests:** 182 passing / 1,029 assertions (was 170).
- **Files added:** app/Models/Category.php, app/Filament/Resources/Categories/**,
  tests/Feature/CategoryTest.php, migration 2026_09_07_000008_create_categories_table
- **Files modified:** app/Support/DerivedCategories.php (renamed),
  app/Services/Shopify/ProductSyncService.php, app/Http/Controllers/HomeController.php,
  app/Models/Product.php, app/Support/HomeContent.php,
  resources/views/components/catalogue.blade.php, resources/views/home.blade.php,
  resources/views/home/sections/{why,categories}.blade.php,
  resources/views/layouts/storefront.blade.php, resources/views/pages/contact.blade.php,
  lang/en/shop.php, lang/ar/shop.php, tests/Feature/HomepageContentTest.php

### Brand logos on the homepage, account view layout (same day)
- **Homepage brand strip now shows logos**, matching the catalogue. Both read the
  same `Brand::logoUrlMap()`, so a logo uploaded once appears in both places.
  Each brand renders as a fixed white tile with the artwork contained inside it,
  so brands read as one system whatever shape or aspect their logo is; the brand
  name stands in when no logo has been uploaded. Verified both branches by
  seeding one logo locally: 1 logo tile + 11 name tiles rendered, then reverted.
  Note that **no brand has a logo in the local database** (0 of 50) — production
  has them, which is why the live catalogue shows logos and local does not.
- **Business account view page laid out properly.** Filament puts infolist
  sections in a two-column page grid by default, which left the profile as a
  ragged pair of columns — short cards beside tall ones, dead gaps down the
  middle, and the applicant block squeezed enough that the email address wrapped
  mid-word. All seven sections now span the full width, so the profile reads as a
  single vertical stack.
- **Not visually confirmed:** repeated admin logins through browser automation
  failed this session (session state kept dropping), so the account page change
  is verified structurally (7/7 sections) and by the test that renders the page,
  not by eye.
- **Tests:** 170 passing / 993 assertions.
- **Files modified:** app/Http/Controllers/HomeController.php,
  resources/views/home/sections/brands.blade.php,
  app/Filament/Resources/BusinessAccounts/Schemas/BusinessAccountInfolist.php

### Upload still hanging: raised the PHP limits (same day)
Follow-up to the "Waiting for size" hang. Forensics on the stuck upload:
`storage/app/private/livewire-tmp` held only a 129-byte `.json` sidecar and no
image. Livewire writes that sidecar **first** and then stores the file
(`FileUploadConfiguration::storeTemporaryFile`), so the POST arrived and the
metadata was written while the file itself never landed. The sidecar named the
real file: `ZeroPoreBlackheadMudMask5.png`, **1,920,875 bytes (1.83 MB)** —
against `upload_max_filesize = 2M`. Right at the ceiling.

Raised the local XAMPP php.ini (backed up alongside as `php.ini.bak-<stamp>`):

    upload_max_filesize   2M  -> 16M
    post_max_size         8M  -> 20M

The app's derived field ceiling now reports 12 MB (its own clamp) instead of
1.8 MB, and the orphaned temp metadata was cleared so the uploader starts clean.

**Not verified end to end.** A curl reproduction against the signed Livewire
upload endpoint returns 419 without a browser session, and building a
CSRF-carrying harness was not worth further effort. The limits are confirmed
raised in a fresh PHP process; whether the browser upload now completes needs a
retry **after restarting the PHP server**, because php.ini is read once at
process start — a running server keeps the old 2M limit.

- **Tests:** 170 passing (9 image tests re-run against the new limits).
- **Files modified:** none in the repo — php.ini is system configuration. The
  production equivalents are already documented in `deploy/README.md`.

### Hand-picked featured products and brands (same day)
The homepage's featured grid and brand strip can now be chosen in
Settings -> Homepage instead of being derived. Two nullable JSON columns
(`homepage_featured_product_ids`, `homepage_featured_brands`); empty means
"choose automatically", so the page is never blank on a fresh install and
clearing the picks restores the old behaviour rather than emptying the section.

- Products are stored as ids and **shown in the order picked**. Scoped to
  `publiclyVisible()`, so a product hidden or archived after being picked drops
  off rather than reappearing on the homepage.
- Brands are stored as **names**, not brands-table ids: a storefront "brand" is
  `brand ?: vendor` resolved off the product row, which is what the section
  groups and links on, so the name is the only stable key.
- A picked brand with nothing visible to sell is skipped — the chip states a
  product count, so leaving it in would advertise "0 products" and link to an
  empty search. Verified live: picking "Round Lab" (0 products in this
  catalogue) correctly renders nothing while "Medicube" (65) renders.
- The existing count field now only governs automatic mode; an explicit pick is
  shown in full. Its label says so.
- The product picker searches rather than listing every option — the catalogue
  runs to hundreds of rows and rendering them all would bloat the settings page.

**Test-authoring note:** the first brand assertion checked the whole page and
failed, because a brand name also appears as the label on each product card.
Scoped to the brand strip rather than loosened.

- **Tests:** 170 passing / 993 assertions.
- **Files added:** migration 2026_09_07_000007_add_homepage_featured_picks_to_settings_table
- **Files modified:** app/Models/Setting.php, app/Support/HomeContent.php,
  app/Http/Controllers/HomeController.php, app/Filament/Pages/ManageSettings.php,
  tests/Feature/HomepageContentTest.php

### Bug: the hero image uploader hung on "Waiting for size" (same day)
**Cause, and it was mine.** `WebpUpload` advertised `maxSize(12 * 1024)` — 12MB —
while this PHP allows `upload_max_filesize = 2M` (and `post_max_size = 8M`).
FilePond accepted the file client-side and POSTed it; PHP discarded it before any
application code ran, so the request arrived with no file at all, Livewire never
returned a temporary file, and the widget waited forever. Nothing reached the log,
because nothing in the app ever executed.

**Fix:** the field now derives its own ceiling from the real ini values
(`min(upload_max_filesize, post_max_size)` less headroom for the rest of the
multipart body, clamped to a sane range). Locally that resolves to 1.8 MB, and the
limit is stated in the field's helper text, so an oversized file fails immediately
with a clear message instead of hanging. A regression test asserts the advertised
limit can never exceed what PHP accepts.

**Still needs doing (environment, not code):** 1.8 MB is below a typical hero
photograph. Raising it is a php.ini change on both this machine and the server —
`upload_max_filesize = 16M`, `post_max_size = 20M`, plus `client_max_body_size 20m`
in nginx. Documented in `deploy/README.md`; not applied here because php.ini is
system-wide and outside the project.

- **Tests:** 163 passing / 973 assertions.
- **Files modified:** app/Filament/Support/WebpUpload.php, deploy/README.md,
  tests/Feature/ImageProcessingTest.php

### Final end-to-end test, WebP uploads, production pass (same day)
- **End-to-end journey test.** Walks the real paths rather than repeating unit coverage: a
  visitor browsing with public pricing, being stopped at the order, registering, waiting on
  review, an admin approving from the account view, the order going through attributed to that
  account and appearing in their reporting — plus the founder reordering/rewording the
  homepage, the full Arabic journey, logout return, and every public route answering.
  Found a genuine test-authoring trap while writing it: `actingAs($account, 'business')` makes
  `business` the **default guard for the rest of the test**, so a later bare `actingAs($admin)`
  signed the admin in on the wrong guard and Filament bounced them to login. Guard now named
  explicitly, with the reason recorded.
- **Uploads are converted to WebP** (`App\Support\ImageProcessor`, wired into all five admin
  upload fields through one `WebpUpload` factory so no form can quietly skip it). Downscales
  above 1600px, quality 82, preserves PNG/WebP transparency (without `imagesavealpha` a
  transparent logo comes out on a black box). Deliberate exclusions: SVG passes through
  untouched — rasterising a vector logo makes it bigger and blurrier — and KYC trade licences
  are never touched, being private, often PDFs, and legal evidence. Guarded against decode
  bombs at 40MP, and any failure stores the original rather than losing the upload.
  Verified by a real encode, not a stub: 7 tests covering conversion, size reduction,
  downscale ratio, transparency, SVG pass-through and PDF safety.
- **Alt text audited properly.** Every `<img>` in the storefront already carries an `alt`. Five
  are empty, and all five are correct: each sits directly beside text naming the same thing, so
  a duplicate alt would make the screen-reader experience worse, not better. Left alone
  deliberately. (First audit pass reported false positives — the regex `[^>]*` stops at the
  `>` inside PHP's `->`.)
- **Production gap found: `php artisan storage:link` was missing from the deploy script.**
  Every image set in the panel is written to `storage/app/public` and served from `/storage/...`;
  without the symlink each one is a 404 on the live site while looking perfectly fine in the
  admin preview. Added to `deploy/README.md` with the reasoning.
  Locally the link cannot be created at all — `D:` is not a local NTFS volume, so Windows
  refuses both symlink and junction ("Local NTFS volumes are required"). `artisan storage:link`
  reports success but creates nothing. Not a code defect and not reproducible on the Linux
  server, but it does mean **uploaded images 404 on this machine only**.
- **Tests:** 161 passing / 966 assertions (was 147).
- **Files added:** app/Support/ImageProcessor.php, app/Filament/Support/WebpUpload.php,
  tests/Feature/{EndToEndJourneyTest,ImageProcessingTest}.php
- **Files modified:** deploy/README.md, app/Filament/Pages/ManageSettings.php,
  app/Filament/Resources/{Products/Schemas/ProductForm,Products/RelationManagers/VariantsRelationManager,Brands/Schemas/BrandForm}.php

### Chip icons, sortable account CTA, logout return, sticky save bar (same day)
- **Sticky save bar** on Admin → Settings. The page is ~120 fields, so the button at the very
  bottom meant scrolling the whole way to keep a one-word edit. Styled with a scoped `<style>`
  block rather than utility classes: the admin panel is served by Filament's own compiled
  stylesheet, which only contains the utilities Filament itself uses, so classes added in a
  custom view can silently do nothing. Verified pinned mid-scroll (bar bottom == viewport
  bottom at scrollY 2689 of 5378).
- **Business type icons.** The reference repeats a single Lucide `package-search` glyph on
  every chip; since types are dashboard-managed and the founder can add their own, each type
  instead picks from a curated set (`App\Support\BusinessTypeIcons`) and the six seeded types
  got matching ones — storefront, cross, sparkles, cart, heart, truck. A **key** into a fixed
  map rather than free-text SVG on purpose: the value renders inline on a public page, so
  arbitrary markup here would be an XSS hole. Unknown or null keys fall back to the default
  rather than emitting `<path d="">`.
- **The account CTA is now sortable**, at the founder's request. Only the hero and trust strip
  stay pinned — everything below them, the call-to-action included, is theirs to arrange. Its
  order is set to sit directly before the business-type chips.
- **Logout returns to the page you were on** instead of always the catalogue. The submitted
  target is untrusted, so it is constrained twice: same-origin only (otherwise logout becomes
  an open redirect anyone could aim at a phishing page), and never a page that needs a session
  or a guest-only auth page, which would bounce straight back. `?hl=` is preserved so the
  locale survives. Five tests cover the rules including the open-redirect attempt.
- **Spacing pass.** Three real faults, all consequences of sections becoming reorderable:
  1. `types` had no top padding at all — fine when it always followed another section, wrong
     now that any section can lead. All sections normalised to `py-14 sm:py-16`.
  2. The footer carried `mt-20`, which read as a stray ivory band whenever the page ended on a
     full-bleed dark section. Footer now sits flush (measured gap 0px); inner pages keep their
     breathing room via `pb-20` on the default container instead.
  3. Two sections sharing the page background could end up adjacent with nothing between them.
     Added a `.home-section + .home-section` hairline — full-bleed bands carry their own
     border and break the chain naturally, so it only fires on the case that needs it.
- **Note:** the earlier "WhatsApp renders after business types" report turned out to be a
  misread — the stored order, `sectionOrder()` and the rendered byte offsets all agreed. The
  actual ask was the account CTA.
- **Tests:** 147 passing / 763 assertions (was 135). Added BusinessTypeTest (icons, ordering,
  visibility, Arabic fallback, empty-list fallback) and the logout redirect cases.
- **Files added:** app/Support/BusinessTypeIcons.php, resources/views/home/sections/cta.blade.php,
  tests/Feature/BusinessTypeTest.php, migration 2026_09_07_000006_add_icon_to_business_types_table.
- **Files modified:** app/Models/BusinessType.php, app/Http/Controllers/BusinessAccountController.php,
  app/Support/HomeContent.php, app/Filament/Pages/ManageSettings.php,
  app/Filament/Resources/BusinessTypes/**, resources/views/filament/pages/manage-settings.blade.php,
  resources/views/layouts/storefront.blade.php, resources/views/home.blade.php,
  resources/views/home/sections/{types,whatsapp,categories,featured,steps}.blade.php,
  resources/css/app.css, tests/Feature/HomepageContentTest.php

### Bug: approving an account threw an error (same day)
Two faults, both introduced by my own shared-trait refactor earlier in the day, both in
`ReviewsBusinessAccount`:
1. `afterReview()` was `protected`. Filament binds an action closure to the Livewire
   component but **not to the class scope**, so the protected callback was unreachable, fell
   through to Livewire's `__call`, and threw *"Method ViewBusinessAccount::afterReview does
   not exist"*. The original code only worked because it called the public `fillForm()`.
   Now public, with the reason written down.
2. Both page classes declared their own `afterReview()` and called `parent::afterReview()`.
   A class method shadows the trait's copy, and `parent::` resolves to
   `ViewRecord`/`EditRecord`, neither of which defines it — a fatal waiting to happen on the
   next call. Both now do the work directly.

**Why it shipped:** the existing tests asserted the account view *rendered*, never that the
Approve button *ran*. Added three Livewire action tests (approve from view, reject with
reason from view, approve from edit) that exercise the buttons and assert the audit trail.

- **Tests:** 135 passing / 725 assertions.
- **Files added:** app/Http/Middleware/EnsureCanSubmitInquiry.php, app/Models/BusinessType.php,
  app/Filament/Resources/BusinessTypes/**, resources/views/home/sections/whatsapp.blade.php,
  migration 2026_09_07_000005_create_business_types_table.
- **Files modified:** bootstrap/app.php, routes/web.php, app/Models/BusinessAccount.php,
  app/Support/HomeContent.php, app/Filament/Pages/ManageSettings.php,
  app/Filament/Resources/BusinessAccounts/{Concerns/ReviewsBusinessAccount,Pages/ViewBusinessAccount,Pages/EditBusinessAccount}.php,
  resources/views/cart.blade.php, resources/views/home/partials/featured-card.blade.php,
  resources/views/home/sections/types.blade.php, lang/en/shop.php, lang/ar/shop.php,
  tests/Feature/{HomePageTest,BusinessAccountReviewTest,InquirySubmissionTest,HomepageContentTest}.php

**Still outstanding:** importing Shopify collections as real categories (replacing the
keyword-derived `App\Support\Category` stopgap) and the matching dashboard management screen.

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
