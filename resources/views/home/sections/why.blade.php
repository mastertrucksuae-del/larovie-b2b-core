<section class="border-y border-line bg-blush/50">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14 sm:py-16">
        <h2 class="font-display text-3xl sm:text-4xl text-ink">{{ $t('why_partner') }}</h2>

        <div class="mt-8 grid gap-4 sm:gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach (range(1, 6) as $i)
                <div class="rounded-2xl bg-white ring-1 ring-line p-6">
                    <h3 class="text-sm font-semibold text-ink">{{ $t("why_{$i}_title") }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-plum-600">
                        {{ $t("why_{$i}_body") }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</section>
