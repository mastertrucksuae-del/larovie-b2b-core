<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Wholesale settings')
                    ->description('These fields control the wholesale catalogue and are never overwritten by a Shopify sync.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_visible')
                            ->label('Visible in catalogue')
                            ->helperText('Show this product to wholesale buyers.'),
                        Toggle::make('is_bundle')
                            ->label('Bundle / kit')
                            ->helperText('Bundles are hidden from the solo-product catalogue.'),
                        TextInput::make('moq')
                            ->label('Default MOQ')
                            ->helperText('Minimum order quantity. Variants can override this.')
                            ->numeric()
                            ->minValue(1)
                            ->columnSpanFull(),
                    ]),

                Section::make('Product image')
                    ->description('Upload to override the Shopify image (kept across re-syncs). Leave empty to use the Shopify image.')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Image override')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('product-images')
                            ->maxSize(4096),
                        Placeholder::make('shopify_image')
                            ->label('Current Shopify image')
                            ->content(fn ($record) => $record?->featured_image_url
                                ? new HtmlString('<img src="'.e($record->featured_image_url).'" style="height:120px;border-radius:8px;object-fit:cover;">')
                                : '—'),
                    ]),

                Section::make('Shopify data (read-only)')
                    ->description('Owned by Shopify. Update in Shopify and re-sync.')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('title')->disabled()->dehydrated(false)->columnSpanFull(),
                        TextInput::make('vendor')->disabled()->dehydrated(false),
                        TextInput::make('product_type')->disabled()->dehydrated(false),
                        TextInput::make('shopify_status')->label('Shopify status')->disabled()->dehydrated(false),
                        TextInput::make('handle')->disabled()->dehydrated(false),
                        Textarea::make('description')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->rows(4),
                    ]),
            ]);
    }
}
