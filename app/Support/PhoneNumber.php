<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalize a user-entered mobile number to E.164 (e.g. +971501234567).
     *
     * Rules (MVP, UAE-default):
     *  - Strip spaces, dashes, parentheses, dots.
     *  - "00" international prefix -> "+".
     *  - Already "+..." -> keep as-is.
     *  - Leading "0" (local trunk) -> replace with default country code.
     *  - Bare national number -> prepend default country code.
     *
     * @param  string  $default  Default country calling code, digits only (UAE = 971).
     */
    public static function toE164(string $raw, string $default = '971'): string
    {
        $value = preg_replace('/[\s\-().]/', '', trim($raw));

        if ($value === '') {
            return '';
        }

        // 00 international prefix -> +
        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        }

        if (str_starts_with($value, '+')) {
            $digits = preg_replace('/\D/', '', $value);

            return '+'.$digits;
        }

        // Strip any stray non-digits now that we know there's no leading +
        $value = preg_replace('/\D/', '', $value);

        // Local number with trunk 0 -> country code + rest
        if (str_starts_with($value, '0')) {
            return '+'.$default.ltrim($value, '0');
        }

        // Number already includes the country code
        if (str_starts_with($value, $default)) {
            return '+'.$value;
        }

        // Bare national number
        return '+'.$default.$value;
    }

    /** E.164 without the leading '+', for wa.me links. */
    public static function forWhatsApp(string $raw, string $default = '971'): string
    {
        return ltrim(self::toE164($raw, $default), '+');
    }
}
