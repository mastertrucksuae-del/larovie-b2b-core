<?php

namespace App\Services\WhatsApp;

use App\Models\Inquiry;
use App\Models\Setting;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\URL;

class WhatsAppLink
{
    /**
     * A "Chat on WhatsApp" link that opens a conversation with a short greeting
     * (no quote attached).
     */
    public static function chat(Inquiry $inquiry): string
    {
        $greeting = $inquiry->locale === 'ar'
            ? "مرحباً {$inquiry->customer_name}، بخصوص استفسارك {$inquiry->reference}"
            : "Hello {$inquiry->customer_name}, regarding your inquiry {$inquiry->reference}";

        return self::build($inquiry->customer_mobile, $greeting);
    }

    /**
     * A "Send quote via WhatsApp" link: resolves the settings template
     * and appends a signed link to the quote PDF.
     */
    public static function quote(Inquiry $inquiry): string
    {
        $settings = Setting::current();

        $template = $inquiry->locale === 'ar'
            ? ($settings->whatsapp_message_template_ar ?: $settings->whatsapp_message_template_en)
            : ($settings->whatsapp_message_template_en ?: $settings->whatsapp_message_template_ar);

        $template ??= 'Hello {customer_name}, your quote {quote_number} is ready: {quote_link}';

        $message = strtr($template, [
            '{customer_name}' => $inquiry->customer_name,
            '{reference}' => $inquiry->reference,
            '{quote_number}' => $inquiry->quote_number ?? '',
            '{quote_link}' => self::quoteLink($inquiry),
        ]);

        return self::build($inquiry->customer_mobile, $message);
    }

    /** A signed, temporary public URL to the quote PDF. */
    public static function quoteLink(Inquiry $inquiry): string
    {
        return URL::temporarySignedRoute(
            'quote.download',
            now()->addDays(30),
            ['inquiry' => $inquiry->id],
        );
    }

    protected static function build(string $mobile, string $message): string
    {
        $number = PhoneNumber::forWhatsApp($mobile);

        return "https://wa.me/{$number}?text=".rawurlencode($message);
    }
}
