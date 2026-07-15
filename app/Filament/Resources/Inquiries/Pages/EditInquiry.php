<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Filament\Resources\Inquiries\InquiryResource;
use App\Models\Inquiry;
use App\Services\Quote\QuoteService;
use App\Services\WhatsApp\WhatsAppLink;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInquiry extends EditRecord
{
    protected static string $resource = InquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('chat')
                ->label('Chat on WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->url(fn () => WhatsAppLink::chat($this->getRecord()))
                ->openUrlInNewTab(),

            Action::make('generatePdf')
                ->label('Generate PDF quote')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function (QuoteService $quotes) {
                    $inquiry = $this->getRecord();
                    $quotes->generatePdf($inquiry);
                    $this->refreshFormData(['quote_number', 'quote_valid_until', 'quoted_total']);

                    Notification::make()
                        ->title('Quote PDF generated')
                        ->success()
                        ->actions([
                            Action::make('view')
                                ->label('Open PDF')
                                ->url(WhatsAppLink::quoteLink($inquiry), shouldOpenInNewTab: true),
                        ])
                        ->send();
                }),

            Action::make('purchaseOrder')
                ->label('Purchase order (PDF)')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->action(fn (QuoteService $quotes) => $quotes->purchaseOrderResponse($this->getRecord())),

            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(fn (QuoteService $quotes) => $quotes->csvResponse($this->getRecord())),

            Action::make('sendWhatsApp')
                ->label('Send quote via WhatsApp')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This generates the quote (if needed), marks the inquiry as "Quote sent", and opens WhatsApp with the message ready to send.')
                ->action(function (QuoteService $quotes) {
                    $inquiry = $this->getRecord();
                    $quotes->generatePdf($inquiry);

                    $inquiry->status = Inquiry::STATUS_QUOTE_SENT;
                    $inquiry->stampPipeline(Inquiry::STATUS_QUOTE_SENT);
                    $inquiry->save();

                    $this->refreshFormData(['status', 'quote_number', 'quote_valid_until', 'quoted_total']);

                    Notification::make()
                        ->title('Quote ready to send')
                        ->body('Status set to "Quote sent". Open WhatsApp to deliver the message.')
                        ->success()
                        ->actions([
                            Action::make('open')
                                ->label('Open WhatsApp')
                                ->url(WhatsAppLink::quote($inquiry), shouldOpenInNewTab: true),
                        ])
                        ->persistent()
                        ->send();
                }),
        ];
    }

    /**
     * Move the inquiry to a pipeline stage (Odoo-style status bar). Commits
     * immediately so the change can't be missed.
     */
    public function setStatus(string $status): void
    {
        if (! array_key_exists($status, Inquiry::STATUSES)) {
            return;
        }

        $inquiry = $this->getRecord();
        $inquiry->status = $status;
        $inquiry->stampPipeline($status);
        $inquiry->save();

        $this->refreshFormData(['status', 'quote_sent_at', 'order_confirmed_at']);

        Notification::make()
            ->title('Moved to "'.Inquiry::STATUSES[$status].'"')
            ->success()
            ->send();
    }

    /**
     * After saving prices, recompute totals and auto-advance to
     * "prices filled" once every line has a price.
     */
    protected function afterSave(): void
    {
        $inquiry = $this->getRecord();
        $inquiry->load(['items', 'charges']);
        $inquiry->recalculateTotals();

        if (
            $inquiry->allItemsPriced()
            && in_array($inquiry->status, [Inquiry::STATUS_NEW, Inquiry::STATUS_RESPONDING], true)
        ) {
            $inquiry->status = Inquiry::STATUS_PRICES_FILLED;
            $inquiry->save();
            $this->refreshFormData(['status']);
        }
    }
}
