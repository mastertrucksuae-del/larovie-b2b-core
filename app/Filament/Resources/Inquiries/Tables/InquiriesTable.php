<?php

namespace App\Filament\Resources\Inquiries\Tables;

use App\Models\Inquiry;
use App\Services\WhatsApp\WhatsAppLink;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Ref')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->description(fn (Inquiry $r) => $r->customer_company),
                TextColumn::make('customer_mobile')
                    ->label('Mobile')
                    ->icon(fn (Inquiry $r) => $r->is_whatsapp ? 'heroicon-o-chat-bubble-left-right' : null)
                    ->iconColor('success')
                    ->searchable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->alignCenter(),
                TextColumn::make('quoted_total')
                    ->label('Total')
                    ->money(fn (Inquiry $r) => $r->currency)
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Inquiry::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Inquiry::STATUS_NEW => 'warning',
                        Inquiry::STATUS_RESPONDING => 'info',
                        Inquiry::STATUS_PRICES_FILLED => 'primary',
                        Inquiry::STATUS_QUOTE_SENT => 'success',
                        Inquiry::STATUS_ORDER_CONFIRMED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('utm_source')
                    ->label('Source')
                    ->placeholder('—')
                    ->description(fn (Inquiry $r) => $r->referral_code ? 'code: '.$r->referral_code : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('d M Y, H:i')
                    ->description(fn (Inquiry $r) => $r->created_at?->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Inquiry::STATUSES),
                SelectFilter::make('utm_source')
                    ->label('Source')
                    ->options(fn () => Inquiry::query()
                        ->whereNotNull('utm_source')
                        ->distinct()
                        ->orderBy('utm_source')
                        ->pluck('utm_source', 'utm_source')
                        ->all()),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('Chat')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (Inquiry $record) => WhatsAppLink::chat($record))
                    ->openUrlInNewTab(),
                EditAction::make()->label('Open'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
