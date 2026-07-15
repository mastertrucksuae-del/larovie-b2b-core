<?php

namespace App\Filament\Resources\BusinessAccounts\Tables;

use App\Models\BusinessAccount;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BusinessAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (BusinessAccount $r) => $r->contact_person),
                TextColumn::make('email')
                    ->searchable()
                    ->icon('heroicon-o-envelope'),
                TextColumn::make('phone')
                    ->label('Phone'),
                TextColumn::make('trade_licence_number')
                    ->label('Licence')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => BusinessAccount::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        BusinessAccount::STATUS_PENDING => 'warning',
                        BusinessAccount::STATUS_APPROVED => 'success',
                        BusinessAccount::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Applied')
                    ->dateTime('d M Y, H:i')
                    ->description(fn (BusinessAccount $r) => $r->created_at?->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BusinessAccount::STATUSES),
            ])
            ->recordActions([
                EditAction::make()->label('Review'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
