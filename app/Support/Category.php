<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Product categories for the homepage "Shop by Category" grid.
 *
 * STOPGAP — read before extending. The catalogue has no category taxonomy of its
 * own: `products.product_type` is empty for ~80% of visible products and holds a
 * unique marketing sentence for the rest, `tags` covers well under a third, and
 * Shopify collections are not imported by the sync at all. So categories are
 * derived here by matching keywords against the product title.
 *
 * That is accurate enough to browse by (a "Deep Pore-Detox Foam Cleanser" really
 * is a cleanser) but it is inference, not data. The durable fix is to import
 * Shopify collections during the sync and group on those; when that lands, delete
 * this class rather than growing the keyword list.
 *
 * A product may match more than one category, which is intended — a "Sun Serum"
 * belongs under both Sun Care and Serums.
 */
class Category
{
    /**
     * Keyword patterns per category, matched case-insensitively against the title.
     * `search` is the term handed to the catalogue's `?q=` filter when the card is
     * clicked, so it must be a substring the catalogue search can match too.
     *
     * @var array<string, array{search: string, keywords: array<int, string>}>
     */
    public const MAP = [
        'cleansers' => ['search' => 'Cleanser', 'keywords' => ['cleanser', 'cleansing', 'foam', 'micellar']],
        'serums' => ['search' => 'Serum', 'keywords' => ['serum', 'ampoule', 'essence']],
        'moisturizers' => ['search' => 'Cream', 'keywords' => ['cream', 'moistur', 'lotion', 'balm']],
        'toners' => ['search' => 'Toner', 'keywords' => ['toner', 'pad']],
        'sun_care' => ['search' => 'Sun', 'keywords' => ['sunscreen', 'sun ', 'spf', 'uv ']],
        'masks' => ['search' => 'Mask', 'keywords' => ['mask', 'patch']],
        'haircare' => ['search' => 'Hair', 'keywords' => ['shampoo', 'conditioner', 'hair', 'scalp']],
        'body_care' => ['search' => 'Body', 'keywords' => ['body', 'hand ', 'foot']],
    ];

    /**
     * Categories that actually have products, richest first, each with a
     * representative image.
     *
     * One query for the whole catalogue rather than one per category: at a few
     * hundred visible rows the classification is cheaper in PHP than eight
     * REGEXP scans, and it keeps the matching rules in a single place.
     *
     * @return Collection<int, object{key: string, label: string, search: string, total: int, image: ?string}>
     */
    public static function withCounts(): Collection
    {
        $products = Product::query()
            ->publiclyVisible()
            ->orderBy('id')
            ->get(['id', 'title', 'featured_image_url', 'image_path']);

        return collect(self::MAP)
            ->map(function (array $definition, string $key) use ($products) {
                $matches = $products->filter(
                    fn (Product $product) => self::matches($product->title, $definition['keywords'])
                );

                return (object) [
                    'key' => $key,
                    'label' => __("shop.cat_{$key}"),
                    'search' => $definition['search'],
                    'total' => $matches->count(),
                    'image' => optional($matches->first(fn (Product $p) => filled($p->display_image)))->display_image,
                ];
            })
            ->filter(fn (object $category) => $category->total > 0)
            ->sortByDesc('total')
            ->values();
    }

    /**
     * The category keys a product falls under, richest match first.
     *
     * Used to find "similar products": two items in the same derived category
     * are a far better suggestion than two that merely share a vendor.
     *
     * @return array<int, string>
     */
    public static function keysFor(?string $title): array
    {
        return collect(self::MAP)
            ->filter(fn (array $definition) => self::matches($title, $definition['keywords']))
            ->keys()
            ->all();
    }

    /**
     * A `where` fragment matching any product in the given categories.
     *
     * @param  array<int, string>  $keys
     */
    public static function scopeKeys(\Illuminate\Database\Eloquent\Builder $query, array $keys): \Illuminate\Database\Eloquent\Builder
    {
        $keywords = collect($keys)
            ->flatMap(fn (string $key) => self::MAP[$key]['keywords'] ?? [])
            ->unique();

        if ($keywords->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($keywords) {
            foreach ($keywords as $keyword) {
                $q->orWhere('title', 'like', '%'.$keyword.'%');
            }
        });
    }

    /** @param  array<int, string>  $keywords */
    private static function matches(?string $title, array $keywords): bool
    {
        if (blank($title)) {
            return false;
        }

        $haystack = mb_strtolower($title);

        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
