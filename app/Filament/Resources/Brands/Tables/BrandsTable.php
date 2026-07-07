<?php

namespace App\Filament\Resources\Brands\Tables;

use App\Models\Brand;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_url')
                    ->label('Logo')
                    ->height(40)
                    ->extraImgAttributes(['style' => 'object-fit:contain;'])
                    ->placeholder('No logo'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('products')
                    ->label('Products')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (Brand $record) => Product::query()
                        ->whereRaw('coalesce(nullif(brand, ""), vendor) = ?', [$record->name])
                        ->count()),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
