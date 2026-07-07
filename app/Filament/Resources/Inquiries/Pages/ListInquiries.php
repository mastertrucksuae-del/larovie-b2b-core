<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Filament\Resources\Inquiries\InquiryResource;
use App\Models\Inquiry;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInquiries extends ListRecords
{
    protected static string $resource = InquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
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
