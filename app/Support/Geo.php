<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Best guess at the visitor's country, for defaulting the phone field.
 *
 * Deliberately free of any geo-IP service: a lookup on every page load costs
 * latency and money, and the cost of guessing wrong here is one dropdown change.
 *
 * Only the CDN's edge header is trusted, because it is the one signal that says
 * where the visitor actually is. Accept-Language was tried and removed: `en-US`
 * is the commonest browser locale on earth, so reading a region out of it puts a
 * buyer in Dubai on the United States — worse than simply assuming the shop's
 * own country, which is right for most of this wholesaler's traffic.
 */
class Geo
{
    public static function defaultRegion(?Request $request = null): string
    {
        $request ??= request();

        $candidate = self::fromEdgeHeader($request);

        return Countries::isSupported($candidate)
            ? strtoupper($candidate)
            : PhoneNumber::DEFAULT_REGION;
    }

    /**
     * Cloudflare and several CDNs resolve the country at the edge and pass it
     * down. Free and accurate when present; simply absent otherwise.
     */
    private static function fromEdgeHeader(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'X-Vercel-IP-Country', 'X-Country-Code'] as $header) {
            $value = $request->header($header);

            // Cloudflare sends XX for anonymised traffic and T1 for Tor.
            if (filled($value) && ! in_array(strtoupper($value), ['XX', 'T1'], true)) {
                return $value;
            }
        }

        return null;
    }
}
