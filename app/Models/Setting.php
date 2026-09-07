<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = ['id'];

    /**
     * Defaults applied when the settings row is created.
     *
     * These must live here as well as on the column: a freshly `create([])`d
     * model does not read back DB-level defaults, so without this the first
     * page load of a new install would see a null (falsy) indexing flag and
     * serve `noindex` despite the column defaulting to true.
     */
    protected $attributes = [
        'search_indexing_enabled' => true,
        'require_account_review' => true,
    ];

    protected $casts = [
        'quote_validity_days' => 'integer',
        'last_synced_at' => 'datetime',
        'search_indexing_enabled' => 'boolean',
        'require_account_review' => 'boolean',
        'homepage_content' => 'array',
        'homepage_sections' => 'array',
        'homepage_section_order' => 'array',
        'homepage_featured_product_ids' => 'array',
        'homepage_featured_brands' => 'array',
        'homepage_featured_category_ids' => 'array',
        'default_moq' => 'integer',
        'sync_page_size' => 'integer',
        'sync_cost_floor' => 'integer',
        'homepage_featured_count' => 'integer',
    ];

    /**
     * The single settings row. Created with sensible defaults if missing.
     * Cached per-request.
     */
    protected static ?Setting $current = null;

    public static function current(): self
    {
        return static::$current ??= static::first() ?? static::create([]);
    }

    /**
     * Catalogue and sync knobs, dashboard value first and config as the floor.
     *
     * Falling back to config keeps `.env` working as the default on a fresh
     * install and means an accidentally-cleared field cannot take the sync down
     * — it just reverts to the shipped number.
     */
    public function effectiveDefaultMoq(): int
    {
        return $this->clamped($this->default_moq, (int) config('shopify.default_moq', 12), 1, 10000);
    }

    public function effectiveSyncPageSize(): int
    {
        // Shopify rejects a page size above 250.
        return $this->clamped($this->sync_page_size, (int) config('shopify.page_size', 50), 1, 250);
    }

    public function effectiveSyncCostFloor(): int
    {
        return $this->clamped($this->sync_cost_floor, (int) config('shopify.cost_floor', 200), 0, 2000);
    }

    private function clamped(?int $value, int $fallback, int $min, int $max): int
    {
        $resolved = ($value !== null && $value > 0) ? $value : $fallback;

        return max($min, min($resolved, $max));
    }

    public static function clearCache(): void
    {
        static::$current = null;
    }
}
