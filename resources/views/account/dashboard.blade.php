@extends('layouts.storefront')

@section('title', __('shop.account_title'))

@php
    $statusStyles = [
        'pending'  => ['bg' => 'bg-amber-50 border-amber-200 text-amber-800', 'dot' => 'bg-amber-400'],
        'approved' => ['bg' => 'bg-emerald-50 border-emerald-200 text-emerald-800', 'dot' => 'bg-emerald-500'],
        'rejected' => ['bg' => 'bg-rose-50 border-rose-200 text-rose-700', 'dot' => 'bg-rose-500'],
    ];
    $style = $statusStyles[$account->status] ?? $statusStyles['pending'];
@endphp

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="flex items-start justify-between gap-4 mb-8">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl text-ink leading-tight">{{ __('shop.account_title') }}</h1>
                <p class="mt-2 text-plum-600">{{ __('shop.account_welcome', ['name' => $account->contact_person]) }}</p>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="rounded-full border border-line bg-white px-4 py-2 text-sm text-plum-700 hover:text-plum hover:border-plum/40 transition">
                    {{ __('shop.logout') }}
                </button>
            </form>
        </div>

        {{-- Approval status --}}
        <div class="rounded-2xl border {{ $style['bg'] }} px-5 py-4 mb-6 flex items-start gap-3">
            <span class="mt-1.5 w-2.5 h-2.5 rounded-full {{ $style['dot'] }} shrink-0"></span>
            <div>
                <p class="font-medium">{{ __('shop.account_status') }}: {{ __('shop.status_'.$account->status) }}</p>
                <p class="text-sm mt-1 opacity-90">{{ __('shop.status_'.$account->status.'_note') }}</p>
                @if ($account->status === 'rejected' && $account->review_notes)
                    <p class="text-sm mt-2">{{ $account->review_notes }}</p>
                @endif
            </div>
        </div>

        {{-- Company details --}}
        <div class="rounded-2xl border border-line bg-white p-6">
            <h2 class="font-display text-xl text-ink mb-4">{{ __('shop.company_details') }}</h2>
            <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-plum-500">{{ __('shop.company_name') }}</dt>
                    <dd class="text-ink mt-0.5">{{ $account->company_name }}</dd>
                </div>
                <div>
                    <dt class="text-plum-500">{{ __('shop.contact_person') }}</dt>
                    <dd class="text-ink mt-0.5">{{ $account->contact_person }}</dd>
                </div>
                <div>
                    <dt class="text-plum-500">{{ __('shop.email_label') }}</dt>
                    <dd class="text-ink mt-0.5" dir="ltr">{{ $account->email }}</dd>
                </div>
                <div>
                    <dt class="text-plum-500">{{ __('shop.mobile') }}</dt>
                    <dd class="text-ink mt-0.5" dir="ltr">{{ $account->phone }}</dd>
                </div>
                @if ($account->trade_licence_number)
                    <div>
                        <dt class="text-plum-500">{{ __('shop.trade_licence_number') }}</dt>
                        <dd class="text-ink mt-0.5" dir="ltr">{{ $account->trade_licence_number }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="mt-6 text-center">
            <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-2 rounded-full bg-plum text-white font-medium px-6 py-3 hover:bg-plum-800 transition">
                {{ __('shop.browse_catalogue') }}
            </a>
        </div>
    </div>
@endsection
