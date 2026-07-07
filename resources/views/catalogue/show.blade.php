@extends('layouts.storefront')

@section('title', $product->title)

@section('content')
    <nav class="mb-8 text-sm text-plum-500 flex items-center gap-2">
        <a href="{{ route('catalogue.index') }}" class="hover:text-plum transition">{{ __('shop.catalogue') }}</a>
        <span class="text-line">/</span>
        @if ($product->effective_brand)
            <span>{{ $product->effective_brand }}</span>
            <span class="text-line">/</span>
        @endif
        <span class="text-ink">{{ $product->title }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16">
        {{-- Gallery --}}
        <div>
            <div class="aspect-square rounded-3xl bg-sand overflow-hidden ring-1 ring-line">
                @if ($product->featured_image_url)
                    <img src="{{ $product->featured_image_url }}" alt="{{ $product->title }}" class="h-full w-full object-cover">
                @endif
            </div>
            @php($thumbs = $product->variants->pluck('image_url')->filter()->unique()->take(5))
            @if ($thumbs->count() > 1)
                <div class="mt-4 grid grid-cols-5 gap-3">
                    @foreach ($thumbs as $thumb)
                        <div class="aspect-square rounded-xl bg-sand overflow-hidden ring-1 ring-line">
                            <img src="{{ $thumb }}" alt="" class="h-full w-full object-cover">
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Details --}}
        <div class="lg:pt-4">
            @if ($product->product_type)
                <span class="text-xs uppercase tracking-[0.2em] text-rose-accent">{{ $product->product_type }}</span>
            @endif
            <h1 class="mt-2 font-display text-3xl sm:text-4xl text-ink leading-tight">{{ $product->title }}</h1>
            @if ($product->effective_brand)
                <p class="mt-2 text-plum-500">{{ __('shop.by') }} {{ $product->effective_brand }}</p>
            @endif

            <div class="mt-8 space-y-6">
                <livewire:product-inquiry :product="$product" />
            </div>

            @if ($product->description)
                <div class="mt-12 border-t border-line pt-8">
                    <h2 class="font-display text-xl text-ink mb-4">{{ __('shop.description') }}</h2>
                    <div class="prose prose-sm max-w-none text-plum-700 prose-headings:text-ink prose-headings:font-display">
                        {!! $product->description !!}
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
