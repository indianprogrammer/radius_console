@extends('layout', ['title' => \App\Models\Quote::TYPES[$type] . 's'])
@section('content')
  @php
    $label = \App\Models\Quote::TYPES[$type];
    $isQuotation = $type === \App\Models\Quote::TYPE_QUOTATION;
  @endphp

  <div class="page-header">
    <h1>{{ $label }}s</h1>
    <p class="muted-label">
      {{ $isQuotation
          ? 'Priced offers sent to customers and prospects. Nothing is billed until a quotation is converted to an invoice.'
          : 'Advance / pre-payment requests. A proforma is not a receivable — convert it to an invoice once the customer commits.' }}
    </p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Open</span>
      <span class="sc-value">{{ $totals['open'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Open Value</span>
      <span class="sc-value">{{ number_format($totals['open_value'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Accepted</span>
      <span class="sc-value sc-ok">{{ $totals['accepted'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Converted</span>
      <span class="sc-value sc-ok">{{ $totals['converted'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Lapsed</span>
      <span class="sc-value {{ $totals['expiring'] ? 'sc-bad' : '' }}">{{ $totals['expiring'] }}</span>
    </div>
  </div>

  <a class="btn" href="{{ route('quotes.create', $type) }}">+ New {{ $label }}</a>

  <form class="search-form" method="get" action="{{ route('quotes.index', $type) }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search number or customer…">
    <select name="status">
      <option value="">All Statuses</option>
      @foreach (\App\Models\Quote::STATUSES as $val => $statusLabel)
        <option value="{{ $val }}" @selected(($status ?? '') === $val)>{{ $statusLabel }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn">Search</button>
    @if ($search || $status)
      <a href="{{ route('quotes.index', $type) }}" class="btn">Clear</a>
    @endif
  </form>

  <table>
    <thead>
      <tr>
        <th>Number</th>
        <th>Customer</th>
        <th>Issued</th>
        <th>Valid Until</th>
        <th class="num">Total</th>
        <th>Status</th>
        <th>Invoice</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($quotes as $q)
        <tr>
          <td><a href="{{ route('quotes.show', [$type, $q->id]) }}">{{ $q->number }}</a></td>
          <td>{{ $q->customerLabel() }}</td>
          <td>{{ $q->issue_date?->format('d/m/y') ?? '—' }}</td>
          <td>{{ $q->valid_until?->format('d/m/y') ?? '—' }}</td>
          <td class="num">{{ number_format($q->payableAmount(), 2) }}</td>
          <td>
            <span class="pill pill-{{ $q->statusPill() }}">{{ $q->statusLabel() }}</span>
            @if ($q->isExpired())<span class="pill pill-overdue">Lapsed</span>@endif
          </td>
          <td>
            @if ($q->invoice)
              <a href="{{ route('invoices.show', $q->invoice->id) }}">{{ $q->invoice->number }}</a>
            @else
              —
            @endif
          </td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('quotes.show', [$type, $q->id]) }}'">View</button>
            @unless ($q->isLocked())
              <button class="btn" onclick="window.location.href='{{ route('quotes.edit', [$type, $q->id]) }}'">Edit</button>
              <button class="btn danger" onclick="deleteQuote(event, '{{ route('quotes.destroy', [$type, $q->id]) }}')">Delete</button>
            @endunless
          </td>
        </tr>
      @empty
        <tr><td colspan="8">No {{ strtolower($label) }}s yet. Click <em>+ New {{ $label }}</em> to create one.</td></tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $quotes])
  @include('partials.per-page', ['paginator' => $quotes, 'action' => route('quotes.index', $type)])

  <script>
    function deleteQuote(event, url) {
      if (!confirm('Delete this document? Its line items will be removed.')) return;
      const row = event.currentTarget.closest('tr');
      fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json'
        }
      }).then(async (response) => {
        const body = await response.json().catch(() => ({}));
        if (response.ok) {
          if (row) row.remove();
          window.toast && window.toast(body.message || 'Deleted.', 'success');
        } else {
          window.toast && window.toast(body.message || 'Failed to delete.', 'error');
        }
      }).catch(() => window.toast && window.toast('Failed to delete.', 'error'));
    }
  </script>
@endsection
