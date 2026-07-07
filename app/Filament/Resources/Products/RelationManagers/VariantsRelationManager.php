<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductVariant;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variants & pricing';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                ImageColumn::make('image_url')
                    ->label('')
                    ->height(40)
                    ->square(),
                TextColumn::make('title')
                    ->label('Variant')
                    ->description(fn (ProductVariant $r) => $r->sku ? 'SKU: '.$r->sku : null)
                    ->searchable(),
                TextColumn::make('inventory_quantity')
                    ->label('Stock')
                    ->alignCenter()
                    ->toggleable(),
                TextInputColumn::make('wholesale_price')
                    ->label('Wholesale price')
                    ->type('number')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->placeholder('Price on request')
                    ->width('9rem'),
                TextInputColumn::make('moq')
                    ->label('MOQ')
                    ->type('number')
                    ->rules(['nullable', 'integer', 'min:1'])
                    ->width('6rem'),
                ToggleColumn::make('is_visible')
                    ->label('Visible'),
            ])
            // Shopify owns variants — no create/associate/delete from the panel.
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
