@extends('layouts.storefront')

@section('title', __('shop.register_title'))
@section('meta_description', __('shop.register_intro'))

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="text-center mb-8">
            <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-plum/10 text-plum mb-4">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"/></svg>
            </span>
            <h1 class="font-display text-3xl sm:text-4xl text-ink leading-tight">{{ __('shop.register_title') }}</h1>
            <p class="mt-3 text-plum-600">{{ __('shop.register_intro') }}</p>
        </div>

        @if ($errors->any())
            <div class="mb-5 rounded-xl bg-blush border border-rose-accent/30 px-4 py-3 text-rose-deep text-sm">
                <ul class="list-disc ps-5 space-y-0.5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('register.store') }}" method="POST" enctype="multipart/form-data"
              class="rounded-2xl border border-line bg-white p-6 sm:p-8 space-y-5">
            @csrf
            <input type="text" name="company_website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-ink mb-1.5" for="r_company">{{ __('shop.company_name') }}</label>
                    <input id="r_company" name="company_name" value="{{ old('company_name') }}" required
                           class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink mb-1.5" for="r_person">{{ __('shop.contact_person') }}</label>
                    <input id="r_person" name="contact_person" value="{{ old('contact_person') }}" required
                           class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink mb-1.5" for="r_email">{{ __('shop.email_label') }}</label>
                    <input id="r_email" name="email" type="email" value="{{ old('email') }}" required dir="ltr"
                           class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink mb-1.5" for="r_phone">{{ __('shop.mobile') }}</label>
                    <input id="r_phone" name="phone" value="{{ old('phone') }}" required dir="ltr"
                           class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink mb-1.5" for="r_pass">{{ __('shop.password') }}</label>
                    <input id="r_pass" name="password" type="password" required
                           class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink mb-1.5" for="r_pass2">{{ __('shop.password_confirm') }}</label>
                    <input id="r_pass2" name="password_confirmation" type="password" required
                           class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
                </div>
            </div>

            <div class="border-t border-line pt-5">
                <p class="text-sm font-medium text-ink mb-3 flex items-center gap-2">
                    <svg class="w-4.5 h-4.5 text-plum" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                    {{ __('shop.kyc_documents') }}
                </p>
                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-ink mb-1.5" for="r_tln">{{ __('shop.trade_licence_number') }}</label>
                        <input id="r_tln" name="trade_licence_number" value="{{ old('trade_licence_number') }}" dir="ltr"
                               class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink mb-1.5" for="r_file">{{ __('shop.trade_licence_upload') }}</label>
                        <input id="r_file" name="trade_licence" type="file" accept=".pdf,.jpg,.jpeg,.png"
                               class="w-full text-sm text-plum-700 file:mr-3 file:rounded-full file:border-0 file:bg-plum/10 file:px-4 file:py-2 file:text-plum file:font-medium">
                    </div>
                </div>
                <p class="mt-2 text-xs text-plum-500">{{ __('shop.trade_licence_hint') }}</p>
            </div>

            <button type="submit" class="w-full rounded-full bg-plum text-white font-medium py-3 hover:bg-plum-800 transition">
                {{ __('shop.register_submit') }}
            </button>
            <p class="text-center text-sm text-plum-600">
                {{ __('shop.have_account') }}
                <a href="{{ route('login') }}" class="text-plum font-medium hover:underline">{{ __('shop.login_link') }}</a>
            </p>
        </form>
    </div>
@endsection
