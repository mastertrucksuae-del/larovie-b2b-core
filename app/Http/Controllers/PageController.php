<?php

namespace App\Http\Controllers;

use App\Actions\CreateInquiry;
use App\Models\Setting;
use App\Support\Attribution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function contact(): View
    {
        return view('pages.contact', [
            'settings' => Setting::current(),
        ]);
    }

    /** General (message-only) inquiry from the Contact page form (P0 #4). */
    public function contactSend(Request $request, CreateInquiry $createInquiry): RedirectResponse
    {
        // Honeypot — bots fill the hidden field.
        if (filled($request->input('company_website'))) {
            return redirect()->route('contact');
        }

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_mobile' => ['required', 'string', 'max:30'],
            'is_whatsapp' => ['sometimes', 'boolean'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_company' => ['nullable', 'string', 'max:255'],
            'customer_message' => ['required', 'string', 'max:2000'],
            'referral_code' => ['nullable', 'string', 'max:60'],
        ]);

        $data['is_whatsapp'] = $request->boolean('is_whatsapp');

        $attribution = array_merge(Attribution::all(), [
            'referral_code' => $data['referral_code'] ?? null,
        ]);

        // No cart — a general enquiry with a message only.
        $inquiry = $createInquiry->handle($data, null, app()->getLocale(), $attribution);

        return redirect()
            ->route('inquiry.confirmation', $inquiry->reference)
            ->with('status', __('shop.contact_sent'));
    }

    public function authenticity(): View
    {
        return view('pages.authenticity', [
            'settings' => Setting::current(),
        ]);
    }
}
