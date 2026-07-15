<?php

namespace App\Services\Export;

use App\Models\InquiryItem;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin CSV export of every inquiry line item, resolvable by brand and SKU
 * (P2 — Month-3 supplier data pack). One row per line item across all inquiries.
 */
class LineItemExport
{
    public function csvResponse(): StreamedResponse
    {
        $filename = 'larovie-line-items-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel renders Arabic + currency correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Brand', 'SKU', 'Product', 'Variant', 'Quantity',
                'Unit price', 'Line total', 'Currency',
                'Inquiry ref', 'Status', 'Customer', 'Company',
                'UTM source', 'Referral code', 'Received', 'Quote sent',
            ]);

            InquiryItem::query()
                ->with(['inquiry', 'variant.product'])
                ->chunk(500, function ($items) use ($out) {
                    foreach ($items as $item) {
                        $inquiry = $item->inquiry;
                        $brand = $item->variant?->product?->effective_brand ?? '';

                        fputcsv($out, [
                            $brand,
                            $item->sku,
                            $item->product_title,
                            $item->display_variant_title,
                            $item->quantity,
                            $item->unit_price,
                            $item->line_total,
                            $inquiry?->currency,
                            $inquiry ? ($inquiry->statusLabel()) : '',
                            $inquiry?->customer_name,
                            $inquiry?->customer_company,
                            $inquiry?->utm_source,
                            $inquiry?->referral_code,
                            optional($inquiry?->created_at)->format('Y-m-d H:i'),
                            optional($inquiry?->quote_sent_at)->format('Y-m-d H:i'),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
