<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /** XML sitemap — only exposed once indexing is enabled (P1 #9). */
    public function index(): Response
    {
        abort_unless(Setting::current()->search_indexing_enabled, 404);

        $urls = [];
        $urls[] = ['loc' => route('catalogue.index'), 'priority' => '1.0', 'changefreq' => 'daily'];
        $urls[] = ['loc' => route('authenticity'), 'priority' => '0.6', 'changefreq' => 'monthly'];
        $urls[] = ['loc' => route('contact'), 'priority' => '0.7', 'changefreq' => 'monthly'];

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

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /** Dynamic robots.txt — blocks crawlers until indexing is switched on (P1 #9). */
    public function robots(): Response
    {
        if (Setting::current()->search_indexing_enabled) {
            $body = "User-agent: *\nAllow: /\n\nSitemap: ".route('sitemap')."\n";
        } else {
            $body = "User-agent: *\nDisallow: /\n";
        }

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }
}
