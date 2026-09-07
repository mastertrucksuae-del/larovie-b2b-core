<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * A product category, imported from a Shopify collection.
 *
 * Shopify owns the title, handle and artwork; the admin owns visibility,
 * ordering, the Arabic name and an image override. Nothing admin-owned is
 * touched by a sync.
 */
class Category extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_visible' => 'boolean',
        'is_archived' => 'boolean',
        'sort_order' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /** Products in this category that the storefront may actually show. */
    public function visibleProducts(): BelongsToMany
    {
        return $this->products()->publiclyVisible();
    }

    /** Categories the storefront may show, in the admin's order. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true)
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->orderBy('title');
    }

    /**
     * The name in the active locale.
     *
     * Arabic is optional: a category imported today should still render on the
     * Arabic site rather than showing a blank tile, so it falls back to the
     * Shopify title until someone translates it.
     */
    public function getLabelAttribute(): string
    {
        if (app()->getLocale() === 'ar' && filled($this->title_ar)) {
            return $this->title_ar;
        }

        return (string) $this->title;
    }

    /**
     * Artwork for this category, in order of preference.
     *
     * Admin override, then the Shopify collection image, then a product from
     * inside the category. That last step matters: plenty of Shopify collections
     * carry no image at all, and without it both the homepage tile and the admin
     * list render an empty grey box.
     */
    public function getDisplayImageAttribute(): ?string
    {
        if (filled($this->image_path)) {
            return Storage::disk('public')->url($this->image_path);
        }

        if (filled($this->image_url)) {
            return $this->image_url;
        }

        return $this->firstProductImage();
    }

    /**
     * A product image to stand in for the category.
     *
     * Uses an already-loaded relation when the caller eager-loaded one, so a
     * list of categories does not fire a query per row.
     */
    private function firstProductImage(): ?string
    {
        $products = $this->relationLoaded('products')
            ? $this->products
            : $this->products()
                ->publiclyVisible()
                ->where(function ($q) {
                    $q->whereNotNull('featured_image_url')->orWhereNotNull('image_path');
                })
                ->limit(1)
                ->get();

        return optional($products->first(fn ($product) => filled($product->display_image)))->display_image;
    }
}
