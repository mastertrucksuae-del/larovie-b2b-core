{{-- Guarded here rather than by the caller: the section is switched on
     but has nothing to show when no brands resolve from the catalogue. --}}
@if ($brands->isNotEmpty())
    <section class="border-y border-line bg-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14 sm:py-16">
            <div class="flex items-end justify-between gap-4">
                <h2 class="font-display text-3xl sm:text-4xl text-ink">{{ $t('featured_brands') }}</h2>
                <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-1.5 text-sm text-rose-deep hover:text-plum transition whitespace-nowrap">
                    {{ $t('view_all_brands') }}
                    <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </a>
            </div>

            <ul class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($brands as $brand)
                    <li>
                        {{-- Same tile treatment as the catalogue's brand strip: a fixed
                             white plate with the logo contained inside it, so brands read
                             as one system whatever shape or aspect their artwork is.
                             The name stands in when no logo has been uploaded yet. --}}
                        <a href="{{ route('catalogue.index', ['q' => $brand->name]) }}"
                           class="group relative flex h-full flex-col items-center justify-center gap-2 rounded-xl border border-line bg-white px-3 py-5 text-center hover:border-plum/40 hover:shadow-sm transition"
                           title="{{ $brand->name }}">
                            @if ($brand->logo)
                                <span class="flex h-12 w-full items-center justify-center">
                                    <img src="{{ $brand->logo }}" alt="{{ $brand->name }}"
                                         width="112" height="48"
                                         class="max-h-full max-w-full object-contain"
                                         loading="lazy" decoding="async">
                                </span>
                            @else
                                <span class="flex h-12 items-center justify-center text-sm font-medium text-ink group-hover:text-plum transition line-clamp-2 leading-tight">
                                    {{ $brand->name }}
                                </span>
                            @endif
                            <span class="text-[11px] text-plum-500">{{ __('shop.products_count', ['count' => $brand->total]) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
