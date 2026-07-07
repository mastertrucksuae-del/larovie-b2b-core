<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Shopify\ProductSyncService;
use App\Services\Shopify\ShopifyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopifySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function productNode(int $id, string $title, array $variants): array
    {
        return [
            'legacyResourceId' => $id,
            'title' => $title,
            'handle' => str($title)->slug(),
            'descriptionHtml' => '<p>desc</p>',
            'vendor' => 'Larovie',
            'productType' => 'Fragrance',
            'status' => 'ACTIVE',
            'tags' => ['new'],
            'featuredImage' => ['url' => 'https://img/'.$id.'.jpg'],
            'variants' => ['nodes' => $variants],
        ];
    }

    protected function variantNode(int $id, string $title): array
    {
        return [
            'legacyResourceId' => $id,
            'sku' => 'SKU-'.$id,
            'title' => $title,
            'selectedOptions' => [['name' => 'Size', 'value' => $title]],
            'image' => ['url' => 'https://img/v'.$id.'.jpg'],
            'inventoryQuantity' => 100,
        ];
    }

    /**
     * @param  array<string,list<int>>  $brands  brandName => [productLegacyId, ...]
     */
    protected function syncWith(array $nodes, array $brands = []): array
    {
        $client = new class($nodes, $brands) extends ShopifyClient
        {
            public function __construct(public array $nodes, public array $brands)
            {
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function query(string $query, array $variables = []): array
            {
                // The sync first asks for brand metaobjects, then products.
                if (str_contains($query, 'metaobjects')) {
                    $nodes = [];
                    foreach ($this->brands as $name => $ids) {
                        $nodes[] = [
                            'name' => ['value' => $name],
                            'featured_products' => ['references' => [
                                'nodes' => array_map(fn ($id) => ['legacyResourceId' => $id], $ids),
                            ]],
                        ];
                    }

                    return ['metaobjects' => [
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        'nodes' => $nodes,
                    ]];
                }

                return ['products' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => $this->nodes,
                ]];
            }
        };

        return (new ProductSyncService($client))->sync();
    }

    public function test_resync_preserves_admin_owned_fields(): void
    {
        // Initial import
        $this->syncWith([
            $this->productNode(1, 'Rose Elixir', [$this->variantNode(11, '50ml')]),
        ]);

        $product = Product::where('shopify_product_id', 1)->first();
        $variant = ProductVariant::where('shopify_variant_id', 11)->first();

        // New products default to hidden, no price, MOQ 12.
        $this->assertFalse($product->is_visible);
        $this->assertSame(12, $product->moq);
        $this->assertNull($variant->wholesale_price);

        // Admin curates the catalogue.
        $product->update(['is_visible' => true, 'moq' => 10]);
        $variant->update(['wholesale_price' => 99.50, 'moq' => 6, 'is_visible' => false]);

        // Re-sync with an updated title (Shopify-owned change).
        $this->syncWith([
            $this->productNode(1, 'Rose Elixir Deluxe', [$this->variantNode(11, '50ml Refined')]),
        ]);

        $product->refresh();
        $variant->refresh();

        // Shopify-owned fields updated…
        $this->assertSame('Rose Elixir Deluxe', $product->title);
        $this->assertSame('50ml Refined', $variant->title);

        // …admin-owned fields preserved.
        $this->assertTrue($product->is_visible);
        $this->assertSame(10, $product->moq);
        $this->assertSame('99.50', (string) $variant->wholesale_price);
        $this->assertSame(6, $variant->moq);
        $this->assertFalse($variant->is_visible);
    }

    public function test_brand_is_resolved_from_the_brands_metaobject(): void
    {
        // Product 1 belongs to the "Anua" brand's featured_products; product 2 does not.
        $this->syncWith(
            nodes: [
                $this->productNode(101, 'Heartleaf Toner', [$this->variantNode(1101, 'Default')]),
                $this->productNode(102, 'Unbranded Item', [$this->variantNode(1102, 'Default')]),
            ],
            brands: ['Anua' => [101]],
        );

        $this->assertSame('Anua', Product::where('shopify_product_id', 101)->value('brand'));
        $this->assertNull(Product::where('shopify_product_id', 102)->value('brand'));
    }

    public function test_bundles_are_auto_detected_on_import(): void
    {
        $this->syncWith([
            $this->productNode(201, 'Radiance Glow Kit (4pcs)', [$this->variantNode(2101, 'Default')]),
            $this->productNode(202, 'Heartleaf Toner', [$this->variantNode(2102, 'Default')]),
        ]);

        $this->assertTrue(Product::where('shopify_product_id', 201)->value('is_bundle'));
        $this->assertFalse(Product::where('shopify_product_id', 202)->value('is_bundle'));
    }

    public function test_missing_products_are_archived_not_deleted(): void
    {
        $this->syncWith([
            $this->productNode(1, 'Kept', [$this->variantNode(11, 'A')]),
            $this->productNode(2, 'Gone', [$this->variantNode(21, 'B')]),
        ]);

        $this->assertSame(2, Product::count());

        // Second sync drops product 2.
        $summary = $this->syncWith([
            $this->productNode(1, 'Kept', [$this->variantNode(11, 'A')]),
        ]);

        // Nothing deleted.
        $this->assertSame(2, Product::count());

        $this->assertFalse(Product::where('shopify_product_id', 1)->first()->is_archived);
        $this->assertTrue(Product::where('shopify_product_id', 2)->first()->is_archived);
        $this->assertTrue(ProductVariant::where('shopify_variant_id', 21)->first()->is_archived);
        $this->assertSame(1, $summary['archived']);
    }
}
