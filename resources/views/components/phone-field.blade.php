@props([
    'name' => 'customer_mobile',
    'label' => null,
    'value' => null,
    'country' => null,
    'required' => false,
    'id' => null,
])

@php
    use App\Support\Countries;
    use App\Support\Geo;

    $id = $id ?: $name;
    $countryName = $name.'_country';

    // Sticky across a validation failure, then the buyer's own submitted value,
    // then a guess from the edge/browser. Never silently a wrong country.
    $selected = old($countryName, $country ?: Geo::defaultRegion());
    $selected = Countries::isSupported($selected) ? strtoupper($selected) : \App\Support\PhoneNumber::DEFAULT_REGION;

    $number = old($name, $value);
@endphp

<div>
    @if ($label)
        <label class="block text-sm font-medium text-ink mb-1.5" for="{{ $id }}">
            {{ $label }}@if ($required) * @endif
        </label>
    @endif

    {{-- Forced LTR: a phone number reads left-to-right even on an Arabic page,
         and an RTL container would otherwise flip the code and the digits. --}}
    <div class="flex gap-2" dir="ltr">
        <select name="{{ $countryName }}"
                aria-label="{{ __('shop.country_code') }}"
                class="w-32 shrink-0 rounded-xl border border-line bg-ivory px-2 py-2.5 text-ink text-sm focus:border-plum focus:ring-2 focus:ring-plum/15 focus:bg-white transition">
            @foreach (Countries::options() as $iso => $labelText)
                <option value="{{ $iso }}" @selected($selected === $iso)>
                    {{ $iso }} +{{ Countries::dialCode($iso) }}
                </option>
            @endforeach
        </select>

        <input id="{{ $id }}"
               type="tel"
               name="{{ $name }}"
               value="{{ $number }}"
               @if ($required) required @endif
               inputmode="tel"
               autocomplete="tel-national"
               placeholder="{{ __('shop.mobile_placeholder') }}"
               class="w-full rounded-xl border border-line bg-ivory px-4 py-2.5 text-ink focus:border-plum focus:ring-2 focus:ring-plum/15 focus:bg-white transition">
    </div>

    <p class="mt-1 text-xs text-plum-500">{{ __('shop.mobile_hint') }}</p>

    @error($name) <p class="mt-1 text-xs text-rose-deep">{{ $message }}</p> @enderror
    @error($countryName) <p class="mt-1 text-xs text-rose-deep">{{ $message }}</p> @enderror
</div>
