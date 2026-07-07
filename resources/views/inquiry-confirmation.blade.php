@extends('layouts.storefront')

@section('title', __('shop.inquiry_received'))

@section('content')
    <div class="max-w-xl mx-auto text-center py-16">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blush">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-rose-deep">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </div>

        <h1 class="mt-6 font-display text-4xl text-ink">{{ __('shop.thank_you') }}</h1>
        <p class="mt-3 text-plum-600">{{ __('shop.inquiry_received_message') }}</p>

        <div class="mt-8 inline-flex flex-col items-center rounded-2xl bg-white ring-1 ring-line px-10 py-6">
            <span class="text-xs uppercase tracking-[0.2em] text-plum-500">{{ __('shop.your_reference') }}</span>
            <span class="mt-2 font-display text-3xl tracking-wide text-plum">{{ $inquiry->reference }}</span>
        </div>

        <p class="mt-8 text-sm text-plum-500">{{ __('shop.contact_shortly') }}</p>

        <a href="{{ route('catalogue.index') }}"
           class="mt-6 inline-flex rounded-full bg-plum px-8 py-3 font-medium text-white hover:bg-plum-800 transition">
            {{ __('shop.continue_browsing') }}
        </a>
    </div>
@endsection
