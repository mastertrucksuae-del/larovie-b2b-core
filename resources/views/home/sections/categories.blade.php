{{-- Guarded here rather than by the caller: the section is switched on
     but has nothing to show when no categories resolve from the catalogue. --}}
@if ($categories->isNotEmpty())
    <section class="home-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14 sm:py-16">
        <div class="flex items-end justify-between gap-4">
            <h2 class="font-display text-3xl sm:text-4xl text-ink">{{ $t('shop_by_category') }}</h2>
            <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-1.5 text-sm text-rose-deep hover:text-plum transition whitespace-nowrap">
                {{ $t('view_all') }}
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
            </a>
        </div>

        <div class="mt-8 grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-4">
            @foreach ($categories as $category)
                <a href="{{ route('catalogue.index', ['q' => $category->search]) }}"
                   class="group flex flex-col rounded-2xl bg-white ring-1 ring-line overflow-hidden hover:ring-plum/20 hover:shadow-[0_12px_40px_-12px_rgba(36,19,39,0.16)] transition duration-300">
                    <div class="aspect-[4/3] overflow-hidden bg-sand">
                        @if ($category->image)
                            <img src="{{ \App\Support\Img::at($category->image, 400) }}"
                                 alt="{{ $category->label }}"
                                 width="400" height="300"
                                 class="img-zoom h-full w-full object-cover"
                                 loading="lazy" decoding="async">
                        @endif
                    </div>
                    <div class="p-4">
                        <h3 class="font-medium text-ink group-hover:text-plum transition">{{ $category->label }}</h3>
                        <p class="mt-0.5 text-xs text-plum-500">{{ __('shop.products_count', ['count' => $category->total]) }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
