<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    /**
     * Google drops an entire hreflang cluster if an alternate URL canonicalises
     * somewhere else. Previously every page advertised ?hl=en and ?hl=ar while
     * both canonicalised to the bare URL, so the Arabic pages were never
     * indexable and the annotations did nothing.
     */
    public function test_each_hreflang_alternate_is_self_canonical(): void
    {
        $html = $this->get(route('catalogue.index'))->assertOk()->getContent();

        preg_match_all('/<link rel="alternate" hreflang="([^"]+)" href="([^"]+)">/', $html, $m, PREG_SET_ORDER);
        $this->assertNotEmpty($m, 'No hreflang alternates rendered.');

        foreach ($m as [, $lang, $href]) {
            $target = $this->get($href)->assertOk()->getContent();

            preg_match('/<link rel="canonical" href="([^"]+)">/', $target, $c);
            $this->assertNotEmpty($c, "No canonical on the {$lang} alternate.");

            $this->assertSame(
                $href,
                $c[1],
                "hreflang=\"{$lang}\" points at {$href}, which canonicalises to {$c[1]}."
            );
        }
    }

    public function test_arabic_alternate_actually_serves_arabic(): void
    {
        $this->get(route('catalogue.index').'?hl=ar')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl"', escape: false);
    }

    /**
     * Google re-checks the verification file periodically and revokes access to
     * Search Console if it disappears, so this guards against it being tidied
     * away. It must sit in public/ — the project root is never served.
     */
    public function test_google_site_verification_file_is_in_the_web_root(): void
    {
        $files = glob(public_path('google*.html'));

        $this->assertNotEmpty($files, 'The Google Search Console verification file is missing from public/.');

        foreach ($files as $file) {
            $this->assertStringContainsString(
                'google-site-verification:',
                (string) file_get_contents($file),
                basename($file).' does not contain the verification token.'
            );
        }
    }

    public function test_robots_does_not_block_the_verification_file(): void
    {
        $this->setIndexing(true);

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        foreach (glob(public_path('google*.html')) as $file) {
            $this->assertStringNotContainsString(
                'Disallow: /'.basename($file),
                $body
            );
        }
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
