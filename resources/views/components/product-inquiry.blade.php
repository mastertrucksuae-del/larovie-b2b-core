<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\CartService;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Product $product;

    public ?int $selectedVariantId = null;

    public int $quantity = 1;

    public bool $added = false;

    public function mount(): void
    {
        $first = $this->variants->first();

        if ($first) {
            $this->selectedVariantId = $first->id;
            $this->quantity = $first->effective_moq;
        }
    }

    #[Computed]
    public function variants()
    {
        return $this->product->variants()
            ->where('is_visible', true)
            ->where('is_archived', false)
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function variant(): ?ProductVariant
    {
        return $this->variants->firstWhere('id', $this->selectedVariantId);
    }

    public function updatedSelectedVariantId(): void
    {
        $this->added = false;
        $this->quantity = $this->variant?->effective_moq ?? 1;
    }

    public function addToInquiry(CartService $cart): void
    {
        $variant = $this->variant;

        if (! $variant) {
            return;
        }

        $quantity = max((int) $this->quantity, $variant->effective_moq);
        $cart->setQuantity($variant, $quantity);

        $this->added = true;
        $this->dispatch('cart-updated');
        $this->dispatch('inquiry-open'); // slide the drawer in as confirmation
    }

    public function priceLabel(): string
    {
        $variant = $this->variant;

        if (! $variant || ! $variant->hasPrice()) {
            return __('shop.price_on_request');
        }

        return Money::format($variant->wholesale_price);
    }
}; ?>

<div class="space-y-5">
    @php($variant = $this->variant)

    {{-- Price --}}
    <div class="pb-6 border-b border-line">
        <p class="font-display text-3xl text-ink tabular-nums">{{ $this->priceLabel() }}</p>
        <p class="mt-1 text-sm text-plum-500">{{ __('shop.wholesale_price_note') }}</p>
    </div>

    {{-- Variant selector --}}
    @if ($this->variants->count() > 1)
        <div>
            <label class="block text-sm font-medium text-ink mb-2">{{ __('shop.select_option') }}</label>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->variants as $v)
                    <button
                        type="button"
                        wire:click="$set('selectedVariantId', {{ $v->id }})"
                        @class([
                            'px-4 py-2 rounded-full border text-sm transition cursor-pointer',
                            'border-plum bg-plum text-white' => $selectedVariantId === $v->id,
                            'border-line bg-white text-plum-700 hover:border-plum/40' => $selectedVariantId !== $v->id,
                        ])
                    >
                        {{ $v->title ?: __('shop.default_variant') }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- MOQ + quantity --}}
    @if ($variant)
        <div>
            <label for="qty" class="block text-sm font-medium text-ink mb-2">
                {{ __('shop.quantity') }}
                <span class="text-plum-500 font-normal">· {{ __('shop.moq') }} {{ $variant->effective_moq }}</span>
            </label>
            <input
                id="qty"
                type="number"
                min="{{ $variant->effective_moq }}"
                wire:model="quantity"
                class="w-32 rounded-full border border-line bg-white px-5 py-3 text-ink focus:border-plum focus:ring-2 focus:ring-plum/15"
            >
        </div>
    @endif

    {{-- Add to inquiry --}}
    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button
            type="button"
            wire:click="addToInquiry"
            wire:loading.attr="disabled"
            @disabled(! $variant)
            class="inline-flex items-center gap-2 rounded-full bg-plum px-8 py-3.5 text-white font-medium hover:bg-plum-800 disabled:opacity-50 transition cursor-pointer"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {{ __('shop.add_to_inquiry') }}
        </button>

        @if ($added)
            <span class="inline-flex items-center gap-1.5 text-sm text-rose-deep" wire:transition>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                {{ __('shop.added_to_inquiry') }}
            </span>
        @endif
    </div>
</div>
