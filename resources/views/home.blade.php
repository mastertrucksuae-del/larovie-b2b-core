@extends('layouts.storefront')

@php
    use App\Support\HomeContent;

    // Resolved here rather than read from the layout: Blade evaluates a child's
    // sections before the parent template runs, so the layout's own $settings
    // does not exist yet at this point.
    $settings = \App\Models\Setting::current();

    // Every string resolves through HomeContent, so an override saved in
    // Settings -> Homepage wins and anything left blank falls back to the
    // shipped translation for the active locale.
    $t = fn (string $key, array $replace = []) => HomeContent::text($key, $replace);
    $shows = fn (string $section) => HomeContent::showsSection($section);
    $heroImage = HomeContent::heroImage();
@endphp

@section('title', $settings->company_name.' — '.$t('home_title'))
@section('meta_description', $t('home_meta_description'))
@section('og_image', $heroImage ?? asset('images/larovie-logo-dark-transparant.png'))

{{-- The homepage paints its own edge-to-edge bands, so it opts out of the
     centred container the layout gives every other page. --}}
@section('main_class', '')

@section('content')

    {{-- ── Hero ─────────────────────────────────────────────────────────────── --}}
    <section class="bg-blush/60 border-b border-line">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14 lg:py-20">
            <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-rose-accent">
                        {{ $t('hero_eyebrow') }}
                    </p>
                    <h1 class="font-display mt-5 text-[2.5rem] leading-[1.08] sm:text-5xl lg:text-[3.4rem] text-ink">
                        {{ $t('hero_title') }}
                    </h1>
                    <p class="mt-6 max-w-xl text-base leading-relaxed text-plum-600">
                        {{ $t('hero_subtitle') }}
                    </p>

                    <div class="mt-9 flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('catalogue.index') }}"
                           class="inline-flex items-center justify-center rounded-xl bg-plum px-7 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-plum-800 transition">
                            {{ $t('hero_cta_browse') }}
                        </a>
                        <a href="{{ route('register') }}"
                           class="inline-flex items-center justify-center rounded-xl border border-plum/25 bg-white/70 px-7 py-3.5 text-sm font-semibold text-plum hover:bg-white hover:border-plum/50 transition">
                            {{ $t('hero_cta_account') }}
                        </a>
                    </div>
                </div>

                @if ($heroImage)
                    {{-- Artwork uploaded in Settings -> Homepage wins outright. --}}
                    <div class="overflow-hidden rounded-2xl ring-1 ring-line shadow-[0_24px_60px_-30px_rgba(36,19,39,0.4)]">
                        <img src="{{ $heroImage }}" alt="{{ $t('hero_image_alt') }}"
                             class="aspect-[4/3] h-full w-full object-cover"
                             fetchpriority="high" decoding="sync">
                    </div>
                @elseif ($featured->isNotEmpty())
                    {{-- Fallback composed from live catalogue imagery: no lifestyle
                         photography ships with the storefront, and four real products
                         read better than one lonely bottle on an empty plinth. --}}
                    <div class="relative rounded-2xl bg-gradient-to-br from-sand via-white to-blush p-3 sm:p-4 ring-1 ring-line shadow-[0_24px_60px_-30px_rgba(36,19,39,0.4)]">
                        <div class="grid grid-cols-2 gap-3 sm:gap-4">
                            @foreach ($featured->take(4) as $i => $item)
                                @php $heroImg = $item->display_image; @endphp
                                <div class="aspect-square overflow-hidden rounded-xl bg-white/70">
                                    @if ($heroImg)
                                        <img src="{{ \App\Support\Img::at($heroImg, 500) }}"
                                             @php $hs = \App\Support\Img::srcset($heroImg); @endphp
                                             @if ($hs) srcset="{{ $hs }}" sizes="(min-width: 1024px) 260px, 45vw" @endif
                                             alt="{{ $item->title }}"
                                             width="500" height="500"
                                             class="h-full w-full object-cover"
                                             loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                                             fetchpriority="{{ $i === 0 ? 'high' : 'auto' }}"
                                             decoding="{{ $i === 0 ? 'sync' : 'async' }}">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ── Trust strip ──────────────────────────────────────────────────────── --}}
    <section class="border-b border-line bg-white" aria-label="{{ $t('trust_authentic') }}">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-5">
            <ul class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 lg:flex lg:items-center lg:justify-between text-xs text-plum-700">
                @php
                    $trust = [
                        ['trust_authentic', 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z'],
                        ['trust_registered', 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21'],
                        ['trust_licence', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                        ['trust_support', 'M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z'],
                        ['trust_response', 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                        ['trust_secure', 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z'],
                    ];
                @endphp
                @foreach ($trust as [$key, $path])
                    <li class="inline-flex items-center gap-2">
                        <svg class="w-[18px] h-[18px] shrink-0 text-rose-accent" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                        <span>{{ $t($key) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ── Reorderable sections ────────────────────────────────────────────
         Order and visibility both come from Settings -> Homepage. Each section
         guards its own emptiness, so a switched-on section with no data simply
         renders nothing rather than an empty heading. --}}
    @foreach (HomeContent::sectionOrder() as $section)
        @if ($shows($section))
            @include('home.sections.'.$section)
        @endif
    @endforeach


@endsection
