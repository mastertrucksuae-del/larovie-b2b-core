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
    ];

    protected $casts = [
        'quote_validity_days' => 'integer',
        'last_synced_at' => 'datetime',
        'search_indexing_enabled' => 'boolean',
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

    public static function clearCache(): void
    {
        static::$current = null;
    }
}
