<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Filament\Resources\Inquiries\InquiryResource;
use App\Models\Inquiry;
use App\Services\Export\LineItemExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInquiries extends ListRecords
{
    protected static string $resource = InquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportLineItems')
                ->label('Export line items (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->tooltip('All inquiry line items by brand & SKU — the supplier data pack.')
                ->action(fn (LineItemExport $export) => $export->csvResponse()),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All')->badge(Inquiry::count()),
        ];

        foreach (Inquiry::STATUSES as $value => $label) {
            $tabs[$value] = Tab::make($label)
                ->badge(Inquiry::where('status', $value)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $value));
        }

        return $tabs;
    }
}
