<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
        <xhtml:link rel="alternate" hreflang="en" href="{{ $url['loc'] }}?hl=en"/>
        <xhtml:link rel="alternate" hreflang="ar" href="{{ $url['loc'] }}?hl=ar"/>
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $url['loc'] }}"/>
        @isset($url['lastmod'])<lastmod>{{ $url['lastmod'] }}</lastmod>@endisset
        <changefreq>{{ $url['changefreq'] ?? 'weekly' }}</changefreq>
        <priority>{{ $url['priority'] ?? '0.5' }}</priority>
    </url>
@endforeach
</urlset>
