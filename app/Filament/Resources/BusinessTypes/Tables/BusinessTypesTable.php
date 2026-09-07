<?php

namespace App\Filament\Resources\BusinessTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use App\Support\BusinessTypeIcons;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_en')->label('English')->searchable()->weight('bold'),
                TextColumn::make('name_ar')->label('Arabic')->placeholder('Uses the English name'),
                TextColumn::make('icon')
                    ->label('Icon')
                    ->formatStateUsing(fn (?string $state) => BusinessTypeIcons::options()[$state] ?? 'Storefront')
                    ->color('gray'),
                IconColumn::make('is_visible')->label('Shown')->boolean(),
            ])
            // Drag to set the order the chips appear in on the homepage.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No business types yet')
            ->emptyStateDescription('Add the kinds of business you sell to. They appear as chips on the homepage.');
    }
}
