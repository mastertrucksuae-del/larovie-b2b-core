<?php

namespace App\Support;

use App\Models\InquiryItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Related-product rails for the product page.
 *
 * These exist as much for search as for the shopper: a catalogue of a few
 * hundred products with no cross-links leaves most pages reachable only through
 * the catalogue's infinite scroll, which crawlers do not run. Each rail is a
 * block of plain internal links between pages that genuinely relate.
 *
 * Every rail is scoped to `publiclyVisible()` and excludes the product being
 * viewed, so nothing links to a hidden product or back to itself.
 */
class ProductRecommendations
{
    /**
     * Other products from the same brand.
     *
     * @return Collection<int, Product>
     */
    public static function sameBrand(Product $product, int $limit = 6): Collection
    {
        $brand = $product->effective_brand;

        if (blank($brand)) {
            return collect();
        }

        return self::base($product)
            ->where(fn (Builder $q) => $q->where('brand', $brand)->orWhere(
                fn (Builder $inner) => $inner->whereNull('brand')->where('vendor', $brand)
            ))
            ->limit($limit)
            ->get();
    }

    /**
     * Products in the same derived category.
     *
     * Deliberately biased away from the product's own brand: "more of the same
     * brand" is already its own rail, and a shopper looking at one toner is
     * better served by other toners than by the same maker's shampoo. If the
     * category is thin, same-brand matches are allowed back in to fill it.
     *
     * @return Collection<int, Product>
     */
    public static function similar(Product $product, int $limit = 6): Collection
    {
        $keys = DerivedCategories::keysFor($product->title);

        if ($keys === []) {
            return collect();
        }

        $brand = $product->effective_brand;

        $query = fn () => DerivedCategories::scopeKeys(self::base($product), $keys);

        $others = $query()
            ->when(filled($brand), fn (Builder $q) => $q
                ->where(fn (Builder $inner) => $inner
                    ->whereNot('brand', $brand)
                    ->orWhereNull('brand')))
            ->limit($limit)
            ->get();

        if ($others->count() >= $limit) {
            return $others;
        }

        return $others
            ->concat($query()->whereNotIn('id', $others->pluck('id'))->limit($limit)->get())
            ->take($limit)
            ->values();
    }

    /**
     * Products that appear on the same inquiries as this one.
     *
     * The strongest signal available, because it is drawn from what buyers
     * actually ordered together rather than from anything inferred off a title.
     * Ranked by how many separate inquiries pair them.
     *
     * @return Collection<int, Product>
     */
    public static function boughtTogether(Product $product, int $limit = 6): Collection
    {
        // Inquiry items snapshot the variant, so the join back to a product goes
        // through product_variants rather than a product id on the item.
        $inquiryIds = InquiryItem::query()
            ->whereIn('product_variant_id', $product->variants()->select('id'))
            ->select('inquiry_id');

        $ranked = InquiryItem::query()
            ->join('product_variants', 'product_variants.id', '=', 'inquiry_items.product_variant_id')
            ->whereIn('inquiry_items.inquiry_id', $inquiryIds)
            ->whereNot('product_variants.product_id', $product->id)
            ->groupBy('product_variants.product_id')
            ->orderByRaw('COUNT(DISTINCT inquiry_items.inquiry_id) DESC')
            ->limit($limit)
            ->pluck('product_variants.product_id');

        if ($ranked->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->publiclyVisible()
            ->whereIn('id', $ranked)
            ->with('visibleVariants')
            ->get()
            ->sortBy(fn (Product $p) => $ranked->search($p->id))
            ->values();
    }

    /** Visible products other than the one on screen, cheapest fields eager-loaded. */
    private static function base(Product $product): Builder
    {
        return Product::query()
            ->publiclyVisible()
            ->whereNot('id', $product->id)
            ->with('visibleVariants')
            ->orderBy('id');
    }
}
