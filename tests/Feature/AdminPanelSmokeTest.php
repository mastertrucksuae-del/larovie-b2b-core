<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Inquiries\InquiryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_list_renders_for_admin(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        ProductVariant::factory()->count(2)->for($product)->create();

        $this->actingAs($user)
            ->get(ProductResource::getUrl('index'))
            ->assertOk();
    }

    public function test_product_edit_renders_for_admin(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->get(ProductResource::getUrl('edit', ['record' => $product]))
            ->assertOk();
    }

    public function test_inquiries_list_and_edit_render(): void
    {
        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create();
        InquiryItem::factory()->count(2)->for($inquiry)->create();

        $this->actingAs($user)->get(InquiryResource::getUrl('index'))->assertOk();
        $this->actingAs($user)->get(InquiryResource::getUrl('edit', ['record' => $inquiry]))->assertOk();
    }

    public function test_settings_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(ManageSettings::getUrl())->assertOk();
    }

    public function test_dashboard_renders(): void
    {
        $user = User::factory()->create();
        Inquiry::factory()->count(2)->create();

        $this->actingAs($user)->get('/admin')->assertOk();
    }
}
