<?php

namespace Tests\Feature;

use App\Filament\Resources\BusinessAccounts\BusinessAccountResource;
use App\Filament\Resources\BusinessAccounts\Pages\EditBusinessAccount;
use App\Filament\Resources\BusinessAccounts\Pages\ViewBusinessAccount;
use App\Models\BusinessAccount;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Support\AccountInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Business account review, the pricing gate it controls, and the admin view.
 */
class BusinessAccountReviewTest extends TestCase
{
    use RefreshDatabase;

    private function requireReview(bool $required): void
    {
        Setting::current()->update(['require_account_review' => $required]);
        Setting::clearCache();
    }

    private function register(string $email = 'buyer@example.test'): void
    {
        $this->post(route('register.store'), [
            'company_name' => 'Gulf Pharmacy LLC',
            'contact_person' => 'Sam Tester',
            'email' => $email,
            'phone' => '+971500000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
    }

    private function account(string $status = BusinessAccount::STATUS_APPROVED, string $email = 'buyer@example.test'): BusinessAccount
    {
        return BusinessAccount::create([
            'company_name' => 'Gulf Pharmacy LLC',
            'contact_person' => 'Sam Tester',
            'email' => $email,
            'phone' => '+971500000000',
            'password' => 'password123',
            'status' => $status,
            'locale' => 'en',
        ]);
    }

    private function pricedProduct(): Product
    {
        $product = Product::factory()->create([
            'title' => 'Heartleaf Soothing Serum',
            'is_visible' => true,
            'is_archived' => false,
            'is_bundle' => false,
            'featured_image_url' => 'https://cdn.shopify.com/x.jpg',
        ]);
        ProductVariant::factory()->for($product)->create([
            'is_visible' => true,
            'is_archived' => false,
            'wholesale_price' => 42.5,
            'inventory_quantity' => 50,
        ]);

        return $product;
    }

    // ── The review setting ──────────────────────────────────────────────────

    public function test_review_is_required_by_default(): void
    {
        $this->assertTrue(Setting::current()->require_account_review);
    }

    public function test_registration_waits_for_review_when_review_is_required(): void
    {
        $this->requireReview(true);
        $this->register();

        $account = BusinessAccount::firstOrFail();

        $this->assertSame(BusinessAccount::STATUS_PENDING, $account->status);
        $this->assertNull($account->approved_at);
    }

    public function test_registration_is_approved_immediately_when_review_is_off(): void
    {
        $this->requireReview(false);
        $this->register();

        $account = BusinessAccount::firstOrFail();

        $this->assertSame(BusinessAccount::STATUS_APPROVED, $account->status);
        $this->assertNotNull($account->approved_at);
        $this->assertTrue($account->canSubmitInquiry());
    }

    public function test_the_two_paths_tell_the_applicant_different_things(): void
    {
        $this->requireReview(true);
        $this->register('one@example.test');
        $this->assertSame(__('shop.register_received'), session('status'));

        $this->requireReview(false);
        $this->register('two@example.test');
        $this->assertSame(__('shop.register_approved'), session('status'));
    }

    // ── The ordering gate ───────────────────────────────────────────────────

    public function test_anyone_can_see_prices_without_an_account(): void
    {
        $this->pricedProduct();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(\App\Support\Money::format(42.5));
    }

    public function test_a_guest_cannot_submit_an_inquiry(): void
    {
        $product = $this->pricedProduct();

        $this->withSession(['inquiry_cart' => [$product->variants()->first()->id => 12]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Sam Tester',
                'customer_mobile' => '+971500000000',
            ])
            ->assertRedirect(route('login'));

        $this->assertSame(0, Inquiry::count(), 'No inquiry should be created for a guest.');
        $this->assertSame(route('cart'), session('url.intended'), 'Signing in should return them to their basket.');
    }

    public function test_a_pending_account_cannot_submit_while_review_is_required(): void
    {
        $this->requireReview(true);
        $product = $this->pricedProduct();

        $this->actingAs($this->account(BusinessAccount::STATUS_PENDING), 'business')
            ->withSession(['inquiry_cart' => [$product->variants()->first()->id => 12]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Sam Tester',
                'customer_mobile' => '+971500000000',
            ])
            ->assertRedirect(route('cart'));

        $this->assertSame(0, Inquiry::count());
    }

    public function test_a_pending_account_can_submit_once_review_is_switched_off(): void
    {
        // Turning review off retrospectively frees accounts that were queued
        // under the old setting; otherwise they would be stuck forever.
        $this->requireReview(false);
        $product = $this->pricedProduct();

        $this->actingAs($this->account(BusinessAccount::STATUS_PENDING), 'business')
            ->withSession(['inquiry_cart' => [$product->variants()->first()->id => 12]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Sam Tester',
                'customer_mobile' => '+971500000000',
            ]);

        $this->assertSame(1, Inquiry::count());
    }

    public function test_a_rejected_account_can_never_submit(): void
    {
        $this->requireReview(false);
        $product = $this->pricedProduct();

        $this->actingAs($this->account(BusinessAccount::STATUS_REJECTED), 'business')
            ->withSession(['inquiry_cart' => [$product->variants()->first()->id => 12]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Sam Tester',
                'customer_mobile' => '+971500000000',
            ])
            ->assertRedirect(route('cart'));

        $this->assertSame(0, Inquiry::count());
    }

    public function test_an_approved_account_can_submit(): void
    {
        $product = $this->pricedProduct();

        $this->actingAs($this->account(), 'business')
            ->withSession(['inquiry_cart' => [$product->variants()->first()->id => 12]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Sam Tester',
                'customer_mobile' => '+971500000000',
            ]);

        $this->assertSame(1, Inquiry::count());
    }

    public function test_the_cart_asks_a_guest_to_sign_in_instead_of_showing_the_form(): void
    {
        $this->get(route('cart'))
            ->assertOk()
            ->assertSee(__('shop.order_signin_title'))
            ->assertDontSee(__('shop.submit_inquiry'));
    }

    // ── Attribution & analytics ─────────────────────────────────────────────

    public function test_an_inquiry_submitted_while_signed_in_is_attributed_to_the_account(): void
    {
        $account = $this->account();
        $product = $this->pricedProduct();
        $variant = $product->variants()->first();

        $this->actingAs($account, 'business')
            ->withSession(['inquiry_cart' => [$variant->id => 12]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Sam Tester',
                'customer_mobile' => '+971500000000',
                'customer_email' => $account->email,
            ]);

        $this->assertSame(1, $account->inquiries()->count());
    }

    public function test_reporting_also_counts_the_customers_guest_history(): void
    {
        $account = $this->account();

        // Submitted before they ever registered — email only.
        Inquiry::factory()->create([
            'business_account_id' => null,
            'customer_email' => 'BUYER@example.test',
            'status' => Inquiry::STATUS_ORDER_CONFIRMED,
            'quoted_total' => 1000,
        ]);
        Inquiry::factory()->create([
            'business_account_id' => $account->id,
            'status' => Inquiry::STATUS_ORDER_CONFIRMED,
            'quoted_total' => 500,
        ]);
        // Someone else entirely.
        Inquiry::factory()->create(['business_account_id' => null, 'customer_email' => 'other@example.test']);

        $summary = AccountInsights::for($account)->summary();

        $this->assertSame(2, $summary['total'], 'Guest history matched on email should count.');
        $this->assertSame(2, $summary['confirmed']);
        $this->assertSame(1500.0, $summary['confirmed_value']);
        $this->assertSame(750.0, $summary['average_order']);
        $this->assertSame(100, $summary['conversion']);
    }

    public function test_reporting_is_empty_rather_than_broken_for_a_new_account(): void
    {
        $summary = AccountInsights::for($this->account())->summary();

        $this->assertSame(0, $summary['total']);
        $this->assertNull($summary['conversion']);
        $this->assertNull($summary['average_order']);
        $this->assertSame(0.0, $summary['confirmed_value']);
    }

    public function test_top_products_rank_by_units_requested(): void
    {
        $account = $this->account();
        $inquiry = Inquiry::factory()->create(['business_account_id' => $account->id]);

        InquiryItem::factory()->for($inquiry)->create(['product_title' => 'Toner', 'quantity' => 5]);
        InquiryItem::factory()->for($inquiry)->create(['product_title' => 'Serum', 'quantity' => 40]);

        $top = AccountInsights::for($account)->topProducts();

        $this->assertSame('Serum', $top->first()->title);
        $this->assertSame(40, $top->first()->quantity);
    }

    // ── Signing out ─────────────────────────────────────────────────────────

    public function test_signing_out_returns_the_buyer_to_the_page_they_were_on(): void
    {
        $this->actingAs($this->account(), 'business')
            ->post(route('logout'), ['redirect_to' => route('catalogue.index')])
            ->assertRedirect(route('catalogue.index'));

        $this->assertGuest('business');
    }

    public function test_signing_out_keeps_the_locale_in_the_query_string(): void
    {
        $this->actingAs($this->account(), 'business')
            ->post(route('logout'), ['redirect_to' => route('catalogue.index').'?hl=ar'])
            ->assertRedirect('/catalogue?hl=ar');
    }

    public function test_signing_out_from_a_page_that_needs_an_account_goes_home(): void
    {
        // Returning to /account would bounce straight back to the login screen.
        $this->actingAs($this->account(), 'business')
            ->post(route('logout'), ['redirect_to' => route('account')])
            ->assertRedirect(route('home'));
    }

    public function test_signing_out_cannot_be_used_as_an_open_redirect(): void
    {
        $this->actingAs($this->account(), 'business')
            ->post(route('logout'), ['redirect_to' => 'https://phishing.example/login'])
            ->assertRedirect(route('home'));
    }

    public function test_signing_out_without_a_target_goes_home(): void
    {
        $this->actingAs($this->account(), 'business')
            ->post(route('logout'))
            ->assertRedirect(route('home'));
    }

    // ── Trade licence viewing ───────────────────────────────────────────────

    private function accountWithLicence(string $filename = 'licence.pdf'): BusinessAccount
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put("trade-licences/{$filename}", 'PDFBYTES');

        $account = $this->account();
        $account->update(['trade_licence_path' => "trade-licences/{$filename}"]);

        return $account->refresh();
    }

    public function test_an_admin_can_view_the_licence_inline(): void
    {
        $account = $this->accountWithLicence();

        $response = $this->actingAs(User::factory()->create(), 'web')
            ->get(route('business-account.licence', [$account, 'inline' => 1]))
            ->assertOk();

        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        // Another company's legal document must never sit in a shared cache.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_licence_still_downloads_by_default(): void
    {
        $account = $this->accountWithLicence();

        $response = $this->actingAs(User::factory()->create(), 'web')
            ->get(route('business-account.licence', $account))
            ->assertOk();

        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_the_licence_is_never_reachable_without_an_admin_session(): void
    {
        $account = $this->accountWithLicence();

        $this->get(route('business-account.licence', [$account, 'inline' => 1]))
            ->assertRedirect(route('login'));
    }

    public function test_a_missing_licence_file_404s_rather_than_erroring(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $account = $this->account();
        $account->update(['trade_licence_path' => 'trade-licences/gone.pdf']);

        $this->actingAs(User::factory()->create(), 'web')
            ->get(route('business-account.licence', $account))
            ->assertNotFound();
    }

    public function test_image_licences_are_detected_for_the_preview(): void
    {
        // Images render in an <img>, everything else in a frame.
        $this->assertTrue(\App\Http\Controllers\BusinessAccountController::licenceIsImage(
            tap($this->account(), fn ($a) => $a->trade_licence_path = 'x/licence.JPG')
        ));
        $this->assertFalse(\App\Http\Controllers\BusinessAccountController::licenceIsImage(
            tap($this->account('approved', 'pdf@example.test'), fn ($a) => $a->trade_licence_path = 'x/licence.pdf')
        ));
    }

    // ── The admin view page ─────────────────────────────────────────────────

    public function test_the_view_page_renders_the_customer_and_their_numbers(): void
    {
        $account = $this->account();
        Inquiry::factory()->create([
            'business_account_id' => $account->id,
            'status' => Inquiry::STATUS_ORDER_CONFIRMED,
            'quoted_total' => 900,
        ]);

        $html = $this->actingAs(User::factory()->create())
            ->get(BusinessAccountResource::getUrl('view', ['record' => $account]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Gulf Pharmacy LLC', $html);
        $this->assertStringContainsString('buyer@example.test', $html);
        $this->assertStringContainsString('Activity &amp; value', $html);
        $this->assertStringContainsString(\App\Support\Money::format(900), $html);
    }

    /**
     * Regression: approving from the view page threw "Method afterReview does
     * not exist". Filament binds an action closure to the Livewire component but
     * not to the class scope, so the protected callback was unreachable. Earlier
     * tests only asserted the page rendered, never that the button worked.
     */
    public function test_approving_from_the_view_page_stamps_the_audit_trail(): void
    {
        $admin = User::factory()->create();
        $account = $this->account(BusinessAccount::STATUS_PENDING);

        Livewire::actingAs($admin)
            ->test(ViewBusinessAccount::class, ['record' => $account->getRouteKey()])
            ->callAction('approve')
            ->assertHasNoErrors();

        $account->refresh();

        $this->assertTrue($account->isApproved());
        $this->assertNotNull($account->approved_at);
        $this->assertSame($admin->id, $account->reviewed_by);
    }

    public function test_rejecting_from_the_view_page_records_the_reason(): void
    {
        $admin = User::factory()->create();
        $account = $this->account(BusinessAccount::STATUS_PENDING);

        Livewire::actingAs($admin)
            ->test(ViewBusinessAccount::class, ['record' => $account->getRouteKey()])
            ->callAction('reject', ['review_notes' => 'Trade licence unreadable.'])
            ->assertHasNoErrors();

        $account->refresh();

        $this->assertTrue($account->isRejected());
        $this->assertSame('Trade licence unreadable.', $account->review_notes);
        $this->assertFalse($account->canSubmitInquiry(), 'A rejected account must lose ordering rights.');
    }

    public function test_approving_from_the_edit_page_works_too(): void
    {
        $admin = User::factory()->create();
        $account = $this->account(BusinessAccount::STATUS_PENDING);

        Livewire::actingAs($admin)
            ->test(EditBusinessAccount::class, ['record' => $account->getRouteKey()])
            ->callAction('approve')
            ->assertHasNoErrors();

        $this->assertTrue($account->refresh()->isApproved());
    }

    public function test_the_view_page_survives_an_account_with_no_history(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(BusinessAccountResource::getUrl('view', ['record' => $this->account()]))
            ->assertOk();
    }
}
