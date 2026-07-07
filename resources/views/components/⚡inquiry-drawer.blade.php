<?php

use App\Services\Cart\CartService;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function items()
    {
        return app(CartService::class)->items();
    }

    #[Computed]
    public function subtotal(): float
    {
        return app(CartService::class)->subtotal();
    }

    #[Computed]
    public function hasAnyPrice(): bool
    {
        return app(CartService::class)->hasAnyPrice();
    }

    #[On('cart-updated')]
    public function refresh(): void
    {
        // Recompute the drawer contents.
        unset($this->items, $this->subtotal, $this->hasAnyPrice);
    }

    public function updateQuantity(int $variantId, int $quantity, CartService $cart): void
    {
        $variant = $this->items->firstWhere('variant.id', $variantId)['variant'] ?? null;
        if ($variant) {
            $cart->setQuantity($variant, $quantity);
            $this->dispatch('cart-updated');
        }
    }

    public function remove(int $variantId, CartService $cart): void
    {
        $cart->remove($variantId);
        $this->dispatch('cart-updated');
    }

    public function money(?float $amount): string
    {
        return Money::format($amount);
    }
}; ?>

<div class="flex h-full flex-col">
    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-5 border-b border-line">
        <h2 class="font-display text-xl text-ink">{{ __('shop.your_inquiry') }}</h2>
        <button type="button" x-on:click="inquiryOpen = false"
                class="text-plum-500 hover:text-ink transition cursor-pointer" aria-label="{{ __('shop.close') }}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Items --}}
    <div class="flex-1 overflow-y-auto px-6 py-4">
        @if ($this->items->isEmpty())
            <div class="h-full flex flex-col items-center justify-center text-center py-16">
                <div class="w-14 h-14 rounded-full bg-blush flex items-center justify-center mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-rose-accent">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z" />
                    </svg>
                </div>
                <p class="text-plum-600">{{ __('shop.inquiry_empty') }}</p>
                <button type="button" x-on:click="inquiryOpen = false"
                        class="mt-5 rounded-full bg-plum px-6 py-2.5 text-white text-sm font-medium hover:bg-plum-800 transition cursor-pointer">
                    {{ __('shop.browse_catalogue') }}
                </button>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($this->items as $item)
                    @php($variant = $item['variant'])
                    <li class="flex gap-4 py-4" wire:key="drawer-{{ $variant->id }}">
                        <img src="{{ $variant->display_image }}" alt=""
                             class="h-20 w-20 rounded-xl object-cover bg-sand shrink-0">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-ink leading-snug line-clamp-2">{{ $variant->product?->title }}</p>
                            @if ($variant->title && $variant->title !== 'Default Title')
                                <p class="text-sm text-plum-500">{{ $variant->title }}</p>
                            @endif
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <div class="inline-flex items-center rounded-full border border-line bg-white">
                                    <button type="button" class="px-2.5 py-1 text-plum-600 hover:text-plum cursor-pointer"
                                            wire:click="updateQuantity({{ $variant->id }}, {{ max($variant->effective_moq, $item['quantity'] - 1) }})">−</button>
                                    <span class="px-2 text-sm tabular-nums">{{ $item['quantity'] }}</span>
                                    <button type="button" class="px-2.5 py-1 text-plum-600 hover:text-plum cursor-pointer"
                                            wire:click="updateQuantity({{ $variant->id }}, {{ $item['quantity'] + 1 }})">+</button>
                                </div>
                                <div class="text-end">
                                    @if ($item['line_total'] !== null)
                                        <p class="text-sm font-medium text-ink">{{ $this->money($item['line_total']) }}</p>
                                    @else
                                        <p class="text-xs text-plum-500">{{ __('shop.price_on_request') }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <button type="button" wire:click="remove({{ $variant->id }})"
                                class="text-plum-500/60 hover:text-rose-deep transition self-start cursor-pointer" aria-label="{{ __('shop.remove') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Footer / actions --}}
    @if ($this->items->isNotEmpty())
        <div class="border-t border-line px-6 py-5 bg-white/60 space-y-4">
            <div class="flex items-center justify-between">
                @if ($this->hasAnyPrice)
                    <span class="text-sm text-plum-600">{{ __('shop.estimated_subtotal') }}</span>
                    <span class="font-display text-lg text-ink">{{ $this->money($this->subtotal) }}</span>
                @else
                    <span class="text-sm text-plum-600">{{ __('shop.all_price_on_request') }}</span>
                @endif
            </div>
            <a href="{{ route('cart') }}"
               class="block w-full text-center rounded-full bg-plum px-6 py-3.5 text-white font-medium hover:bg-plum-800 transition">
                {{ __('shop.request_a_quote') }}
            </a>
            <a href="{{ route('cart') }}"
               class="flex items-center justify-center gap-1.5 text-sm text-plum-600 hover:text-plum transition">
                {{ __('shop.view_full_inquiry') }}
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 rtl:rotate-180">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>
    @endif
</div>
