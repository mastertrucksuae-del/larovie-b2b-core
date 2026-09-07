<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Support\Img;
use App\Filament\Support\WebpUpload;
use App\Models\ProductVariant;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variants & pricing';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                WebpUpload::make('image_path', 'variant-images')
                    ->label('Image override')
                    ->helperText('Overrides the Shopify variant image. Kept across re-syncs. Converted to WebP.')
                    ->imageEditor()
                    ->columnSpanFull(),
                TextInput::make('wholesale_price')
                    ->label('Wholesale price')
                    ->numeric()
                    ->minValue(0)
                    ->placeholder('Price on request'),
                TextInput::make('moq')
                    ->label('MOQ')
                    ->numeric()
                    ->minValue(1),
                Toggle::make('is_visible')
                    ->label('Visible in catalogue'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                ImageColumn::make('display_image')
                    ->label('')
                    ->state(fn ($record) => Img::absolute($record->display_image))
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
            // Shopify owns variants — no create/associate/delete, but the admin can
            // edit the image, price, MOQ and visibility.
            ->headerActions([])
            ->recordActions([
                EditAction::make()->label('Edit image / details'),
            ])
            ->toolbarActions([]);
    }
}
