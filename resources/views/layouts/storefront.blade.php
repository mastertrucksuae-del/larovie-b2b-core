@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
    $settings = \App\Models\Setting::current();
    $logo = $settings->logo_path
        ? \Illuminate\Support\Facades\Storage::url($settings->logo_path)
        : asset('images/larovie-logo-dark-transparant.png');
    $logoWhite = asset('images/larovie-logo-white-transparant.png');
    $contactTel = \App\Support\Contact::tel();
    $waLink = \App\Support\Contact::whatsappLink();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $settings->company_name) — {{ __('shop.wholesale') }}</title>

    @if ($locale === 'ar')
        {{-- Arabic UI font: Cairo (Google Fonts CDN) --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    @endif

    {{-- SEO (P1 #9) --}}
    <meta name="description" content="@yield('meta_description', __('shop.meta_description_default'))">
    <link rel="canonical" href="{{ url()->current() }}">
    @if ($settings->search_indexing_enabled)
        <meta name="robots" content="index, follow">
        {{-- hreflang pairs — distinct per-language URLs via ?hl= --}}
        <link rel="alternate" hreflang="en" href="{{ url()->current() }}?hl=en">
        <link rel="alternate" hreflang="ar" href="{{ url()->current() }}?hl=ar">
        <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}">
    @else
        <meta name="robots" content="noindex, nofollow">
    @endif

    {{-- Open Graph / Twitter --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $settings->company_name }}">
    <meta property="og:title" content="@yield('title', $settings->company_name)">
    <meta property="og:description" content="@yield('meta_description', __('shop.meta_description_default'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', asset('images/larovie-logo-dark-transparant.png'))">
    <meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_AE' : 'en_US' }}">
    <meta name="twitter:card" content="summary">

    {{-- Organization structured data --}}
    <script type="application/ld+json">
    @php
        $org = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $settings->legal_entity_name ?: $settings->company_name,
            'url' => url('/'),
            'logo' => asset('images/larovie-logo-dark-transparant.png'),
            'email' => $settings->company_email,
            'telephone' => $settings->company_phone,
            'address' => $settings->company_address ? [
                '@type' => 'PostalAddress',
                'streetAddress' => $settings->company_address,
                'addressCountry' => 'AE',
            ] : null,
        ]);
    @endphp
    {!! json_encode($org, JSON_UNESCAPED_UNICODE) !!}
    </script>

    @if ($settings->ga4_measurement_id)
        {{-- Analytics (P0 #5) --}}
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $settings->ga4_measurement_id }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $settings->ga4_measurement_id }}');
        </script>
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-dvh antialiased {{ $locale === 'ar' ? 'font-arabic' : 'font-sans' }}"
      x-data="{ inquiryOpen: false, mobileNav: false }"
      @inquiry-open.window="inquiryOpen = true"
      @keydown.escape.window="inquiryOpen = false; mobileNav = false">

    {{-- Announcement bar --}}
    <div class="bg-plum text-white/90 text-center text-xs sm:text-sm py-2 px-4 tracking-wide">
        {{ __('shop.announcement') }}
    </div>

    <header class="sticky top-0 z-40 bg-ivory/85 backdrop-blur-md border-b border-line">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-24 items-center justify-between gap-4">
                <a href="{{ route('catalogue.index') }}" class="flex items-center shrink-0">
                    <img src="{{ $logo }}" alt="{{ $settings->company_name }}" class="h-16 sm:h-20 w-auto">
                </a>

                {{-- Primary nav (desktop) --}}
                <nav class="hidden lg:flex items-center gap-7 text-sm font-medium text-plum-700">
                    <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-2 hover:text-plum transition">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z"/></svg>
                        {{ __('shop.nav_catalogue') }}
                    </a>
                    <a href="{{ route('authenticity') }}" class="inline-flex items-center gap-2 hover:text-plum transition">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                        {{ __('shop.nav_authenticity') }}
                    </a>
                    <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 hover:text-plum transition">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-6"/></svg>
                        {{ __('shop.nav_contact') }}
                    </a>
                </nav>

                <div class="flex items-center gap-1.5 sm:gap-2.5">
                    {{-- Tap-to-call --}}
                    @if ($contactTel)
                        <a href="{{ $contactTel }}"
                           class="inline-flex items-center gap-2 rounded-full border border-line bg-white h-10 w-10 md:w-auto justify-center md:px-4 text-xs font-medium text-plum-700 hover:text-plum hover:border-plum/40 transition"
                           aria-label="{{ __('shop.call_us') }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                            <span class="hidden md:inline" dir="ltr">{{ $settings->company_phone }}</span>
                        </a>
                    @endif

                    {{-- WhatsApp tap-to-chat (brand green via inline style so it always renders) --}}
                    @if ($waLink)
                        <a href="{{ $waLink }}" target="_blank" rel="noopener"
                           style="background-color:#25D366"
                           class="inline-flex items-center gap-2 rounded-full h-10 w-10 md:w-auto justify-center md:px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105 transition"
                           aria-label="{{ __('shop.chat_whatsapp') }}">
                            <svg class="w-5 h-5 shrink-0" fill="#ffffff" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                            <span class="hidden md:inline">WhatsApp</span>
                        </a>
                    @endif

                    {{-- Language switch --}}
                    <div class="hidden sm:flex items-center rounded-full border border-line bg-white p-0.5 text-xs">
                        <a href="{{ route('locale.switch', 'en') }}"
                           @class(['px-3 py-1.5 rounded-full transition', 'bg-plum text-white' => $locale === 'en', 'text-plum-600 hover:text-plum' => $locale !== 'en'])>EN</a>
                        <a href="{{ route('locale.switch', 'ar') }}"
                           @class(['px-3 py-1.5 rounded-full transition', 'bg-plum text-white' => $locale === 'ar', 'text-plum-600 hover:text-plum' => $locale !== 'ar'])>ع</a>
                    </div>

                    <livewire:cart-counter />

                    {{-- Mobile menu toggle --}}
                    <button type="button" @click="mobileNav = !mobileNav"
                            class="lg:hidden inline-flex items-center justify-center rounded-full border border-line bg-white w-10 h-10 text-plum-700 hover:text-plum transition"
                            aria-label="{{ __('shop.menu') }}" :aria-expanded="mobileNav">
                        <svg x-show="!mobileNav" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
                        <svg x-show="mobileNav" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile nav panel --}}
        <div x-show="mobileNav" x-cloak x-collapse class="lg:hidden border-t border-line/70 bg-ivory">
            <nav class="mx-auto max-w-7xl px-3 sm:px-5 py-3 flex flex-col text-plum-800">
                <a href="{{ route('catalogue.index') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-plum/[0.06] transition">
                    <span class="text-plum-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z"/></svg></span>
                    <span class="font-medium">{{ __('shop.nav_catalogue') }}</span>
                </a>
                <a href="{{ route('authenticity') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-plum/[0.06] transition">
                    <span class="text-plum-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg></span>
                    <span class="font-medium">{{ __('shop.nav_authenticity') }}</span>
                </a>
                <a href="{{ route('contact') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-plum/[0.06] transition">
                    <span class="text-plum-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-6"/></svg></span>
                    <span class="font-medium">{{ __('shop.nav_contact') }}</span>
                </a>
                <a href="{{ route('register') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-plum/[0.06] transition">
                    <span class="text-plum-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"/></svg></span>
                    <span class="font-medium">{{ __('shop.nav_register') }}</span>
                </a>
                <div class="mt-2 pt-3 border-t border-line/70 flex items-center gap-2 px-3 text-xs">
                    <a href="{{ route('locale.switch', 'en') }}" @class(['px-3 py-1.5 rounded-full border', 'bg-plum text-white border-plum' => $locale === 'en', 'border-line text-plum-700' => $locale !== 'en'])>EN</a>
                    <a href="{{ route('locale.switch', 'ar') }}" @class(['px-3 py-1.5 rounded-full border', 'bg-plum text-white border-plum' => $locale === 'ar', 'border-line text-plum-700' => $locale !== 'ar'])>العربية</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-blush border border-rose-accent/30 px-4 py-3 text-rose-deep">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mt-20 bg-plum-950 text-white/70">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14">
            <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <img src="{{ $logoWhite }}" alt="{{ $settings->company_name }}" class="h-14 w-auto mb-4">
                    <p class="font-display text-lg text-white/90 max-w-xs leading-snug">{{ __('shop.tagline') }}</p>
                    <p class="mt-4 inline-flex items-center gap-2 text-xs text-white/70">
                        <svg class="w-4 h-4 text-rose-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        {{ __('shop.authentic_badge') }}
                    </p>
                </div>

                {{-- Contact --}}
                <div>
                    <h3 class="text-xs uppercase tracking-[0.2em] text-white/50 mb-3">{{ __('shop.wholesale_enquiries') }}</h3>
                    <ul class="space-y-2 text-white/90 text-sm">
                        @if ($settings->company_email)
                            <li><a href="mailto:{{ $settings->company_email }}" class="hover:text-white transition">{{ $settings->company_email }}</a></li>
                        @endif
                        @if ($settings->company_phone)
                            <li><a href="{{ $contactTel }}" class="hover:text-white transition" dir="ltr">{{ $settings->company_phone }}</a></li>
                        @endif
                        @if ($waLink)
                            <li><a href="{{ $waLink }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 hover:text-white transition">
                                <svg class="w-4 h-4 text-[#25D366]" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/></svg>
                                WhatsApp
                            </a></li>
                        @endif
                        <li class="pt-1"><a href="{{ route('contact') }}" class="text-rose-accent hover:text-white transition">{{ __('shop.nav_contact') }} &rarr;</a></li>
                    </ul>
                </div>

                {{-- Legal identity (P0 #2) --}}
                <div>
                    <h3 class="text-xs uppercase tracking-[0.2em] text-white/50 mb-3">{{ __('shop.registered_business') }}</h3>
                    <div class="text-sm text-white/70 space-y-1.5 leading-relaxed">
                        <p class="text-white/90">{{ $settings->legal_entity_name ?: $settings->company_name }}</p>
                        @if ($settings->company_address)
                            <p>{{ $settings->company_address }}</p>
                        @endif
                        @if ($settings->trade_licence_number)
                            <p>{{ __('shop.trade_licence') }}: <span dir="ltr">{{ $settings->trade_licence_number }}</span></p>
                        @endif
                        @if ($settings->trn)
                            <p>{{ __('shop.trn') }}: <span dir="ltr">{{ $settings->trn }}</span></p>
                        @endif
                    </div>
                </div>

                {{-- Explore + language --}}
                <div>
                    <h3 class="text-xs uppercase tracking-[0.2em] text-white/50 mb-3">{{ __('shop.explore') }}</h3>
                    <ul class="space-y-2 text-sm text-white/70">
                        <li><a href="{{ route('catalogue.index') }}" class="hover:text-white transition">{{ __('shop.nav_catalogue') }}</a></li>
                        <li><a href="{{ route('authenticity') }}" class="hover:text-white transition">{{ __('shop.nav_authenticity') }}</a></li>
                        <li><a href="{{ route('contact') }}" class="hover:text-white transition">{{ __('shop.nav_contact') }}</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-white transition">{{ __('shop.nav_register') }}</a></li>
                    </ul>
                    <div class="mt-4 flex gap-3 text-sm">
                        <a href="{{ route('locale.switch', 'en') }}" class="hover:text-white transition {{ $locale === 'en' ? 'text-white' : '' }}">English</a>
                        <span class="text-white/30">·</span>
                        <a href="{{ route('locale.switch', 'ar') }}" class="hover:text-white transition {{ $locale === 'ar' ? 'text-white' : '' }}">العربية</a>
                    </div>
                </div>
            </div>
            <div class="mt-12 pt-6 border-t border-white/10 flex flex-col sm:flex-row justify-between gap-2 text-sm text-white/50">
                <p>&copy; {{ date('Y') }} {{ $settings->legal_entity_name ?: $settings->company_name }}. {{ __('shop.all_rights_reserved') }}</p>
                <p>{{ __('shop.wholesale') }}</p>
            </div>
        </div>
    </footer>

    {{-- Inquiry side drawer --}}
    <div x-cloak x-show="inquiryOpen" class="relative z-50" role="dialog" aria-modal="true">
        {{-- Backdrop --}}
        <div x-show="inquiryOpen" x-transition.opacity.duration.300ms
             @click="inquiryOpen = false"
             class="fixed inset-0 bg-plum-950/40 backdrop-blur-sm"></div>

        {{-- Panel (slides from the inline-end side) --}}
        <div class="fixed inset-y-0 end-0 flex max-w-full">
            <div x-show="inquiryOpen"
                 x-transition:enter="transform transition ease-out duration-300"
                 x-transition:enter-start="translate-x-full rtl:-translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in duration-200"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full rtl:-translate-x-full"
                 class="w-screen max-w-md bg-ivory shadow-2xl h-full">
                <livewire:inquiry-drawer />
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
