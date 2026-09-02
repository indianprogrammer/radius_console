@extends('layout', ['title' => 'Leads'])
@section('content')
  <div class="page-header">
    <h1>Leads</h1>
    <p class="muted-label">Sales pipeline. A lead is a prospect — it becomes a quotation, then a subscriber, once it is won.</p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Open Leads</span>
      <span class="sc-value">{{ $totals['open'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Follow-ups Due</span>
      <span class="sc-value {{ $totals['due'] > 0 ? 'sc-bad' : 'sc-ok' }}">{{ $totals['due'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Unassigned</span>
      <span class="sc-value sc-warn">{{ $totals['unassigned'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Open Pipeline</span>
      <span class="sc-value">{{ number_format($totals['pipeline'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Win Rate</span>
      <span class="sc-value sc-ok">{{ number_format($totals['win_rate'], 1) }}%</span>
    </div>
  </div>

  {{-- Stage counts. Clicking a stage filters the list below. --}}
  <div class="stat-cards">
    @foreach ($funnel as $stage => $count)
      <a class="stat-card" href="{{ route('leads.index', ['status' => $stage]) }}">
        <span class="sc-label">{{ \App\Models\Lead::STATUSES[$stage] }}</span>
        <span class="sc-value">{{ $count }}</span>
      </a>
    @endforeach
  </div>

  <a class="btn" href="{{ route('leads.create') }}">+ New Lead</a>
  <a class="btn" href="{{ route('leads.index', ['due' => 1]) }}">Follow-ups Due</a>
  <a class="btn" href="{{ route('leads.index', ['unassigned' => 1]) }}">Unassigned</a>

  <form class="search-form" method="get" action="{{ route('leads.index') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search number, name, company or phone…">
    <select name="status">
      <option value="">All Stages</option>
      @foreach (\App\Models\Lead::STATUSES as $val => $label)
        <option value="{{ $val }}" @selected(($status ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="rating">
      <option value="">Any Rating</option>
      @foreach (\App\Models\Lead::RATINGS as $val => $label)
        <option value="{{ $val }}" @selected(($rating ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="source">
      <option value="">Any Source</option>
      @foreach (\App\Models\Lead::SOURCES as $val => $label)
        <option value="{{ $val }}" @selected(($source ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="staff_id">
      <option value="">Any Owner</option>
      @foreach ($staff as $s)
        <option value="{{ $s->id }}" @selected((int) ($staffId ?? 0) === (int) $s->id)>{{ $s->name }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn">Search</button>
    @if ($search || $status || $rating || $source || $staffId || $unassigned || $due || $openOnly)
      <a href="{{ route('leads.index') }}" class="btn">Clear</a>
    @endif
  </form>

  <table>
    <thead>
      <tr>
        <th>Number</th>
        <th>Prospect</th>
        <th>Contact</th>
        <th>Source</th>
        <th>Rating</th>
        <th>Stage</th>
        <th>Owner</th>
        <th class="num">Value</th>
        <th>Follow-up</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($leads as $l)
        <tr>
          <td><a href="{{ route('leads.show', $l->id) }}">{{ $l->number }}</a></td>
          <td>
            {{ $l->name }}
            @if ($l->company)<div class="muted-label">{{ $l->company }}</div>@endif
          </td>
          <td>
            {{ $l->phone ?: '—' }}
            @if ($l->email)<div class="muted-label">{{ $l->email }}</div>@endif
          </td>
          <td>{{ $l->sourceLabel() }}</td>
          <td><span class="pill pill-{{ $l->ratingPill() }}">{{ $l->ratingLabel() }}</span></td>
          <td>
            <span class="pill pill-{{ $l->statusPill() }}">{{ $l->statusLabel() }}</span>
            @if ($l->quote)<div class="muted-label">{{ $l->quote->number }}</div>@endif
          </td>
          <td>{{ $l->owner->name ?? '— unassigned —' }}</td>
          <td class="num">{{ number_format($l->estimated_value, 2) }}</td>
          <td>
            {{ $l->next_follow_up_at?->format('d/m/y H:i') ?? '—' }}
            @if ($l->isFollowUpDue())<span class="pill pill-overdue">Due</span>@endif
          </td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('leads.show', $l->id) }}'">Open</button>
            <button class="btn" onclick="window.location.href='{{ route('leads.edit', $l->id) }}'">Edit</button>
            <button class="btn danger" onclick="deleteLead(event, '{{ route('leads.destroy', $l->id) }}')">Delete</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="10">No leads found. Click <em>+ New Lead</em> to capture a prospect.</td></tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $leads])
  @include('partials.per-page', ['paginator' => $leads, 'action' => route('leads.index')])

  <script>
    function deleteLead(event, url) {
      if (!confirm('Delete this lead? Its activity trail is removed too.')) return;
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
          window.toast && window.toast('Lead deleted.', 'success');
        } else {
          window.toast && window.toast('Failed to delete.', 'error');
        }
      }).catch(() => {
        window.toast && window.toast('Failed to delete.', 'error');
      });
    }
  </script>
@endsection