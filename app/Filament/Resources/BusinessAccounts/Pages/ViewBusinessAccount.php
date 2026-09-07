<?php

namespace App\Filament\Resources\BusinessAccounts\Pages;

use App\Filament\Resources\BusinessAccounts\BusinessAccountResource;
use App\Filament\Resources\BusinessAccounts\Concerns\ReviewsBusinessAccount;
use App\Support\AccountInsights;
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
