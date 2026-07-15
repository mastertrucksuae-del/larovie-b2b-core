<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Contact link helpers for the storefront (P0 #1). Resolves tap-to-call and
 * WhatsApp tap-to-chat links from the Filament-managed settings singleton.
 */
class Contact
{
    public static function phone(): ?string
    {
        return Setting::current()->company_phone ?: null;
    }

    /** tel: link (E.164) for the company phone, or null when unset. */
    public static function tel(): ?string
    {
        $phone = self::phone();

        return $phone ? 'tel:'.PhoneNumber::toE164($phone) : null;
    }

    /** The WhatsApp Business number, falling back to the company phone. */
    public static function whatsappNumber(): ?string
    {
        $settings = Setting::current();

        return $settings->company_whatsapp ?: $settings->company_phone ?: null;
    }

    /** wa.me tap-to-chat link with a pre-filled wholesale message. */
    public static function whatsappLink(?string $message = null): ?string
    {
        $number = self::whatsappNumber();

        if (! $number) {
            return null;
        }

        $text = $message ?? __('shop.wa_prefill');

        return 'https://wa.me/'.PhoneNumber::forWhatsApp($number).'?text='.rawurlencode($text);
    }
}
