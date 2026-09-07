<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ProductRecommendations;
use App\Support\RecentlyViewed;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    public function index(): View
    {
        // The catalogue grid (search, sort, infinite scroll) is a Livewire component.
        return view('catalogue.index');
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_visible && ! $product->is_archived, 404);

        $product->load(['variants' => fn ($q) => $q->where('is_visible', true)
            ->where('is_archived', false)
            ->orderBy('id')]);

        abort_if($product->variants->isEmpty(), 404);

        // Read the trail before recording this visit, so the rail shows what the
        // buyer looked at *before* this page rather than leading with it.
        $recentlyViewed = RecentlyViewed::products($product);
        RecentlyViewed::record($product);

        return view('catalogue.show', [
            'product' => $product,
            'sameBrand' => ProductRecommendations::sameBrand($product),
            'similar' => ProductRecommendations::similar($product),
            'boughtTogether' => ProductRecommendations::boughtTogether($product),
            'recentlyViewed' => $recentlyViewed,
        ]);
    }
}
