<?php

namespace App\Filament\Resources\BusinessAccounts;

use App\Filament\Resources\BusinessAccounts\Pages\EditBusinessAccount;
use App\Filament\Resources\BusinessAccounts\Pages\ListBusinessAccounts;
use App\Filament\Resources\BusinessAccounts\Schemas\BusinessAccountForm;
use App\Filament\Resources\BusinessAccounts\Tables\BusinessAccountsTable;
use App\Models\BusinessAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BusinessAccountResource extends Resource
{
    protected static ?string $model = BusinessAccount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $recordTitleAttribute = 'company_name';

    protected static ?string $navigationLabel = 'Business accounts';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false; // customers self-register on the storefront
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = BusinessAccount::where('status', BusinessAccount::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return BusinessAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BusinessAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinessAccounts::route('/'),
            'edit' => EditBusinessAccount::route('/{record}/edit'),
        ];
    }
}
