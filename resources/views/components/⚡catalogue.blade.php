<?php

use App\Models\Brand;
use App\Models\Product;
use App\Services\Cart\CartService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url(as: 'q', history: true, keep: false)]
    public string $search = '';

    #[Url(history: true)]
    public string $sort = 'brand';

    #[Url(history: true)]
    public ?string $type = null;

    public int $perPage = 24;

    public const SORTS = [
        'brand' => 'Brand',
        'category' => 'Category',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'stock' => 'Stock',
        'name' => 'Name',
    ];

    public function updated($property): void
    {
        // Any filter/sort change restarts the infinite scroll from the top.
        if (in_array($property, ['search', 'sort', 'type'], true)) {
            $this->perPage = 24;
        }
    }

    public function loadMore(): void
    {
        $this->perPage += 24;
    }

    /**
     * Quick-add from a card. Single-variant products are added straight to the
     * inquiry (and the drawer opens); multi-variant products need a choice, so
     * we navigate to the product page.
     */
    public function quickAdd(int $productId, CartService $cart)
    {
        $product = Product::publiclyVisible()
            ->with(['variants' => fn ($q) => $q->where('is_visible', true)->where('is_archived', false)])
            ->find($productId);

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

    public function clearFilters(): void
    {
        $this->reset(['search', 'type']);
        $this->sort = 'brand';
        $this->perPage = 24;
    }

    /**
     * Jump to a brand from the navigation strip: force brand grouping, load enough
     * pages for that brand's products to exist in the DOM, then tell the matching
     * section (browser-side) to expand and scroll into view.
     */
    public function goToBrand(int $index): void
    {
        $nav = $this->brandNav;

        if (! array_key_exists($index, $nav)) {
            return;
        }

        if ($this->sort !== 'brand') {
            $this->sort = 'brand';
        }

        $needed = $nav[$index]['start'] + $nav[$index]['count'];
        if ($this->perPage < $needed) {
            $this->perPage = (int) (ceil($needed / 24) * 24);
        }

        $this->dispatch('brand-jump', index: $index);
    }

    /** Products matching the current search + category filters (no ordering/aggregates). */
    protected function filteredQuery()
    {
        return Product::query()
            ->publiclyVisible()
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('title', 'like', $term)
                        ->orWhere('brand', 'like', $term)
                        ->orWhere('vendor', 'like', $term)
                        ->orWhere('product_type', 'like', $term)
                        ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $term));
                });
            })
            ->when($this->type, fn ($q) => $q->where('product_type', $this->type));
    }

    protected function baseQuery()
    {
        return $this->filteredQuery()
            ->with(['variants' => fn ($q) => $q->where('is_visible', true)->where('is_archived', false)])
            ->withMin(['variants as min_price' => fn ($q) => $q->where('is_visible', true)
                ->where('is_archived', false)->whereNotNull('wholesale_price')], 'wholesale_price')
            ->withSum(['variants as total_stock' => fn ($q) => $q->where('is_visible', true)
                ->where('is_archived', false)], 'inventory_quantity');
    }

    protected function applySort($query)
    {
        return match ($this->sort) {
            'category' => $query->orderByRaw('product_type is null, product_type asc')->orderBy('title'),
            'price_asc' => $query->orderByRaw('min_price is null, min_price asc')->orderBy('title'),
            'price_desc' => $query->orderByRaw('min_price desc')->orderBy('title'),
            'stock' => $query->orderByDesc('total_stock')->orderBy('title'),
            'name' => $query->orderBy('title'),
            // Default: brands with the most products first, kept contiguously grouped.
            // "Brand" = the Brands metaobject value, falling back to vendor.
            default => $query
                ->orderByRaw('(select count(*) from products p2
                    where coalesce(nullif(p2.brand, ""), p2.vendor) = coalesce(nullif(products.brand, ""), products.vendor)
                    and p2.is_visible = 1 and p2.is_archived = 0) desc')
                ->orderByRaw('coalesce(nullif(brand, ""), vendor) is null, coalesce(nullif(brand, ""), vendor) asc')
                ->orderBy('title'),
        };
    }

    #[Computed]
    public function total(): int
    {
        return $this->filteredQuery()->count();
    }

    /**
     * Brands in the same order the product grid uses (most products first, then
     * alphabetical, "Other" last), each with its product count and the absolute
     * index of its first product in the full ordered list. That start offset lets
     * goToBrand() load exactly enough pages to reveal the brand before scrolling.
     */
    #[Computed]
    public function brandNav(): array
    {
        // Order by the grouped alias `b` (not the raw coalesce expression) so the
        // query is valid under MySQL's only_full_group_by mode.
        $rows = $this->filteredQuery()
            ->selectRaw('coalesce(nullif(brand, ""), vendor) as b, count(*) as c')
            ->groupBy('b')
            ->orderByRaw('c desc')
            ->orderByRaw('b is null, b asc')
            ->get();

        $logos = Brand::logoUrlMap();

        $cursor = 0;
        $nav = [];
        foreach ($rows as $row) {
            $brand = (string) $row->b;
            $count = (int) $row->c;
            $nav[] = [
                'brand' => $brand,
                'label' => $brand !== '' ? $brand : __('shop.other'),
                'logo' => $logos[$brand] ?? null,
                'count' => $count,
                'start' => $cursor,
            ];
            $cursor += $count;
        }

        return $nav;
    }

    #[Computed]
    public function products()
    {
        return $this->applySort($this->baseQuery())
            ->take($this->perPage)
            ->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->total > $this->perPage;
    }

    #[Computed]
    public function categories()
    {
        return Product::publiclyVisible()
            ->whereNotNull('product_type')
            ->distinct()
            ->orderBy('product_type')
            ->pluck('product_type');
    }

    #[Computed]
    public function brands()
    {
        return Product::publiclyVisible()
            ->whereNotNull('vendor')
            ->distinct()
            ->orderBy('vendor')
            ->pluck('vendor');
    }
}; ?>

<div>
    {{-- Brand navigation slider: a sticky, horizontally-scrollable strip of brand
         chips that jump to (and expand) the matching brand section on the page. --}}
    @if (count($this->brandNav) > 1)
        <div class="sticky top-24 z-30 -mx-4 sm:-mx-6 lg:-mx-8 mb-8 border-b border-line bg-ivory/90 backdrop-blur-md"
             x-data="{
                 active: null,
                 sync() {
                     const line = 160;
                     let current = null;
                     document.querySelectorAll('section[data-brand-index]').forEach(el => {
                         if (el.getBoundingClientRect().top - line <= 0) current = el.getAttribute('data-brand-index');
                     });
                     this.active = current === null ? null : parseInt(current);
                 }
             }"
             x-init="$nextTick(() => sync())"
             x-effect="active !== null && $refs['chip' + active] && $refs['chip' + active].scrollIntoView({ inline: 'center', block: 'nearest' })"
             @scroll.window.throttle.100ms="sync()"
             @resize.window.throttle.150ms="sync()">
            <div class="flex items-center gap-3 px-4 sm:px-6 lg:px-8 py-3">
                <span class="hidden sm:block shrink-0 text-xs font-semibold uppercase tracking-[0.15em] text-plum-500/70">{{ __('shop.brands') }}</span>
                <div class="flex gap-2 overflow-x-auto scroll-smooth no-scrollbar py-0.5 -my-0.5">
                    @foreach ($this->brandNav as $i => $b)
                        <button type="button" wire:click="goToBrand({{ $i }})"
                                x-ref="chip{{ $i }}"
                                :class="active === {{ $i }}
                                    ? 'bg-plum text-white border-plum shadow-sm'
                                    : 'bg-white text-ink border-line hover:border-plum/50 hover:bg-blush/40'"
                                class="shrink-0 inline-flex items-center gap-2 rounded-full border py-2 pe-4 whitespace-nowrap transition-colors cursor-pointer {{ empty($b['logo']) ? 'ps-4' : 'ps-2' }} text-sm font-medium">
                            @if (! empty($b['logo']))
                                <img src="{{ $b['logo'] }}" alt="" aria-hidden="true"
                                     class="h-6 w-6 rounded-full object-contain bg-white ring-1 ring-line/70 p-0.5" loading="lazy">
                            @endif
                            <span>{{ $b['label'] }}</span>
                            <span :class="active === {{ $i }} ? 'bg-white/20 text-white' : 'bg-blush text-rose-deep'"
                                  class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[11px] font-semibold transition-colors">{{ $b['count'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Controls: a dominant search bar, with compact filters alongside --}}
    <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative flex-1 lg:min-w-0">
            <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-6 text-plum-500/70">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </span>
            <input type="search" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('shop.search_placeholder') }}"
                   class="w-full rounded-full border border-line bg-white ps-14 pe-5 py-4 text-lg text-ink placeholder:text-plum-500/50 shadow-sm focus:border-plum focus:ring-2 focus:ring-plum/15 transition">
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <select wire:model.live="type"
                    class="w-40 rounded-full border border-line bg-white px-4 py-3.5 text-sm text-ink focus:border-plum focus:ring-2 focus:ring-plum/15">
                <option value="">{{ __('shop.all_categories') }}</option>
                @foreach ($this->categories as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>

            <select wire:model.live="sort"
                    class="w-44 rounded-full border border-line bg-white px-4 py-3.5 text-sm text-ink focus:border-plum focus:ring-2 focus:ring-plum/15"
                    aria-label="{{ __('shop.sort_by') }}">
                @foreach (self::SORTS as $value => $label)
                    <option value="{{ $value }}">{{ __('shop.sort_by') }}: {{ __('shop.sort_'.$value) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Result meta --}}
    <div class="mb-6 flex items-center justify-between text-sm text-plum-500">
        <span>{{ trans_choice('shop.products_count', $this->total, ['count' => number_format($this->total)]) }}</span>
        @if ($search !== '' || $type)
            <button wire:click="clearFilters" class="text-rose-deep hover:underline cursor-pointer">{{ __('shop.clear_filters') }}</button>
        @endif
    </div>

    @if ($this->products->isEmpty())
        <div class="text-center py-24">
            <p class="font-display text-2xl text-ink">{{ __('shop.no_products') }}</p>
            <button wire:click="clearFilters" class="mt-4 text-rose-deep hover:underline cursor-pointer">{{ __('shop.clear_filters') }}</button>
        </div>
    @else
        <div wire:loading.class="opacity-40" class="transition duration-200">
            @if ($sort === 'brand')
                @php
                    // Map each brand to its navigation index + true total (across all pages).
                    $navByBrand = [];
                    foreach ($this->brandNav as $i => $b) {
                        $navByBrand[$b['brand']] = ['index' => $i, 'count' => $b['count'], 'logo' => $b['logo'] ?? null];
                    }
                    // Products already arrive ordered contiguously by brand.
                    $groups = $this->products->groupBy(fn ($p) => (string) ($p->effective_brand ?? ''));
                @endphp
                @foreach ($groups as $brandKey => $items)
                    @php
                        $meta = $navByBrand[(string) $brandKey] ?? ['index' => $loop->index, 'count' => $items->count()];
                        $idx = $meta['index'];
                        $logo = $meta['logo'] ?? null;
                        $label = $brandKey !== '' ? $brandKey : __('shop.other');
                    @endphp
                    <section id="brand-{{ $idx }}" data-brand-index="{{ $idx }}" wire:key="brand-sec-{{ $idx }}"
                             x-data="{ open: true }"
                             x-on:brand-jump.window="if ($event.detail.index === {{ $idx }}) { open = true; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' })) }"
                             class="scroll-mt-44">
                        <div class="flex items-center gap-3 mt-12 mb-5 first:mt-0">
                            <button type="button" @click="open = !open" :aria-expanded="open"
                                    aria-controls="brand-grid-{{ $idx }}"
                                    class="group flex items-center gap-3 cursor-pointer text-start">
                                <svg class="w-5 h-5 shrink-0 text-plum-500 transition-transform duration-200 rtl:-scale-x-100"
                                     :class="open ? 'rotate-90' : ''"
                                     fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                                @if ($logo)
                                    <img src="{{ $logo }}" alt="{{ $label }}"
                                         class="h-9 w-auto max-w-28 object-contain shrink-0" loading="lazy">
                                @endif
                                <h2 class="font-display text-2xl text-ink whitespace-nowrap group-hover:text-plum transition-colors">{{ $label }}</h2>
                                <span class="inline-flex items-center justify-center min-w-6 h-6 px-2 rounded-full bg-blush text-rose-deep text-xs font-semibold">
                                    {{ $meta['count'] }}
                                </span>
                            </button>
                            <span class="h-px flex-1 bg-line"></span>
                        </div>
                        <div id="brand-grid-{{ $idx }}" x-show="open" x-collapse
                             class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5 sm:gap-7">
                            @foreach ($items as $product)
                                @include('catalogue.partials.product-card', ['product' => $product])
                            @endforeach
                        </div>
                    </section>
                @endforeach
            @else
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5 sm:gap-7">
                    @foreach ($this->products as $product)
                        @include('catalogue.partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Infinite scroll sentinel + graceful fallback --}}
        @if ($this->hasMore)
            <div class="mt-12 flex flex-col items-center gap-4">
                <div wire:loading wire:target="loadMore" class="flex items-center gap-2 text-sm text-plum-500">
                    <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                    {{ __('shop.loading_more') }}
                </div>

                <div wire:key="sentinel-{{ $perPage }}"
                     x-data="{ obs: null }"
                     x-init="obs = new IntersectionObserver((entries) => { if (entries[0].isIntersecting) { $wire.loadMore() } }, { rootMargin: '700px' }); obs.observe($el)"
                     x-on:destroy="obs && obs.disconnect()"
                     class="h-px w-full"></div>

                <button wire:click="loadMore" wire:loading.attr="disabled"
                        class="rounded-full border border-plum/30 px-8 py-3 text-sm font-medium text-plum hover:bg-plum hover:text-white transition cursor-pointer">
                    {{ __('shop.load_more') }}
                </button>
            </div>
        @endif
    @endif
</div>
