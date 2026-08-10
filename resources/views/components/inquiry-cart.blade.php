<?php

use App\Services\Cart\CartService;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /** @var array<int,int> variantId => quantity */
    public array $quantities = [];

    public function mount(CartService $cart): void
    {
        foreach ($cart->items() as $item) {
            $this->quantities[$item['variant']->id] = $item['quantity'];
        }
    }

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

    public function updateQuantity(int $variantId, CartService $cart): void
    {
        $variant = $this->items->firstWhere('variant.id', $variantId)['variant'] ?? null;

        if (! $variant) {
            return;
        }

        $qty = (int) ($this->quantities[$variantId] ?? $variant->effective_moq);
        $cart->setQuantity($variant, $qty);

        // Reflect any MOQ clamping back into the input.
        $this->quantities[$variantId] = max($qty, $variant->effective_moq);
        $this->dispatch('cart-updated');
    }

    public function remove(int $variantId, CartService $cart): void
    {
        $cart->remove($variantId);
        unset($this->quantities[$variantId]);
        $this->dispatch('cart-updated');
    }

    public function clearAll(CartService $cart): void
    {
        $cart->clear();
        $this->quantities = [];
        $this->dispatch('cart-updated');
    }

    public function money(?float $amount): string
    {
        return Money::format($amount);
    }
}; ?>

<div>
    @if ($this->items->isEmpty())
        <div class="text-center py-16">
            <p class="font-display text-2xl text-ink">{{ __('shop.inquiry_empty') }}</p>
            <a href="{{ route('catalogue.index') }}"
               class="mt-5 inline-flex rounded-full bg-plum px-6 py-3 text-white font-medium hover:bg-plum-800 transition">
                {{ __('shop.browse_catalogue') }}
            </a>
        </div>
    @else
        <ul class="divide-y divide-line">
            @foreach ($this->items as $item)
                @php($variant = $item['variant'])
                <li class="flex items-center gap-4 py-5 first:pt-0" wire:key="line-{{ $variant->id }}">
                    <img src="{{ $variant->display_image }}"
                         alt="" class="h-20 w-20 rounded-xl object-cover bg-sand shrink-0">

                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-ink leading-snug">{{ $variant->product?->title }}</p>
                        @if ($variant->title && $variant->title !== 'Default Title')
                            <p class="text-sm text-plum-500">{{ $variant->title }}</p>
                        @endif
                        @if ($variant->sku)
                            <p class="text-xs text-plum-500/70 mt-0.5">{{ $variant->sku }}</p>
                        @endif
                    </div>

                    <div class="flex flex-col items-center gap-1">
                        <input
                            type="number"
                            min="{{ $variant->effective_moq }}"
                            wire:model="quantities.{{ $variant->id }}"
                            wire:change="updateQuantity({{ $variant->id }})"
                            class="w-20 rounded-full border border-line bg-white px-3 py-2 text-center tabular-nums focus:border-plum focus:ring-2 focus:ring-plum/15"
                        >
                        <span class="text-[11px] text-plum-500/70">{{ __('shop.moq') }} {{ $variant->effective_moq }}</span>
                    </div>

                    <div class="w-28 text-end">
                        @if ($item['line_total'] !== null)
                            <p class="font-medium text-ink tabular-nums">{{ $this->money($item['line_total']) }}</p>
                        @else
                            <p class="text-sm text-plum-500">{{ __('shop.price_on_request') }}</p>
                        @endif
                    </div>

                    <button type="button" wire:click="remove({{ $variant->id }})"
                            class="text-plum-500/60 hover:text-rose-deep transition cursor-pointer" title="{{ __('shop.remove') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="flex items-center justify-between mt-6 pt-5 border-t border-line">
            <button type="button" wire:click="clearAll"
                    class="text-sm text-plum-500 hover:text-rose-deep transition cursor-pointer">
                {{ __('shop.clear_inquiry') }}
            </button>

            <div class="text-end">
                @if ($this->hasAnyPrice)
                    <p class="text-sm text-plum-500">{{ __('shop.estimated_subtotal') }}</p>
                    <p class="font-display text-2xl text-ink tabular-nums">{{ $this->money($this->subtotal) }}</p>
                    <p class="text-xs text-plum-500/70 mt-1">{{ __('shop.subtotal_note') }}</p>
                @else
                    <p class="text-sm text-plum-500">{{ __('shop.all_price_on_request') }}</p>
                @endif
            </div>
        </div>
    @endif
</div>
