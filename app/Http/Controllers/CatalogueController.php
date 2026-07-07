<?php

namespace App\Http\Controllers;

use App\Models\Product;
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

        return view('catalogue.show', [
            'product' => $product,
        ]);
    }
}
