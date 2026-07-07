<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'tags' => 'array',
        'is_visible' => 'boolean',
        'is_archived' => 'boolean',
        'is_bundle' => 'boolean',
        'moq' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** Variants that should appear in the public catalogue. */
    public function visibleVariants(): HasMany
    {
        return $this->variants()->where('is_visible', true)->where('is_archived', false);
    }

    /** Products shown in the public wholesale catalogue (solo products only). */
    public function scopePubliclyVisible($query)
    {
        return $query->where('is_visible', true)
            ->where('is_archived', false)
            ->where('is_bundle', false);
    }

    /** Brand from the Shopify "Brands" metaobject, falling back to the vendor field. */
    public function getEffectiveBrandAttribute(): ?string
    {
        return filled($this->brand) ? $this->brand : $this->vendor;
    }

    /** Admin image override if uploaded, otherwise the Shopify featured image. */
    public function getDisplayImageAttribute(): ?string
    {
        return filled($this->image_path)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path)
            : $this->featured_image_url;
    }

    /** Cheapest wholesale price across visible variants, or null if none priced. */
    public function getStartingPriceAttribute(): ?float
    {
        return $this->variants
            ->where('is_visible', true)
            ->where('is_archived', false)
            ->whereNotNull('wholesale_price')
            ->min('wholesale_price');
    }
}
