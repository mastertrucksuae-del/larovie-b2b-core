<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use App\Support\Img;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('display_image')
                    ->label('')
                    ->state(fn (Category $record) => Img::absolute($record->display_image))
                    ->height(44)
                    ->width(44)
                    ->extraImgAttributes(['class' => 'object-cover rounded-lg'])
                    ->defaultImageUrl(asset('images/larovie-logo-dark.webp')),
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
                // Toggled straight from the list: switching a category on is the
                // single most common action here, and opening an edit page to
                // flip one boolean is friction for no gain.
                ToggleColumn::make('is_visible')->label('Shown'),
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
