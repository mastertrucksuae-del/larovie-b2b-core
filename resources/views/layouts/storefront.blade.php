@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
    $settings = \App\Models\Setting::current();
    $logo = $settings->logo_path
        ? \Illuminate\Support\Facades\Storage::url($settings->logo_path)
        : asset('images/larovie-logo-dark-transparant.png');
    $logoWhite = asset('images/larovie-logo-white-transparant.png');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $settings->company_name) — {{ __('shop.wholesale') }}</title>
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-dvh antialiased {{ $locale === 'ar' ? 'font-arabic' : 'font-sans' }}"
      x-data="{ inquiryOpen: false }"
      @inquiry-open.window="inquiryOpen = true"
      @keydown.escape.window="inquiryOpen = false">

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

                <div class="flex items-center gap-3 sm:gap-5">
                    {{-- Language switch --}}
                    <div class="hidden sm:flex items-center rounded-full border border-line bg-white p-0.5 text-xs">
                        <a href="{{ route('locale.switch', 'en') }}"
                           @class(['px-3 py-1.5 rounded-full transition', 'bg-plum text-white' => $locale === 'en', 'text-plum-600 hover:text-plum' => $locale !== 'en'])>EN</a>
                        <a href="{{ route('locale.switch', 'ar') }}"
                           @class(['px-3 py-1.5 rounded-full transition', 'bg-plum text-white' => $locale === 'ar', 'text-plum-600 hover:text-plum' => $locale !== 'ar'])>ع</a>
                    </div>

                    <livewire:cart-counter />
                </div>
            </div>
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
            <div class="grid gap-10 md:grid-cols-3">
                <div>
                    <img src="{{ $logoWhite }}" alt="{{ $settings->company_name }}" class="h-14 w-auto mb-4">
                    <p class="font-display text-lg text-white/90 max-w-xs leading-snug">{{ __('shop.tagline') }}</p>
                </div>
                <div class="md:text-center">
                    <h3 class="text-xs uppercase tracking-[0.2em] text-white/50 mb-3">{{ __('shop.wholesale_enquiries') }}</h3>
                    <p class="text-white/90">{{ $settings->company_email }}</p>
                    <p class="text-white/90">{{ $settings->company_phone }}</p>
                    @if ($settings->company_address)
                        <p class="mt-2 text-sm text-white/60">{{ $settings->company_address }}</p>
                    @endif
                </div>
                <div class="md:text-end">
                    <h3 class="text-xs uppercase tracking-[0.2em] text-white/50 mb-3">{{ __('shop.language') }}</h3>
                    <div class="flex md:justify-end gap-3">
                        <a href="{{ route('locale.switch', 'en') }}" class="hover:text-white transition {{ $locale === 'en' ? 'text-white' : '' }}">English</a>
                        <span class="text-white/30">·</span>
                        <a href="{{ route('locale.switch', 'ar') }}" class="hover:text-white transition {{ $locale === 'ar' ? 'text-white' : '' }}">العربية</a>
                    </div>
                </div>
            </div>
            <div class="mt-12 pt-6 border-t border-white/10 flex flex-col sm:flex-row justify-between gap-2 text-sm text-white/50">
                <p>&copy; {{ date('Y') }} {{ $settings->company_name }}. {{ __('shop.all_rights_reserved') }}</p>
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
