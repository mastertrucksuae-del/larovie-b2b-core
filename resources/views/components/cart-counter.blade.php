<?php

use App\Services\Cart\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public int $count = 0;

    public function mount(CartService $cart): void
    {
        $this->count = $cart->count();
    }

    #[On('cart-updated')]
    public function refresh(CartService $cart): void
    {
        $this->count = $cart->count();
    }
}; ?>

<button type="button" x-on:click="$dispatch('inquiry-open')"
        class="relative inline-flex items-center gap-2 rounded-full bg-plum px-5 py-2.5 text-white text-sm font-medium hover:bg-plum-800 transition cursor-pointer">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
    </svg>
    <span class="hidden sm:inline">{{ __('shop.inquiry') }}</span>
    @if ($count > 0)
        <span class="min-w-5 h-5 px-1.5 inline-flex items-center justify-center rounded-full bg-rose-accent text-white text-xs font-semibold">
            {{ $count }}
        </span>
    @endif
</button>
