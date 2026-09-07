<?php

namespace App\Filament\Resources\BusinessAccounts\Pages;

use App\Filament\Resources\BusinessAccounts\BusinessAccountResource;
use App\Filament\Resources\BusinessAccounts\Concerns\ReviewsBusinessAccount;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBusinessAccount extends EditRecord
{
    use ReviewsBusinessAccount;

    protected static string $resource = BusinessAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->reviewActions(),
            ViewAction::make()->label('Full profile'),
        ];
    }

    /** The form on this page has to pick up the new status, not just the model. */
    public function afterReview(): void
    {
        // Not `parent::` — see ViewBusinessAccount: the trait's copy is shadowed
        // by this declaration and EditRecord defines no afterReview().
        $this->record->refresh();

        $this->fillForm();
    }
}
