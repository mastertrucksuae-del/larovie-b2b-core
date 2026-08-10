<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /** Paths that must never be crawled, indexing on or off. */
    private const PRIVATE_PATHS = [
        '/admin',
        '/account',
        '/login',
        '/register',
        '/cart',
        '/inquiry',
        '/quote',
        '/locale',
    ];

    /** XML sitemap — only exposed once indexing is enabled (P1 #9). */
    public function index(): Response
    {
        abort_unless(Setting::current()->search_indexing_enabled, 404);

        // Rendered per request on purpose. This was behind a 1-hour Cache::remember,
        // which meant a deploy that changed the sitemap template kept serving the
        // old XML for up to an hour with nothing to indicate why — a silent trap,
        // and it hid the hreflang fix in b741fff. The query is ~350 rows of two
        // columns and crawlers hit this rarely; the Cache-Control header below
        // already stops anything from hammering it.
        $urls = [
            ['loc' => route('catalogue.index'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => route('authenticity'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => route('contact'), 'priority' => '0.7', 'changefreq' => 'monthly'],
        ];

        Product::query()
            ->publiclyVisible()
            ->orderBy('id')
            ->get(['handle', 'updated_at'])
            ->each(function (Product $product) use (&$urls) {
                $urls[] = [
                    'loc' => route('catalogue.show', $product->handle),
                    'lastmod' => optional($product->updated_at)->toAtomString(),
                    'priority' => '0.8',
                    'changefreq' => 'weekly',
                ];
            });

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** Dynamic robots.txt — blocks crawlers until indexing is switched on (P1 #9). */
    public function robots(): Response
    {
        if (Setting::current()->search_indexing_enabled) {
            $lines = ['User-agent: *', 'Allow: /'];

            foreach (self::PRIVATE_PATHS as $path) {
                $lines[] = 'Disallow: '.$path;
            }

            // Filter/sort permutations are the same products in a different order.
            $lines[] = 'Disallow: /*?q=';
            $lines[] = 'Disallow: /*?sort=';
            $lines[] = 'Disallow: /*?type=';
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap');

            $body = implode("\n", $lines)."\n";
        } else {
            $body = "User-agent: *\nDisallow: /\n";
        }

        return response($body, 200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
