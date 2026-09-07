<?php

namespace App\Filament\Resources\BusinessAccounts\Pages;

use App\Filament\Resources\BusinessAccounts\BusinessAccountResource;
use App\Filament\Resources\BusinessAccounts\Concerns\ReviewsBusinessAccount;
use App\Models\BusinessAccount;
use App\Support\AccountInsights;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBusinessAccount extends ViewRecord
{
    use ReviewsBusinessAccount;

    protected static string $resource = BusinessAccountResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->company_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Reviewing an application means reading the licence, so it opens
            // in place rather than sending the reviewer to their downloads.
            Action::make('viewLicence')
                ->label('View trade licence')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('gray')
                ->visible(fn (BusinessAccount $record) => filled($record->trade_licence_path))
                ->modalHeading(fn (BusinessAccount $record) => 'Trade licence — '.$record->company_name)
                ->modalContent(fn (BusinessAccount $record) => view(
                    'filament.licence-preview',
                    ['record' => $record],
                ))
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
            ...$this->reviewActions(),
            EditAction::make()->label('Edit notes'),
        ];
    }

    /**
     * The reporting figures are memoised per account, so a decision made on this
     * page has to drop the memo or the panel would still show pre-decision
     * numbers after the record refreshes.
     */
    public function afterReview(): void
    {
        // Not `parent::` — this class declaring afterReview() discards the
        // trait's copy, and ViewRecord has no such method to inherit.
        AccountInsights::flush();

        $this->record->refresh();
    }
}
