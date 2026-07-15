<?php

namespace App\Http\Middleware;

use App\Support\Attribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * First-touch attribution (P0 #5). On the visitor's first storefront page view
 * we snapshot the UTM parameters, landing page and referrer into the session,
 * then persist them onto whichever inquiry they submit. First-touch: never
 * overwritten within a session, so the original campaign wins.
 */
class CaptureAttribution
{
    private const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethod('GET')
            && ! $request->is('admin*')
            && ! $request->session()->has(Attribution::SESSION_KEY)
        ) {
            $data = [];

            foreach (self::UTM_KEYS as $key) {
                $value = $request->query($key);
                if (is_string($value) && $value !== '') {
                    $data[$key] = mb_substr($value, 0, 255);
                }
            }

            $data['landing_page'] = mb_substr($request->fullUrl(), 0, 2000);

            if ($referer = $request->headers->get('referer')) {
                $data['referrer'] = mb_substr($referer, 0, 255);
            }

            $request->session()->put(Attribution::SESSION_KEY, $data);
        }

        return $next($request);
    }
}
