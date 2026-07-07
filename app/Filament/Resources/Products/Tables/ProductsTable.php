<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('display_image')
                    ->label('')
                    ->height(44)
                    ->square(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn (Product $r) => $r->vendor),
                TextColumn::make('product_type')
                    ->label('Type')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->alignCenter(),
                ToggleColumn::make('is_visible')
                    ->label('Visible'),
                ToggleColumn::make('is_bundle')
                    ->label('Bundle')
                    ->tooltip('Bundles/kits are hidden from the solo-product catalogue'),
                TextInputColumn::make('moq')
                    ->label('MOQ')
                    ->type('number')
                    ->rules(['nullable', 'integer', 'min:1'])
                    ->width('6rem'),
                IconColumn::make('is_archived')
                    ->label('Archived')
                    ->boolean()
                    ->trueIcon('heroicon-o-archive-box')
                    ->falseIcon('heroicon-o-check-circle')
                    ->toggleable(),
                TextColumn::make('synced_at')
                    ->label('Last synced')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('product_type')
                    ->label('Type')
                    ->options(fn () => Product::query()
                        ->whereNotNull('product_type')
                        ->distinct()
                        ->orderBy('product_type')
                        ->pluck('product_type', 'product_type')
                        ->all()),
                TernaryFilter::make('is_visible')
                    ->label('Visibility'),
                TernaryFilter::make('is_bundle')
                    ->label('Bundles'),
                TernaryFilter::make('is_archived')
                    ->label('Archived')
                    ->default(false),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('bulkEdit')
                    ->label('Bulk edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->schema([
                        Select::make('is_visible')
                            ->label('Visibility')
                            ->options(['keep' => 'No change', '1' => 'Visible', '0' => 'Hidden'])
                            ->default('keep'),
                        Select::make('is_bundle')
                            ->label('Bundle')
                            ->options(['keep' => 'No change', '1' => 'Mark as bundle', '0' => 'Not a bundle'])
                            ->default('keep'),
                        TextInput::make('moq')
                            ->label('Set MOQ')
                            ->helperText('Leave blank to keep each product’s MOQ.')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('wholesale_price')
                            ->label('Set wholesale price on ALL variants')
                            ->helperText('Leave blank to keep existing variant prices.')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->action(function (array $data, Collection $records) {
                        $visible = (string) ($data['is_visible'] ?? 'keep');
                        $bundle = (string) ($data['is_bundle'] ?? 'keep');

                        foreach ($records as $product) {
                            $updates = [];
                            if ($visible !== 'keep') {
                                $updates['is_visible'] = $visible === '1';
                            }
                            if ($bundle !== 'keep') {
                                $updates['is_bundle'] = $bundle === '1';
                            }
                            if (filled($data['moq'] ?? null)) {
                                $updates['moq'] = (int) $data['moq'];
                            }
                            if ($updates) {
                                $product->update($updates);
                            }
                            if (filled($data['wholesale_price'] ?? null)) {
                                $product->variants()->update(['wholesale_price' => (float) $data['wholesale_price']]);
                            }
                        }
                    })
                    ->deselectRecordsAfterCompletion()
                    ->successNotificationTitle('Products updated'),
                BulkActionGroup::make([
                    BulkAction::make('show')
                        ->label('Make visible')
                        ->icon('heroicon-o-eye')
                        ->action(fn (Collection $records) => $records->each->update(['is_visible' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('hide')
                        ->label('Hide')
                        ->icon('heroicon-o-eye-slash')
                        ->action(fn (Collection $records) => $records->each->update(['is_visible' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('title');
    }
}
