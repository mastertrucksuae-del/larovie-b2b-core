@php
    use App\Models\BusinessType;
    use App\Support\BusinessTypeIcons;

    // Admin-managed list wins. The shipped six are kept as a fallback so the
    // section never renders as an empty heading — on a fresh install, or if
    // every type is switched off by accident.
    $managed = BusinessType::forStorefront();

    $chips = $managed->isNotEmpty()
        ? $managed->map(fn (BusinessType $type) => [
            'label' => $type->label,
            'icon' => $type->icon_path,
        ])
        : collect([
            'retailers' => 'storefront',
            'pharmacies' => 'pharmacy',
            'salons' => 'sparkles',
            'ecommerce' => 'cart',
            'clinics' => 'clinic',
            'distributors' => 'truck',
        ])->map(fn (string $icon, string $key) => [
            'label' => $t("type_{$key}"),
            'icon' => BusinessTypeIcons::path($icon),
        ])->values();
@endphp

@if ($chips->isNotEmpty())
    <section class="home-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14 sm:py-16">
        <h2 class="font-display text-2xl sm:text-3xl text-ink">{{ $t('business_types') }}</h2>
        <ul class="mt-5 flex flex-wrap gap-2.5">
            @foreach ($chips as $chip)
                <li class="inline-flex items-center gap-2 rounded-full border border-line bg-white px-4 py-2 text-sm text-plum-700">
                    {{-- Decorative: the label beside it already names the type, so
                         a screen reader announcing the icon would just repeat it. --}}
                    <svg class="w-4 h-4 shrink-0 text-rose-accent" fill="none" stroke="currentColor"
                         stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $chip['icon'] }}"/>
                    </svg>
                    {{ $chip['label'] }}
                </li>
            @endforeach
        </ul>
    </section>
@endif
