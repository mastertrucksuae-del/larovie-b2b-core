@extends('layouts.storefront')

@section('title', __('shop.login_title'))
@section('meta_description', __('shop.login_title'))

@section('content')
    <div class="max-w-md mx-auto">
        <div class="text-center mb-8">
            <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-plum/10 text-plum mb-4">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
            </span>
            <h1 class="font-display text-3xl sm:text-4xl text-ink leading-tight">{{ __('shop.login_title') }}</h1>
            <p class="mt-3 text-plum-600">{{ __('shop.login_intro') }}</p>
        </div>

        @if (session('status'))
            <div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-emerald-800 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-xl bg-blush border border-rose-accent/30 px-4 py-3 text-rose-deep text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('login.attempt') }}" method="POST"
              class="rounded-2xl border border-line bg-white p-6 sm:p-8 space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-ink mb-1.5" for="l_email">{{ __('shop.email_label') }}</label>
                <input id="l_email" name="email" type="email" value="{{ old('email') }}" required dir="ltr"
                       class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-ink mb-1.5" for="l_pass">{{ __('shop.password') }}</label>
                <input id="l_pass" name="password" type="password" required
                       class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
            </div>
            <label class="flex items-center gap-2 text-sm text-plum-700">
                <input type="checkbox" name="remember" value="1" class="rounded border-line text-plum focus:ring-plum">
                {{ __('shop.remember_me') }}
            </label>
            <button type="submit" class="w-full rounded-full bg-plum text-white font-medium py-3 hover:bg-plum-800 transition">
                {{ __('shop.login_submit') }}
            </button>
            <p class="text-center text-sm text-plum-600">
                {{ __('shop.no_account') }}
                <a href="{{ route('register') }}" class="text-plum font-medium hover:underline">{{ __('shop.register_link') }}</a>
            </p>
        </form>
    </div>
@endsection
