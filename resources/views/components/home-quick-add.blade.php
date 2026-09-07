<?php

use App\Models\Product;
use App\Services\Cart\CartService;
use Livewire\Component;

/**
 * "Add to enquiry" button for a homepage featured card.
 *
 * Mirrors the catalogue grid's `quickAdd`: a single-variant product goes straight
 * into the inquiry and opens the drawer, while a product with real variant choices
 * routes to its own page, because picking the wrong variant for the buyer is worse
 * than one extra click.
 */
new class extends Component
{
    public Product $product;

    public function add(CartService $cart)
    {
        $product = Product::publiclyVisible()
            ->with(['variants' => fn ($q) => $q->where('is_visible', true)->where('is_archived', false)])
            ->find($this->product->id);

        if (! $product || $product->variants->isEmpty()) {
            return null;
        }

        if ($product->variants->count() > 1) {
            return $this->redirect(route('catalogue.show', $product->handle), navigate: true);
        }

        $variant = $product->variants->first();
        $cart->add($variant, $variant->effective_moq);
        $this->dispatch('cart-updated');
        $this->dispatch('inquiry-open');

        return null;
    }
}; ?>

<button type="button" wire:click="add" wire:loading.attr="disabled" wire:target="add"
        class="inline-flex flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-plum px-3 h-10 text-xs font-semibold text-white hover:bg-plum-800 disabled:opacity-60 transition cursor-pointer">
    <svg wire:loading.remove wire:target="add" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
    </svg>
    <svg wire:loading wire:target="add" class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z"/>
    </svg>
    {{ \App\Support\HomeContent::text('add_to_inquiry') }}
</button>
