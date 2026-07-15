@php
    /** @var \App\Models\Inquiry $record */
    $record = $this->getRecord();
    $statuses = \App\Models\Inquiry::STATUSES;
    $keys = array_keys($statuses);
    $current = $record->status;
    $currentIndex = array_search($current, $keys, true);
    $currentIndex = $currentIndex === false ? 0 : $currentIndex;

    // Each stage owns a colour.
    $colors = [
        'new_inquiry'    => '#f59e0b', // amber
        'responding'     => '#0ea5e9', // sky
        'prices_filled'  => '#8b5cf6', // violet
        'quote_sent'     => '#10b981', // emerald
        'order_confirmed' => '#059669', // deep emerald
    ];
    $count = count($statuses);
@endphp

<div class="w-full">
    <div style="display:flex; width:100%; border-radius:10px; overflow:hidden;">
        @foreach ($statuses as $key => $label)
            @php
                $index = array_search($key, $keys, true);
                $color = $colors[$key] ?? '#6b7280';
                $isCurrent = $index === $currentIndex;
                $isDone = $index < $currentIndex;
                $isFirst = $index === 0;
                $isLast = $index === $count - 1;

                if ($isCurrent) {
                    $bg = $color; $fg = '#ffffff'; $weight = 700;
                } elseif ($isDone) {
                    $bg = $color.'26'; $fg = $color; $weight = 600; // ~15% tint
                } else {
                    $bg = 'rgba(148,163,184,0.14)'; $fg = '#64748b'; $weight = 500;
                }

                // Arrow / chevron shape (LTR admin).
                if ($isFirst) {
                    $clip = 'polygon(0 0, calc(100% - 14px) 0, 100% 50%, calc(100% - 14px) 100%, 0 100%)';
                } elseif ($isLast) {
                    $clip = 'polygon(0 0, 100% 0, 100% 100%, 0 100%, 14px 50%)';
                } else {
                    $clip = 'polygon(0 0, calc(100% - 14px) 0, 100% 50%, calc(100% - 14px) 100%, 0 100%, 14px 50%)';
                }
            @endphp
            <button type="button"
                    wire:click="setStatus('{{ $key }}')"
                    wire:loading.attr="disabled"
                    title="{{ $label }}"
                    style="flex:1 1 0; min-width:0; padding:12px 10px; text-align:center; cursor:pointer;
                           background:{{ $bg }}; color:{{ $fg }}; font-weight:{{ $weight }}; font-size:13px;
                           border:0; clip-path:{{ $clip }};
                           margin-inline-start:{{ $isFirst ? '0' : '-12px' }};
                           transition:filter .15s ease;"
                    onmouseover="this.style.filter='brightness(0.95)'"
                    onmouseout="this.style.filter='none'">
                <span style="display:inline-flex; align-items:center; gap:6px; justify-content:center; white-space:nowrap;">
                    @if ($isDone)
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;flex:none;"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 10a1 1 0 1 1 1.4-1.4l3.1 3.1 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    @endif
                    {{ $label }}
                </span>
            </button>
        @endforeach
    </div>
    <p style="margin-top:8px; font-size:12px; color:#6b7280;">
        Click a stage to move this inquiry through the pipeline.
    </p>
</div>
