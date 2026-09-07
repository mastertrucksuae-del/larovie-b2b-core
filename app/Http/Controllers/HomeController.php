<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Category;
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
    private const CATEGORY_LIMIT = 8;

    private const BRAND_LIMIT = 12;

    public function index(): View
    {
        return view('home', [
            'categories' => Category::withCounts()->take(self::CATEGORY_LIMIT),
            'brands' => $this->brands(),
            'featured' => $this->featured(),
        ]);
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
     * @return Collection<int, object{name: string, total: int}>
     */
    private function brands(): Collection
    {
        return Product::query()
            ->publiclyVisible()
            ->get(['brand', 'vendor'])
            ->groupBy(fn (Product $product) => (string) $product->effective_brand)
            ->forget('')
            ->map->count()
            ->sortDesc()
            ->take(self::BRAND_LIMIT)
            ->map(fn (int $total, string $name) => (object) ['name' => $name, 'total' => $total])
            ->values();
    }

    /**
     * Featured grid. Only products with artwork qualify — a card with an empty
     * image well is worse than one fewer card. How many appear is set in
     * Settings -> Homepage.
     *
     * @return Collection<int, Product>
     */
    private function featured(): Collection
    {
        return Product::query()
            ->publiclyVisible()
            ->where(fn (Builder $q) => $q->whereNotNull('featured_image_url')->orWhereNotNull('image_path'))
            ->with(['visibleVariants'])
            ->orderBy('id')
            ->limit(HomeContent::featuredCount())
            ->get();
    }
}
