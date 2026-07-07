<?php

namespace App\Http\Controllers;

use App\Actions\CreateInquiry;
use App\Models\Inquiry;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function cart(CartService $cart): View
    {
        return view('cart', [
            'items' => $cart->items(),
            'subtotal' => $cart->subtotal(),
            'hasAnyPrice' => $cart->hasAnyPrice(),
        ]);
    }

    public function store(Request $request, CartService $cart, CreateInquiry $createInquiry): RedirectResponse
    {
        // Honeypot: bots fill hidden fields. Silently bounce if the trap is set.
        if (filled($request->input('company_website'))) {
            return redirect()->route('catalogue.index');
        }

        if ($cart->isEmpty()) {
            return redirect()->route('cart')->with('error', __('Your inquiry is empty.'));
        }

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_mobile' => ['required', 'string', 'max:30'],
            'is_whatsapp' => ['sometimes', 'boolean'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_company' => ['nullable', 'string', 'max:255'],
            'customer_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['is_whatsapp'] = $request->boolean('is_whatsapp');

        $inquiry = $createInquiry->handle($data, $cart, app()->getLocale());

        $cart->clear();

        return redirect()->route('inquiry.confirmation', $inquiry->reference);
    }

    public function confirmation(string $reference): View
    {
        $inquiry = Inquiry::where('reference', $reference)->firstOrFail();

        return view('inquiry-confirmation', [
            'inquiry' => $inquiry,
        ]);
    }
}
