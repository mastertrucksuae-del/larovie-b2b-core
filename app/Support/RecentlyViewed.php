<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * The visitor's recently viewed products.
 *
 * Session-backed, matching how the inquiry cart already works: buyers browse
 * without an account, so there is nothing to hang this off server-side, and a
 * browsing trail is not worth a database write per page view.
 */
class RecentlyViewed
{
    private const SESSION_KEY = 'recently_viewed';

    /** How many ids are remembered. Kept small — this is a rail, not a history. */
    private const MAX = 12;

    /** Push a product to the front of the trail. */
    public static function record(Product $product): void
    {
        $ids = collect(self::ids())
            ->reject(fn (int $id) => $id === $product->id)
            ->prepend($product->id)
            ->take(self::MAX)
            ->values()
            ->all();

        session()->put(self::SESSION_KEY, $ids);
    }

    /** @return array<int, int> */
    public static function ids(): array
    {
        return array_values(array_filter(
            (array) session()->get(self::SESSION_KEY, []),
            fn ($id) => is_int($id) || ctype_digit((string) $id),
        ));
    }

    /**
     * Recently viewed products, most recent first.
     *
     * Ordered in PHP: the ids carry the recency, and SQL would hand them back in
     * whatever order it liked. Products that have since been hidden or archived
     * drop out because the query is scoped to what is publicly visible.
     *
     * @return Collection<int, Product>
     */
    public static function products(?Product $exclude = null, int $limit = 6): Collection
    {
        $ids = collect(self::ids())
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $exclude && $id === $exclude->id);

        if ($ids->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->publiclyVisible()
            ->whereIn('id', $ids)
            ->with('visibleVariants')
            ->get()
            ->sortBy(fn (Product $product) => $ids->search($product->id))
            ->take($limit)
            ->values();
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
