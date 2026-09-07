<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ordering is for registered buyers.
 *
 * Browsing and pricing stay open to everyone — the catalogue is a shopfront and
 * hiding prices only costs traffic — but submitting an inquiry means signing in
 * first, and being approved when manual review is switched on.
 *
 * The guard lives in middleware rather than the controller so it cannot be
 * bypassed by posting straight at the route.
 */
class EnsureCanSubmitInquiry
{
    public function handle(Request $request, Closure $next): Response
    {
        $buyer = auth('business')->user();

        if (! $buyer) {
            // Remember the cart so signing in drops them back where they were
            // rather than on a dashboard with their basket seemingly lost.
            session()->put('url.intended', route('cart'));

            return redirect()->route('login')->with('error', __('shop.order_requires_account'));
        }

        if (! $buyer->canSubmitInquiry()) {
            return redirect()->route('cart')->with('error', __('shop.order_requires_approval'));
        }

        return $next($request);
    }
}
