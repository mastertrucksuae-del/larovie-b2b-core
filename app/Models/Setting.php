<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quote_validity_days' => 'integer',
        'last_synced_at' => 'datetime',
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
