<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Subscriber;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quotations & Proforma Invoices: the shared CRUD surface, per-type numbering,
 * the totals maths, and the convert-to-invoice path (the only place a pre-sale
 * document becomes a receivable).
 */
class QuoteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Quote ISP', 'domain' => 'quotes.test', 'slug' => 'quotes', 'status' => 'active',
        ]);
    }

    private function url(string $path): string
    {
        // ResolveTenant keys off the request Host, so a FULL url is required.
        return 'http://' . $this->tenant->domain . '/' . ltrim($path, '/');
    }

    private function subscriber(string $username = 'quoteuser'): Subscriber
    {
        $plan = Plan::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Basic', 'price' => 500,
            'duration' => 1, 'duration_unit' => 'months',
        ]);

        return Subscriber::create([
            'tenant_id'       => $this->tenant->id,
            'username'        => $username,
            'radius_username' => 'quotes-' . $username,
            'password_enc'    => 'x',
            'plan_id'         => $plan->id,
            'status'          => 'active',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'status'        => 'draft',
            'customer_name' => 'Acme Prospect',
            'issue_date'    => '2026-09-01',
            'items'         => [
                ['label' => 'Installation', 'qty' => 2, 'unit_price' => 500, 'taxable' => 1, 'tax_rate' => 18],
            ],
        ], $overrides);
    }

    public function test_each_type_gets_its_own_numbering_series(): void
    {
        $this->post($this->url('/quotation'), $this->payload())->assertRedirect();
        $this->post($this->url('/proforma'), $this->payload())->assertRedirect();
        $this->post($this->url('/quotation'), $this->payload())->assertRedirect();

        $month = date('ym');
        $numbers = Quote::where('tenant_id', $this->tenant->id)->orderBy('id')->pluck('number')->all();

        $this->assertSame([
            "QTN-{$month}-0001",
            "PRO-{$month}-0001",
            "QTN-{$month}-0002",
        ], $numbers);
    }

    public function test_an_unknown_document_type_is_not_found(): void
    {
        $this->get($this->url('/estimate'))->assertNotFound();
    }

    public function test_totals_are_computed_from_the_items_and_the_discount(): void
    {
        $this->post($this->url('/quotation'), $this->payload([
            'discount_amount' => 100,
            'items' => [
                ['label' => 'Installation', 'qty' => 2, 'unit_price' => 500, 'taxable' => 1, 'tax_rate' => 18],
                ['label' => 'Cable', 'qty' => 10, 'unit_price' => 20, 'taxable' => 0],
            ],
        ]))->assertRedirect();

        $quote = Quote::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame(1200.0, $quote->subtotal);      // 1000 + 200
        $this->assertSame(100.0, $quote->discount_amount);
        $this->assertSame(180.0, $quote->tax_amount);      // 18% of the taxable line only
        $this->assertSame(1280.0, $quote->amount);         // 1200 - 100 + 180
        $this->assertSame(2, $quote->items()->count());
    }

    public function test_a_discount_can_never_exceed_the_subtotal(): void
    {
        $this->post($this->url('/quotation'), $this->payload([
            'discount_amount' => 99999,
            'items' => [['label' => 'Installation', 'qty' => 1, 'unit_price' => 100, 'taxable' => 0]],
        ]))->assertRedirect();

        $quote = Quote::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame(100.0, $quote->discount_amount);
        $this->assertSame(0.0, $quote->amount);
    }

    public function test_blank_item_rows_are_dropped_rather_than_rejected(): void
    {
        $this->post($this->url('/quotation'), $this->payload([
            'items' => [
                ['label' => 'Real item', 'qty' => 1, 'unit_price' => 100],
                ['label' => '', 'qty' => 1, 'unit_price' => 0],
            ],
        ]))->assertRedirect();

        $this->assertSame(1, Quote::where('tenant_id', $this->tenant->id)->firstOrFail()->items()->count());
    }

    public function test_a_document_must_be_addressed_to_a_subscriber_or_a_named_prospect(): void
    {
        $this->post($this->url('/quotation'), $this->payload([
            'customer_name' => '',
            'subscriber_id' => null,
        ]))->assertSessionHasErrors('customer_name');

        $this->assertSame(0, Quote::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_editing_replaces_the_line_items(): void
    {
        $this->post($this->url('/quotation'), $this->payload())->assertRedirect();
        $quote = Quote::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->put($this->url('/quotation/' . $quote->id), $this->payload([
            'items' => [['label' => 'Replaced', 'qty' => 1, 'unit_price' => 250, 'taxable' => 0]],
        ]))->assertRedirect();

        $quote->refresh();
        $this->assertSame(1, $quote->items()->count());
        $this->assertSame('Replaced', $quote->items()->first()->label);
        $this->assertSame(250.0, $quote->amount);
    }

    public function test_converting_creates_an_invoice_and_freezes_the_document(): void
    {
        $subscriber = $this->subscriber();

        $this->post($this->url('/proforma'), $this->payload([
            'subscriber_id' => $subscriber->id,
        ]))->assertRedirect();

        $quote = Quote::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->post($this->url('/proforma/' . $quote->id . '/convert'))->assertRedirect();

        $quote->refresh();
        $invoice = Invoice::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame('converted', $quote->status);
        $this->assertSame($invoice->id, $quote->converted_invoice_id);
        $this->assertNotNull($quote->converted_at);
        $this->assertTrue($quote->isLocked());
        $this->assertFalse($quote->isConvertible());

        // The invoice mirrors the document and records its provenance.
        $this->assertSame($subscriber->id, $invoice->subscriber_id);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame(1180.0, $invoice->amount);   // 1000 + 180 tax
        $this->assertStringContainsString($quote->number, $invoice->notes);
        $this->assertSame(1, $invoice->items()->count());
    }

    public function test_a_discount_survives_conversion_as_its_own_negative_line(): void
    {
        $subscriber = $this->subscriber();

        $this->post($this->url('/quotation'), $this->payload([
            'subscriber_id'   => $subscriber->id,
            'discount_amount' => 100,
        ]))->assertRedirect();

        $quote = Quote::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->post($this->url('/quotation/' . $quote->id . '/convert'))->assertRedirect();

        $invoice = Invoice::where('tenant_id', $this->tenant->id)->firstOrFail();

        // 1000 - 100 discount + 180 tax
        $this->assertSame(900.0, $invoice->subtotal);
        $this->assertSame(1080.0, $invoice->amount);

        $discountLine = InvoiceItem::where('invoice_id', $invoice->id)->where('label', 'Discount')->firstOrFail();
        $this->assertSame(-100.0, $discountLine->line_total);

        // Items must reconcile with the header, or the printed invoice lies.
        $lineSum = InvoiceItem::where('invoice_id', $invoice->id)->sum('line_total');
        $this->assertSame(1080.0, round((float) $lineSum, 2));
    }

    public function test_a_document_cannot_be_converted_twice(): void
    {
        $subscriber = $this->subscriber();
        $this->post($this->url('/quotation'), $this->payload(['subscriber_id' => $subscriber->id]))->assertRedirect();

        $quote = Quote::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->post($this->url('/quotation/' . $quote->id . '/convert'))->assertRedirect();

        $this->post($this->url('/quotation/' . $quote->id . '/convert'))
            ->assertSessionHasErrors('quote');

        $this->assertSame(1, Invoice::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_a_prospect_document_cannot_be_converted_until_a_subscriber_exists(): void
    {
        $this->post($this->url('/quotation'), $this->payload())->assertRedirect();
        $quote = Quote::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->post($this->url('/quotation/' . $quote->id . '/convert'))
            ->assertSessionHasErrors('quote');

        $this->assertSame(0, Invoice::where('tenant_id', $this->tenant->id)->count());
        $this->assertNull($quote->refresh()->converted_invoice_id);
    }

    public function test_a_converted_document_can_no_longer_be_edited_or_deleted(): void
    {
        $subscriber = $this->subscriber();
        $this->post($this->url('/quotation'), $this->payload(['subscriber_id' => $subscriber->id]))->assertRedirect();

        $quote = Quote::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->post($this->url('/quotation/' . $quote->id . '/convert'))->assertRedirect();

        $this->get($this->url('/quotation/' . $quote->id . '/edit'))->assertSessionHasErrors('quote');
        $this->put($this->url('/quotation/' . $quote->id), $this->payload())->assertSessionHasErrors('quote');
        $this->delete($this->url('/quotation/' . $quote->id))->assertSessionHasErrors('quote');

        $this->assertSame(1, Quote::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_a_lapsed_quotation_is_derived_from_its_validity_date(): void
    {
        $quote = Quote::create([
            'tenant_id'   => $this->tenant->id,
            'type'        => Quote::TYPE_QUOTATION,
            'number'      => Quote::nextNumber($this->tenant->id, Quote::TYPE_QUOTATION),
            'status'      => 'sent',
            'customer_name' => 'Late Larry',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($quote->isExpired());

        // An accepted document is awaiting conversion, not lapsed.
        $quote->update(['status' => 'accepted']);
        $this->assertFalse($quote->fresh()->isExpired());
    }

    public function test_the_listing_is_scoped_to_its_type_and_tenant(): void
    {
        $this->post($this->url('/quotation'), $this->payload(['customer_name' => 'Only A Quotation']))->assertRedirect();
        $this->post($this->url('/proforma'), $this->payload(['customer_name' => 'Only A Proforma']))->assertRedirect();

        $this->get($this->url('/quotation'))
            ->assertOk()
            ->assertSee('Only A Quotation')
            ->assertDontSee('Only A Proforma');

        $other = Tenant::create([
            'name' => 'Other ISP', 'domain' => 'other-quotes.test', 'slug' => 'otherq', 'status' => 'active',
        ]);

        $this->get('http://' . $other->domain . '/quotation')
            ->assertOk()
            ->assertDontSee('Only A Quotation');
    }

    public function test_line_totals_are_computed_from_qty_price_and_tax(): void
    {
        $line = QuoteItem::computeLine(199.99, 3, true, 18);

        $this->assertSame(599.97, $line['amount']);
        $this->assertSame(107.99, $line['tax_amount']);
        $this->assertSame(707.96, $line['line_total']);

        // A non-taxable line carries no rate, even if one was submitted.
        $untaxed = QuoteItem::computeLine(100, 1, false, 18);
        $this->assertSame(0.0, $untaxed['tax_rate']);
        $this->assertSame(0.0, $untaxed['tax_amount']);
    }
}
