{{-- Guarded here rather than by the caller: the section is switched on
     but has nothing to show when nothing in the catalogue carries artwork. --}}
@if ($featured->isNotEmpty())
    <section class="home-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14 sm:py-16">
        <div class="flex items-end justify-between gap-4">
            <h2 class="font-display text-3xl sm:text-4xl text-ink">{{ $t('featured_products') }}</h2>
            <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-1.5 text-sm text-rose-deep hover:text-plum transition whitespace-nowrap">
                {{ $t('hero_cta_browse') }}
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
            </a>
        </div>

        <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($featured as $product)
                @include('home.partials.featured-card', ['product' => $product, 'priority' => false])
            @endforeach
        </div>
    </section>
@endif
