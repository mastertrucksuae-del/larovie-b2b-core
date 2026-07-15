<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        // An explicit ?hl= param wins (used by hreflang alternates & search
        // crawlers), and is remembered for the rest of the session.
        $hl = $request->query('hl');
        if (is_string($hl) && in_array($hl, self::SUPPORTED, true)) {
            session(['locale' => $hl]);
            $locale = $hl;
        } else {
            $locale = session('locale', config('app.locale'));
        }

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
