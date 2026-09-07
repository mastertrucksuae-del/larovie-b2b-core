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

    private function makeProduct(string $title = 'Heartleaf Soothing Serum'): Product
    {
        $product = Product::factory()->create([
            'title' => $title,
            'is_visible' => true,
            'is_archived' => false,
            'is_bundle' => false,
            'brand' => 'Anua',
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
        Setting::current()->update(['trade_licence_number' => '39037']);
        Setting::clearCache();
        $this->setOverrides(['en' => ['trust_licence' => 'Licence no. :number']]);

        $this->get(route('home'))->assertOk()->assertSee('Licence no. 39037');
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
