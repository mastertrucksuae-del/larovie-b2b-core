@php
    /**
     * A horizontal strip of related products.
     *
     * Expects $title (string), $products (Collection<Product>) and optionally
     * $moreUrl / $moreLabel for a "see all" link. Renders nothing when empty, so
     * callers can include it unconditionally.
     *
     * Every card is a plain <a> to the product page — these rails are the
     * storefront's internal linking, so they must survive with JavaScript off.
     */
    $products = $products ?? collect();
@endphp

@if ($products->isNotEmpty())
    <section class="mt-16 border-t border-line pt-10" aria-labelledby="{{ $railId = 'rail-'.\Illuminate\Support\Str::slug($title) }}">
        <div class="flex items-end justify-between gap-4">
            <h2 id="{{ $railId }}" class="font-display text-2xl sm:text-3xl text-ink">{{ $title }}</h2>

            @if (! empty($moreUrl))
                <a href="{{ $moreUrl }}" class="inline-flex items-center gap-1.5 text-sm text-rose-deep hover:text-plum transition whitespace-nowrap">
                    {{ $moreLabel ?? __('shop.view_all') }}
                    <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </a>
            @endif
        </div>

        {{-- Scrolls sideways on narrow screens rather than wrapping into a tall
             block that pushes the rest of the page out of reach. --}}
        <ul class="mt-6 flex gap-4 overflow-x-auto no-scrollbar snap-x snap-mandatory sm:grid sm:grid-cols-3 lg:grid-cols-6 sm:overflow-visible">
            @foreach ($products as $item)
                @php
                    $img = $item->display_image;
                    $set = \App\Support\Img::srcset($img);
                @endphp
                <li class="w-40 shrink-0 snap-start sm:w-auto">
                    <a href="{{ route('catalogue.show', $item->handle) }}"
                       class="group block h-full rounded-2xl bg-white ring-1 ring-line overflow-hidden hover:ring-plum/20 hover:shadow-[0_12px_40px_-12px_rgba(36,19,39,0.16)] transition duration-300">
                        <div class="aspect-square bg-sand overflow-hidden">
                            @if ($img)
                                <img src="{{ \App\Support\Img::at($img, 300) }}"
                                     @if ($set) srcset="{{ $set }}" sizes="(min-width: 1024px) 180px, 160px" @endif
                                     alt="{{ $item->title }}"
                                     width="300" height="300"
                                     class="img-zoom h-full w-full object-cover"
                                     loading="lazy" decoding="async">
                            @endif
                        </div>
                        <div class="p-3">
                            @if ($item->effective_brand)
                                <span class="block text-[10px] uppercase tracking-[0.16em] text-plum-500 truncate">{{ $item->effective_brand }}</span>
                            @endif
                            <h3 class="mt-1 text-sm font-medium text-ink leading-snug line-clamp-2 group-hover:text-plum transition">
                                {{ $item->title }}
                            </h3>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
