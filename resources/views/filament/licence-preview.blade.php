@php
    use App\Http\Controllers\BusinessAccountController;

    $inline = route('business-account.licence', [$record, 'inline' => 1]);
    $download = route('business-account.licence', $record);
    $isImage = BusinessAccountController::licenceIsImage($record);
@endphp

<div class="space-y-3">
    @if ($isImage)
        {{-- Scrolls rather than shrinking to nothing: a licence is only useful
             if the registration number is actually readable. --}}
        <div class="max-h-[70vh] overflow-auto rounded-lg border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-gray-900">
            <img src="{{ $inline }}"
                 alt="{{ __('shop.trade_licence') }} — {{ $record->company_name }}"
                 class="w-full h-auto">
        </div>
    @else
        {{-- PDFs go in a frame so the browser's own viewer handles paging and
             zoom; no JS library needed. --}}
        <iframe src="{{ $inline }}"
                title="{{ __('shop.trade_licence') }} — {{ $record->company_name }}"
                class="w-full rounded-lg border border-gray-200 dark:border-white/10"
                style="height:70vh"></iframe>
    @endif

    <div class="flex items-center justify-between gap-3 text-sm">
        <span class="text-gray-500 dark:text-gray-400">
            @if ($record->trade_licence_number)
                {{ __('shop.trade_licence_number') }}:
                <span class="font-medium text-gray-900 dark:text-white" dir="ltr">{{ $record->trade_licence_number }}</span>
            @else
                {{ __('shop.trade_licence_number') }}: —
            @endif
        </span>

        {{-- Kept alongside the preview: a browser that cannot render the file
             inline still needs a way to open it. --}}
        <a href="{{ $download }}"
           class="font-medium text-primary-600 hover:underline dark:text-primary-400">
            Download original
        </a>
    </div>
</div>
