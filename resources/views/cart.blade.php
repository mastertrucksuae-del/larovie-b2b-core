@extends('layouts.storefront')

@section('title', __('shop.your_inquiry'))

@section('content')
    <div class="mb-8">
        <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-1.5 text-sm text-plum-500 hover:text-plum transition mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 rtl:rotate-180"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            {{ __('shop.continue_browsing') }}
        </a>
        <h1 class="font-display text-4xl text-ink">{{ __('shop.your_inquiry') }}</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Line items --}}
        <div class="lg:col-span-2">
            <div class="rounded-2xl bg-white ring-1 ring-line p-6 sm:p-8">
                <livewire:inquiry-cart />
            </div>
        </div>

        {{-- Submission form --}}
        <div class="lg:col-span-1">
            <div class="rounded-2xl bg-white ring-1 ring-line p-6 sm:p-8 sticky top-28">
                <h2 class="font-display text-2xl text-ink mb-1">{{ __('shop.request_a_quote') }}</h2>
                <p class="text-sm text-plum-500 mb-6">{{ __('shop.form_intro') }}</p>

                @if ($errors->any())
                    <div class="mb-4 rounded-xl bg-blush border border-rose-accent/30 px-3 py-2 text-sm text-rose-deep">
                        {{ __('shop.form_errors') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('inquiry.store') }}" class="space-y-4">
                    @csrf

                    {{-- Honeypot --}}
                    <div class="hidden" aria-hidden="true">
                        <label>Company website<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink mb-1.5">{{ __('shop.name') }} *</label>
                        <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                               class="w-full rounded-xl border border-line bg-ivory px-4 py-2.5 text-ink focus:border-plum focus:ring-2 focus:ring-plum/15 focus:bg-white transition">
                        @error('customer_name') <p class="mt-1 text-xs text-rose-deep">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink mb-1.5">{{ __('shop.mobile') }} *</label>
                        <div class="flex gap-2" dir="ltr">
                            <span class="inline-flex items-center rounded-xl border border-line bg-sand px-3 text-plum-600 text-sm">+971</span>
                            <input type="tel" name="customer_mobile" value="{{ old('customer_mobile') }}" required placeholder="50 123 4567"
                                   class="w-full rounded-xl border border-line bg-ivory px-4 py-2.5 text-ink focus:border-plum focus:ring-2 focus:ring-plum/15 focus:bg-white transition">
                        </div>
                        @error('customer_mobile') <p class="mt-1 text-xs text-rose-deep">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2.5 text-sm text-ink py-1">
                        <input type="checkbox" name="is_whatsapp" value="1" checked
                               class="rounded border-line text-plum focus:ring-plum">
                        {{ __('shop.is_whatsapp') }}
                    </label>

                    <div>
                        <label class="block text-sm font-medium text-ink mb-1.5">{{ __('shop.email') }}</label>
                        <input type="email" name="customer_email" value="{{ old('customer_email') }}"
                               class="w-full rounded-xl border border-line bg-ivory px-4 py-2.5 text-ink focus:border-plum focus:ring-2 focus:ring-plum/15 focus:bg-white transition">
                        @error('customer_email') <p class="mt-1 text-xs text-rose-deep">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink mb-1.5">{{ __('shop.company') }}</label>
                        <input type="text" name="customer_company" value="{{ old('customer_company') }}"
                               class="w-full rounded-xl border border-line bg-ivory px-4 py-2.5 text-ink focus:border-plum focus:ring-2 focus:ring-plum/15 focus:bg-white transition">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink mb-1.5">{{ __('shop.message') }}</label>
                        <textarea name="customer_message" rows="3"
                                  class="w-full rounded-xl border border-line bg-ivory px-4 py-2.5 text-ink focus:border-plum focus:ring-2 focus:ring-plum/15 focus:bg-white transition">{{ old('customer_message') }}</textarea>
                    </div>

                    <button type="submit"
                            class="w-full rounded-full bg-plum px-6 py-3.5 text-white font-medium hover:bg-plum-800 transition">
                        {{ __('shop.submit_inquiry') }}
                    </button>
                    <p class="text-xs text-plum-500 text-center">{{ __('shop.no_obligation') }}</p>
                </form>
            </div>
        </div>
    </div>
@endsection
