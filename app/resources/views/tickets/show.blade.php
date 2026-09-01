@extends('layout', ['title' => $ticket->number])
@section('content')
  <div class="page-header">
    <h1>{{ $ticket->number }} — {{ $ticket->subject }}</h1>
    <p class="muted-label">
      {{ $ticket->categoryLabel() }} · {{ $ticket->sourceLabel() }} ·
      raised {{ $ticket->created_at?->format('d/m/y H:i') ?? '—' }}
      <span class="pill pill-{{ $ticket->statusPill() }}">{{ $ticket->statusLabel() }}</span>
      <span class="pill pill-{{ in_array($ticket->priority, ['urgent', 'high']) ? 'overdue' : 'info' }}">{{ $ticket->priorityLabel() }}</span>
      @if ($ticket->isOverdue())<span class="pill pill-overdue">Overdue</span>@endif
    </p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Details</h4>
      <div class="table-wrap">
        <table class="data-table">
          <tbody>
            <tr><th>Owner</th><td>{{ $ticket->owner->name ?? '— unassigned —' }}</td>
                <th>Team</th><td>{{ $ticket->group->name ?? '—' }}</td></tr>
            <tr><th>Subscriber</th><td>{{ $ticket->subscriber->username ?? '—' }}</td>
                <th>Franchise</th><td>{{ $ticket->franchise->name ?? 'Head Office' }}</td></tr>
            <tr><th>Contact</th><td>{{ $ticket->contact_name ?: '—' }}{{ $ticket->contact_phone ? ' · ' . $ticket->contact_phone : '' }}</td>
                <th>Assigned At</th><td>{{ $ticket->assigned_at?->format('d/m/y H:i') ?? '—' }}</td></tr>
            <tr><th>Due At</th><td>{{ $ticket->due_at?->format('d/m/y H:i') ?? '—' }}</td>
                <th>Resolved At</th><td>{{ $ticket->resolved_at?->format('d/m/y H:i') ?? '—' }}</td></tr>
            <tr><th>Site Address</th><td colspan="3">{{ $ticket->address ?: '—' }}</td></tr>
            <tr><th>Description</th><td colspan="3">{{ $ticket->description ?: '—' }}</td></tr>
            @if ($ticket->resolution)
              <tr><th>Resolution</th><td colspan="3">{{ $ticket->resolution }}</td></tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Assignees <span class="muted-label">({{ $ticket->assignees->count() }})</span></h4>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Code</th><th>Name</th><th>Designation</th><th>Role</th><th>Since</th><th>Actions</th></tr>
          </thead>
          <tbody>
            @forelse ($ticket->assignees as $a)
              <tr>
                <td>{{ $a->code }}</td>
                <td>
                  <a href="{{ route('staff.show', $a->id) }}">{{ $a->name }}</a>
                  @if ($a->pivot->is_primary)<span class="pill pill-paid">Owner</span>@endif
                </td>
                <td>{{ $a->designation ?: '—' }}</td>
                <td>{{ $a->roleLabel() }}</td>
                <td>{{ $a->pivot->assigned_at ? \Illuminate\Support\Carbon::parse($a->pivot->assigned_at)->format('d/m/y H:i') : '—' }}</td>
                <td>
                  @if ($a->pivot->is_primary)
                    <span class="muted-label">Reassign to change</span>
                  @else
                    <form method="POST" action="{{ route('tickets.assignees.destroy', ['ticket' => $ticket->id, 'staff' => $a->id]) }}" style="display:inline">
                      @csrf
                      @method('DELETE')
                      <button class="btn danger" type="submit" onclick="return confirm('Remove this assignee?')">Remove</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6">Nobody is assigned yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Assign / Add Staff</h4>
      <form method="POST" action="{{ route('tickets.assign', $ticket->id) }}">
        @csrf
        <div class="form-grid">

          <div class="field col-4">
            <label for="assign_owner">Owner</label>
            <select name="assigned_staff_id" id="assign_owner" class="gui-input">
              <option value="">— unassign —</option>
              @foreach ($staff as $s)
                <option value="{{ $s->id }}" @selected((int) $ticket->assigned_staff_id === (int) $s->id)>
                  {{ $s->code }} — {{ $s->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="field col-4">
            <label for="assign_group">Team</label>
            <select name="staff_group_id" id="assign_group" class="gui-input">
              <option value="">— no team —</option>
              @foreach ($groups as $g)
                <option value="{{ $g->id }}" @selected((int) $ticket->staff_group_id === (int) $g->id)>
                  {{ $g->name }} ({{ $g->members_count }} members)
                </option>
              @endforeach
            </select>
            <span class="hint">Expanded into assignees on save.</span>
          </div>

          <div class="field col-4">
            <label for="assign_multi">Assignees <span class="muted-label">(multi)</span></label>
            <select name="assignee_ids[]" id="assign_multi" class="gui-input" multiple size="6">
              @foreach ($staff as $s)
                <option value="{{ $s->id }}" @selected($ticket->assignees->contains($s->id))>
                  {{ $s->code }} — {{ $s->name }}
                </option>
              @endforeach
            </select>
            <span class="hint">Ctrl / Cmd click for several. Unselected staff are removed.</span>
          </div>

          <div class="field col-12">
            <label for="assign_note">Note</label>
            <input type="text" name="note" id="assign_note" class="gui-input" maxlength="1000" placeholder=" ">
            <span class="hint">Recorded on the activity trail.</span>
          </div>

        </div>
        <div class="form-actions">
          <button class="btn" type="submit">Save Assignment</button>
        </div>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Reassign to Another Staff</h4>
      <p class="hint">Hands ownership to someone else and keeps the existing collaborators. The change is logged from → to.</p>
      <form method="POST" action="{{ route('tickets.reassign', $ticket->id) }}">
        @csrf
        <div class="form-grid">

          <div class="field col-5">
            <label for="reassign_to">New Owner <em>*</em></label>
            <select name="assigned_staff_id" id="reassign_to" class="gui-input" required>
              <option value="">— select staff —</option>
              @foreach ($staff as $s)
                @continue((int) $ticket->assigned_staff_id === (int) $s->id)
                <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}{{ $s->designation ? ' (' . $s->designation . ')' : '' }}</option>
              @endforeach
            </select>
          </div>

          <div class="field col-7">
            <label for="reassign_note">Reason / Note</label>
            <input type="text" name="note" id="reassign_note" class="gui-input" maxlength="1000" placeholder=" ">
          </div>

        </div>
        <div class="form-actions">
          <button class="btn" type="submit">Reassign Ticket</button>
        </div>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Activity</h4>
      <form method="POST" action="{{ route('tickets.comment', $ticket->id) }}">
        @csrf
        <div class="form-grid">
          <div class="field col-12">
            <label for="note">Add Comment</label>
            <textarea name="note" id="note" class="gui-input" rows="2" maxlength="2000" required placeholder=" "></textarea>
          </div>
        </div>
        <div class="form-actions">
          <button class="btn" type="submit">Add Comment</button>
        </div>
      </form>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>When</th><th>Event</th><th>Detail</th><th>Note</th><th>By</th></tr>
          </thead>
          <tbody>
            @forelse ($ticket->events as $e)
              <tr>
                <td>{{ $e->created_at?->format('d/m/y H:i') ?? '—' }}</td>
                <td>{{ $e->typeLabel() }}</td>
                <td>{{ $e->summary() }}</td>
                <td>{{ $e->note ?: '—' }}</td>
                <td>{{ $e->actor ?: 'system' }}</td>
              </tr>
            @empty
              <tr><td colspan="5">No activity recorded.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <a class="btn" href="{{ route('tickets.index') }}">Back to Tickets</a>
    <a class="btn" href="{{ route('tickets.edit', $ticket->id) }}">Edit Ticket</a>
  </div>
@endsection
