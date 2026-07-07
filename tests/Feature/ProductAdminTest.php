<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_edit_updates_selected_products_and_variants(): void
    {
        $this->actingAs(User::factory()->create());

        $a = Product::factory()->create(['is_visible' => false, 'moq' => 1, 'is_bundle' => false]);
        ProductVariant::factory()->count(2)->for($a)->create(['wholesale_price' => null]);
        $b = Product::factory()->create(['is_visible' => false, 'moq' => 1]);
        ProductVariant::factory()->for($b)->create(['wholesale_price' => null]);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkEdit', [$a->getKey(), $b->getKey()], data: [
                'is_visible' => '1',
                'is_bundle' => 'keep',
                'moq' => 24,
                'wholesale_price' => 99.5,
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue($a->refresh()->is_visible);
        $this->assertSame(24, $a->moq);
        $this->assertSame(24, $b->refresh()->moq);
        // Wholesale price applied to every variant.
        $this->assertEquals(99.5, $a->variants()->first()->wholesale_price);
        $this->assertSame(2, $a->variants()->where('wholesale_price', 99.5)->count());
    }

    public function test_blank_bulk_edit_fields_keep_existing_values(): void
    {
        $this->actingAs(User::factory()->create());

        $p = Product::factory()->create(['moq' => 12, 'is_visible' => true]);
        ProductVariant::factory()->for($p)->create(['wholesale_price' => 50]);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkEdit', [$p], data: [
                'is_visible' => 'keep',
                'is_bundle' => 'keep',
                'moq' => null,
                'wholesale_price' => null,
            ]);

        $p->refresh();
        $this->assertSame(12, $p->moq);
        $this->assertTrue($p->is_visible);
        $this->assertEquals(50, $p->variants()->first()->wholesale_price);
    }

    public function test_image_override_takes_precedence_over_shopify_image(): void
    {
        $product = Product::factory()->create([
            'featured_image_url' => 'https://cdn.shopify.test/a.jpg',
            'image_path' => null,
        ]);

        $this->assertSame('https://cdn.shopify.test/a.jpg', $product->display_image);

        $product->update(['image_path' => 'product-images/custom.jpg']);
        $this->assertStringContainsString('product-images/custom.jpg', $product->fresh()->display_image);
    }
}
