<?php

namespace App\Services\Cart;

use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;

/**
 * Inquiry cart (no login). Stores only variant id + quantity; live product data
 * is resolved on read and snapshotted at submission time.
 *
 * Persistence (P1 #11): the session is the within-request store, backed by a
 * 30-day cookie so the cart survives session expiry and browser restarts. When
 * a fresh session has no cart, it rehydrates from the cookie.
 */
class CartService
{
    protected const SESSION_KEY = 'inquiry_cart';

    protected const COOKIE_KEY = 'inquiry_cart';

    protected const COOKIE_MINUTES = 60 * 24 * 30; // 30 days

    /** Cap distinct lines so the cart can never overflow the ~4KB cookie. */
    protected const MAX_LINES = 150;

    /** @return array<int,int> variantId => quantity */
    protected function raw(): array
    {
        $session = session()->get(self::SESSION_KEY);

        if ($session === null) {
            // Fresh/expired session — rehydrate from the durable cookie.
            $cookie = $this->fromCookie();
            if (! empty($cookie)) {
                session()->put(self::SESSION_KEY, $cookie);
            }

            return $cookie;
        }

        return $session;
    }

    /** @return array<int,int> */
    protected function fromCookie(): array
    {
        $raw = request()->cookie(self::COOKIE_KEY);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $variantId => $qty) {
            if ((int) $qty > 0) {
                $out[(int) $variantId] = (int) $qty;
            }
        }

        return $out;
    }

    protected function persist(array $cart): void
    {
        session()->put(self::SESSION_KEY, $cart);
        Cookie::queue(self::COOKIE_KEY, json_encode($cart), self::COOKIE_MINUTES);
    }

    /**
     * Add (or increase) a variant in the cart, clamped to at least its MOQ.
     */
    public function add(ProductVariant $variant, int $quantity): void
    {
        $cart = $this->raw();

        // Never let a new distinct line push the cart past the cookie limit.
        if (! isset($cart[$variant->id]) && count($cart) >= self::MAX_LINES) {
            return;
        }

        $moq = $variant->effective_moq;

        $current = $cart[$variant->id] ?? 0;
        $next = max($current + $quantity, $moq);

        $cart[$variant->id] = $next;
        $this->persist($cart);
    }

    /**
     * Set an exact quantity. Below MOQ is clamped up; zero/negative removes.
     */
    public function setQuantity(ProductVariant $variant, int $quantity): void
    {
        $cart = $this->raw();

        if ($quantity <= 0) {
            unset($cart[$variant->id]);
        } else {
            $cart[$variant->id] = max($quantity, $variant->effective_moq);
        }

        $this->persist($cart);
    }

    public function remove(int $variantId): void
    {
        $cart = $this->raw();
        unset($cart[$variantId]);
        $this->persist($cart);
    }

    public function clear(): void
    {
        // Set an authoritative empty state (not forget) so a later read in the
        // same request can't rehydrate the just-cleared cart from the cookie.
        session()->put(self::SESSION_KEY, []);
        Cookie::queue(Cookie::forget(self::COOKIE_KEY));
    }

    public function isEmpty(): bool
    {
        return empty($this->raw());
    }

    /** Total number of line items (distinct variants). */
    public function count(): int
    {
        return count($this->raw());
    }

    /** Total units across all lines. */
    public function totalUnits(): int
    {
        return array_sum($this->raw());
    }

    /**
     * Resolve cart into displayable line items with live variant data.
     * Silently drops variants that no longer exist / are archived.
     *
     * @return Collection<int, array{variant: ProductVariant, quantity: int, line_total: ?float}>
     */
    public function items(): Collection
    {
        $cart = $this->raw();

        if (empty($cart)) {
            return collect();
        }

        $variants = ProductVariant::with('product')
            ->whereIn('id', array_keys($cart))
            ->where('is_archived', false)
            ->get()
            ->keyBy('id');

        // Prune stale ids from the session.
        $pruned = array_intersect_key($cart, $variants->all());
        if (count($pruned) !== count($cart)) {
            $this->persist($pruned);
            $cart = $pruned;
        }

        return collect($cart)->map(function (int $qty, int $variantId) use ($variants) {
            $variant = $variants[$variantId];
            $price = $variant->wholesale_price;

            return [
                'variant' => $variant,
                'quantity' => $qty,
                'line_total' => $price !== null ? round($qty * (float) $price, 2) : null,
            ];
        })->values();
    }

    /** Subtotal of lines that have a price; null-priced lines are excluded. */
    public function subtotal(): float
    {
        return (float) $this->items()->sum(fn ($item) => $item['line_total'] ?? 0);
    }

    public function hasAnyPrice(): bool
    {
        return $this->items()->contains(fn ($item) => $item['line_total'] !== null);
    }
}
