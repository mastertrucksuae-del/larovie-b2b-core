<section class="bg-plum">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-16 sm:py-20 text-center">
        <h2 class="font-display text-3xl sm:text-4xl text-white">{{ $t('cta_title') }}</h2>
        <p class="mt-4 text-sm leading-relaxed text-white/70">{{ $t('cta_body') }}</p>

        <div class="mt-8 flex flex-col sm:flex-row justify-center gap-3">
            @guest('business')
                <a href="{{ route('register') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-blush px-7 py-3.5 text-sm font-semibold text-plum hover:bg-white transition">
                    {{ $t('hero_cta_account') }}
                </a>
            @else
                <a href="{{ route('account') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-blush px-7 py-3.5 text-sm font-semibold text-plum hover:bg-white transition">
                    {{ __('shop.nav_my_account') }}
                </a>
            @endguest
            <a href="{{ route('contact') }}"
               class="inline-flex items-center justify-center rounded-xl border border-white/25 px-7 py-3.5 text-sm font-semibold text-white hover:bg-white/10 transition">
                {{ $t('cta_contact') }}
            </a>
        </div>
    </div>
</section>
