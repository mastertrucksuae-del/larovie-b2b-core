<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'options' => 'array',
        'wholesale_price' => 'decimal:2',
        'inventory_quantity' => 'integer',
        'moq' => 'integer',
        'is_visible' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Effective MOQ: variant MOQ, else product MOQ, else 1.
     */
    public function getEffectiveMoqAttribute(): int
    {
        return $this->moq
            ?? $this->product?->moq
            ?? 1;
    }

    public function hasPrice(): bool
    {
        return $this->wholesale_price !== null;
    }

    /** Human label combining product + variant title. */
    public function getDisplayNameAttribute(): string
    {
        $product = $this->product?->title ?? '';
        if ($this->title && $this->title !== 'Default Title') {
            return trim($product.' — '.$this->title);
        }

        return $product;
    }
}
