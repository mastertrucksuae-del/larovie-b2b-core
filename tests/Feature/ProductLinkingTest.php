<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductRecommendations;
use App\Support\RecentlyViewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Internal linking on the product page.
 *
 * These rails are the storefront's only crawlable path between product pages —
 * the catalogue is an infinite scroll — so "does it render links" matters as
 * much as "are the suggestions good".
 */
class ProductLinkingTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $title, ?string $brand = 'Anua', bool $visible = true): Product
    {
        $product = Product::factory()->create([
            'title' => $title,
            'brand' => $brand,
            'is_visible' => $visible,
            'is_archived' => false,
            'is_bundle' => false,
            'featured_image_url' => 'https://cdn.shopify.com/x.jpg',
        ]);

        ProductVariant::factory()->for($product)->create([
            'is_visible' => true,
            'is_archived' => false,
        ]);

        return $product;
    }

    // ── Same brand ──────────────────────────────────────────────────────────

    public function test_same_brand_rail_lists_stablemates_only(): void
    {
        $subject = $this->product('Heartleaf Toner');
        $sibling = $this->product('Heartleaf Cleansing Oil');
        $other = $this->product('Dynasty Cream', 'Beauty of Joseon');

        $ids = ProductRecommendations::sameBrand($subject)->pluck('id');

        $this->assertTrue($ids->contains($sibling->id));
        $this->assertFalse($ids->contains($other->id), 'A different brand must not appear.');
        $this->assertFalse($ids->contains($subject->id), 'A product must never link to itself.');
    }

    public function test_rails_never_surface_a_hidden_product(): void
    {
        $subject = $this->product('Heartleaf Toner');
        $hidden = $this->product('Secret Toner', 'Anua', visible: false);

        $this->assertFalse(ProductRecommendations::sameBrand($subject)->pluck('id')->contains($hidden->id));
        $this->assertFalse(ProductRecommendations::similar($subject)->pluck('id')->contains($hidden->id));
    }

    public function test_same_brand_rail_is_empty_when_the_brand_is_unknown(): void
    {
        $subject = $this->product('Mystery Item', brand: null);

        $this->assertTrue(ProductRecommendations::sameBrand($subject)->isEmpty());
    }

    // ── Similar ─────────────────────────────────────────────────────────────

    public function test_similar_rail_matches_on_category_not_brand(): void
    {
        $subject = $this->product('Heartleaf Toner');
        $otherToner = $this->product('Rice Toner', 'Round Lab');
        $unrelated = $this->product('Sun Stick', 'Round Lab');

        $ids = ProductRecommendations::similar($subject)->pluck('id');

        $this->assertTrue($ids->contains($otherToner->id), 'Another toner should be suggested.');
        $this->assertFalse($ids->contains($unrelated->id), 'A sun product is not similar to a toner.');
    }

    public function test_similar_rail_falls_back_to_the_same_brand_when_the_category_is_thin(): void
    {
        // Only one other toner exists and it shares the brand, so excluding the
        // brand outright would leave the rail empty rather than useful.
        $subject = $this->product('Heartleaf Toner');
        $sibling = $this->product('Rice Toner');

        $this->assertTrue(
            ProductRecommendations::similar($subject)->pluck('id')->contains($sibling->id)
        );
    }

    // ── Often requested together ────────────────────────────────────────────

    public function test_bought_together_ranks_by_how_often_products_share_an_inquiry(): void
    {
        $subject = $this->product('Heartleaf Toner');
        $frequent = $this->product('Cleansing Oil');
        $occasional = $this->product('Sun Cream');

        // Two inquiries pair the subject with $frequent, one with $occasional.
        foreach ([[$frequent, $occasional], [$frequent, null]] as [$a, $b]) {
            $inquiry = Inquiry::factory()->create();

            foreach (array_filter([$subject, $a, $b]) as $product) {
                InquiryItem::factory()->for($inquiry)->create([
                    'product_variant_id' => $product->variants()->first()->id,
                ]);
            }
        }

        $ranked = ProductRecommendations::boughtTogether($subject);

        $this->assertSame($frequent->id, $ranked->first()->id, 'The most frequent pairing should rank first.');
        $this->assertTrue($ranked->pluck('id')->contains($occasional->id));
        $this->assertFalse($ranked->pluck('id')->contains($subject->id));
    }

    public function test_bought_together_is_empty_when_nothing_has_been_ordered_with_it(): void
    {
        $this->assertTrue(ProductRecommendations::boughtTogether($this->product('Lonely Serum'))->isEmpty());
    }

    // ── Recently viewed ─────────────────────────────────────────────────────

    public function test_recently_viewed_returns_the_newest_first(): void
    {
        $first = $this->product('First Toner');
        $second = $this->product('Second Toner');

        RecentlyViewed::record($first);
        RecentlyViewed::record($second);

        $this->assertSame(
            [$second->id, $first->id],
            RecentlyViewed::products()->pluck('id')->all()
        );
    }

    public function test_viewing_a_product_again_moves_it_to_the_front_without_duplicating(): void
    {
        $a = $this->product('A Toner');
        $b = $this->product('B Toner');

        RecentlyViewed::record($a);
        RecentlyViewed::record($b);
        RecentlyViewed::record($a);

        $this->assertSame([$a->id, $b->id], RecentlyViewed::products()->pluck('id')->all());
    }

    public function test_browsing_a_product_page_records_it_but_keeps_it_out_of_its_own_rail(): void
    {
        $earlier = $this->product('Earlier Toner');
        $current = $this->product('Current Toner');

        $this->get(route('catalogue.show', $earlier->handle))->assertOk();

        $response = $this->get(route('catalogue.show', $current->handle))->assertOk();

        $rail = $response->viewData('recentlyViewed');

        $this->assertTrue($rail->pluck('id')->contains($earlier->id));
        $this->assertFalse($rail->pluck('id')->contains($current->id), 'A page must not list itself as recently viewed.');
        $this->assertContains($current->id, RecentlyViewed::ids(), 'The visit should still be recorded.');
    }

    // ── The rendered page ───────────────────────────────────────────────────

    public function test_the_product_page_renders_crawlable_links_to_related_products(): void
    {
        $subject = $this->product('Heartleaf Toner');
        $sibling = $this->product('Heartleaf Cleansing Oil');

        $html = $this->get(route('catalogue.show', $subject->handle))->assertOk()->getContent();

        $this->assertStringContainsString(route('catalogue.show', $sibling->handle), $html);
        $this->assertStringContainsString(__('shop.rail_same_brand', ['brand' => 'Anua']), $html);
    }

    public function test_rails_are_hidden_entirely_when_there_is_nothing_to_show(): void
    {
        $lonely = $this->product('Solitary Item', brand: null);

        $html = $this->get(route('catalogue.show', $lonely->handle))->assertOk()->getContent();

        $this->assertStringNotContainsString(__('shop.rail_recently_viewed'), $html);
        $this->assertStringNotContainsString(__('shop.rail_bought_together'), $html);
    }

    public function test_rail_headings_are_translated(): void
    {
        $subject = $this->product('Heartleaf Toner');
        $this->product('Heartleaf Cleansing Oil');

        $this->get(route('catalogue.show', $subject->handle).'?hl=ar')
            ->assertOk()
            ->assertSee(__('shop.rail_same_brand', ['brand' => 'Anua'], 'ar'));
    }
}
