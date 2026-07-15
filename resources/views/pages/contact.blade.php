@extends('layouts.storefront')

@section('title', __('shop.nav_contact'))
@section('meta_description', __('shop.meta_contact'))

@php
    $contactTel = \App\Support\Contact::tel();
    $waLink = \App\Support\Contact::whatsappLink();
@endphp

@section('content')
    <div class="mb-10 max-w-2xl">
        <p class="text-xs uppercase tracking-[0.25em] text-rose-accent mb-3">{{ __('shop.wholesale') }}</p>
        <h1 class="font-display text-4xl sm:text-5xl text-ink leading-[1.1]">{{ __('shop.contact_title') }}</h1>
        <p class="mt-4 text-plum-600 leading-relaxed">{{ __('shop.contact_intro') }}</p>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-10 lg:grid-cols-2">
        {{-- Contact details + map --}}
        <div class="space-y-6">
            <div class="rounded-2xl border border-line bg-white p-6 space-y-4">
                @if ($settings->company_phone)
                    <a href="{{ $contactTel }}" class="flex items-start gap-3 group">
                        <span class="mt-0.5 inline-flex w-9 h-9 items-center justify-center rounded-full bg-plum/10 text-plum shrink-0">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                        </span>
                        <span>
                            <span class="block text-xs uppercase tracking-wider text-plum-500">{{ __('shop.call_us') }}</span>
                            <span class="block text-ink group-hover:text-plum transition" dir="ltr">{{ $settings->company_phone }}</span>
                        </span>
                    </a>
                @endif

                @if ($waLink)
                    <a href="{{ $waLink }}" target="_blank" rel="noopener" class="flex items-start gap-3 group">
                        <span class="mt-0.5 inline-flex w-9 h-9 items-center justify-center rounded-full bg-[#25D366]/15 text-[#128C7E] shrink-0">
                            <svg class="w-4.5 h-4.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/></svg>
                        </span>
                        <span>
                            <span class="block text-xs uppercase tracking-wider text-plum-500">{{ __('shop.chat_whatsapp') }}</span>
                            <span class="block text-ink group-hover:text-plum transition">{{ __('shop.wa_cta') }}</span>
                        </span>
                    </a>
                @endif

                @if ($settings->company_email)
                    <a href="mailto:{{ $settings->company_email }}" class="flex items-start gap-3 group">
                        <span class="mt-0.5 inline-flex w-9 h-9 items-center justify-center rounded-full bg-plum/10 text-plum shrink-0">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                        </span>
                        <span>
                            <span class="block text-xs uppercase tracking-wider text-plum-500">{{ __('shop.email_us') }}</span>
                            <span class="block text-ink group-hover:text-plum transition">{{ $settings->company_email }}</span>
                        </span>
                    </a>
                @endif

                @if ($settings->company_address)
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex w-9 h-9 items-center justify-center rounded-full bg-plum/10 text-plum shrink-0">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                        </span>
                        <span>
                            <span class="block text-xs uppercase tracking-wider text-plum-500">{{ __('shop.visit_us') }}</span>
                            <span class="block text-ink leading-relaxed">{{ $settings->company_address }}</span>
                            @if ($settings->contact_hours)
                                <span class="block mt-1 text-sm text-plum-500">{{ $settings->contact_hours }}</span>
                            @endif
                        </span>
                    </div>
                @endif

                <div class="pt-2 border-t border-line text-sm text-plum-600 space-y-1">
                    <p class="text-ink font-medium">{{ $settings->legal_entity_name ?: $settings->company_name }}</p>
                    @if ($settings->trade_licence_number)
                        <p>{{ __('shop.trade_licence') }}: <span dir="ltr">{{ $settings->trade_licence_number }}</span></p>
                    @endif
                    @if ($settings->trn)
                        <p>{{ __('shop.trn') }}: <span dir="ltr">{{ $settings->trn }}</span></p>
                    @endif
                </div>
            </div>

            @if ($settings->google_maps_embed)
                <div class="rounded-2xl overflow-hidden border border-line aspect-[4/3]">
                    <iframe src="{{ $settings->google_maps_embed }}" class="w-full h-full" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="{{ __('shop.visit_us') }}"></iframe>
                </div>
            @endif
        </div>

        {{-- Inquiry form --}}
        <div class="rounded-2xl border border-line bg-white p-6 sm:p-8">
            <h2 class="font-display text-2xl text-ink mb-1">{{ __('shop.contact_form_title') }}</h2>
            <p class="text-sm text-plum-600 mb-6">{{ __('shop.contact_form_intro') }}</p>

            @if ($errors->any())
                <div class="mb-5 rounded-xl bg-blush border border-rose-accent/30 px-4 py-3 text-rose-deep text-sm">
                    {{ __('shop.form_errors') }}
                </div>
            @endif

            <form action="{{ route('contact.send') }}" method="POST" class="space-y-4">
                @csrf
                {{-- Honeypot --}}
                <input type="text" name="company_website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                <div>
                    <label class="block text-sm font-medium text-ink mb-1" for="c_name">{{ __('shop.name') }}</label>
                    <input id="c_name" name="customer_name" value="{{ old('customer_name') }}" required
                           class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-ink mb-1" for="c_mobile">{{ __('shop.mobile') }}</label>
                        <input id="c_mobile" name="customer_mobile" value="{{ old('customer_mobile') }}" required dir="ltr"
                               class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink mb-1" for="c_email">{{ __('shop.email') }}</label>
                        <input id="c_email" name="customer_email" type="email" value="{{ old('customer_email') }}"
                               class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-ink mb-1" for="c_company">{{ __('shop.company') }}</label>
                    <input id="c_company" name="customer_company" value="{{ old('customer_company') }}"
                           class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-ink mb-1" for="c_message">{{ __('shop.message_required') }}</label>
                    <textarea id="c_message" name="customer_message" rows="4" required
                              class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">{{ old('customer_message') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-ink mb-1" for="c_referral">{{ __('shop.referral_code') }}</label>
                    <input id="c_referral" name="referral_code" value="{{ old('referral_code', request('code')) }}"
                           class="w-full rounded-lg border border-line bg-ivory px-3 py-2.5 focus:border-plum focus:ring-1 focus:ring-plum outline-none">
                    <p class="mt-1 text-xs text-plum-500">{{ __('shop.referral_code_hint') }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-plum-700">
                    <input type="checkbox" name="is_whatsapp" value="1" @checked(old('is_whatsapp')) class="rounded border-line text-plum focus:ring-plum">
                    {{ __('shop.is_whatsapp') }}
                </label>

                <button type="submit" class="w-full rounded-full bg-plum text-white font-medium py-3 hover:bg-plum-800 transition">
                    {{ __('shop.send_message') }}
                </button>
                <p class="text-center text-xs text-plum-500">{{ __('shop.no_obligation') }}</p>
            </form>
        </div>
    </div>
@endsection
