<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquirySubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function makeVariant(array $attrs = []): ProductVariant
    {
        $product = Product::factory()->create();

        return ProductVariant::factory()->for($product)->create($attrs);
    }

    public function test_submission_snapshots_items_and_generates_reference(): void
    {
        $variant = $this->makeVariant([
            'wholesale_price' => 50,
            'moq' => 12,
            'sku' => 'LRV-TEST-01',
            'title' => '50ml / Rose',
        ]);

        $response = $this->withSession(['inquiry_cart' => [$variant->id => 24]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Aisha Trading',
                'customer_mobile' => '050 111 2222',
                'is_whatsapp' => '1',
                'customer_email' => 'buyer@example.com',
            ]);

        $inquiry = Inquiry::first();
        $this->assertNotNull($inquiry);
        $response->assertRedirect(route('inquiry.confirmation', $inquiry->reference));

        // Reference format
        $this->assertMatchesRegularExpression('/^LRV-\d{4}-\d{4}$/', $inquiry->reference);

        // Mobile normalized to E.164
        $this->assertSame('+971501112222', $inquiry->customer_mobile);
        $this->assertTrue($inquiry->is_whatsapp);

        // Snapshotted line item
        $item = $inquiry->items()->first();
        $this->assertSame('50ml / Rose', $item->variant_title);
        $this->assertSame('LRV-TEST-01', $item->sku);
        $this->assertSame(24, $item->quantity);
        $this->assertNull($item->unit_price); // filled by admin later

        // Cart cleared
        $this->assertSame([], session('inquiry_cart', []));
    }

    public function test_snapshot_survives_variant_archival(): void
    {
        $variant = $this->makeVariant(['title' => 'Amber', 'sku' => 'SNAP-1']);

        $this->withSession(['inquiry_cart' => [$variant->id => 6]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Test',
                'customer_mobile' => '0501234567',
            ]);

        // Variant is deleted; the snapshot must remain intact regardless of the
        // live product data (product_variant_id is nulled by the FK on MySQL).
        $variant->delete();

        $item = Inquiry::first()->items()->first();
        $this->assertNotNull($item);
        $this->assertSame('SNAP-1', $item->sku);
        $this->assertSame('Amber', $item->variant_title);
        $this->assertSame(6, $item->quantity);
    }

    public function test_honeypot_blocks_bots(): void
    {
        $variant = $this->makeVariant();

        $this->withSession(['inquiry_cart' => [$variant->id => 6]])
            ->post(route('inquiry.store'), [
                'customer_name' => 'Bot',
                'customer_mobile' => '0501234567',
                'company_website' => 'http://spam.example',
            ])
            ->assertRedirect(route('catalogue.index'));

        $this->assertSame(0, Inquiry::count());
    }

    public function test_empty_cart_cannot_submit(): void
    {
        $this->post(route('inquiry.store'), [
            'customer_name' => 'Nobody',
            'customer_mobile' => '0501234567',
        ])->assertRedirect(route('cart'));

        $this->assertSame(0, Inquiry::count());
    }
}
