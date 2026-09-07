<?php

namespace App\Models;

use App\Support\BusinessTypeIcons;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A customer type shown in the homepage's "Business types we serve" list.
 *
 * Managed entirely by hand in the admin panel — there is nothing in Shopify to
 * import this from.
 */
class BusinessType extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The name in the active locale.
     *
     * Arabic is optional: a type added in a hurry with only an English name
     * should still appear on the Arabic site rather than rendering as a blank
     * chip.
     */
    public function getLabelAttribute(): string
    {
        if (app()->getLocale() === 'ar' && filled($this->name_ar)) {
            return $this->name_ar;
        }

        return (string) $this->name_en;
    }

    /**
     * SVG path data for this type's chip icon.
     *
     * Resolved through the icon map so an unset or stale key degrades to the
     * default rather than rendering an empty <path d="">.
     */
    public function getIconPathAttribute(): string
    {
        return BusinessTypeIcons::path($this->icon);
    }

    /**
     * The list for the storefront.
     *
     * @return Collection<int, self>
     */
    public static function forStorefront(): Collection
    {
        return static::query()->visible()->get();
    }
}
