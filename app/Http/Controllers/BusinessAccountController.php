<?php

namespace App\Http\Controllers;

use App\Models\BusinessAccount;
use App\Models\Setting;
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
            'phone_country' => ['nullable', 'string', 'size:2', \Illuminate\Validation\Rule::in(array_keys(\App\Support\Countries::options()))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'trade_licence_number' => ['nullable', 'string', 'max:100'],
            'trade_licence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        // KYC document goes on the PRIVATE disk — never publicly reachable.
        $path = $request->hasFile('trade_licence')
            ? $request->file('trade_licence')->store('trade-licences', 'local')
            : null;

        // With review switched off in Settings, there is no queue to wait in:
        // the account is approved on the spot and can price immediately.
        $reviewRequired = Setting::current()->require_account_review;

        BusinessAccount::create([
            'company_name' => $data['company_name'],
            'contact_person' => $data['contact_person'],
            'email' => $data['email'],
            // Stored E.164 so it matches inquiry numbers and works in wa.me links.
            'phone' => \App\Support\PhoneNumber::toE164(
                $data['phone'],
                $data['phone_country'] ?? \App\Support\PhoneNumber::DEFAULT_REGION,
            ),
            'password' => $data['password'], // hashed via model cast
            'trade_licence_number' => $data['trade_licence_number'] ?? null,
            'trade_licence_path' => $path,
            'status' => $reviewRequired ? BusinessAccount::STATUS_PENDING : BusinessAccount::STATUS_APPROVED,
            'approved_at' => $reviewRequired ? null : now(),
            'locale' => app()->getLocale(),
        ]);

        return redirect()->route('login')->with(
            'status',
            __($reviewRequired ? 'shop.register_received' : 'shop.register_approved')
        );
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
        // Resolved before the session is thrown away — it comes from the form
        // body, but read it first so the ordering is not a trap for later edits.
        $target = $this->destinationAfterLogout($request);

        Auth::guard('business')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to($target);
    }

    /**
     * Where to land after signing out.
     *
     * Signing out from the middle of the catalogue should leave the buyer where
     * they were, not bounce them to the homepage. The submitted value is
     * untrusted, so it is constrained two ways: same-origin only, or logout
     * becomes an open redirect anyone could point at a phishing page; and never
     * a page that needs a session (or a guest-only auth page), which would bounce
     * straight back or read as an error the moment they arrive.
     */
    private function destinationAfterLogout(Request $request): string
    {
        $submitted = trim((string) $request->input('redirect_to'));

        if ($submitted === '') {
            return route('home');
        }

        $parts = parse_url($submitted);

        if ($parts === false || (isset($parts['host']) && $parts['host'] !== $request->getHost())) {
            return route('home');
        }

        $path = '/'.ltrim($parts['path'] ?? '/', '/');

        foreach (['/account', '/admin', '/login', '/register'] as $blocked) {
            if ($path === $blocked || str_starts_with($path, $blocked.'/')) {
                return route('home');
            }
        }

        // The query string carries the ?hl= locale, so keep it.
        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
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
