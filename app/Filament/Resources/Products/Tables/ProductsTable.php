<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('featured_image_url')
                    ->label('')
                    ->height(44)
                    ->square(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn (Product $r) => $r->vendor),
                TextColumn::make('product_type')
                    ->label('Type')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->alignCenter(),
                ToggleColumn::make('is_visible')
                    ->label('Visible'),
                ToggleColumn::make('is_bundle')
                    ->label('Bundle')
                    ->tooltip('Bundles/kits are hidden from the solo-product catalogue'),
                TextInputColumn::make('moq')
                    ->label('MOQ')
                    ->type('number')
                    ->rules(['nullable', 'integer', 'min:1'])
                    ->width('6rem'),
                IconColumn::make('is_archived')
                    ->label('Archived')
                    ->boolean()
                    ->trueIcon('heroicon-o-archive-box')
                    ->falseIcon('heroicon-o-check-circle')
                    ->toggleable(),
                TextColumn::make('synced_at')
                    ->label('Last synced')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('product_type')
                    ->label('Type')
                    ->options(fn () => Product::query()
                        ->whereNotNull('product_type')
                        ->distinct()
                        ->orderBy('product_type')
                        ->pluck('product_type', 'product_type')
                        ->all()),
                TernaryFilter::make('is_visible')
                    ->label('Visibility'),
                TernaryFilter::make('is_bundle')
                    ->label('Bundles'),
                TernaryFilter::make('is_archived')
                    ->label('Archived')
                    ->default(false),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('show')
                        ->label('Make visible')
                        ->icon('heroicon-o-eye')
                        ->action(fn (Collection $records) => $records->each->update(['is_visible' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('hide')
                        ->label('Hide')
                        ->icon('heroicon-o-eye-slash')
                        ->action(fn (Collection $records) => $records->each->update(['is_visible' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('title');
    }
}
