@extends('layout', ['title' => 'Tickets'])
@section('content')
  <div class="page-header">
    <h1>Tickets</h1>
    <p class="muted-label">Helpdesk / work orders. Assign to one staff member, several, or a whole team — and reassign at any time.</p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Open</span>
      <span class="sc-value">{{ $totals['open'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Unassigned</span>
      <span class="sc-value sc-warn">{{ $totals['unassigned'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Urgent</span>
      <span class="sc-value sc-warn">{{ $totals['urgent'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Overdue (SLA)</span>
      <span class="sc-value sc-warn">{{ $totals['overdue'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Resolved</span>
      <span class="sc-value sc-ok">{{ $totals['resolved'] }}</span>
    </div>
  </div>

  <a class="btn" href="{{ route('tickets.create') }}">+ New Ticket</a>
  <a class="btn" href="{{ route('tickets.index', ['unassigned' => 1]) }}">Unassigned Queue</a>
  <a class="btn" href="{{ route('staff-groups.index') }}">Teams</a>

  <form class="search-form" method="get" action="{{ route('tickets.index') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search number, subject or contact…">
    <select name="status">
      <option value="">All Statuses</option>
      @foreach (\App\Models\Ticket::STATUSES as $val => $label)
        <option value="{{ $val }}" @selected(($status ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="priority">
      <option value="">All Priorities</option>
      @foreach (\App\Models\Ticket::PRIORITIES as $val => $label)
        <option value="{{ $val }}" @selected(($priority ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="category">
      <option value="">All Categories</option>
      @foreach (\App\Models\Ticket::CATEGORIES as $val => $label)
        <option value="{{ $val }}" @selected(($category ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="staff_id">
      <option value="">Any Staff</option>
      @foreach ($staff as $s)
        <option value="{{ $s->id }}" @selected((int) ($staffId ?? 0) === (int) $s->id)>{{ $s->name }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn">Search</button>
    @if ($search || $status || $priority || $category || $staffId || $unassigned)
      <a href="{{ route('tickets.index') }}" class="btn">Clear</a>
    @endif
  </form>

  <table>
    <thead>
      <tr>
        <th>Number</th>
        <th>Subject</th>
        <th>Customer</th>
        <th>Priority</th>
        <th>Owner</th>
        <th>Assignees</th>
        <th>Status</th>
        <th>Due</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($tickets as $t)
        <tr>
          <td><a href="{{ route('tickets.show', $t->id) }}">{{ $t->number }}</a></td>
          <td>
            {{ $t->subject }}
            <div class="muted-label">{{ $t->categoryLabel() }} · {{ $t->sourceLabel() }}</div>
          </td>
          <td>
            {{ $t->subscriber->username ?? ($t->contact_name ?: '—') }}
            @if ($t->contact_phone)<div class="muted-label">{{ $t->contact_phone }}</div>@endif
          </td>
          <td>
            <span class="pill pill-{{ in_array($t->priority, ['urgent', 'high']) ? 'overdue' : 'info' }}">{{ $t->priorityLabel() }}</span>
          </td>
          <td>{{ $t->owner->name ?? '— unassigned —' }}</td>
          <td>
            @if ($t->group)<div class="muted-label">Team: {{ $t->group->name }}</div>@endif
            {{ $t->assignees->count() }} staff
            @if ($t->assignees->isNotEmpty())
              <div class="muted-label">{{ $t->assignees->pluck('name')->take(3)->implode(', ') }}{{ $t->assignees->count() > 3 ? '…' : '' }}</div>
            @endif
          </td>
          <td><span class="pill pill-{{ $t->statusPill() }}">{{ $t->statusLabel() }}</span></td>
          <td>
            {{ $t->due_at?->format('d/m/y H:i') ?? '—' }}
            @if ($t->isOverdue())<span class="pill pill-overdue">Overdue</span>@endif
          </td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('tickets.show', $t->id) }}'">Open</button>
            <button class="btn" onclick="window.location.href='{{ route('tickets.edit', $t->id) }}'">Edit</button>
            <button class="btn danger" onclick="deleteTicket(event, '{{ route('tickets.destroy', $t->id) }}')">Delete</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="9">No tickets found. Click <em>+ New Ticket</em> to raise one.</td></tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $tickets])
  @include('partials.per-page', ['paginator' => $tickets, 'action' => route('tickets.index')])

  <script>
    function deleteTicket(event, url) {
      if (!confirm('Delete this ticket? Its activity trail is removed too.')) return;
      const row = event.currentTarget.closest('tr');
      fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json'
        }
      }).then(async response => {
        const body = await response.json().catch(() => ({}));
        if (response.ok) {
          if (row) row.remove();
          window.toast && window.toast(body.message || 'Ticket deleted.', 'success');
        } else {
          window.toast && window.toast(body.message || 'Failed to delete ticket.', 'error');
        }
      }).catch(() => window.toast && window.toast('Failed to delete ticket.', 'error'));
    }
  </script>
@endsection
