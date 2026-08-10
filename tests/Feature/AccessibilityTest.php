<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks in the Lighthouse accessibility failures fixed on 2026-08-10:
 * unnamed buttons, an unlabelled select, and a skipped heading level.
 */
class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $brand = 'Medicube'): Product
    {
        $product = Product::factory()->create([
            'is_visible' => true,
            'is_archived' => false,
            'brand' => $brand,
            'product_type' => 'Moisturiser',
        ]);
        ProductVariant::factory()->for($product)->create([
            'is_visible' => true,
            'is_archived' => false,
        ]);

        return $product;
    }

    public function test_icon_only_cart_button_has_an_accessible_name(): void
    {
        // The visible label is `hidden` below sm, so without aria-label the
        // button announces as just "button" on mobile.
        $html = $this->get(route('catalogue.index'))->assertOk()->getContent();

        preg_match_all('/<button\b[^>]*>/s', $html, $buttons);

        $cartButtons = array_filter(
            $buttons[0],
            fn (string $tag) => str_contains($tag, 'inquiry-open')
        );

        $this->assertNotEmpty($cartButtons, 'Could not find the inquiry/cart button.');

        foreach ($cartButtons as $tag) {
            $this->assertStringContainsString(
                'aria-label=',
                $tag,
                'The inquiry/cart button is missing an aria-label.'
            );
        }
    }

    public function test_every_icon_only_button_is_named(): void
    {
        $this->makeProduct();

        $html = $this->get(route('catalogue.index'))->assertOk()->getContent();

        preg_match_all('/(<button\b[^>]*>)(.*?)<\/button>/s', $html, $m, PREG_SET_ORDER);

        foreach ($m as [$full, $tag, $inner]) {
            // Text that a screen reader can actually announce: strip svg/markup.
            $visibleText = trim(strip_tags(preg_replace('/<svg.*?<\/svg>/s', '', $inner)));

            if ($visibleText !== '') {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/aria-label="[^"]+"|aria-labelledby="[^"]+"|title="[^"]+"/',
                $tag,
                'Icon-only button with no accessible name: '.substr($tag, 0, 120)
            );
        }
    }

    public function test_filter_controls_are_labelled(): void
    {
        $this->makeProduct();

        $html = $this->get(route('catalogue.index'))->assertOk()->getContent();

        // Every select and search input needs a name; a placeholder is not one.
        preg_match_all('/<select\b[^>]*>/', $html, $selects);
        $this->assertNotEmpty($selects[0]);
        foreach ($selects[0] as $tag) {
            $this->assertMatchesRegularExpression(
                '/aria-label="[^"]+"|aria-labelledby="[^"]+"|id="[^"]+"/',
                $tag,
                "Unlabelled select: {$tag}"
            );
        }

        preg_match_all('/<input\b[^>]*type="search"[^>]*>/', $html, $inputs);
        foreach ($inputs[0] as $tag) {
            $this->assertStringContainsString('aria-label=', $tag, "Unlabelled search input: {$tag}");
        }
    }

    public function test_brand_sections_emit_a_heading_even_when_showing_a_logo(): void
    {
        // A logo'd brand used to render an <img> with no heading at all, so the
        // page jumped from the h1 straight to the product cards' h3.
        Brand::create(['name' => 'Medicube', 'logo_path' => 'brands/medicube.webp']);
        $this->makeProduct('Medicube');

        $html = $this->get(route('catalogue.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('<h2', $html);

        $levels = [];
        preg_match_all('/<h([1-6])\b/', $html, $m);
        foreach ($m[1] as $level) {
            $levels[] = (int) $level;
        }

        $this->assertNotEmpty($levels);
        $this->assertSame(1, $levels[0], 'The first heading on the page should be the h1.');

        for ($i = 1; $i < count($levels); $i++) {
            $this->assertLessThanOrEqual(
                $levels[$i - 1] + 1,
                $levels[$i],
                "Heading order skips a level: h{$levels[$i - 1]} followed by h{$levels[$i]}."
            );
        }
    }

    public function test_headings_do_not_skip_when_sorting_outside_brand_grouping(): void
    {
        $this->makeProduct();

        $html = $this->get(route('catalogue.index').'?sort=name')->assertOk()->getContent();

        preg_match_all('/<h([1-6])\b/', $html, $m);
        $levels = array_map('intval', $m[1]);

        for ($i = 1; $i < count($levels); $i++) {
            $this->assertLessThanOrEqual($levels[$i - 1] + 1, $levels[$i], 'Ungrouped grid skips a heading level.');
        }
    }
}
