<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogueComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function seedProducts(int $count, array $attrs = []): void
    {
        Product::factory()->count($count)->create($attrs)->each(
            fn (Product $p) => ProductVariant::factory()->for($p)->create(['wholesale_price' => 50])
        );
    }

    public function test_it_renders_and_paginates_24_at_a_time(): void
    {
        $this->seedProducts(30);

        $component = Livewire::test('catalogue')->assertSet('perPage', 24);
        $this->assertCount(24, $component->instance()->products);

        $component->call('loadMore')->assertSet('perPage', 48);
        $this->assertCount(30, $component->instance()->products);
    }

    public function test_search_filters_by_title(): void
    {
        Product::factory()->create(['title' => 'Rose Petal Serum'])
            ->each(fn (Product $p) => ProductVariant::factory()->for($p)->create());
        Product::factory()->create(['title' => 'Amber Musk Oil'])
            ->each(fn (Product $p) => ProductVariant::factory()->for($p)->create());

        Livewire::test('catalogue')
            ->set('search', 'rose')
            ->assertSee('Rose Petal Serum')
            ->assertDontSee('Amber Musk Oil');
    }

    public function test_search_resets_pagination(): void
    {
        $this->seedProducts(30);

        Livewire::test('catalogue')
            ->call('loadMore')
            ->assertSet('perPage', 48)
            ->set('search', 'x')
            ->assertSet('perPage', 24);
    }

    public function test_hidden_and_archived_products_are_excluded(): void
    {
        Product::factory()->create(['title' => 'Visible One'])
            ->each(fn (Product $p) => ProductVariant::factory()->for($p)->create());
        Product::factory()->hidden()->create(['title' => 'Hidden One']);
        Product::factory()->create(['title' => 'Archived One', 'is_archived' => true]);

        Livewire::test('catalogue')
            ->assertSee('Visible One')
            ->assertDontSee('Hidden One')
            ->assertDontSee('Archived One');
    }

    public function test_sort_by_price_orders_ascending(): void
    {
        $cheap = Product::factory()->create(['title' => 'Cheap', 'vendor' => 'A']);
        ProductVariant::factory()->for($cheap)->create(['wholesale_price' => 10]);
        $pricey = Product::factory()->create(['title' => 'Pricey', 'vendor' => 'B']);
        ProductVariant::factory()->for($pricey)->create(['wholesale_price' => 999]);

        $component = Livewire::test('catalogue')->set('sort', 'price_asc');

        $this->assertSame('Cheap', $component->instance()->products->first()->title);
    }

    public function test_default_sort_puts_the_largest_brand_first(): void
    {
        Product::factory()->create(['title' => 'Solo', 'brand' => 'SmallCo', 'vendor' => 'x'])
            ->each(fn (Product $p) => ProductVariant::factory()->for($p)->create());
        foreach (['A', 'B', 'C'] as $t) {
            Product::factory()->create(['title' => "Big {$t}", 'brand' => 'BigCo', 'vendor' => 'x'])
                ->each(fn (Product $p) => ProductVariant::factory()->for($p)->create());
        }

        $first = Livewire::test('catalogue')->instance()->products->first();

        $this->assertSame('BigCo', $first->effective_brand);
    }

    public function test_search_matches_metaobject_brand(): void
    {
        Product::factory()->create(['title' => 'Toner', 'brand' => 'Anua', 'vendor' => 'Larovie'])
            ->each(fn (Product $p) => ProductVariant::factory()->for($p)->create());
        Product::factory()->create(['title' => 'Cream', 'brand' => 'Medicube', 'vendor' => 'Larovie'])
            ->each(fn (Product $p) => ProductVariant::factory()->for($p)->create());

        Livewire::test('catalogue')
            ->set('search', 'anua')
            ->assertSee('Toner')
            ->assertDontSee('Cream');
    }

    public function test_brand_nav_reflects_products_per_brand(): void
    {
        foreach (range(1, 3) as $i) {
            Product::factory()->create(['title' => "Anua {$i}", 'brand' => 'Anua', 'vendor' => 'x'])
                ->each(fn (Product $p) => ProductVariant::factory()->for($p)->create());
        }
        Product::factory()->create(['title' => 'Abib 1', 'brand' => 'Abib', 'vendor' => 'x'])
            ->each(fn (Product $p) => ProductVariant::factory()->for($p)->create());

        $nav = collect(Livewire::test('catalogue')->instance()->brandNav());

        $this->assertSame(3, $nav->firstWhere('brand', 'Anua')['count']);
        $this->assertSame(1, $nav->firstWhere('brand', 'Abib')['count']);
        // Biggest brand leads the navigation.
        $this->assertSame('Anua', $nav->first()['brand']);
    }

    public function test_bundles_are_excluded_from_the_catalogue(): void
    {
        Product::factory()->create(['title' => 'Solo Serum'])
            ->each(fn (Product $p) => ProductVariant::factory()->for($p)->create());
        Product::factory()->create(['title' => 'Glow Bundle Kit', 'is_bundle' => true])
            ->each(fn (Product $p) => ProductVariant::factory()->for($p)->create());

        Livewire::test('catalogue')
            ->assertSee('Solo Serum')
            ->assertDontSee('Glow Bundle Kit');
    }

    public function test_quick_add_adds_single_variant_product(): void
    {
        $product = Product::factory()->create(['title' => 'One Variant']);
        ProductVariant::factory()->for($product)->create(['moq' => 6]);

        Livewire::test('catalogue')
            ->call('quickAdd', $product->id)
            ->assertDispatched('cart-updated')
            ->assertDispatched('inquiry-open');

        $this->assertSame(6, app(\App\Services\Cart\CartService::class)->totalUnits());
    }
}
