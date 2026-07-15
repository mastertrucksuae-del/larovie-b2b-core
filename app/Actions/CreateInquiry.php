<?php

namespace App\Actions;

use App\Models\Inquiry;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AdminNewInquiryNotification;
use App\Notifications\BuyerInquiryReceivedNotification;
use App\Services\Cart\CartService;
use App\Support\PhoneNumber;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class CreateInquiry
{
    /** Attribution keys persisted verbatim onto the inquiry (P0 #5, #6). */
    private const ATTRIBUTION_KEYS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'landing_page', 'referrer', 'referral_code',
    ];

    /**
     * Create an inquiry, snapshotting every cart line item so the quote stays
     * accurate even after a re-sync or variant archival. A null/empty cart
     * produces a general (message-only) inquiry — used by the Contact page.
     *
     * @param  array{customer_name:string, customer_mobile:string, is_whatsapp?:bool, customer_email?:?string, customer_company?:?string, customer_message?:?string}  $data
     * @param  array<string, ?string>  $attribution  UTM / referral / landing-page values captured on first visit
     */
    public function handle(array $data, ?CartService $cart = null, string $locale = 'en', array $attribution = []): Inquiry
    {
        $currency = Setting::current()->default_currency ?? 'AED';

        $inquiry = DB::transaction(function () use ($data, $cart, $locale, $currency, $attribution) {
            $inquiry = Inquiry::create(array_merge([
                'status' => Inquiry::STATUS_NEW,
                'customer_name' => $data['customer_name'],
                'customer_mobile' => PhoneNumber::toE164($data['customer_mobile']),
                'is_whatsapp' => $data['is_whatsapp'] ?? false,
                'customer_email' => $data['customer_email'] ?? null,
                'customer_company' => $data['customer_company'] ?? null,
                'customer_message' => $data['customer_message'] ?? null,
                'currency' => $currency,
                'locale' => in_array($locale, ['en', 'ar'], true) ? $locale : 'en',
            ], $this->attributionColumns($attribution)));

            foreach ($cart?->items() ?? [] as $item) {
                /** @var \App\Models\ProductVariant $variant */
                $variant = $item['variant'];

                $inquiry->items()->create([
                    'product_variant_id' => $variant->id,
                    'product_title' => $variant->product?->title ?? '',
                    'variant_title' => $variant->title,
                    'sku' => $variant->sku,
                    'image_url' => $variant->display_image,
                    'quantity' => $item['quantity'],
                    // Prices are filled by the admin during "responding".
                    'unit_price' => null,
                    'line_total' => null,
                ]);
            }

            return $inquiry;
        });

        $this->dispatchNotifications($inquiry);

        return $inquiry;
    }

    /** @return array<string, ?string> whitelisted attribution columns */
    private function attributionColumns(array $attribution): array
    {
        return collect(self::ATTRIBUTION_KEYS)
            ->mapWithKeys(fn (string $key) => [$key => $attribution[$key] ?? null])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    /**
     * Notify admins (email + in-panel push) and acknowledge the buyer (P0 #8, P1 #11).
     * Never let a delivery failure break inquiry submission.
     */
    private function dispatchNotifications(Inquiry $inquiry): void
    {
        try {
            $settings = Setting::current();
            $adminEmail = $settings->notification_email ?: $settings->company_email;

            if ($adminEmail) {
                NotificationFacade::route('mail', $adminEmail)
                    ->notify(new AdminNewInquiryNotification($inquiry));
            }

            // In-panel "push" alert to every admin (P0 #8). Written synchronously
            // (notifyNow) so it appears instantly regardless of the queue worker.
            $dbNotification = FilamentNotification::make()
                ->title('New inquiry: '.$inquiry->reference)
                ->body(trim($inquiry->customer_name.' — '.($inquiry->customer_company ?: $inquiry->customer_mobile)))
                ->icon('heroicon-o-inbox-arrow-down')
                ->iconColor('warning')
                ->actions([
                    FilamentAction::make('view')
                        ->label('Open')
                        ->url(url('/admin/inquiries/'.$inquiry->getKey().'/edit')),
                ])
                ->toDatabase();

            foreach (User::all() as $admin) {
                $admin->notifyNow($dbNotification);
            }

            // Buyer acknowledgement with reference number (P1 #11).
            if ($inquiry->customer_email) {
                NotificationFacade::route('mail', $inquiry->customer_email)
                    ->notify(new BuyerInquiryReceivedNotification($inquiry));
            }
        } catch (\Throwable $e) {
            Log::warning('Inquiry notification dispatch failed: '.$e->getMessage(), [
                'inquiry' => $inquiry->reference,
            ]);
        }
    }
}
