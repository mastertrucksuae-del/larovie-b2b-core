<section class="home-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14 sm:py-16">
    <h2 class="font-display text-3xl sm:text-4xl text-ink">{{ $t('how_it_works') }}</h2>

    <ol class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (range(1, 4) as $i)
            <li class="border-t-2 border-rose-accent/40 pt-5">
                <span class="font-display block text-3xl text-rose-accent leading-none">{{ $i }}</span>
                <h3 class="mt-4 text-sm font-semibold text-ink">{{ $t("step_{$i}_title") }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-plum-600">{{ $t("step_{$i}_body") }}</p>
            </li>
        @endforeach
    </ol>
</section>
