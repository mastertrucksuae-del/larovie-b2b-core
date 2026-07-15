<?php

namespace App\Filament\Resources\BusinessAccounts\Pages;

use App\Filament\Resources\BusinessAccounts\BusinessAccountResource;
use App\Models\BusinessAccount;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBusinessAccounts extends ListRecords
{
    protected static string $resource = BusinessAccountResource::class;

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All')->badge(BusinessAccount::count()),
        ];

        foreach (BusinessAccount::STATUSES as $key => $label) {
            $tabs[$key] = Tab::make($label)
                ->badge(BusinessAccount::where('status', $key)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $key));
        }

        return $tabs;
    }
}
