<?php

namespace App\Filament\Resources\BusinessAccounts\Concerns;

use App\Models\BusinessAccount;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * The approve / reject header actions, shared by the edit and view pages.
 *
 * Kept in one place so the two pages cannot drift: an approval granted from the
 * view screen must stamp the same audit trail as one granted from the edit
 * screen.
 */
trait ReviewsBusinessAccount
{
    /** @return array<int, Action> */
    protected function reviewActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (BusinessAccount $record) => ! $record->isApproved())
                ->requiresConfirmation()
                ->modalDescription('Approve this business account. The applicant will be able to sign in and see wholesale pricing.')
                ->action(function (BusinessAccount $record) {
                    $record->update([
                        'status' => BusinessAccount::STATUS_APPROVED,
                        'approved_at' => now(),
                        'reviewed_by' => Auth::id(),
                    ]);

                    $this->afterReview();

                    Notification::make()->title('Account approved')->success()->send();
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (BusinessAccount $record) => ! $record->isRejected())
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

                    $this->afterReview();

                    Notification::make()->title('Account rejected')->danger()->send();
                }),
        ];
    }

    /**
     * Each page refreshes whatever it renders the record through.
     *
     * Public deliberately: Filament binds an action closure to the Livewire
     * component but not to this class's scope, so a protected method is
     * unreachable from inside `->action(...)` and falls through to Livewire's
     * __call, which throws "method does not exist".
     */
    public function afterReview(): void
    {
        $this->record->refresh();
    }
}
