<?php

namespace App\Actions;

use App\Models\Inquiry;
use App\Models\Setting;
use App\Services\Cart\CartService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

class CreateInquiry
{
    /**
     * Create an inquiry from the current cart, snapshotting every line item
     * so the quote stays accurate even after a re-sync or variant archival.
     *
     * @param  array{customer_name:string, customer_mobile:string, is_whatsapp:bool, customer_email?:?string, customer_company?:?string, customer_message?:?string}  $data
     */
    public function handle(array $data, CartService $cart, string $locale = 'en'): Inquiry
    {
        $currency = Setting::current()->default_currency ?? 'AED';

        return DB::transaction(function () use ($data, $cart, $locale, $currency) {
            $inquiry = Inquiry::create([
                'status' => Inquiry::STATUS_NEW,
                'customer_name' => $data['customer_name'],
                'customer_mobile' => PhoneNumber::toE164($data['customer_mobile']),
                'is_whatsapp' => $data['is_whatsapp'] ?? false,
                'customer_email' => $data['customer_email'] ?? null,
                'customer_company' => $data['customer_company'] ?? null,
                'customer_message' => $data['customer_message'] ?? null,
                'currency' => $currency,
                'locale' => in_array($locale, ['en', 'ar'], true) ? $locale : 'en',
            ]);

            foreach ($cart->items() as $item) {
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
    }
}
