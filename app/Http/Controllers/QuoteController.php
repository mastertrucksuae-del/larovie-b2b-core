<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Services\Quote\QuoteService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class QuoteController extends Controller
{
    /**
     * Serve the quote PDF via a signed link (used in the WhatsApp message).
     * Regenerates on the fly if the stored file is missing.
     */
    public function download(Inquiry $inquiry, QuoteService $quotes): Response
    {
        if (blank($inquiry->quote_pdf_path) || ! Storage::disk('local')->exists($inquiry->quote_pdf_path)) {
            $quotes->generatePdf($inquiry);
            $inquiry->refresh();
        }

        return response(
            Storage::disk('local')->get($inquiry->quote_pdf_path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="quote-'.$inquiry->reference.'.pdf"',
            ]
        );
    }
}
