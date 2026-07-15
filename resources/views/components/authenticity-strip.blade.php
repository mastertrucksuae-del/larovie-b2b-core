{{-- Authenticity guarantee strip (P0 #3) — shown on the catalogue. --}}
<a href="{{ route('authenticity') }}"
   class="group block rounded-2xl border border-line bg-white/70 px-4 sm:px-6 py-4 hover:border-plum/30 transition">
    <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
        <div class="flex items-center gap-2 shrink-0">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-plum/10 text-plum">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
            </span>
            <span class="font-display text-lg text-ink">{{ __('shop.authentic_title') }}</span>
        </div>
        <ul class="grid grid-cols-2 sm:flex sm:flex-1 sm:flex-wrap items-center gap-x-6 gap-y-2 text-sm text-plum-700">
            <li class="inline-flex items-center gap-1.5"><span class="text-rose-accent">&#10003;</span> {{ __('shop.authentic_point_1') }}</li>
            <li class="inline-flex items-center gap-1.5"><span class="text-rose-accent">&#10003;</span> {{ __('shop.authentic_point_2') }}</li>
            <li class="inline-flex items-center gap-1.5"><span class="text-rose-accent">&#10003;</span> {{ __('shop.authentic_point_3') }}</li>
            <li class="inline-flex items-center gap-1.5"><span class="text-rose-accent">&#10003;</span> {{ __('shop.authentic_point_4') }}</li>
        </ul>
        <span class="hidden sm:inline text-sm font-medium text-plum group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5 transition">{{ __('shop.learn_more') }} &rarr;</span>
    </div>
</a>
