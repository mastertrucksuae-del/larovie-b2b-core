<?php

namespace App\Filament\Resources\BusinessTypes;

use App\Filament\Resources\BusinessTypes\Pages\ListBusinessTypes;
use App\Models\BusinessType;
use App\Support\BusinessTypeIcons;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * "Business types we serve" — the homepage chips.
 *
 * Everything happens on the list screen: there are only ever a handful of these
 * and they are two fields each, so a separate create/edit page would be more
 * clicks than the job deserves.
 */
class BusinessTypeResource extends Resource
{
    protected static ?string $model = BusinessType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $recordTitleAttribute = 'name_en';

    protected static ?string $navigationLabel = 'Business types';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_en')
                ->label('Name (English)')
                ->required()
                ->maxLength(255),
            TextInput::make('name_ar')
                ->label('Name (Arabic)')
                ->maxLength(255)
                ->helperText('Optional. Falls back to the English name on the Arabic site.'),
            Select::make('icon')
                ->label('Icon')
                ->options(BusinessTypeIcons::options())
                ->default(BusinessTypeIcons::DEFAULT)
                ->native(false)
                ->helperText('Shown before the name on the homepage chip.'),
            Toggle::make('is_visible')
                ->label('Show on the homepage')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return Tables\BusinessTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinessTypes::route('/'),
        ];
    }
}
