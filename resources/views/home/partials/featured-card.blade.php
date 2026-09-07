@php
    use App\Support\HomeContent;

    $variants = $product->visibleVariants;
    $first = $variants->first();

    // Shopify names a sole variant "Default Title"; showing that as a size would
    // be noise, so only a real option label (100 ml, 2-pack…) is rendered.
    $variantLabel = $first && ! in_array($first->title, ['Default Title', 'Default'], true)
        ? $first->title
        : null;

    $moq = $product->moq ?? optional($first)->effective_moq ?? 1;
    $stock = $variants->sum('inventory_quantity');

    // Stock across this catalogue averages ~4 units a variant, so the usual
    // "low stock under 10" rule would badge literally every card as limited and
    // carry no signal. 3 keeps the warning worth reading.
    $lowStockThreshold = 3;
    $price = $product->starting_price;

    $img = $product->display_image;
    $srcset = \App\Support\Img::srcset($img);
    $priority = ($priority ?? false) === true;
@endphp

<article class="group flex flex-col rounded-2xl bg-white ring-1 ring-line overflow-hidden hover:ring-plum/20 hover:shadow-[0_12px_40px_-12px_rgba(36,19,39,0.16)] transition duration-300">
    <a href="{{ route('catalogue.show', $product->handle) }}"
       class="block relative aspect-square bg-sand overflow-hidden"
       tabindex="-1" aria-hidden="true">
        @if ($img)
            <img src="{{ \App\Support\Img::at($img, 400) }}"
                 @if ($srcset) srcset="{{ $srcset }}" sizes="(min-width: 1024px) 300px, (min-width: 640px) 45vw, 90vw" @endif
                 alt="{{ $product->title }}"
                 width="400" height="400"
                 class="img-zoom h-full w-full object-cover"
                 loading="{{ $priority ? 'eager' : 'lazy' }}"
                 fetchpriority="{{ $priority ? 'high' : 'auto' }}"
                 decoding="{{ $priority ? 'sync' : 'async' }}">
        @endif
    </a>

    <div class="flex flex-1 flex-col p-4">
        @if ($product->effective_brand)
            <span class="text-[11px] uppercase tracking-[0.18em] text-plum-500">{{ $product->effective_brand }}</span>
        @endif

        <h3 class="mt-1.5 font-medium text-ink leading-snug line-clamp-2">
            <a href="{{ route('catalogue.show', $product->handle) }}" class="hover:text-plum transition">
                {{ $product->title }}
            </a>
        </h3>

        @if ($variantLabel)
            <p class="mt-1 text-xs text-rose-deep">{{ $variantLabel }}</p>
        @endif

        {{-- Stock signal --}}
        <p class="mt-3 inline-flex items-center gap-1.5 text-xs
                  {{ $stock > 0 ? ($stock <= $lowStockThreshold ? 'text-amber-700' : 'text-emerald-700') : 'text-plum-500' }}">
            <span class="w-1.5 h-1.5 rounded-full {{ $stock > 0 ? ($stock <= $lowStockThreshold ? 'bg-amber-500' : 'bg-emerald-500') : 'bg-plum-500/50' }}"></span>
            @if ($stock > 0)
                {{ $stock <= $lowStockThreshold ? HomeContent::text('limited_stock') : HomeContent::text('in_stock') }}
            @else
                {{ HomeContent::text('out_of_stock') }}
            @endif
        </p>

        <p class="mt-1.5 text-xs text-plum-500">{{ HomeContent::choice('min_order', $moq, ['count' => $moq]) }}</p>

        {{-- Pricing is public: the gate is on submitting an inquiry, not on
             reading a price. --}}
        <div class="mt-1.5 min-h-[1.5rem]">
            @if ($price !== null)
                <p class="text-base font-semibold text-ink tabular-nums">
                    <span class="text-[11px] font-normal text-plum-500 me-1">{{ __('shop.from') }}</span>{{ \App\Support\Money::format($price) }}
                </p>
            @else
                <p class="text-xs font-medium text-plum-600">{{ __('shop.price_on_request') }}</p>
            @endif
        </div>

        <div class="mt-auto pt-4 flex items-center gap-2">
            <a href="{{ route('catalogue.show', $product->handle) }}"
               class="inline-flex flex-1 items-center justify-center rounded-lg border border-line bg-white px-3 h-10 text-xs font-medium text-plum-700 hover:border-plum/40 hover:text-plum transition">
                {{ HomeContent::text('view_details') }}
            </a>
            <livewire:home-quick-add :product="$product" :key="'quick-add-'.$product->id" />
        </div>
    </div>
</article>
