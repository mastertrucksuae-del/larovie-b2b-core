<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryCharge extends Model
{
    use HasFactory;

    public const TYPE_FIXED = 'fixed';
    public const TYPE_PERCENT = 'percent';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_billable' => 'boolean',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /**
     * The resolved money value of this charge.
     * Fixed → the amount; Percent → that percentage of the given base (products subtotal).
     */
    public function resolve(float $base): float
    {
        if ($this->type === self::TYPE_PERCENT) {
            return round($base * (float) $this->amount / 100, 2);
        }

        return round((float) $this->amount, 2);
    }

    /** Label with the percentage shown, e.g. "Shipping (5%)". */
    public function displayLabel(): string
    {
        if ($this->type === self::TYPE_PERCENT) {
            $pct = rtrim(rtrim(number_format((float) $this->amount, 2), '0'), '.');

            return "{$this->label} ({$pct}%)";
        }

        return (string) $this->label;
    }
}
