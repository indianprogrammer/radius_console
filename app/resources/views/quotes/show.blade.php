@extends('layout', ['title' => \App\Models\Quote::TYPES[$type] . ' ' . $quote->number])
@section('content')
  @php $label = \App\Models\Quote::TYPES[$type]; @endphp

  <div class="page-header">
    <h1>{{ $label }} {{ $quote->number }}</h1>
    <p class="muted-label">
      {{ $quote->customerLabel() }} ·
      Issued {{ $quote->issue_date?->format('d/m/y') ?? '—' }} ·
      Valid until {{ $quote->valid_until?->format('d/m/y') ?? '—' }}
      <span class="pill pill-{{ $quote->statusPill() }}">{{ $quote->statusLabel() }}</span>
      @if ($quote->isExpired())<span class="pill pill-overdue">Lapsed</span>@endif
    </p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  @if ($quote->invoice)
    <div class="alert alert-info">
      Converted to invoice
      <a href="{{ route('invoices.show', $quote->invoice->id) }}">{{ $quote->invoice->number }}</a>
      on {{ $quote->converted_at?->format('d/m/y H:i') }}. This document is now read-only.
    </div>
  @endif

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Subtotal</span>
      <span class="sc-value">{{ number_format($quote->subtotal, 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Discount</span>
      <span class="sc-value">{{ number_format($quote->discount_amount, 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Tax</span>
      <span class="sc-value">{{ number_format($quote->tax_amount, 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Total</span>
      <span class="sc-value">{{ number_format($quote->payableAmount(), 2) }}</span>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Customer</h4>
      <div class="table-wrap">
        <table class="data-table">
          <tbody>
            <tr>
              <th>Name</th><td>{{ $quote->customerLabel() }}</td>
              <th>Subscriber</th>
              <td>
                @if ($quote->subscriber)
                  {{ $quote->subscriber->username }}
                @else
                  <span class="muted-label">Prospect — not a subscriber yet</span>
                @endif
              </td>
            </tr>
            <tr>
              <th>Email</th><td>{{ $quote->customer_email ?: '—' }}</td>
              <th>Phone</th><td>{{ $quote->customer_phone ?: '—' }}</td>
            </tr>
            <tr>
              <th>GSTIN</th><td>{{ $quote->customer_gstin ?: '—' }}</td>
              <th>Address</th><td>{{ $quote->customer_address ?: '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
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
              <th class="num">Qty</th>
              <th class="num">Unit Price</th>
              <th class="num">Amount</th>
              <th class="num">Tax</th>
              <th class="num">Line Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($quote->items as $item)
              <tr>
                <td>
                  {{ $item->label }}
                  @if ($item->description)<div class="muted-label">{{ $item->description }}</div>@endif
                </td>
                <td class="num">{{ $item->qty }}</td>
                <td class="num">{{ number_format($item->unit_price, 2) }}</td>
                <td class="num">{{ number_format($item->amount, 2) }}</td>
                <td class="num">{{ $item->taxable ? number_format($item->tax_amount, 2) . ' (' . number_format($item->tax_rate, 2) . '%)' : '—' }}</td>
                <td class="num">{{ number_format($item->line_total, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="6">No line items on this document.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if ($quote->notes)
        <p class="hint"><strong>Notes:</strong> {{ $quote->notes }}</p>
      @endif
      @if ($quote->terms)
        <p class="hint"><strong>Terms:</strong> {{ $quote->terms }}</p>
      @endif
    </div>
  </div>

  <div class="form-actions">
    <a class="btn" href="{{ route('quotes.index', $type) }}">Back to {{ $label }}s</a>

    @unless ($quote->isLocked())
      <a class="btn" href="{{ route('quotes.edit', [$type, $quote->id]) }}">Edit</a>

      @if ($quote->isConvertible())
        {{-- The only action that creates a receivable, so it is an explicit POST. --}}
        <form method="POST" action="{{ route('quotes.convert', [$type, $quote->id]) }}"
              onsubmit="return confirm('Convert {{ $quote->number }} to an invoice? This document will become read-only.');">
          @csrf
          <button class="btn btn-primary" type="submit">Convert to Invoice</button>
        </form>
      @endif
    @endunless
  </div>
@endsection
