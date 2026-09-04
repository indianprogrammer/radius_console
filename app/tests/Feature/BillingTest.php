<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscriber;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Billing & Invoices: invoice generation, payment recording and the derived
 * invoice status / ledger figures.
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Billing ISP', 'domain' => 'billing.test', 'slug' => 'billing', 'status' => 'active',
        ]);

        $tax = TaxRate::create([
            'tenant_id' => $this->tenant->id, 'name' => 'GST', 'rate' => 18.00, 'type' => 'percentage',
        ]);

        $plan = Plan::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Home 50', 'price' => 599,
            'duration' => 1, 'duration_unit' => 'months',
        ]);
        // Pivot rows carry tenant_id (RLS isolation), so stamp it on attach.
        $plan->taxes()->attach($tax->id, ['tenant_id' => $this->tenant->id]);

        // Invoices are derived from the subscriber's PLAN (the per-subscriber
        // `billing_items` repeater was removed), so the plan price + its tax
        // pivot are the only inputs the figures below depend on.
        $this->subscriber = Subscriber::create([
            'tenant_id'       => $this->tenant->id,
            'username'        => 'billinguser',
            'radius_username' => 'billing_billinguser',
            'password_enc'    => 'enc',
            'plan_id'         => $plan->id,
            'status'          => 'active',
        ]);
    }

    /**
     * Plan 599 @18% GST: subtotal 599, tax 107.82, precise amount 706.82,
     * persisted `total` 707 (Settings > Billing rounds the payable up).
     */
    private const SUBTOTAL = 599.0;
    private const TAX      = 107.82;
    private const PAYABLE  = 707.0;

    private function url(string $path): string
    {
        // ResolveTenant keys off the request Host, so a FULL url is required.
        return 'http://' . $this->tenant->domain . '/' . ltrim($path, '/');
    }

    public function test_invoice_is_generated_from_the_subscriber_plan_with_plan_tax(): void
    {
        $invoice = app(InvoiceService::class)->generateFromSubscriber($this->subscriber);

        // One line item: the plan itself.
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('Home 50', $invoice->items()->first()->label);

        $this->assertSame(self::SUBTOTAL, $invoice->subtotal);
        $this->assertSame(self::TAX, $invoice->tax_amount);
        $this->assertSame(self::PAYABLE, $invoice->total);
    }

    public function test_recording_a_payment_moves_the_invoice_to_partial_then_paid(): void
    {
        $invoice = app(InvoiceService::class)->generateFromSubscriber($this->subscriber);

        $this->post($this->url('/payments'), [
            'invoice_id' => $invoice->id,
            'amount'     => 680,
            'method'     => 'upi',
            'reference'  => 'UTR-1',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame(680.0, $invoice->paid_amount);
        $this->assertSame(round(self::PAYABLE - 680, 2), $invoice->balance());

        $this->post($this->url('/payments'), [
            'invoice_id' => $invoice->id,
            'amount'     => self::PAYABLE - 680,
            'method'     => 'cash',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(0.0, $invoice->balance());
    }

    public function test_pending_payments_do_not_reduce_the_invoice_balance(): void
    {
        $invoice = app(InvoiceService::class)->generateFromSubscriber($this->subscriber);

        $this->post($this->url('/payments'), [
            'invoice_id' => $invoice->id,
            'amount'     => self::PAYABLE,
            'method'     => 'cheque',
            'status'     => 'pending',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame(0.0, $invoice->paid_amount);
    }

    public function test_deleting_a_payment_restores_the_invoice_balance(): void
    {
        $invoice = app(InvoiceService::class)->generateFromSubscriber($this->subscriber);

        $this->post($this->url('/payments'), [
            'invoice_id' => $invoice->id,
            'amount'     => self::PAYABLE,
            'method'     => 'cash',
        ])->assertRedirect();

        $this->assertSame('paid', $invoice->refresh()->status);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->delete($this->url('/payments/' . $payment->id))->assertRedirect();

        $invoice->refresh();
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame(0.0, $invoice->paid_amount);
    }

    public function test_payment_always_takes_the_subscriber_from_the_selected_invoice(): void
    {
        $invoice = app(InvoiceService::class)->generateFromSubscriber($this->subscriber);

        $other = Subscriber::create([
            'tenant_id' => $this->tenant->id, 'username' => 'other',
            'radius_username' => 'billing_other', 'password_enc' => 'enc', 'status' => 'active',
        ]);

        $this->post($this->url('/payments'), [
            'invoice_id'    => $invoice->id,
            'subscriber_id' => $other->id, // must be ignored
            'amount'        => 100,
            'method'        => 'cash',
        ])->assertRedirect();

        $this->assertSame(
            (int) $this->subscriber->id,
            (int) Payment::where('invoice_id', $invoice->id)->firstOrFail()->subscriber_id
        );
    }

    public function test_ledger_closing_balance_equals_billed_minus_collected(): void
    {
        $invoice = app(InvoiceService::class)->generateFromSubscriber($this->subscriber);

        $this->post($this->url('/payments'), [
            'invoice_id' => $invoice->id,
            'amount'     => 680,
            'method'     => 'cash',
        ])->assertRedirect();

        $this->get($this->url('/ledger'))
            ->assertOk()
            ->assertViewHas('summary', fn (array $s) => $s['debit'] === self::PAYABLE
                && $s['credit'] === 680.0
                && $s['closing'] === round(self::PAYABLE - 680, 2));
    }

    public function test_invoices_are_scoped_to_the_current_tenant(): void
    {
        app(InvoiceService::class)->generateFromSubscriber($this->subscriber);

        $otherTenant = Tenant::create([
            'name' => 'Other ISP', 'domain' => 'other-billing.test', 'slug' => 'otherbilling', 'status' => 'active',
        ]);

        $this->get('http://' . $otherTenant->domain . '/invoices')
            ->assertOk()
            ->assertViewHas('invoices', fn ($paginator) => $paginator->total() === 0);

        $this->assertSame(1, Invoice::where('tenant_id', $this->tenant->id)->count());
    }
}
