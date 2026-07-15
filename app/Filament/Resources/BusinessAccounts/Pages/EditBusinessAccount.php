<?php

namespace App\Filament\Resources\BusinessAccounts\Pages;

use App\Filament\Resources\BusinessAccounts\BusinessAccountResource;
use App\Models\BusinessAccount;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditBusinessAccount extends EditRecord
{
    protected static string $resource = BusinessAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (BusinessAccount $record) => $record->status !== BusinessAccount::STATUS_APPROVED)
                ->requiresConfirmation()
                ->modalDescription('Approve this business account. The applicant will be able to sign in.')
                ->action(function (BusinessAccount $record) {
                    $record->update([
                        'status' => BusinessAccount::STATUS_APPROVED,
                        'approved_at' => now(),
                        'reviewed_by' => Auth::id(),
                    ]);
                    $this->fillForm();

                    Notification::make()->title('Account approved')->success()->send();
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (BusinessAccount $record) => $record->status !== BusinessAccount::STATUS_REJECTED)
                ->form([
                    Textarea::make('review_notes')
                        ->label('Reason (shown to the applicant)')
                        ->rows(3),
                ])
                ->action(function (BusinessAccount $record, array $data) {
                    $record->update([
                        'status' => BusinessAccount::STATUS_REJECTED,
                        'review_notes' => $data['review_notes'] ?? null,
                        'reviewed_by' => Auth::id(),
                    ]);
                    $this->fillForm();

                    Notification::make()->title('Account rejected')->danger()->send();
                }),
        ];
    }
}
