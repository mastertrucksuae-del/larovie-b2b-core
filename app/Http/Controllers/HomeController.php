<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\DerivedCategories;
use App\Support\HomeContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Marketing homepage. Every section is driven off the live catalogue rather than
 * hard-coded copy, so the category counts, brand list and featured grid stay
 * honest after each Shopify sync.
 */
class HomeController extends Controller
{
    public const CATEGORY_LIMIT = 8;

    private const BRAND_LIMIT = 12;

    public function index(): View
    {
        return view('home', [
            'categories' => $this->categories(),
            'brands' => $this->brands(),
            'featured' => $this->featured(),
        ]);
    }

    /**
     * Category tiles.
     *
     * Real Shopify collections when any have been imported and made visible;
     * otherwise the keyword-guessed fallback, so a store that has not run a
     * collection sync still gets a working section instead of a blank heading.
     *
     * Both shapes expose the same fields the view reads, so the template does
     * not care which source it got.
     *
     * @return Collection<int, object{label: string, total: int, image: ?string, url: string}>
     */
    private function categories(): Collection
    {
        $imported = Category::forHomepage(self::CATEGORY_LIMIT);

        if ($imported->isNotEmpty()) {
            return $imported->map(fn (Category $category) => (object) [
                'label' => $category->label,
                'total' => (int) $category->visible_products_count,
                'image' => $category->display_image,
                'url' => route('catalogue.index', ['category' => $category->id]),
            ])->values();
        }

        return DerivedCategories::withCounts()
            ->take(self::CATEGORY_LIMIT)
            ->map(fn (object $category) => (object) [
                'label' => $category->label,
                'total' => $category->total,
                'image' => $category->image,
                'url' => route('catalogue.index', ['q' => $category->search]),
            ])
            ->values();
    }

    /**
     * Brands ranked by catalogue depth.
     *
     * Grouped in PHP rather than SQL on purpose. "Brand" means `brand ?: vendor`,
     * expressed by the `effective_brand` accessor; the SQL equivalent
     * (COALESCE(NULLIF(brand, ''), vendor)) is rejected by MariaDB under the
     * ONLY_FULL_GROUP_BY mode Laravel enables on this connection, and spelling the
     * fallback out in two languages invites the definitions to drift. A few
     * hundred two-column rows group instantly.
     *
     * @return Collection<int, object{name: string, total: int, logo: ?string}>
     */
    private function brands(): Collection
    {
        $counts = Product::query()
            ->publiclyVisible()
            ->get(['brand', 'vendor'])
            ->groupBy(fn (Product $product) => (string) $product->effective_brand)
            ->forget('')
            ->map->count();

        // One lookup for the whole strip, keyed by brand name — the same map the
        // catalogue uses, so a logo uploaded once shows in both places.
        $logos = Brand::logoUrlMap();

        $chosen = HomeContent::featuredBrandNames();

        if ($chosen !== []) {
            // Hand-picked, in the order chosen. A brand with nothing visible to
            // sell is dropped: the chip states a product count, so leaving it in
            // would advertise "0 products" and link to an empty search.
            return collect($chosen)
                ->filter(fn (string $name) => ($counts[$name] ?? 0) > 0)
                ->map(fn (string $name) => (object) [
                    'name' => $name,
                    'total' => (int) $counts[$name],
                    'logo' => $logos[$name] ?? null,
                ])
                ->values();
        }

        return $counts
            ->sortDesc()
            ->take(self::BRAND_LIMIT)
            ->map(fn (int $total, string $name) => (object) [
                'name' => $name,
                'total' => $total,
                'logo' => $logos[$name] ?? null,
            ])
            ->values();
    }

    /**
     * Featured grid.
     *
     * Hand-picked in Settings -> Homepage when anything is chosen there;
     * otherwise the earliest visible products that have artwork, since a card
     * with an empty image well is worse than one fewer card.
     *
     * @return Collection<int, Product>
     */
    private function featured(): Collection
    {
        $chosen = HomeContent::featuredProductIds();

        if ($chosen !== []) {
            // Hand-picked, in the order chosen, and shown in full — the count
            // setting governs the automatic fallback, not an explicit choice.
            // Still scoped to publiclyVisible so a product hidden or archived
            // after being picked cannot reappear on the homepage.
            return Product::query()
                ->publiclyVisible()
                ->whereIn('id', $chosen)
                ->with(['visibleVariants'])
                ->get()
                ->sortBy(fn (Product $product) => array_search($product->id, $chosen, true))
                ->values();
        }

        return Product::query()
            ->publiclyVisible()
            ->where(fn (Builder $q) => $q->whereNotNull('featured_image_url')->orWhereNotNull('image_path'))
            ->with(['visibleVariants'])
            ->orderBy('id')
            ->limit(HomeContent::featuredCount())
            ->get();
    }
}
