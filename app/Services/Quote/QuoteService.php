<?php

namespace App\Services\Quote;

use App\Models\Inquiry;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
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
     * Render + store the branded customer quote PDF. Returns the storage path.
     */
    public function generatePdf(Inquiry $inquiry): string
    {
        $pdf = $this->renderPdf($inquiry, isPurchaseOrder: false);

        $path = "quotes/{$inquiry->reference}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        $inquiry->quote_pdf_path = $path;
        $inquiry->save();

        return $path;
    }

    /**
     * Build the supplier purchase order — the same document as the customer
     * quote, minus the customer identity — and return it as an inline download.
     */
    public function purchaseOrderResponse(Inquiry $inquiry): Response
    {
        $pdf = $this->renderPdf($inquiry, isPurchaseOrder: true);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="purchase-order-'.$inquiry->reference.'.pdf"',
        ]);
    }

    /** Shared DomPDF renderer for both the customer quote and the supplier PO. */
    private function renderPdf(Inquiry $inquiry, bool $isPurchaseOrder): \Barryvdh\DomPDF\PDF
    {
        $this->prepare($inquiry);

        return Pdf::loadView('quotes.pdf', [
            'inquiry' => $inquiry,
            'settings' => Setting::current(),
            'isPurchaseOrder' => $isPurchaseOrder,
            'images' => $this->embedLineImages($inquiry),
        ])->setPaper('a4');
    }

    /**
     * Resolve each line item's featured image to a base64 data URI, keyed by
     * item id. DomPDF has remote fetching disabled, so images (local admin
     * overrides or Shopify CDN URLs) are inlined here. Failures degrade to no
     * image rather than breaking the PDF.
     *
     * @return array<int, string>
     */
    private function embedLineImages(Inquiry $inquiry): array
    {
        $images = [];

        foreach ($inquiry->items as $item) {
            $uri = $this->imageDataUri($item->image_url);
            if ($uri !== null) {
                $images[$item->id] = $uri;
            }
        }

        return $images;
    }

    private function imageDataUri(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        try {
            // Admin override images live on the public disk (…/storage/…) — read
            // straight off disk instead of round-tripping through HTTP.
            if (str_contains($url, '/storage/')) {
                $relative = ltrim(Str::after($url, '/storage/'), '/');

                if (Storage::disk('public')->exists($relative)) {
                    $mime = Storage::disk('public')->mimeType($relative) ?: 'image/jpeg';

                    return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($relative));
                }
            }

            if (Str::startsWith($url, ['http://', 'https://'])) {
                $response = Http::timeout(8)->get($url);

                if ($response->successful()) {
                    $mime = $response->header('Content-Type') ?: 'image/jpeg';

                    return 'data:'.$mime.';base64,'.base64_encode($response->body());
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
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
