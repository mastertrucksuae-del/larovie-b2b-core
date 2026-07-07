<?php

namespace App\Services\Shopify;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Imports products + variants from Shopify into the local catalogue.
 *
 * Admin-owned fields are NEVER overwritten:
 *   Product:  is_visible, moq
 *   Variant:  wholesale_price, moq, is_visible
 *
 * Products/variants absent from the latest sync are soft-archived
 * (is_archived = true), never hard-deleted, to protect inquiry snapshots.
 */
class ProductSyncService
{
    /** @var array{created:int, updated:int, archived:int, variants:int} */
    protected array $summary = [
        'created' => 0,
        'updated' => 0,
        'archived' => 0,
        'variants' => 0,
    ];

    /** @var list<int> */
    protected array $seenProductIds = [];

    /** @var list<int> */
    protected array $seenVariantIds = [];

    /** @var array<int,string> productLegacyId => brand name (from the Brands metaobject) */
    protected array $brandMap = [];

    public function __construct(protected ShopifyClient $client) {}

    /**
     * Run a full catalogue sync.
     *
     * @return array{created:int, updated:int, archived:int, variants:int}
     */
    public function sync(): array
    {
        $this->buildBrandMap();

        $cursor = null;
        $pageSize = (int) config('shopify.page_size', 50);

        do {
            $data = $this->client->query($this->productsQuery(), [
                'cursor' => $cursor,
                'pageSize' => $pageSize,
            ]);

            $connection = $data['products'] ?? ['nodes' => [], 'pageInfo' => []];

            foreach ($connection['nodes'] ?? [] as $node) {
                $this->upsertProduct($node);
            }

            $pageInfo = $connection['pageInfo'] ?? [];
            $cursor = ($pageInfo['hasNextPage'] ?? false) ? ($pageInfo['endCursor'] ?? null) : null;
        } while ($cursor !== null);

        $this->archiveMissing();

        $setting = Setting::current();
        $setting->last_synced_at = now();
        $setting->save();
        Setting::clearCache();

        return $this->summary;
    }

    protected function upsertProduct(array $node): void
    {
        $shopifyId = (int) ($node['legacyResourceId'] ?? 0);
        if ($shopifyId === 0) {
            return;
        }

        $this->seenProductIds[] = $shopifyId;

        // Shopify-owned fields only — admin fields (is_visible, moq) are excluded.
        $ownedAttributes = [
            'title' => $node['title'] ?? '',
            'handle' => $node['handle'] ?? null,
            'description' => $node['descriptionHtml'] ?? null,
            'vendor' => $node['vendor'] ?? null,
            'product_type' => $node['productType'] ?? null,
            'tags' => $node['tags'] ?? [],
            'featured_image_url' => $node['featuredImage']['url'] ?? null,
            'shopify_status' => isset($node['status']) ? strtolower((string) $node['status']) : null,
            'brand' => $this->brandMap[$shopifyId] ?? null,
            'synced_at' => now(),
            'is_archived' => false,
        ];

        $product = Product::firstOrNew(['shopify_product_id' => $shopifyId]);

        if ($product->exists) {
            $this->summary['updated']++;
        } else {
            $this->summary['created']++;
            // Sensible defaults for admin fields on first import.
            $product->is_visible = false;
            $product->moq = (int) config('shopify.default_moq', 12);
            // Auto-detect bundles once, on import; admin can override afterwards.
            $product->is_bundle = \App\Support\BundleDetector::isBundle(
                $ownedAttributes['title'] ?? null,
                $ownedAttributes['product_type'] ?? null,
                $ownedAttributes['tags'] ?? [],
            );
        }

        $product->fill($ownedAttributes)->save();

        foreach ($node['variants']['nodes'] ?? [] as $variantNode) {
            $this->upsertVariant($product, $variantNode);
        }
    }

    protected function upsertVariant(Product $product, array $node): void
    {
        $shopifyId = (int) ($node['legacyResourceId'] ?? 0);
        if ($shopifyId === 0) {
            return;
        }

        $this->seenVariantIds[] = $shopifyId;
        $this->summary['variants']++;

        // Shopify-owned fields only — admin fields (wholesale_price, moq, is_visible) excluded.
        $ownedAttributes = [
            'product_id' => $product->id,
            'sku' => $node['sku'] ?? null,
            'title' => $node['title'] ?? null,
            'options' => $node['selectedOptions'] ?? [],
            'image_url' => $node['image']['url'] ?? $product->featured_image_url,
            'inventory_quantity' => $node['inventoryQuantity'] ?? null,
            'is_archived' => false,
        ];

        $variant = ProductVariant::firstOrNew(['shopify_variant_id' => $shopifyId]);

        if (! $variant->exists) {
            // Default visibility for brand-new variants.
            $variant->is_visible = true;
        }

        $variant->fill($ownedAttributes)->save();
    }

    /**
     * Soft-archive any product/variant not present in this sync run.
     */
    protected function archiveMissing(): void
    {
        $archivedProducts = Product::where('is_archived', false)
            ->when(
                ! empty($this->seenProductIds),
                fn ($q) => $q->whereNotIn('shopify_product_id', $this->seenProductIds)
            )
            ->update(['is_archived' => true]);

        ProductVariant::where('is_archived', false)
            ->when(
                ! empty($this->seenVariantIds),
                fn ($q) => $q->whereNotIn('shopify_variant_id', $this->seenVariantIds)
            )
            ->update(['is_archived' => true]);

        $this->summary['archived'] = (int) $archivedProducts;
    }

    /**
     * Build the product→brand map from the Shopify "Brands" metaobject.
     * Each brand entry lists its products in the `featured_products` field.
     */
    protected function buildBrandMap(): void
    {
        $cursor = null;

        do {
            $data = $this->client->query($this->brandsQuery(), ['cursor' => $cursor]);
            $connection = $data['metaobjects'] ?? ['nodes' => [], 'pageInfo' => []];

            foreach ($connection['nodes'] ?? [] as $node) {
                $name = trim((string) ($node['name']['value'] ?? ''));
                if ($name === '') {
                    continue;
                }

                foreach ($node['featured_products']['references']['nodes'] ?? [] as $ref) {
                    $legacyId = (int) ($ref['legacyResourceId'] ?? 0);
                    if ($legacyId !== 0) {
                        $this->brandMap[$legacyId] = $name;
                    }
                }
            }

            $pageInfo = $connection['pageInfo'] ?? [];
            $cursor = ($pageInfo['hasNextPage'] ?? false) ? ($pageInfo['endCursor'] ?? null) : null;
        } while ($cursor !== null);
    }

    protected function brandsQuery(): string
    {
        return <<<'GRAPHQL'
        query Brands($cursor: String) {
          metaobjects(type: "brand", first: 100, after: $cursor) {
            pageInfo { hasNextPage endCursor }
            nodes {
              name: field(key: "name") { value }
              featured_products: field(key: "featured_products") {
                references(first: 250) {
                  nodes { ... on Product { legacyResourceId } }
                }
              }
            }
          }
        }
        GRAPHQL;
    }

    protected function productsQuery(): string
    {
        return <<<'GRAPHQL'
        query Products($cursor: String, $pageSize: Int!) {
          products(first: $pageSize, after: $cursor) {
            pageInfo { hasNextPage endCursor }
            nodes {
              legacyResourceId
              title
              handle
              descriptionHtml
              vendor
              productType
              status
              tags
              featuredImage { url }
              variants(first: 100) {
                nodes {
                  legacyResourceId
                  sku
                  title
                  price
                  inventoryQuantity
                  selectedOptions { name value }
                  image { url }
                }
              }
            }
          }
        }
        GRAPHQL;
    }
}
