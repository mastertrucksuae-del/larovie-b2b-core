<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Inquiries\InquiryResource;
use App\Models\Inquiry;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestInquiries extends TableWidget
{
    protected static ?string $heading = 'Recent inquiries';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Inquiry::query()->latest()->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('reference')->label('Ref')->weight('bold'),
                TextColumn::make('customer_name')->label('Customer'),
                TextColumn::make('items_count')->counts('items')->label('Items')->alignCenter(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Inquiry::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Inquiry::STATUS_NEW => 'warning',
                        Inquiry::STATUS_RESPONDING => 'info',
                        Inquiry::STATUS_PRICES_FILLED => 'primary',
                        Inquiry::STATUS_QUOTE_SENT => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Received')->since(),
            ])
            ->recordActions([
                Action::make('open')
                    ->url(fn (Inquiry $record) => InquiryResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
