<?php

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Resources\Brands\BrandResource;
use App\Models\Brand;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBrands extends ListRecords
{
    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncBrands')
                ->label('Import brands from products')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->tooltip('Adds a row for every brand used by your products so you can upload its logo.')
                ->action(function () {
                    $added = Brand::syncFromProducts();

                    Notification::make()
                        ->title($added > 0 ? "Added {$added} brand(s)" : 'All brands are already listed')
                        ->body($added > 0 ? 'Upload a logo for each to make it recognisable in the catalogue.' : null)
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
