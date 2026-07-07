@php
    $starting = $product->starting_price;
    $moq = $product->moq ?? optional($product->variants->first())->effective_moq ?? 1;
@endphp
<a href="{{ route('catalogue.show', $product->handle) }}"
   class="group flex flex-col rounded-2xl bg-white ring-1 ring-line overflow-hidden hover:ring-plum/20 hover:shadow-[0_12px_40px_-12px_rgba(62,35,64,0.18)] transition duration-300">
    <div class="relative aspect-square bg-sand overflow-hidden">
        @if ($product->display_image)
            <img src="{{ $product->display_image }}" alt="{{ $product->title }}"
                 class="img-zoom h-full w-full object-cover" loading="lazy">
        @endif
        @if ($starting === null)
            <span class="absolute top-3 end-3 rounded-full bg-white/85 backdrop-blur px-3 py-1 text-[11px] font-medium text-plum">
                {{ __('shop.on_request') }}
            </span>
        @endif

        {{-- Quick add --}}
        <button type="button"
                wire:click.stop.prevent="quickAdd({{ $product->id }})"
                wire:loading.attr="disabled"
                wire:target="quickAdd({{ $product->id }})"
                title="{{ __('shop.quick_add') }}" aria-label="{{ __('shop.quick_add') }}"
                class="absolute bottom-3 end-3 w-11 h-11 rounded-full bg-white text-plum shadow-[0_4px_14px_-2px_rgba(62,35,64,0.35)] flex items-center justify-center hover:bg-plum hover:text-white active:scale-95 transition cursor-pointer">
            <svg wire:loading.remove wire:target="quickAdd({{ $product->id }})" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            <svg wire:loading wire:target="quickAdd({{ $product->id }})" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
        </button>
    </div>
    <div class="flex flex-1 flex-col p-4">
        @if ($product->product_type)
            <span class="text-[11px] uppercase tracking-[0.15em] text-rose-accent">{{ $product->product_type }}</span>
        @endif
        <h3 class="mt-1 font-medium text-ink leading-snug line-clamp-2 group-hover:text-plum transition">{{ $product->title }}</h3>

        <div class="mt-auto pt-4 flex items-end justify-between">
            <div>
                @if ($starting !== null)
                    <p class="text-[11px] text-plum-500">{{ __('shop.from') }}</p>
                    <p class="text-lg font-semibold text-ink tabular-nums">{{ \App\Support\Money::format($starting) }}</p>
                @else
                    <p class="text-sm font-medium text-plum-600">{{ __('shop.price_on_request') }}</p>
                @endif
            </div>
            <span class="text-[11px] text-plum-500 whitespace-nowrap">{{ __('shop.moq') }} {{ $moq }}</span>
        </div>
    </div>
</a>
