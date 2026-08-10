<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The settings row is memoised statically and PHPUnit shares the process,
        // so a previous test's (rolled-back) instance would leak into this one.
        Setting::clearCache();
        Cache::forget('sitemap.xml');
    }

    private function setIndexing(bool $enabled): void
    {
        Setting::current()->update(['search_indexing_enabled' => $enabled]);
        Setting::clearCache();
    }

    public function test_storefront_is_indexable_out_of_the_box(): void
    {
        // Regression guard: the gate shipped defaulting to OFF, which served
        // noindex + "Disallow: /" + a 404 sitemap on the live site.
        $this->assertTrue(Setting::current()->search_indexing_enabled);

        $this->get(route('catalogue.index'))
            ->assertOk()
            ->assertSee('index, follow', escape: false)
            ->assertDontSee('noindex', escape: false);
    }

    public function test_robots_allows_crawling_but_protects_private_paths(): void
    {
        $this->setIndexing(true);

        $response = $this->get('/robots.txt')->assertOk();
        $body = $response->getContent();

        $this->assertStringContainsString('Allow: /', $body);
        $this->assertStringNotContainsString("\nDisallow: /\n", $body);
        $this->assertStringContainsString('Disallow: /account', $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $body);
    }

    public function test_sitemap_lists_visible_products(): void
    {
        $this->setIndexing(true);

        $product = Product::factory()->create(['is_visible' => true, 'is_archived' => false]);
        ProductVariant::factory()->for($product)->create(['is_visible' => true, 'is_archived' => false]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(route('catalogue.index'), escape: false)
            ->assertSee(route('catalogue.show', $product->handle), escape: false);
    }

    public function test_disabling_indexing_closes_everything_off(): void
    {
        $this->setIndexing(false);

        $this->get(route('catalogue.index'))
            ->assertOk()
            ->assertSee('noindex, nofollow', escape: false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /', escape: false);

        $this->get(route('sitemap'))->assertNotFound();
    }

    public function test_pages_carry_canonical_and_structured_data(): void
    {
        $this->get(route('catalogue.index'))
            ->assertOk()
            ->assertSee('rel="canonical"', escape: false)
            ->assertSee('"@type":"Organization"', escape: false)
            ->assertSee('"@type":"WebSite"', escape: false);
    }

    public function test_product_page_emits_product_and_breadcrumb_schema(): void
    {
        $product = Product::factory()->create(['is_visible' => true, 'is_archived' => false]);
        ProductVariant::factory()->for($product)->create([
            'is_visible' => true,
            'is_archived' => false,
            'wholesale_price' => 120.50,
        ]);

        $this->get(route('catalogue.show', $product->handle))
            ->assertOk()
            ->assertSee('"@type":"Product"', escape: false)
            ->assertSee('"@type":"BreadcrumbList"', escape: false)
            ->assertSee('"@type":"AggregateOffer"', escape: false);
    }
}
