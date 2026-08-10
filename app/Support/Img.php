<?php

namespace App\Support;

/**
 * Image URL helpers.
 *
 * Product and brand images come from two places: the Shopify CDN (which resizes
 * on the fly via a `width` query param and negotiates WebP/AVIF from the Accept
 * header) and local admin uploads on the `public` disk (no resizing available).
 *
 * These helpers emit a `srcset` for the former and degrade to a plain `src` for
 * the latter, so a 300px card never downloads a 2000px original.
 */
class Img
{
    /** Widths offered to the browser, in CSS pixels. */
    public const CARD_WIDTHS = [200, 300, 400, 600, 800];

    public const HERO_WIDTHS = [400, 600, 800, 1000, 1400];

    /** True when the CDN behind this URL can resize it for us. */
    public static function isResizable(?string $url): bool
    {
        return filled($url) && str_contains($url, 'cdn.shopify.com');
    }

    /** The same image at a given width, when the source supports it. */
    public static function at(?string $url, int $width): ?string
    {
        if (! self::isResizable($url)) {
            return $url;
        }

        // Drop any width Shopify already baked in, then request ours.
        $url = preg_replace('/([?&])width=\d+&?/', '$1', $url);
        $url = rtrim((string) $url, '?&');

        return $url.(str_contains($url, '?') ? '&' : '?').'width='.$width;
    }

    /**
     * A `srcset` string, or null when the source cannot be resized (in which
     * case the caller should just render `src`).
     *
     * @param  array<int, int>  $widths
     */
    public static function srcset(?string $url, array $widths = self::CARD_WIDTHS): ?string
    {
        if (! self::isResizable($url)) {
            return null;
        }

        return implode(', ', array_map(
            fn (int $w) => self::at($url, $w).' '.$w.'w',
            $widths
        ));
    }
}
