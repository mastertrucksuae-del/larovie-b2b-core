<?php

namespace Tests\Feature;

use App\Models\BusinessAccount;
use App\Models\Inquiry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Support\Countries;
use App\Support\Geo;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phone entry: pick a country, type the number, and let the backend sort it out.
 */
class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{0: string, 1: string, 2: string}> */
    public static function numbers(): array
    {
        return [
            'bare national' => ['501112222', 'AE', '+971501112222'],
            'spaced' => ['50 111 2222', 'AE', '+971501112222'],
            'trunk zero' => ['0501112222', 'AE', '+971501112222'],
            // The case that used to break: the buyer types the code themselves.
            'already has the code' => ['971501112222', 'AE', '+971501112222'],
            'already E.164' => ['+971501112222', 'AE', '+971501112222'],
            'double zero prefix' => ['00971501112222', 'AE', '+971501112222'],
            'saudi' => ['0555123456', 'SA', '+966555123456'],
            'uk landline' => ['02071838750', 'GB', '+442071838750'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_every_way_of_typing_a_number_normalises_the_same(string $input, string $region, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toE164($input, $region));
    }

    public function test_a_country_code_is_never_doubled(): void
    {
        // Typing the code when the picker already supplies it must not produce
        // +971971…, which is what the old string-rules version did.
        foreach (['971501112222', '+971501112222', '00971501112222'] as $input) {
            $this->assertSame('+971501112222', PhoneNumber::toE164($input, 'AE'));
        }
    }

    public function test_unparseable_input_degrades_instead_of_throwing(): void
    {
        // A wrong number stored is recoverable; a failed submission is not.
        $this->assertSame('', PhoneNumber::toE164(''));
        $this->assertSame('', PhoneNumber::toE164('not a phone'));
        $this->assertNotSame('', PhoneNumber::toE164('12', 'AE'));
    }

    public function test_a_stored_number_splits_back_into_country_and_national_parts(): void
    {
        // So the edit form shows "+971 [50 111 2222]", not "+971 [+971501112222]".
        $this->assertSame(['AE', '501112222'], PhoneNumber::split('+971501112222'));
        $this->assertSame(['GB', '2071838750'], PhoneNumber::split('+442071838750'));
        $this->assertSame(['AE', ''], PhoneNumber::split(null));
    }

    public function test_the_old_dialling_code_argument_still_works(): void
    {
        // Callers that predate libphonenumber passed "971" where a region goes.
        $this->assertSame('+971501112222', PhoneNumber::toE164('501112222', '971'));
    }

    // ── The picker ──────────────────────────────────────────────────────────

    public function test_the_country_list_leads_with_the_gulf(): void
    {
        $options = array_keys(Countries::options());

        $this->assertSame(Countries::PRIORITY, array_slice($options, 0, count(Countries::PRIORITY)));
        $this->assertGreaterThan(200, count($options));
    }

    public function test_the_default_country_follows_the_edge_header_then_falls_back(): void
    {
        // No edge header at all: fall back to the shop's own country.
        $this->assertSame('AE', Geo::defaultRegion(\Illuminate\Http\Request::create('/')));

        $this->assertSame('SA', Geo::defaultRegion(
            tap(request()->duplicate(), fn ($r) => $r->headers->set('CF-IPCountry', 'SA'))
        ));

        // Cloudflare sends XX for anonymised traffic; that is not a country.
        $this->assertSame('AE', Geo::defaultRegion(
            tap(request()->duplicate(), fn ($r) => $r->headers->set('CF-IPCountry', 'XX'))
        ));
    }

    public function test_the_forms_render_a_country_picker(): void
    {
        foreach (['register', 'contact'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('_country', escape: false);
        }

        // The cart shows a sign-in panel to guests, so its form only exists
        // once an approved buyer is looking at it.
        $account = BusinessAccount::create([
            'company_name' => 'Gulf Pharmacy', 'contact_person' => 'Sam',
            'email' => 'cart@example.test', 'phone' => '+971501112222', 'password' => 'password123',
            'status' => BusinessAccount::STATUS_APPROVED, 'locale' => 'en',
        ]);

        $this->actingAs($account, 'business')
            ->get(route('cart'))
            ->assertOk()
            ->assertSee('customer_mobile_country', escape: false);
    }

    // ── End to end ──────────────────────────────────────────────────────────

    public function test_an_inquiry_stores_the_number_in_e164_using_the_chosen_country(): void
    {
        Setting::current()->update(['require_account_review' => false]);
        Setting::clearCache();

        $product = Product::factory()->create(['is_visible' => true, 'is_archived' => false, 'is_bundle' => false]);
        $variant = ProductVariant::factory()->for($product)->create(['is_visible' => true, 'is_archived' => false]);

        $account = BusinessAccount::create([
            'company_name' => 'Gulf Pharmacy', 'contact_person' => 'Sam',
            'email' => 'b@e.test', 'phone' => '+971501112222', 'password' => 'password123',
            'status' => BusinessAccount::STATUS_APPROVED, 'locale' => 'en',
        ]);

        $this->actingAs($account, 'business')
            ->withSession(['inquiry_cart' => [$variant->id => 12]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Sam',
                'customer_mobile' => '0555123456',
                'customer_mobile_country' => 'SA',
            ]);

        $this->assertSame('+966555123456', Inquiry::firstOrFail()->customer_mobile);
    }

    public function test_registration_stores_the_number_in_e164(): void
    {
        $this->post(route('register.store'), [
            'company_name' => 'Gulf Pharmacy', 'contact_person' => 'Sam',
            'email' => 'new@example.test', 'phone' => '0555123456', 'phone_country' => 'SA',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $this->assertSame('+966555123456', BusinessAccount::firstOrFail()->phone);
    }

    public function test_an_unknown_country_is_rejected(): void
    {
        $this->post(route('register.store'), [
            'company_name' => 'Gulf Pharmacy', 'contact_person' => 'Sam',
            'email' => 'bad@example.test', 'phone' => '0555123456', 'phone_country' => 'ZZ',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('phone_country');

        $this->assertSame(0, BusinessAccount::count());
    }
}
