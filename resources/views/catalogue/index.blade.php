@extends('layouts.storefront')

@section('title', __('shop.catalogue'))
@section('meta_description', __('shop.meta_catalogue'))

@section('content')
    <div class="mb-10 max-w-2xl">
        <p class="text-xs uppercase tracking-[0.25em] text-rose-accent mb-3">{{ __('shop.wholesale') }}</p>
        <h1 class="font-display text-4xl sm:text-5xl text-ink leading-[1.1]">{{ __('shop.wholesale_catalogue') }}</h1>
        <p class="mt-4 text-plum-600 leading-relaxed">{{ __('shop.catalogue_intro') }}</p>
    </div>

    <div class="mb-10">
        <x-authenticity-strip />
    </div>

    <livewire:catalogue />
@endsection
