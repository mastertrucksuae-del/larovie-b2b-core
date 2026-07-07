<?php

namespace App\Services\Quote;

use App\Models\Inquiry;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteService
{
    /**
     * Ensure the inquiry has a quote number, valid-until date and fresh totals.
     * Idempotent — an existing quote number is kept.
     */
    public function prepare(Inquiry $inquiry): Inquiry
    {
        $inquiry->loadMissing('items', 'charges');
        $inquiry->recalculateTotals();

        $dirty = false;

        if (blank($inquiry->quote_number)) {
            $inquiry->quote_number = Inquiry::generateQuoteNumber();
            $dirty = true;
        }

        if (blank($inquiry->quote_valid_until)) {
            $days = (int) (Setting::current()->quote_validity_days ?? 14);
            $inquiry->quote_valid_until = now()->addDays($days)->toDateString();
            $dirty = true;
        }

        if ($dirty) {
            $inquiry->save();
        }

        return $inquiry;
    }

    /**
     * Render + store the branded PDF quote. Returns the storage path.
     */
    public function generatePdf(Inquiry $inquiry): string
    {
        $this->prepare($inquiry);

        $pdf = Pdf::loadView('quotes.pdf', [
            'inquiry' => $inquiry,
            'settings' => Setting::current(),
        ])->setPaper('a4');

        $path = "quotes/{$inquiry->reference}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        $inquiry->quote_pdf_path = $path;
        $inquiry->save();

        return $path;
    }

    /** Streamed CSV download of the quote line items + meta. */
    public function csvResponse(Inquiry $inquiry): StreamedResponse
    {
        $this->prepare($inquiry);
        $settings = Setting::current();

        $filename = "quote-{$inquiry->reference}.csv";

        return response()->streamDownload(function () use ($inquiry, $settings) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Arabic correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [$settings->company_name.' — Quotation']);
            fputcsv($out, ['Quote number', $inquiry->quote_number]);
            fputcsv($out, ['Reference', $inquiry->reference]);
            fputcsv($out, ['Customer', $inquiry->customer_name]);
            fputcsv($out, ['Company', $inquiry->customer_company]);
            fputcsv($out, ['Mobile', $inquiry->customer_mobile]);
            fputcsv($out, ['Valid until', optional($inquiry->quote_valid_until)->format('Y-m-d')]);
            fputcsv($out, ['Currency', $inquiry->currency]);
            fputcsv($out, []);

            fputcsv($out, ['SKU', 'Product', 'Variant', 'Qty', 'Unit Price', 'Line Total']);
            foreach ($inquiry->items as $item) {
                fputcsv($out, [
                    $item->sku,
                    $item->product_title,
                    $item->variant_title,
                    $item->quantity,
                    $item->unit_price,
                    $item->line_total,
                ]);
            }

            // Billable extra charges (shipping, etc.) — fixed or % of subtotal.
            $base = (float) $inquiry->quoted_subtotal;
            $billable = $inquiry->charges->where('is_billable', true);
            if ($billable->isNotEmpty()) {
                fputcsv($out, []);
                fputcsv($out, ['Additional charges', '', '', '', '', '']);
                foreach ($billable as $charge) {
                    fputcsv($out, ['', $charge->displayLabel(), '', '', '', $charge->resolve($base)]);
                }
            }

            fputcsv($out, []);
            fputcsv($out, ['', '', '', '', 'Subtotal', $inquiry->quoted_subtotal]);
            if ($billable->isNotEmpty()) {
                fputcsv($out, ['', '', '', '', 'Charges', $billable->sum(fn ($c) => $c->resolve($base))]);
            }
            fputcsv($out, ['', '', '', '', 'Total', $inquiry->quoted_total]);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
