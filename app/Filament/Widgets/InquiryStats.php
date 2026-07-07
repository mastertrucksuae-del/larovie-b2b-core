<?php

namespace App\Filament\Widgets;

use App\Models\Inquiry;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InquiryStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            Stat::make('New inquiries', Inquiry::where('status', Inquiry::STATUS_NEW)->count())
                ->description('Awaiting a response')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color('warning'),

            Stat::make('In progress', Inquiry::whereIn('status', [
                Inquiry::STATUS_RESPONDING,
                Inquiry::STATUS_PRICES_FILLED,
            ])->count())
                ->description('Responding / priced')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('info'),

            Stat::make('Quotes sent', Inquiry::where('status', Inquiry::STATUS_QUOTE_SENT)->count())
                ->description('Delivered to customers')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('success'),

            Stat::make('Visible products', Product::publiclyVisible()->count())
                ->description('Live in the catalogue')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('gray'),
        ];
    }
}
