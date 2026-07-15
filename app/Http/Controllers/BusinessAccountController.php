<?php

namespace App\Http\Controllers;

use App\Models\BusinessAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Lightweight KYC business accounts (P1 #10). Sign-up captures company details
 * and a trade licence, then waits on admin approval (pending/approved/rejected).
 */
class BusinessAccountController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot
        if (filled($request->input('company_website'))) {
            return redirect()->route('register');
        }

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:business_accounts,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'trade_licence_number' => ['nullable', 'string', 'max:100'],
            'trade_licence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        // KYC document goes on the PRIVATE disk — never publicly reachable.
        $path = $request->hasFile('trade_licence')
            ? $request->file('trade_licence')->store('trade-licences', 'local')
            : null;

        BusinessAccount::create([
            'company_name' => $data['company_name'],
            'contact_person' => $data['contact_person'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'], // hashed via model cast
            'trade_licence_number' => $data['trade_licence_number'] ?? null,
            'trade_licence_path' => $path,
            'status' => BusinessAccount::STATUS_PENDING,
            'locale' => app()->getLocale(),
        ]);

        return redirect()->route('login')->with('status', __('shop.register_received'));
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('business')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('shop.login_failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('business')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('catalogue.index');
    }

    public function dashboard(): View
    {
        return view('account.dashboard', [
            'account' => Auth::guard('business')->user(),
        ]);
    }

    /** Admin-only download of a KYC trade licence (guarded by the web/admin guard). */
    public function licence(BusinessAccount $account): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($account->trade_licence_path, 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($account->trade_licence_path);
    }
}
