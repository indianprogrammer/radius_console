@extends('layout', ['title' => 'Invoices'])
@section('content')
  <div class="page-header">
    <h1>Invoices</h1>
    <p class="muted-label">Generated from each subscriber&rsquo;s billing items. Record receipts against them under Payments.</p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Billed</span>
      <span class="sc-value">{{ number_format($totals['billed'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Collected</span>
      <span class="sc-value sc-ok">{{ number_format($totals['collected'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Outstanding</span>
      <span class="sc-value sc-warn">{{ number_format($totals['outstanding'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Overdue Invoices</span>
      <span class="sc-value {{ $totals['overdue'] ? 'sc-bad' : '' }}">{{ $totals['overdue'] }}</span>
    </div>
  </div>

  <a class="btn" href="{{ route('invoices.create') }}">+ Generate Invoice</a>

  <form class="search-form" method="get" action="{{ route('invoices.index') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search invoice no. or subscriber…">
    <select name="status">
      <option value="">All Statuses</option>
      @foreach (\App\Models\Invoice::STATUSES as $val => $label)
        <option value="{{ $val }}" @selected(($status ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn">Search</button>
    @if ($search || $status)
      <a href="{{ route('invoices.index') }}" class="btn">Clear</a>
    @endif
  </form>

  <table>
    <thead>
      <tr>
        <th>Invoice&nbsp;No.</th>
        <th>Subscriber</th>
        <th>Issued</th>
        <th>Due</th>
        <th class="num">Total</th>
        <th class="num">Paid</th>
        <th class="num">Balance</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($invoices as $inv)
        <tr>
          <td><a href="{{ route('invoices.show', $inv->id) }}">{{ $inv->number }}</a></td>
          <td>{{ $inv->subscriber->username ?? '—' }}</td>
          <td>{{ $inv->created_at?->format('d/m/y') ?? '—' }}</td>
          <td>{{ $inv->due_date?->format('d/m/y') ?? '—' }}</td>
          <td class="num">{{ number_format($inv->payableAmount(), 2) }}</td>
          <td class="num">{{ number_format($inv->paid_amount, 2) }}</td>
          <td class="num">{{ number_format($inv->balance(), 2) }}</td>
          <td>
            <span class="pill pill-{{ $inv->status }}">{{ \App\Models\Invoice::STATUSES[$inv->status] ?? $inv->status }}</span>
            @if ($inv->isOverdue())
              <span class="pill pill-overdue">Overdue</span>
            @endif
          </td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('invoices.show', $inv->id) }}'">View</button>
            @if ($inv->balance() > 0 && $inv->status !== 'void')
              <button class="btn" onclick="window.location.href='{{ route('payments.create', ['invoice_id' => $inv->id]) }}'">Pay</button>
            @endif
            <button class="btn danger" onclick="deleteInvoice(event, '{{ route('invoices.destroy', $inv->id) }}')">Delete</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="9">No invoices yet. Click <em>+ Generate Invoice</em> to create one from a subscriber&rsquo;s billing items.</td></tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $invoices])
  @include('partials.per-page', ['paginator' => $invoices, 'action' => route('invoices.index')])

  <script>
    function deleteInvoice(event, url) {
      if (!confirm('Delete this invoice? Its line items and linked payments references will be removed.')) return;
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
          window.toast && window.toast('Invoice deleted.', 'success');
        } else {
          window.toast && window.toast('Failed to delete invoice.', 'error');
        }
      }).catch(() => window.toast && window.toast('Failed to delete invoice.', 'error'));
    }
  </script>
@endsection
