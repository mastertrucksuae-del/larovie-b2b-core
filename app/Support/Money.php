<?php

namespace App\Support;

use App\Models\Setting;

class Money
{
    /**
     * Format an amount with the configured currency, e.g. "AED 1,250.00".
     * Returns null-safe: pass a null amount to get the "price on request" caller's job.
     */
    public static function format(int|float|string|null $amount, ?string $currency = null): string
    {
        $currency ??= Setting::current()->default_currency ?? 'AED';

        return $currency.' '.number_format((float) $amount, 2);
    }
}
