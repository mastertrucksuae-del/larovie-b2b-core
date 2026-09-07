<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use App\Http\Controllers\HomeController;
use App\Support\HomeContent;
use App\Support\Img;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    /** Why a category is, or is not, tiling on the homepage. */
    private static function homepageStatus(Category $record): string
    {
        if ($record->is_archived) {
            return 'Archived in Shopify';
        }

        if (! $record->is_visible) {
            return 'Switched off';
        }

        if ((int) ($record->visible_products_count ?? 0) === 0) {
            // Distinguish "nothing was imported" from "nothing inside it is
            // live yet" — they need completely different fixes.
            return (int) ($record->total_products_count ?? 0) > 0
                ? 'Products not live yet'
                : 'Collection is empty';
        }

        $picked = HomeContent::featuredCategoryIds();

        if ($picked !== [] && ! in_array($record->id, $picked, true)) {
            return 'Not picked in Settings';
        }

        // Asks the homepage itself, so the badge can never drift from what the
        // storefront actually renders — including the limit on how many tile.
        if (! Category::forHomepage(HomeController::CATEGORY_LIMIT)->contains('id', $record->id)) {
            return 'Beyond the first '.HomeController::CATEGORY_LIMIT;
        }

        return 'Showing';
    }

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
                // Counts only what the storefront can actually show. The raw
                // relation count is misleading: a category can hold 85 products
                // and still tile to nothing if none of them are visible.
                TextColumn::make('visible_products_count')
                    ->label('Live products')
                    ->counts([
                        'products as visible_products_count' => fn ($q) => $q->publiclyVisible(),
                        'products as total_products_count',
                    ])
                    ->badge()
                    ->color(fn (?int $state) => $state > 0 ? 'gray' : 'danger')
                    // "0" alone reads like the import failed. "0 of 77" says the
                    // collection imported fine and the products inside it are
                    // what need attention.
                    ->description(fn (Category $record) => sprintf(
                        'of %d in the collection',
                        (int) ($record->total_products_count ?? 0)
                    )),
                // Toggled straight from the list: switching a category on is the
                // single most common action here, and opening an edit page to
                // flip one boolean is friction for no gain.
                ToggleColumn::make('is_visible')->label('Shown'),
                // Turns "why isn't this on the homepage?" from guesswork into an
                // answer. Every rule that can silently drop a category is named.
                TextColumn::make('homepage_status')
                    ->label('On homepage')
                    ->badge()
                    ->state(fn (Category $record) => self::homepageStatus($record))
                    ->color(fn (string $state) => $state === 'Showing' ? 'success' : 'warning')
                    ->tooltip(fn (string $state) => match ($state) {
                        'Products not live yet' => 'The collection imported fine. Its products are hidden, archived or bundles, so the storefront has nothing to show. Make them visible under Products.',
                        'Switched off' => 'Turn on the Shown toggle to put this category on the homepage.',
                        'Not picked in Settings' => 'Settings -> Homepage has a hand-picked list of featured categories, and this one is not in it. Add it there, or clear the list to show categories in this order.',
                        'Collection is empty' => 'No products from this Shopify collection matched the catalogue. Run a product sync first, then import collections again.',
                        default => null,
                    }),
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
