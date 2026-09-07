<x-filament-panels::page>
    {{--
        The settings page is long — the homepage copy alone is ~120 fields across
        collapsible groups — so a save button at the very bottom means scrolling
        the whole way down to keep a one-word edit. This pins it to the foot of
        the viewport instead.

        Styled here rather than with utility classes: the admin panel is served
        by Filament's own compiled stylesheet, which only contains the utilities
        Filament itself uses, so classes added in a custom view can silently do
        nothing. Dark mode hangs off the `.dark` class Filament puts on <html>.
    --}}
    <style>
        .larovie-save-bar {
            position: sticky;
            bottom: 0;
            z-index: 20;
            margin-top: 1.5rem;
            padding: 0.875rem 0;
            background-color: rgb(249 250 251 / 0.97);
            border-top: 1px solid rgb(17 24 39 / 0.08);
            backdrop-filter: blur(8px);
        }

        .dark .larovie-save-bar {
            background-color: rgb(17 24 39 / 0.97);
            border-top-color: rgb(255 255 255 / 0.1);
        }

        @supports not (backdrop-filter: blur(8px)) {
            /* Without blur the bar has to be fully opaque or text reads through it. */
            .larovie-save-bar { background-color: rgb(249 250 251); }
            .dark .larovie-save-bar { background-color: rgb(17 24 39); }
        }
    </style>

    <form wire:submit="save">
        {{ $this->form }}

        <div class="larovie-save-bar">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                Save settings
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
