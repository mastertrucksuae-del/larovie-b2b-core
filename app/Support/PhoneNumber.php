<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Normalises user-entered mobile numbers to E.164.
 *
 * Backed by libphonenumber rather than hand-rolled string rules. The rules this
 * replaced could not tell a bare national number from one that merely began with
 * the country's own digits, and had no idea which national prefixes are real —
 * so a Saudi or British number entered by a GCC buyer came out mangled.
 *
 * The buyer picks their country beside the field and types the number the way
 * they say it out loud. Whatever they actually type — bare, with a trunk zero,
 * with the country code, with a leading + — resolves to the same E.164 value,
 * and a code already present is never doubled.
 */
class PhoneNumber
{
    /** Fallback region when nothing better is known. The shop is UAE-based. */
    public const DEFAULT_REGION = 'AE';

    /**
     * Normalise to E.164 (e.g. +971501234567).
     *
     * Returns the raw input's digits as a best effort when it cannot be parsed,
     * rather than throwing: a slightly wrong number stored is recoverable, a
     * failed inquiry submission is not.
     *
     * @param  string  $region  ISO 3166-1 alpha-2 (AE, SA, GB…). A bare dialling
     *                          code is still accepted for older callers.
     */
    public static function toE164(string $raw, string $region = self::DEFAULT_REGION): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return '';
        }

        $region = self::normaliseRegion($region);

        try {
            $util = PhoneNumberUtil::getInstance();
            $parsed = $util->parse($raw, $region);

            // Accept anything parseable. isValidNumber() is stricter than a
            // wholesale form should be — it rejects perfectly reachable numbers
            // in ranges libphonenumber has not caught up with.
            if ($util->isPossibleNumber($parsed)) {
                return $util->format($parsed, PhoneNumberFormat::E164);
            }
        } catch (NumberParseException) {
            // Fall through to the best-effort path below.
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';

        return $digits === '' ? '' : '+'.$digits;
    }

    /** E.164 without the leading '+', for wa.me links. */
    public static function forWhatsApp(string $raw, string $region = self::DEFAULT_REGION): string
    {
        return ltrim(self::toE164($raw, $region), '+');
    }

    /**
     * A number formatted for display, e.g. "+971 50 111 2222".
     */
    public static function forDisplay(string $raw, string $region = self::DEFAULT_REGION): string
    {
        try {
            $util = PhoneNumberUtil::getInstance();
            $parsed = $util->parse($raw, self::normaliseRegion($region));

            return $util->format($parsed, PhoneNumberFormat::INTERNATIONAL);
        } catch (NumberParseException) {
            return $raw;
        }
    }

    /**
     * Split a stored E.164 number back into [region, national number].
     *
     * The form shows the dialling code in its own control, so pasting the whole
     * stored value into the text input would render "+971 +971 50…". Returns the
     * default region and an empty string when there is nothing to split.
     *
     * @return array{0: string, 1: string}
     */
    public static function split(?string $stored): array
    {
        $stored = trim((string) $stored);

        if ($stored === '') {
            return [self::DEFAULT_REGION, ''];
        }

        try {
            $util = PhoneNumberUtil::getInstance();
            $parsed = $util->parse($stored, self::DEFAULT_REGION);
            $region = $util->getRegionCodeForNumber($parsed);

            return [
                $region && $region !== 'ZZ' ? $region : self::DEFAULT_REGION,
                (string) $parsed->getNationalNumber(),
            ];
        } catch (NumberParseException) {
            return [self::DEFAULT_REGION, preg_replace('/\D/', '', $stored) ?? ''];
        }
    }

    /**
     * Older callers passed a dialling code ("971") where a region is expected.
     * Map it back so nothing that predates libphonenumber breaks.
     */
    private static function normaliseRegion(string $region): string
    {
        $region = strtoupper(trim($region));

        if ($region === '' ) {
            return self::DEFAULT_REGION;
        }

        if (ctype_digit($region)) {
            $mapped = PhoneNumberUtil::getInstance()->getRegionCodeForCountryCode((int) $region);

            return ($mapped && $mapped !== 'ZZ') ? $mapped : self::DEFAULT_REGION;
        }

        return $region;
    }
}
