<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use libphonenumber\PhoneNumberUtil;
use Locale;

/**
 * Dialling-code options for the phone field.
 *
 * Built from libphonenumber's own region list so the codes can never drift from
 * the parser that has to understand them, with names from intl.
 */
class Countries
{
    /**
     * Shown first, because this is a UAE wholesaler selling into the Gulf.
     * A buyer in Dubai should not scroll past 200 countries to find their own.
     */
    public const PRIORITY = ['AE', 'SA', 'KW', 'QA', 'BH', 'OM'];

    /**
     * ISO region => "United Arab Emirates (+971)".
     *
     * Cached: building it walks 245 regions through intl, and the list only
     * changes when the library is upgraded.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return Cache::rememberForever('phone.country-options.'.app()->getLocale(), function () {
            $util = PhoneNumberUtil::getInstance();
            $locale = app()->getLocale();

            $all = [];

            foreach ($util->getSupportedRegions() as $region) {
                $code = $util->getCountryCodeForRegion($region);

                if ($code === 0) {
                    continue;
                }

                $name = Locale::getDisplayRegion('-'.$region, $locale) ?: $region;
                $all[$region] = $name.' (+'.$code.')';
            }

            asort($all, SORT_NATURAL | SORT_FLAG_CASE);

            $priority = [];

            foreach (self::PRIORITY as $region) {
                if (isset($all[$region])) {
                    $priority[$region] = $all[$region];
                    unset($all[$region]);
                }
            }

            return $priority + $all;
        });
    }

    /** The dialling code for a region, e.g. AE => 971. */
    public static function dialCode(string $region): ?int
    {
        $code = PhoneNumberUtil::getInstance()->getCountryCodeForRegion(strtoupper($region));

        return $code > 0 ? $code : null;
    }

    public static function isSupported(?string $region): bool
    {
        return filled($region) && array_key_exists(strtoupper($region), self::options());
    }
}
