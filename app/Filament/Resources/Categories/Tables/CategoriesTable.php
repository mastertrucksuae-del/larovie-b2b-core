<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('display_image')->label('')->square(),
                TextColumn::make('title')
                    ->label('Category')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Category $record) => $record->title_ar ?: 'No Arabic name yet'),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_visible')
                    ->label('Shown')
                    ->boolean(),
                TextColumn::make('synced_at')
                    ->label('Last imported')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Never')
                    ->toggleable(),
            ])
            // Drag to set the order the tiles appear in on the homepage.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_visible')->label('Shown on storefront'),
                // Archived rows are hidden by default: they are collections that
                // no longer exist in Shopify and are kept only so their settings
                // survive if the collection comes back.
                TernaryFilter::make('is_archived')->label('Archived')->default(false),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading('No categories yet')
            ->emptyStateDescription('Import your Shopify collections to get started.');
    }
}
