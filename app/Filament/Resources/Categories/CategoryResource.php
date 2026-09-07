<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Filament\Resources\Categories\Tables\CategoriesTable;
use App\Models\Category;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Categories, imported from Shopify collections.
 *
 * No manual creation, for the same reason products have none: the catalogue is
 * owned by Shopify, and a hand-made row would have no collection behind it to
 * draw products from. Admins own what is shown, in what order, the Arabic name
 * and the artwork.
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    /** Nudge towards newly imported categories, which arrive hidden. */
    public static function getNavigationBadge(): ?string
    {
        $unreviewed = Category::query()
            ->where('is_visible', false)
            ->where('is_archived', false)
            ->count();

        return $unreviewed > 0 ? (string) $unreviewed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
