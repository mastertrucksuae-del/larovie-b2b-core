@extends('layouts.storefront')

@section('title', $product->title)

@php
    $metaDesc = $product->description
        ? \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($product->description))), 155)
        : trim(($product->effective_brand ? $product->effective_brand.' — ' : '').$product->title.'. '.__('shop.meta_description_default'));
@endphp

@section('meta_description', $metaDesc)
@if ($product->display_image)
    @section('og_image', $product->display_image)
@endif

@push('head')
    <script type="application/ld+json">
    @php
        $startingPrice = $product->starting_price;

        $ld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->title,
            'image' => $product->display_image,
            'description' => $metaDesc,
            'sku' => optional($product->variants->first())->sku,
            'category' => $product->product_type ?: null,
            'url' => url()->current(),
            'brand' => $product->effective_brand ? [
                '@type' => 'Brand',
                'name' => $product->effective_brand,
            ] : null,
            // Wholesale pricing is quote-based, so we publish the "from" price as
            // a lower bound rather than a fixed offer.
            'offers' => $startingPrice !== null ? [
                '@type' => 'AggregateOffer',
                'priceCurrency' => \App\Support\Money::currency(),
                'lowPrice' => (string) round((float) $startingPrice, 2),
                'offerCount' => $product->variants->count(),
                'availability' => 'https://schema.org/InStock',
                'businessFunction' => 'https://schema.org/Sell',
                'eligibleCustomerType' => 'https://schema.org/Business',
                'url' => url()->current(),
            ] : null,
        ]);

        $crumbs = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_values(array_filter([
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => __('shop.catalogue'),
                    'item' => route('catalogue.index'),
                ],
                $product->effective_brand ? [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $product->effective_brand,
                ] : null,
                [
                    '@type' => 'ListItem',
                    'position' => $product->effective_brand ? 3 : 2,
                    'name' => $product->title,
                    'item' => url()->current(),
                ],
            ])),
        ];
    @endphp
    {!! json_encode([$ld, $crumbs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <nav class="mb-8 text-sm text-plum-500 flex items-center gap-2">
        <a href="{{ route('catalogue.index') }}" class="hover:text-plum transition">{{ __('shop.catalogue') }}</a>
        <span class="text-line">/</span>
        @if ($product->effective_brand)
            <span>{{ $product->effective_brand }}</span>
            <span class="text-line">/</span>
        @endif
        <span class="text-ink">{{ $product->title }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16">
        {{-- Gallery --}}
        <div>
            <div class="aspect-square rounded-3xl bg-sand overflow-hidden ring-1 ring-line">
                @if ($product->display_image)
                    @php($heroSrcset = \App\Support\Img::srcset($product->display_image, \App\Support\Img::HERO_WIDTHS))
                    <img src="{{ \App\Support\Img::at($product->display_image, 800) }}"
                         @if ($heroSrcset) srcset="{{ $heroSrcset }}" sizes="(min-width: 1024px) 600px, 100vw" @endif
                         alt="{{ $product->title }}"
                         width="800" height="800"
                         class="h-full w-full object-cover"
                         fetchpriority="high" decoding="sync">
                @endif
            </div>
            @php($thumbs = $product->variants->map->display_image->filter()->unique()->take(5))
            @if ($thumbs->count() > 1)
                <div class="mt-4 grid grid-cols-5 gap-3">
                    @foreach ($thumbs as $thumb)
                        <div class="aspect-square rounded-xl bg-sand overflow-hidden ring-1 ring-line">
                            <img src="{{ \App\Support\Img::at($thumb, 200) }}" alt=""
                                 width="200" height="200" class="h-full w-full object-cover"
                                 loading="lazy" decoding="async">
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Details --}}
        <div class="lg:pt-4">
            @if ($product->product_type)
                <span class="text-xs uppercase tracking-[0.2em] text-rose-deep">{{ $product->product_type }}</span>
            @endif
            <h1 class="mt-2 font-display text-3xl sm:text-4xl text-ink leading-tight">{{ $product->title }}</h1>
            @if ($product->effective_brand)
                <p class="mt-2 text-plum-500">{{ __('shop.by') }} {{ $product->effective_brand }}</p>
            @endif

            <div class="mt-8 space-y-6">
                <livewire:product-inquiry :product="$product" />
            </div>

            @if ($product->description)
                <div class="mt-12 border-t border-line pt-8">
                    <h2 class="font-display text-xl text-ink mb-4">{{ __('shop.description') }}</h2>
                    <div class="prose prose-sm max-w-none text-plum-700 prose-headings:text-ink prose-headings:font-display">
                        {!! $product->description !!}
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
