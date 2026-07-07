<?php

namespace App\Filament\Resources\Brands\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Brand')
                    ->description('The name must match the brand exactly as it appears on your products (from Shopify). Upload a logo to make the brand recognisable in the catalogue.')
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Exact brand name as used on products.'),
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('brands')
                            ->maxSize(2048)
                            ->helperText('A PNG with a transparent background works best. Max 2 MB.'),
                    ]),
            ]);
    }
}
