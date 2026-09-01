@extends('layout', ['title' => 'Invoice ' . $invoice->number])
@section('content')
  <div class="page-header">
    <h1>Invoice {{ $invoice->number }}</h1>
    <p class="muted-label">
      {{ $invoice->subscriber->username ?? '—' }} ·
      Issued {{ $invoice->created_at?->format('d/m/y') ?? '—' }} ·
      Due {{ $invoice->due_date?->format('d/m/y') ?? '—' }}
      <span class="pill pill-{{ $invoice->status }}">{{ \App\Models\Invoice::STATUSES[$invoice->status] ?? $invoice->status }}</span>
      @if ($invoice->isOverdue())<span class="pill pill-overdue">Overdue</span>@endif
    </p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Subtotal</span>
      <span class="sc-value">{{ number_format($invoice->subtotal ?? 0, 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Tax</span>
      <span class="sc-value">{{ number_format($invoice->tax_amount ?? 0, 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Total</span>
      <span class="sc-value">{{ number_format($invoice->payableAmount(), 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Paid</span>
      <span class="sc-value sc-ok">{{ number_format($invoice->paid_amount, 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Balance</span>
      <span class="sc-value {{ $invoice->balance() > 0 ? 'sc-warn' : 'sc-ok' }}">{{ number_format($invoice->balance(), 2) }}</span>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Line Items</h4>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Item</th>
              <th>Type</th>
              <th class="num">Qty</th>
              <th class="num">Unit Price</th>
              <th class="num">Amount</th>
              <th class="num">Tax</th>
              <th class="num">Line Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($invoice->items as $item)
              <tr>
                <td>
                  {{ $item->label }}
                  @if ($item->is_refundable)<span class="pill pill-info">Refundable</span>@endif
                  @if ($item->description)<div class="muted-label">{{ $item->description }}</div>@endif
                </td>
                <td>
                  {{ ucfirst(str_replace('-', ' ', $item->type)) }}
                  @if ($item->billing_cycle)<div class="muted-label">{{ ucfirst($item->billing_cycle) }}</div>@endif
                </td>
                <td class="num">{{ $item->qty }}</td>
                <td class="num">{{ number_format($item->unit_price, 2) }}</td>
                <td class="num">{{ number_format($item->amount, 2) }}</td>
                <td class="num">{{ $item->taxable ? number_format($item->tax_amount, 2) . ' (' . number_format($item->tax_rate, 2) . '%)' : '—' }}</td>
                <td class="num">{{ number_format($item->line_total, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="7">No line items on this invoice.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Payments</h4>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Receipt&nbsp;No.</th>
              <th>Date</th>
              <th>Method</th>
              <th>Reference</th>
              <th>Status</th>
              <th class="num">Amount</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($invoice->payments as $p)
              <tr>
                <td><a href="{{ route('payments.edit', $p->id) }}">{{ $p->number }}</a></td>
                <td>{{ $p->paid_at?->format('d/m/y H:i') ?? '—' }}</td>
                <td>{{ $p->methodLabel() }}</td>
                <td>{{ $p->reference ?: '—' }}</td>
                <td><span class="pill pill-{{ $p->status }}">{{ \App\Models\Payment::STATUSES[$p->status] ?? $p->status }}</span></td>
                <td class="num">{{ number_format($p->amount, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="6">No payments recorded against this invoice.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($invoice->notes)
        <p class="hint">{{ $invoice->notes }}</p>
      @endif
    </div>
  </div>

  <div class="form-actions">
    <a class="btn" href="{{ route('invoices.index') }}">Back to Invoices</a>
    <a class="btn" href="{{ route('invoices.edit', $invoice->id) }}">Edit</a>
    @if ($invoice->balance() > 0 && $invoice->status !== 'void')
      <a class="btn" href="{{ route('payments.create', ['invoice_id' => $invoice->id]) }}">Record Payment</a>
    @endif
  </div>
@endsection
