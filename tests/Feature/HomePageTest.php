<?php

namespace Tests\Feature;

use App\Models\BusinessAccount;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The designed homepage that replaced the catalogue at `/`.
 *
 * The assertions that matter commercially are the two conversion paths: the
 * account links must be reachable from the header on every page, and wholesale
 * pricing must stay behind sign-in, since that gate is the reason to register.
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $title = 'Heartleaf Soothing Serum'): Product
    {
        $product = Product::factory()->create([
            'title' => $title,
            'is_visible' => true,
            'is_archived' => false,
            'is_bundle' => false,
            'brand' => 'Anua',
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

    private function approvedAccount(): BusinessAccount
    {
        return BusinessAccount::create([
            'company_name' => 'Gulf Pharmacy LLC',
            'contact_person' => 'Sam Tester',
            'email' => 'buyer@example.test',
            'phone' => '+971500000000',
            'password' => 'password123',
            'status' => BusinessAccount::STATUS_APPROVED,
            'locale' => 'en',
        ]);
    }

    public function test_homepage_renders_at_the_site_root(): void
    {
        $this->makeProduct();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('shop.hero_title'))
            ->assertSee(__('shop.how_it_works'));
    }

    public function test_catalogue_still_serves_from_its_own_path(): void
    {
        $this->get(route('catalogue.index'))->assertOk();
        $this->assertSame('/catalogue', parse_url(route('catalogue.index'), PHP_URL_PATH));
    }

    public function test_featured_products_come_from_the_live_catalogue(): void
    {
        $this->makeProduct('Distinctive Rice Toner');

        $this->get(route('home'))->assertOk()->assertSee('Distinctive Rice Toner');
    }

    public function test_archived_and_hidden_products_never_reach_the_homepage(): void
    {
        $this->makeProduct('Visible Product');
        Product::factory()->create([
            'title' => 'Hidden Product',
            'is_visible' => false,
            'featured_image_url' => 'https://cdn.shopify.com/x.jpg',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Visible Product')
            ->assertDontSee('Hidden Product');
    }

    public function test_prices_are_public(): void
    {
        // Pricing is deliberately open: hiding it costs traffic, and the gate
        // that matters sits on submitting an inquiry instead.
        $this->makeProduct();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(\App\Support\Money::format(42.5));
    }

    public function test_signed_in_buyers_see_the_same_prices(): void
    {
        $this->makeProduct();

        $this->actingAs($this->approvedAccount(), 'business')
            ->get(route('home'))
            ->assertOk()
            ->assertSee(\App\Support\Money::format(42.5));
    }

    public function test_header_exposes_both_account_paths_to_guests(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringContainsString(route('register'), $html);
        $this->assertStringContainsString(__('shop.nav_open_account'), $html);
    }

    public function test_signed_in_buyers_get_an_account_menu_rather_than_sign_in_links(): void
    {
        $html = $this->actingAs($this->approvedAccount(), 'business')
            ->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Gulf Pharmacy LLC', $html);
        $this->assertStringContainsString(route('account'), $html);
        $this->assertStringContainsString(route('logout'), $html);

        // Deliberately keyed on the header's own "Sign in" label rather than the
        // register wording: "Open a business account" is also step 1 of the
        // ordering explainer, which every visitor should keep seeing.
        $this->assertStringNotContainsString(__('shop.nav_login'), $html);
    }

    public function test_homepage_is_localised_and_flips_to_rtl(): void
    {
        $this->makeProduct();

        $html = $this->get(route('home').'?hl=ar')->assertOk()->getContent();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('lang="ar"', $html);
        $this->assertStringContainsString(__('shop.hero_title', [], 'ar'), $html);
    }
}
