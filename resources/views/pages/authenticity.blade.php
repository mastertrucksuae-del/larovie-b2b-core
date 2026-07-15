@extends('layouts.storefront')

@section('title', __('shop.authentic_title'))
@section('meta_description', __('shop.meta_authenticity'))

@php
    $statement = app()->getLocale() === 'ar'
        ? $settings->authenticity_statement_ar
        : $settings->authenticity_statement_en;
@endphp

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-12">
            <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-plum/10 text-plum mb-5">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
            </span>
            <p class="text-xs uppercase tracking-[0.25em] text-rose-accent mb-3">{{ __('shop.authentic_title') }}</p>
            <h1 class="font-display text-4xl sm:text-5xl text-ink leading-[1.1]">{{ __('shop.authentic_badge') }}</h1>
        </div>

        <div class="prose-plum text-lg text-plum-700 leading-relaxed text-center mb-12">
            @if ($statement)
                {!! nl2br(e($statement)) !!}
            @else
                <p>{{ __('shop.authentic_statement_default') }}</p>
            @endif
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            @foreach (['authentic_point_1', 'authentic_point_2', 'authentic_point_3', 'authentic_point_4'] as $point)
                <div class="flex items-start gap-3 rounded-2xl border border-line bg-white p-5">
                    <span class="mt-0.5 inline-flex w-8 h-8 items-center justify-center rounded-full bg-plum/10 text-plum shrink-0">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    </span>
                    <p class="text-ink">{{ __('shop.'.$point) }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-12 text-center">
            <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full bg-plum text-white font-medium px-6 py-3 hover:bg-plum-800 transition">
                {{ __('shop.contact_title') }}
            </a>
        </div>
    </div>
@endsection
