@extends('layout', ['title' => 'Ledger'])
@section('content')
  <div class="page-header">
    <h1>Ledger</h1>
    <p class="muted-label">
      Account statement built from invoices (debit) and payments (credit).
      A positive closing balance is money still owed to you.
    </p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Total Debit (Invoiced)</span>
      <span class="sc-value">{{ number_format($summary['debit'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Total Credit (Received)</span>
      <span class="sc-value sc-ok">{{ number_format($summary['credit'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Closing Balance</span>
      <span class="sc-value {{ $summary['closing'] > 0 ? 'sc-warn' : 'sc-ok' }}">{{ number_format($summary['closing'], 2) }}</span>
    </div>
  </div>

  <form class="search-form" method="get" action="{{ route('ledger.index') }}">
    <select name="subscriber_id">
      <option value="">All Subscribers</option>
      @foreach ($subscribers as $s)
        <option value="{{ $s->id }}" @selected((int) ($subscriberId ?? 0) === (int) $s->id)>
          {{ $s->username }}{{ trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')) ? ' — ' . trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')) : '' }}
        </option>
      @endforeach
    </select>
    <input type="date" name="from" value="{{ $from ?? '' }}" title="From date">
    <input type="date" name="to" value="{{ $to ?? '' }}" title="To date">
    <button type="submit" class="btn">Apply</button>
    @if ($subscriberId || $from || $to)
      <a href="{{ route('ledger.index') }}" class="btn">Clear</a>
    @endif
  </form>

  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Particulars</th>
        <th>Subscriber</th>
        <th>Reference</th>
        <th class="num">Debit</th>
        <th class="num">Credit</th>
        <th class="num">Balance</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($entries as $e)
        <tr>
          <td>{{ $e['at']?->format('d/m/y H:i') ?? '—' }}</td>
          <td>
            <span class="pill pill-{{ $e['type'] }}">{{ ucfirst($e['type']) }}</span>
            <a href="{{ $e['url'] }}">{{ $e['label'] }}</a>
          </td>
          <td>{{ $e['subscriber'] }}</td>
          <td>{{ $e['reference'] ?: '—' }}</td>
          <td class="num">{{ $e['debit'] ? number_format($e['debit'], 2) : '—' }}</td>
          <td class="num">{{ $e['credit'] ? number_format($e['credit'], 2) : '—' }}</td>
          <td class="num">{{ number_format($e['balance'], 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="7">No ledger entries for the selected filters.</td></tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $entries])
  @include('partials.per-page', ['paginator' => $entries, 'action' => route('ledger.index')])
@endsection
