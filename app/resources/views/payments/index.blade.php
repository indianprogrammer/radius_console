@extends('layout', ['title' => 'Payments'])
@section('content')
  <div class="page-header">
    <h1>Payments</h1>
    <p class="muted-label">Receipts against invoices. Saving a payment re-derives the invoice status automatically.</p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Total Collected</span>
      <span class="sc-value sc-ok">{{ number_format($collected, 2) }}</span>
    </div>
  </div>

  <a class="btn" href="{{ route('payments.create') }}">+ Record Payment</a>

  <form class="search-form" method="get" action="{{ route('payments.index') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search receipt no., reference or subscriber…">
    <select name="method">
      <option value="">All Methods</option>
      @foreach (\App\Models\Payment::METHODS as $val => $label)
        <option value="{{ $val }}" @selected(($method ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="status">
      <option value="">All Statuses</option>
      @foreach (\App\Models\Payment::STATUSES as $val => $label)
        <option value="{{ $val }}" @selected(($status ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn">Search</button>
    @if ($search || $method || $status)
      <a href="{{ route('payments.index') }}" class="btn">Clear</a>
    @endif
  </form>

  <table>
    <thead>
      <tr>
        <th>Receipt&nbsp;No.</th>
        <th>Date</th>
        <th>Subscriber</th>
        <th>Invoice</th>
        <th>Method</th>
        <th>Reference</th>
        <th class="num">Amount</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($payments as $p)
        <tr>
          <td>{{ $p->number }}</td>
          <td>{{ $p->paid_at?->format('d/m/y H:i') ?? '—' }}</td>
          <td>{{ $p->subscriber->username ?? '—' }}</td>
          <td>
            @if ($p->invoice)
              <a href="{{ route('invoices.show', $p->invoice->id) }}">{{ $p->invoice->number }}</a>
            @else
              <span class="muted-label">On account</span>
            @endif
          </td>
          <td>{{ $p->methodLabel() }}</td>
          <td>{{ $p->reference ?: '—' }}</td>
          <td class="num">{{ number_format($p->amount, 2) }}</td>
          <td><span class="pill pill-{{ $p->status }}">{{ \App\Models\Payment::STATUSES[$p->status] ?? $p->status }}</span></td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('payments.edit', $p->id) }}'">Edit</button>
            <button class="btn danger" onclick="deletePayment(event, '{{ route('payments.destroy', $p->id) }}')">Delete</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="9">No payments recorded yet. Click <em>+ Record Payment</em> to add one.</td></tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $payments])
  @include('partials.per-page', ['paginator' => $payments, 'action' => route('payments.index')])

  <script>
    function deletePayment(event, url) {
      if (!confirm('Delete this payment? The linked invoice balance will be recalculated.')) return;
      const row = event.currentTarget.closest('tr');
      fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json'
        }
      }).then(response => {
        if (response.ok) {
          if (row) row.remove();
          window.toast && window.toast('Payment deleted.', 'success');
        } else {
          window.toast && window.toast('Failed to delete payment.', 'error');
        }
      }).catch(() => window.toast && window.toast('Failed to delete payment.', 'error'));
    }
  </script>
@endsection
