<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Jobs\SyncShopifyProducts;
use App\Models\Setting;
use App\Services\Shopify\ShopifyClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        $lastSynced = Setting::current()->last_synced_at;

        return [
            Action::make('sync')
                ->label('Sync from Shopify')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->badge($lastSynced ? 'Last: '.$lastSynced->diffForHumans() : 'Never synced')
                ->disabled(fn () => Cache::get(SyncShopifyProducts::RUNNING_KEY, false))
                ->requiresConfirmation()
                ->modalHeading('Sync products from Shopify')
                ->modalDescription('Imports the latest products and variants. Your visibility, MOQ and wholesale prices are preserved.')
                ->action(function () {
                    if (! app(ShopifyClient::class)->isConfigured()) {
                        Notification::make()
                            ->title('Shopify is not configured')
                            ->body('Set SHOPIFY_STORE_DOMAIN and SHOPIFY_ADMIN_API_TOKEN in your .env, then try again.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Cache::put(SyncShopifyProducts::RUNNING_KEY, true, now()->addMinutes(15));
                    SyncShopifyProducts::dispatch();

                    Notification::make()
                        ->title('Sync started')
                        ->body('Products are importing in the background. Refresh in a moment to see updates.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
