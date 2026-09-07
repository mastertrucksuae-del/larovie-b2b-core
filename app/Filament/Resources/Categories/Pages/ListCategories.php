<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Services\Shopify\ProductSyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importCollections')
                ->label('Import from Shopify')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Names, handles and images are refreshed from your Shopify collections. Your visibility, ordering, Arabic names and image overrides are left untouched.')
                ->action(function (ProductSyncService $sync) {
                    $before = Category::count();

                    try {
                        $sync->syncCollections();
                    } catch (Throwable $e) {
                        // Shopify credentials expire and the API rate-limits;
                        // neither should look like a blank success.
                        Notification::make()
                            ->title('Import failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    $added = Category::count() - $before;

                    Notification::make()
                        ->title($added > 0 ? "Imported {$added} new " . str('category')->plural($added) : 'Categories are already up to date')
                        ->body($added > 0 ? 'New categories arrive hidden — switch on the ones you want on the homepage.' : null)
                        ->success()
                        ->send();
                }),
        ];
    }
}
