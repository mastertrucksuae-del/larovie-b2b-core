<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Brand extends Model
{
    protected $guarded = ['id'];

    /** Public URL for the uploaded logo, or null when none is set. */
    public function getLogoUrlAttribute(): ?string
    {
        return filled($this->logo_path)
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }

    /**
     * Ensure a brand row exists for every distinct effective brand used by
     * products (the `brand` column, falling back to `vendor`). Existing rows —
     * and their uploaded logos — are left untouched. Returns how many new brands
     * were added.
     */
    public static function syncFromProducts(): int
    {
        $names = Product::query()
            ->selectRaw('coalesce(nullif(brand, ""), vendor) as b')
            ->whereRaw('coalesce(nullif(brand, ""), vendor) is not null')
            ->whereRaw('coalesce(nullif(brand, ""), vendor) <> ""')
            ->distinct()
            ->pluck('b')
            ->filter(fn ($n) => filled($n))
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return 0;
        }

        $existing = static::whereIn('name', $names->all())->pluck('name')->all();
        $new = $names->reject(fn ($n) => in_array($n, $existing, true))->values();

        if ($new->isEmpty()) {
            return 0;
        }

        $now = now();
        static::insertOrIgnore(
            $new->map(fn ($n) => [
                'name' => $n,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        return $new->count();
    }

    /** Map of brand name => logo URL, for brands that have a logo uploaded. */
    public static function logoUrlMap(): array
    {
        return static::query()
            ->whereNotNull('logo_path')
            ->get(['name', 'logo_path'])
            ->mapWithKeys(fn (self $brand) => [$brand->name => $brand->logo_url])
            ->all();
    }
}
