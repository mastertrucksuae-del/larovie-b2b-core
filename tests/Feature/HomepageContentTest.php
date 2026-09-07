<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Support\HomeContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Homepage copy is admin-editable and fully bilingual.
 *
 * Two guarantees are locked in here: every editable field has shipped wording in
 * both languages (so a fresh install is never half-translated), and an override
 * saved in the dashboard reaches the page for that language only.
 */
class HomepageContentTest extends TestCase
{
    use RefreshDatabase;

    private function setOverrides(array $content, array $sections = []): void
    {
        $setting = Setting::current();
        $setting->update([
            'homepage_content' => $content,
            'homepage_sections' => $sections ?: null,
        ]);
        Setting::clearCache();
    }

    private function makeProduct(string $title = 'Heartleaf Soothing Serum', string $brand = 'Anua'): Product
    {
        $product = Product::factory()->create([
            'title' => $title,
            'is_visible' => true,
            'is_archived' => false,
            'is_bundle' => false,
            'brand' => $brand,
            'featured_image_url' => 'https://cdn.shopify.com/x.jpg',
        ]);

        ProductVariant::factory()->for($product)->create([
            'is_visible' => true,
            'is_archived' => false,
            'inventory_quantity' => 50,
        ]);

        return $product;
    }

    // ── Translation coverage ────────────────────────────────────────────────

    public function test_every_editable_field_has_english_and_arabic_wording(): void
    {
        $en = require base_path('lang/en/shop.php');
        $ar = require base_path('lang/ar/shop.php');

        foreach (HomeContent::keys() as $key) {
            $this->assertArrayHasKey($key, $en, "Missing English wording for '{$key}'.");
            $this->assertArrayHasKey($key, $ar, "Missing Arabic wording for '{$key}'.");
            $this->assertNotSame(
                $en[$key],
                $ar[$key],
                "'{$key}' has identical English and Arabic wording — it looks untranslated."
            );
        }
    }

    public function test_the_two_language_files_stay_in_step(): void
    {
        $en = array_keys(require base_path('lang/en/shop.php'));
        $ar = array_keys(require base_path('lang/ar/shop.php'));

        $this->assertSame([], array_values(array_diff($en, $ar)), 'Keys present in English but missing from Arabic.');
        $this->assertSame([], array_values(array_diff($ar, $en)), 'Keys present in Arabic but missing from English.');
    }

    // ── Overrides ───────────────────────────────────────────────────────────

    public function test_an_override_replaces_the_shipped_wording_on_the_page(): void
    {
        $this->setOverrides(['en' => ['hero_title' => 'Bulk Beauty For UAE Retailers']]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Bulk Beauty For UAE Retailers')
            ->assertDontSee(__('shop.hero_title'));
    }

    public function test_a_blank_field_falls_back_to_the_shipped_wording(): void
    {
        $this->setOverrides(['en' => ['hero_title' => '']]);

        $this->get(route('home'))->assertOk()->assertSee(__('shop.hero_title'));
    }

    public function test_overrides_are_independent_per_language(): void
    {
        $this->setOverrides(['en' => ['hero_eyebrow' => 'ENGLISH ONLY EYEBROW']]);

        $this->get(route('home'))->assertOk()->assertSee('ENGLISH ONLY EYEBROW');

        // Arabic was not overridden, so it keeps its own translation.
        $this->get(route('home').'?hl=ar')
            ->assertOk()
            ->assertDontSee('ENGLISH ONLY EYEBROW')
            ->assertSee(__('shop.hero_eyebrow', [], 'ar'));
    }

    public function test_an_override_keeps_placeholder_substitution(): void
    {
        // Retargeted from trust_licence, which used to interpolate the trade
        // licence number. Public copy no longer prints that number anywhere —
        // it states registration instead — but the substitution mechanism is
        // still live for :count, so the coverage moves rather than disappears.
        $this->makeProduct();
        $this->setOverrides(['en' => ['min_order' => 'Order at least :count unit|Order at least :count units']]);

        $this->assertSame(
            'Order at least 12 units',
            HomeContent::choice('min_order', 12, ['count' => 12])
        );
    }

    public function test_public_pages_state_registration_without_the_licence_number(): void
    {
        Setting::current()->update(['trade_licence_number' => '39037']);
        Setting::clearCache();

        foreach ([route('home'), route('contact')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee(__('shop.licence_registered'))
                ->assertDontSee('39037');
        }
    }

    public function test_an_override_keeps_plural_forms(): void
    {
        $this->setOverrides(['en' => ['min_order' => 'Min :count carton|Min :count cartons']]);

        $this->assertSame('Min 1 carton', HomeContent::choice('min_order', 1, ['count' => 1]));
        $this->assertSame('Min 6 cartons', HomeContent::choice('min_order', 6, ['count' => 6]));
    }

    // ── Section visibility & counts ─────────────────────────────────────────

    public function test_a_section_can_be_switched_off(): void
    {
        $this->makeProduct();

        $this->get(route('home'))->assertOk()->assertSee(__('shop.how_it_works'));

        $this->setOverrides([], ['steps' => false]);

        $this->get(route('home'))->assertOk()->assertDontSee(__('shop.how_it_works'));
    }

    public function test_an_untouched_section_keeps_its_default_when_another_is_saved(): void
    {
        // Regression guard: storing only one flag must not read as "everything
        // else is off" and blank the rest of the page.
        $this->setOverrides([], ['steps' => false]);

        $this->assertFalse(HomeContent::showsSection('steps'));
        $this->assertTrue(HomeContent::showsSection('why'));
        $this->assertTrue(HomeContent::showsSection('brands'));
    }

    public function test_featured_product_count_follows_the_setting_and_is_clamped(): void
    {
        Setting::current()->update(['homepage_featured_count' => 12]);
        Setting::clearCache();
        $this->assertSame(12, HomeContent::featuredCount());

        Setting::current()->update(['homepage_featured_count' => 99]);
        Setting::clearCache();
        $this->assertSame(24, HomeContent::featuredCount(), 'An oversized value should clamp, not render hundreds of cards.');

        Setting::current()->update(['homepage_featured_count' => 0]);
        Setting::clearCache();
        $this->assertSame(8, HomeContent::featuredCount(), 'Zero should fall back to the default, not empty the grid.');
    }

    // ── Hand-picked featured products and brands ────────────────────────────

    public function test_featured_products_are_picked_automatically_when_none_are_chosen(): void
    {
        $a = $this->makeProduct('Automatic Alpha');
        $b = $this->makeProduct('Automatic Beta');

        $this->get(route('home'))->assertOk()->assertSee($a->title)->assertSee($b->title);
    }

    public function test_choosing_featured_products_shows_exactly_those_in_the_chosen_order(): void
    {
        $first = $this->makeProduct('Chosen First');
        $second = $this->makeProduct('Chosen Second');
        $ignored = $this->makeProduct('Not Chosen');

        Setting::current()->update(['homepage_featured_product_ids' => [$second->id, $first->id]]);
        Setting::clearCache();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Not Chosen', $html);
        $this->assertLessThan(
            mb_strpos($html, 'Chosen First'),
            mb_strpos($html, 'Chosen Second'),
            'Products should appear in the order they were picked.'
        );
    }

    public function test_a_picked_product_that_is_later_hidden_drops_off_the_homepage(): void
    {
        $shown = $this->makeProduct('Still Visible');
        $hidden = $this->makeProduct('Since Hidden');

        Setting::current()->update(['homepage_featured_product_ids' => [$shown->id, $hidden->id]]);
        Setting::clearCache();
        $hidden->update(['is_visible' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Still Visible')
            ->assertDontSee('Since Hidden');
    }

    /** The brands strip alone — a brand name also appears on each product card. */
    private function brandsSection(string $html): string
    {
        $start = mb_strpos($html, __('shop.featured_brands'));

        if ($start === false) {
            return '';
        }

        $end = mb_strpos($html, '</section>', $start);

        return mb_substr($html, $start, $end === false ? null : $end - $start);
    }

    public function test_choosing_featured_brands_shows_exactly_those_in_order(): void
    {
        $this->makeProduct('Soothing Toner', 'Anua');
        $this->makeProduct('Dokdo Toner', 'Round Lab');
        $this->makeProduct('Zero Pore Pad', 'Medicube');

        Setting::current()->update(['homepage_featured_brands' => ['Round Lab', 'Anua']]);
        Setting::clearCache();

        $section = $this->brandsSection($this->get(route('home'))->assertOk()->getContent());

        $this->assertStringNotContainsString('Medicube', $section, 'An unpicked brand must not appear in the strip.');
        $this->assertLessThan(
            mb_strpos($section, 'Anua'),
            mb_strpos($section, 'Round Lab'),
            'Brands should appear in the order they were picked.'
        );
    }

    public function test_a_picked_brand_with_nothing_visible_is_skipped(): void
    {
        // The chip states a product count, so an empty brand would advertise
        // "0 products" and link to an empty search.
        $this->makeProduct('Soothing Toner', 'Anua');

        Setting::current()->update(['homepage_featured_brands' => ['Anua', 'Ghost Brand']]);
        Setting::clearCache();

        $section = $this->brandsSection($this->get(route('home'))->assertOk()->getContent());

        $this->assertStringContainsString('Anua', $section);
        $this->assertStringNotContainsString('Ghost Brand', $section);
    }

    public function test_clearing_the_picks_returns_to_automatic_selection(): void
    {
        $product = $this->makeProduct('Back To Automatic');

        Setting::current()->update([
            'homepage_featured_product_ids' => [],
            'homepage_featured_brands' => null,
        ]);
        Setting::clearCache();

        $this->get(route('home'))->assertOk()->assertSee('Back To Automatic');
    }

    public function test_the_settings_page_offers_both_pickers(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(ManageSettings::getUrl())
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('homepage_featured_product_ids', $html);
        $this->assertStringContainsString('homepage_featured_brands', $html);
    }

    // ── Section order ───────────────────────────────────────────────────────

    private function orderOnPage(string $html): array
    {
        $markers = [
            'categories' => __('shop.shop_by_category'),
            'brands' => __('shop.featured_brands'),
            'featured' => __('shop.featured_products'),
            'why' => __('shop.why_partner'),
            'steps' => __('shop.how_it_works'),
            'types' => __('shop.business_types'),
            'whatsapp' => __('shop.whatsapp_title'),
            'cta' => __('shop.cta_title'),
        ];

        $positions = [];

        foreach ($markers as $key => $needle) {
            $at = mb_strpos($html, $needle);

            if ($at !== false) {
                $positions[$key] = $at;
            }
        }

        asort($positions);

        return array_keys($positions);
    }

    public function test_sections_render_in_the_shipped_order_by_default(): void
    {
        $this->makeProduct();
        Setting::current()->update(['company_whatsapp' => '+971500000000']);
        Setting::clearCache();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(array_keys(HomeContent::SECTIONS), $this->orderOnPage($html));
    }

    public function test_a_saved_order_changes_the_order_on_the_page(): void
    {
        $this->makeProduct();
        Setting::current()->update(['company_whatsapp' => '+971500000000']);
        Setting::clearCache();

        $wanted = ['steps', 'featured', 'whatsapp', 'cta', 'types', 'why', 'brands', 'categories'];
        Setting::current()->update(['homepage_section_order' => $wanted]);
        Setting::clearCache();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame($wanted, $this->orderOnPage($html));
    }

    public function test_a_partial_order_keeps_the_rest_visible(): void
    {
        // Only two sections named: the other four must still render, appended in
        // their shipped order, rather than vanishing.
        Setting::current()->update(['homepage_section_order' => ['types', 'why']]);
        Setting::clearCache();

        $this->assertSame(
            ['types', 'why', 'categories', 'brands', 'featured', 'steps', 'whatsapp', 'cta'],
            HomeContent::sectionOrder()
        );
    }

    public function test_a_stale_or_malformed_order_cannot_hide_a_section(): void
    {
        Setting::current()->update(['homepage_section_order' => ['retired_section', 'featured', 'featured', 42]]);
        Setting::clearCache();

        $order = HomeContent::sectionOrder();

        $this->assertSame(array_unique($order), $order, 'Duplicates should collapse.');
        $this->assertNotContains('retired_section', $order);
        $this->assertEqualsCanonicalizing(array_keys(HomeContent::SECTIONS), $order, 'Every known section must survive.');
        $this->assertSame('featured', $order[0]);
    }

    public function test_order_and_visibility_work_together(): void
    {
        $this->makeProduct();

        Setting::current()->update([
            'homepage_section_order' => ['steps', 'categories', 'why'],
            'homepage_sections' => ['categories' => false],
        ]);
        Setting::clearCache();

        $order = $this->orderOnPage($this->get(route('home'))->assertOk()->getContent());

        $this->assertNotContains('categories', $order, 'A hidden section should not render even when ordered.');
        $this->assertSame('steps', $order[0]);
        $this->assertSame('why', $order[1]);
    }

    // ── Dashboard ───────────────────────────────────────────────────────────

    public function test_the_settings_page_exposes_every_field_in_both_languages(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(ManageSettings::getUrl())
            ->assertOk()
            ->getContent();

        foreach (HomeContent::keys() as $key) {
            $this->assertStringContainsString("homepage_content.en.{$key}", $html, "No English input for '{$key}'.");
            $this->assertStringContainsString("homepage_content.ar.{$key}", $html, "No Arabic input for '{$key}'.");
        }

        foreach (array_keys(HomeContent::SECTIONS) as $section) {
            $this->assertStringContainsString("homepage_sections.{$section}", $html);
        }

        $this->assertStringContainsString('homepage_section_order', $html);
        $this->assertStringContainsString('homepage_hero_image_path', $html);
        $this->assertStringContainsString('homepage_featured_count', $html);
    }
}
