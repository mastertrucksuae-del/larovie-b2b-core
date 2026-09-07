<?php

namespace Tests\Feature;

use App\Filament\Resources\BusinessAccounts\Pages\ViewBusinessAccount;
use App\Models\BusinessAccount;
use App\Models\BusinessType;
use App\Models\Inquiry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Support\AccountInsights;
use App\Support\HomeContent;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The whole thing, end to end.
 *
 * Where the other suites prove each piece in isolation, this walks the journeys
 * a real person takes and checks the pieces still line up: a visitor browsing
 * and being stopped at the order, registering, an admin approving them, the
 * order going through and showing up in that customer's reporting — plus the
 * founder rearranging the homepage from the dashboard and both languages.
 */
class EndToEndJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function catalogue(int $count = 6): Product
    {
        $first = null;

        foreach (range(1, $count) as $i) {
            $product = Product::factory()->create([
                'title' => "Heartleaf Soothing Toner {$i}",
                'handle' => "heartleaf-toner-{$i}",
                'brand' => $i <= 3 ? 'Anua' : 'Round Lab',
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
                'moq' => 12,
            ]);

            $first ??= $product;
        }

        return $first;
    }

    private function register(string $email): void
    {
        $this->post(route('register.store'), [
            'company_name' => 'Gulf Pharmacy LLC',
            'contact_person' => 'Sam Tester',
            'email' => $email,
            'phone' => '0501112222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function test_a_buyer_can_browse_register_be_approved_and_order(): void
    {
        $admin = User::factory()->create();
        $product = $this->catalogue();
        $variant = $product->variants()->first();

        // ── 1. A visitor browses. Prices are public; no account needed to look.
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('shop.hero_title'))
            ->assertSee(Money::format(42.5));

        $this->get(route('catalogue.index'))->assertOk();

        // The product page cross-links to its stablemates — the storefront's only
        // crawlable path between products.
        $this->get(route('catalogue.show', $product->handle))
            ->assertOk()
            ->assertSee(route('catalogue.show', 'heartleaf-toner-2'), escape: false);

        // ── 2. They fill a basket, then hit the wall at the order.
        $this->withSession(['inquiry_cart' => [$variant->id => 24]])
            ->get(route('cart'))
            ->assertOk()
            ->assertSee(__('shop.order_signin_title'))
            ->assertDontSee(__('shop.submit_inquiry'));

        $this->withSession(['inquiry_cart' => [$variant->id => 24]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Sam Tester',
                'customer_mobile' => '0501112222',
            ])
            ->assertRedirect(route('login'));

        $this->assertSame(0, Inquiry::count(), 'A guest must not be able to place an order.');

        // ── 3. They register. Review is on, so they wait.
        Setting::current()->update(['require_account_review' => true]);
        Setting::clearCache();

        $this->register('buyer@example.test');
        $account = BusinessAccount::firstOrFail();
        $this->assertTrue($account->isPending());

        // Signed in but unapproved: still cannot order, and told why.
        $this->actingAs($account, 'business')
            ->withSession(['inquiry_cart' => [$variant->id => 24]])
            ->get(route('cart'))
            ->assertOk()
            ->assertSee(__('shop.order_pending_title'));

        $this->actingAs($account, 'business')
            ->withSession(['inquiry_cart' => [$variant->id => 24]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Sam Tester',
                'customer_mobile' => '0501112222',
            ])
            ->assertRedirect(route('cart'));

        $this->assertSame(0, Inquiry::count());

        // ── 4. An admin approves them from the account view page.
        Livewire::actingAs($admin)
            ->test(ViewBusinessAccount::class, ['record' => $account->getRouteKey()])
            ->callAction('approve')
            ->assertHasNoErrors();

        $account->refresh();
        $this->assertTrue($account->isApproved());
        $this->assertSame($admin->id, $account->reviewed_by);

        // ── 5. Now the order goes through, prefilled and attributed.
        $this->actingAs($account, 'business')
            ->get(route('cart'))
            ->assertOk()
            ->assertSee('Gulf Pharmacy LLC', escape: false);

        $response = $this->actingAs($account, 'business')
            ->withSession(['inquiry_cart' => [$variant->id => 24]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Sam Tester',
                'customer_mobile' => '0501112222',
                'customer_email' => $account->email,
            ]);

        $inquiry = Inquiry::firstOrFail();
        $response->assertRedirect(route('inquiry.confirmation', $inquiry->reference));

        $this->assertSame($account->id, $inquiry->business_account_id, 'The order should belong to the account.');
        $this->assertSame(24, $inquiry->items()->first()->quantity);
        $this->assertSame('+971501112222', $inquiry->customer_mobile);

        // ── 6. The confirmation page, then the admin's view of that customer.
        $this->get(route('inquiry.confirmation', $inquiry->reference))
            ->assertOk()
            ->assertSee($inquiry->reference);

        AccountInsights::flush();
        $this->assertSame(1, AccountInsights::for($account->refresh())->summary()['total']);

        // Guard named explicitly: acting as the buyer earlier made `business`
        // the default guard for the rest of the test, so a bare actingAs() would
        // sign the admin in on the wrong one and Filament would bounce them.
        $this->actingAs($admin, 'web')
            ->get(\App\Filament\Resources\BusinessAccounts\BusinessAccountResource::getUrl('view', ['record' => $account]))
            ->assertOk()
            ->assertSee('Gulf Pharmacy LLC');
    }

    public function test_turning_review_off_lets_a_new_buyer_order_straight_away(): void
    {
        $product = $this->catalogue(1);
        $variant = $product->variants()->first();

        Setting::current()->update(['require_account_review' => false]);
        Setting::clearCache();

        $this->register('instant@example.test');

        $account = BusinessAccount::firstOrFail();
        $this->assertTrue($account->isApproved());
        $this->assertNotNull($account->approved_at);

        $this->actingAs($account, 'business')
            ->withSession(['inquiry_cart' => [$variant->id => 12]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Sam Tester',
                'customer_mobile' => '0501112222',
            ]);

        $this->assertSame(1, Inquiry::count());
    }

    public function test_the_founder_can_rearrange_and_reword_the_homepage(): void
    {
        $this->catalogue(2);

        // Reorder, hide a section, and override copy in one go.
        Setting::current()->update([
            'homepage_section_order' => ['steps', 'categories', 'featured', 'why', 'brands', 'types'],
            'homepage_sections' => ['brands' => false],
            'homepage_content' => ['en' => ['hero_title' => 'Bulk Beauty For UAE Retailers']],
        ]);
        Setting::clearCache();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Bulk Beauty For UAE Retailers', $html);
        $this->assertStringNotContainsString(__('shop.featured_brands'), $html, 'A hidden section must not render.');
        $this->assertLessThan(
            mb_strpos($html, __('shop.shop_by_category')),
            mb_strpos($html, __('shop.how_it_works')),
            'The saved order should drive the page.'
        );

        // Untouched copy still falls back to the shipped translation.
        $this->assertStringContainsString(__('shop.how_it_works'), $html);
    }

    public function test_the_storefront_works_end_to_end_in_arabic(): void
    {
        $product = $this->catalogue(2);
        BusinessType::query()->update(['is_visible' => true]);

        $home = $this->get(route('home').'?hl=ar')->assertOk()->getContent();

        $this->assertStringContainsString('dir="rtl"', $home);
        $this->assertStringContainsString('lang="ar"', $home);
        $this->assertStringContainsString(__('shop.hero_title', [], 'ar'), $home);
        $this->assertStringContainsString(__('shop.business_types', [], 'ar'), $home);

        // The locale carries through the rest of the journey.
        $this->get(route('catalogue.show', $product->handle).'?hl=ar')
            ->assertOk()
            ->assertSee(__('shop.rail_same_brand', ['brand' => 'Anua'], 'ar'));

        $this->get(route('cart').'?hl=ar')
            ->assertOk()
            ->assertSee(__('shop.order_signin_title', [], 'ar'));
    }

    public function test_signing_out_returns_the_buyer_to_where_they_were(): void
    {
        $account = BusinessAccount::create([
            'company_name' => 'Gulf Pharmacy LLC',
            'contact_person' => 'Sam Tester',
            'email' => 'out@example.test',
            'phone' => '0501112222',
            'password' => 'password123',
            'status' => BusinessAccount::STATUS_APPROVED,
            'locale' => 'en',
        ]);

        $this->actingAs($account, 'business')
            ->post(route('logout'), ['redirect_to' => route('catalogue.index')])
            ->assertRedirect(route('catalogue.index'));

        $this->assertGuest('business');
    }

    public function test_the_public_pages_all_answer(): void
    {
        $product = $this->catalogue(2);

        foreach ([
            route('home'),
            route('catalogue.index'),
            route('catalogue.show', $product->handle),
            route('cart'),
            route('contact'),
            route('authenticity'),
            route('login'),
            route('register'),
            route('robots'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_every_editable_homepage_field_is_translated_in_both_languages(): void
    {
        $en = require base_path('lang/en/shop.php');
        $ar = require base_path('lang/ar/shop.php');

        foreach (HomeContent::keys() as $key) {
            $this->assertArrayHasKey($key, $en, "No English wording for '{$key}'.");
            $this->assertArrayHasKey($key, $ar, "No Arabic wording for '{$key}'.");
        }

        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($ar))));
        $this->assertSame([], array_values(array_diff(array_keys($ar), array_keys($en))));
    }
}
