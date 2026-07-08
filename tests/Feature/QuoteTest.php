<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Setting;
use App\Services\Quote\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function inquiryWithPricedItems(): Inquiry
    {
        Setting::current();
        $inquiry = Inquiry::factory()->create();

        InquiryItem::factory()->for($inquiry)->create(['quantity' => 10, 'unit_price' => 25]);
        InquiryItem::factory()->for($inquiry)->create(['quantity' => 4, 'unit_price' => 12.50]);

        return $inquiry->load('items');
    }

    public function test_totals_are_computed_from_line_items(): void
    {
        $inquiry = $this->inquiryWithPricedItems();

        $inquiry->recalculateTotals();

        // 10*25 + 4*12.50 = 250 + 50 = 300
        $this->assertSame('300.00', (string) $inquiry->quoted_subtotal);
        $this->assertSame('300.00', (string) $inquiry->quoted_total);

        $this->assertSame('250.00', (string) $inquiry->items[0]->line_total);
        $this->assertSame('50.00', (string) $inquiry->items[1]->line_total);
    }

    public function test_billable_charges_add_to_total_internal_do_not(): void
    {
        $inquiry = $this->inquiryWithPricedItems(); // items subtotal = 300
        \App\Models\InquiryCharge::factory()->for($inquiry)->create(['label' => 'Shipping', 'amount' => 40, 'is_billable' => true]);
        \App\Models\InquiryCharge::factory()->for($inquiry)->internal()->create(['amount' => 15]); // parking, internal

        $inquiry->load(['items', 'charges'])->recalculateTotals();

        // 300 products + 40 billable shipping = 340; the 15 internal is excluded.
        $this->assertSame('300.00', (string) $inquiry->quoted_subtotal);
        $this->assertSame('340.00', (string) $inquiry->quoted_total);
        $this->assertSame(40.0, $inquiry->billableChargesTotal());
        $this->assertSame(15.0, $inquiry->internalChargesTotal());
    }

    public function test_percentage_charges_resolve_against_the_subtotal(): void
    {
        $inquiry = $this->inquiryWithPricedItems(); // items subtotal = 300
        \App\Models\InquiryCharge::factory()->for($inquiry)->percent(10)->create(['label' => 'Shipping', 'is_billable' => true]);

        $inquiry->load(['items', 'charges'])->recalculateTotals();

        // 10% of 300 = 30 → total 330
        $this->assertSame('330.00', (string) $inquiry->quoted_total);
        $this->assertSame(30.0, $inquiry->billableChargesTotal());
        $this->assertSame('Shipping (10%)', $inquiry->charges->first()->displayLabel());
    }

    public function test_all_items_priced_detection(): void
    {
        $inquiry = Inquiry::factory()->create();
        InquiryItem::factory()->for($inquiry)->create(['unit_price' => 10]);
        InquiryItem::factory()->for($inquiry)->create(['unit_price' => null]);

        $this->assertFalse($inquiry->load('items')->allItemsPriced());

        $inquiry->items[1]->update(['unit_price' => 5]);
        $this->assertTrue($inquiry->load('items')->allItemsPriced());
    }

    public function test_generate_pdf_assigns_quote_number_and_stores_file(): void
    {
        Http::fake(['*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg'])]);
        Storage::fake('local');
        $inquiry = $this->inquiryWithPricedItems();

        $path = app(QuoteService::class)->generatePdf($inquiry);
        $inquiry->refresh();

        $this->assertNotNull($inquiry->quote_number);
        $this->assertMatchesRegularExpression('/^Q-\d{4}-\d{4}$/', $inquiry->quote_number);
        $this->assertNotNull($inquiry->quote_valid_until);
        Storage::disk('local')->assertExists($path);
    }

    public function test_purchase_order_generates_pdf_without_customer_name(): void
    {
        Http::fake(['*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg'])]);
        $inquiry = $this->inquiryWithPricedItems();
        $inquiry->update(['customer_name' => 'Very Secret Buyer LLC']);

        $response = app(QuoteService::class)->purchaseOrderResponse($inquiry);

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        // StreamedResponse writes to the output buffer — capture it to inspect.
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringStartsWith('%PDF', $content);
        // Supplier PO must not leak the customer identity into the document.
        $this->assertStringNotContainsString('Very Secret Buyer LLC', $content);
    }

    public function test_signed_quote_route_serves_pdf(): void
    {
        Http::fake(['*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg'])]);
        $inquiry = $this->inquiryWithPricedItems();

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'quote.download',
            now()->addDay(),
            ['inquiry' => $inquiry->id],
        );

        $this->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Unsigned access is rejected.
        $this->get(route('quote.download', $inquiry))->assertStatus(403);
    }
}
