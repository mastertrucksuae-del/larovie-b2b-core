<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inquiry extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new_inquiry';
    public const STATUS_RESPONDING = 'responding';
    public const STATUS_PRICES_FILLED = 'prices_filled';
    public const STATUS_QUOTE_SENT = 'quote_sent';

    public const STATUSES = [
        self::STATUS_NEW => 'New inquiry',
        self::STATUS_RESPONDING => 'Responding',
        self::STATUS_PRICES_FILLED => 'Prices filled',
        self::STATUS_QUOTE_SENT => 'Quote sent',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'is_whatsapp' => 'boolean',
        'quote_valid_until' => 'date',
        'quoted_subtotal' => 'decimal:2',
        'quoted_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Inquiry $inquiry) {
            if (blank($inquiry->reference)) {
                $inquiry->reference = static::generateReference();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(InquiryItem::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(InquiryCharge::class);
    }

    /** Sum of product line totals — the base for percentage charges. */
    public function itemsSubtotal(): float
    {
        return (float) $this->items->sum(function (InquiryItem $item) {
            return $item->unit_price !== null
                ? round($item->quantity * (float) $item->unit_price, 2)
                : 0;
        });
    }

    /** Extra charges shown on the customer quote (e.g. shipping), fixed or %. */
    public function billableChargesTotal(): float
    {
        $base = $this->itemsSubtotal();

        return (float) $this->charges->where('is_billable', true)->sum(fn (InquiryCharge $c) => $c->resolve($base));
    }

    /** Internal-only expenses (e.g. parking) — never on the customer quote. */
    public function internalChargesTotal(): float
    {
        $base = $this->itemsSubtotal();

        return (float) $this->charges->where('is_billable', false)->sum(fn (InquiryCharge $c) => $c->resolve($base));
    }

    /**
     * Generate the next human-readable reference, e.g. LRV-2026-0001.
     * Year-scoped, zero-padded sequence.
     */
    public static function generateReference(): string
    {
        $year = now()->year;
        $prefix = "LRV-{$year}-";

        $last = static::where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** Generate a quote number the first time a quote is produced. */
    public static function generateQuoteNumber(): string
    {
        $year = now()->year;
        $prefix = "Q-{$year}-";

        $last = static::where('quote_number', 'like', $prefix.'%')
            ->orderByDesc('quote_number')
            ->value('quote_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** True once every line item has a unit price. */
    public function allItemsPriced(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }

        return $this->items->every(fn (InquiryItem $item) => $item->unit_price !== null);
    }

    /** Recompute line totals + subtotal/total from current unit prices. */
    public function recalculateTotals(): void
    {
        $subtotal = 0;

        foreach ($this->items as $item) {
            if ($item->unit_price !== null) {
                $line = round($item->quantity * (float) $item->unit_price, 2);
                if ((float) $item->line_total !== $line) {
                    $item->line_total = $line;
                    $item->save();
                }
                $subtotal += $line;
            }
        }

        $this->quoted_subtotal = $subtotal;
        // Billable extra charges (shipping, etc.) add to the customer total.
        $this->quoted_total = round($subtotal + $this->billableChargesTotal(), 2);
        $this->save();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
