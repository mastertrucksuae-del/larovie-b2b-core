<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\WebpUpload;
use App\Models\Category;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('From Shopify')
                ->description('Owned by the collection and refreshed on every import.')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('title')->label('Name (English)')->disabled(),
                    TextInput::make('handle')->label('Handle')->disabled(),
                    Placeholder::make('collection_image')
                        ->label('Collection image')
                        ->content(fn (?Category $record) => $record?->image_url ?: 'None')
                        ->columnSpanFull(),
                ]),

            Section::make('Your settings')
                ->description('Never touched by an import.')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Toggle::make('is_visible')
                        ->label('Show on the storefront')
                        ->helperText('Imported categories start hidden until you review them.'),
                    TextInput::make('title_ar')
                        ->label('Name (Arabic)')
                        ->maxLength(255)
                        ->helperText('Optional. Falls back to the English name.'),
                    WebpUpload::make('image_path', 'category-images')
                        ->label('Image override')
                        ->helperText('Replaces the Shopify collection image on the homepage tile.')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
