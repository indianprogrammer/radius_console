@extends('layout', ['title' => $lead->number])
@section('content')
  <div class="page-header">
    <h1>{{ $lead->number }} — {{ $lead->displayName() }}</h1>
    <p class="muted-label">
      {{ $lead->sourceLabel() }} · captured {{ $lead->created_at?->format('d/m/y H:i') ?? '—' }}
      <span class="pill pill-{{ $lead->statusPill() }}">{{ $lead->statusLabel() }}</span>
      <span class="pill pill-{{ $lead->ratingPill() }}">{{ $lead->ratingLabel() }}</span>
      @if ($lead->isFollowUpDue())<span class="pill pill-overdue">Follow-up due</span>@endif
    </p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <a class="btn" href="{{ route('leads.edit', $lead->id) }}">Edit Lead</a>
  <a class="btn" href="{{ route('leads.board') }}">Pipeline Board</a>
  <a class="btn" href="{{ route('leads.index') }}">Back to Leads</a>
  @if ($lead->quote)
    <a class="btn" href="{{ route('quotes.show', ['quotation', $lead->quote_id]) }}">View Quotation {{ $lead->quote->number }}</a>
  @endif

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Prospect</h4>
      <div class="table-wrap">
        <table class="data-table">
          <tbody>
            <tr><th>Contact</th><td>{{ $lead->name }}</td>
                <th>Company</th><td>{{ $lead->company ?: '—' }}</td></tr>
            <tr><th>Phone</th><td>{{ $lead->phone ?: '—' }}{{ $lead->alternate_phone ? ' · ' . $lead->alternate_phone : '' }}</td>
                <th>Email</th><td>{{ $lead->email ?: '—' }}</td></tr>
            <tr><th>Owner</th><td>{{ $lead->owner->name ?? '— unassigned —' }}</td>
                <th>Franchise</th><td>{{ $lead->franchise->name ?? 'Head Office' }}</td></tr>
            <tr><th>Interested Plan</th><td>{{ $lead->plan->name ?? '— not decided —' }}</td>
                <th>Estimated Value</th><td>{{ number_format($lead->estimated_value, 2) }}</td></tr>
            <tr><th>Last Contacted</th><td>{{ $lead->last_contacted_at?->format('d/m/y H:i') ?? 'never' }}</td>
                <th>Next Follow-up</th><td>{{ $lead->next_follow_up_at?->format('d/m/y H:i') ?? '—' }}</td></tr>
            <tr><th>Quotation</th>
                <td>{{ $lead->quote->number ?? '— none raised —' }}</td>
                <th>Subscriber</th><td>{{ $lead->subscriber->username ?? '— not onboarded —' }}</td></tr>
            @if ($lead->won_at)
              <tr><th>Won At</th><td colspan="3">{{ $lead->won_at->format('d/m/y H:i') }}</td></tr>
            @endif
            @if ($lead->lost_at)
              <tr><th>Lost At</th><td>{{ $lead->lost_at->format('d/m/y H:i') }}</td>
                  <th>Reason</th><td>{{ $lead->lost_reason ?: '—' }}</td></tr>
            @endif
            <tr><th>Address</th><td colspan="3">{{ trim(($lead->address ?: '') . ' ' . ($lead->city ?: '') . ' ' . ($lead->state ?: '') . ' ' . ($lead->pincode ?: '')) ?: '—' }}</td></tr>
            <tr><th>Notes</th><td colspan="3">{{ $lead->notes ?: '—' }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @if ($lead->isOpen())
    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Log Activity</h4>
        <p class="hint">A call, email, meeting or site visit also refreshes "last contacted" and moves a new lead to Contacted.</p>
        <form method="POST" action="{{ route('leads.activity', $lead->id) }}">
          @csrf
          <div class="form-grid">
            <div class="field col-3">
              <label for="activity_type">Type <em>*</em></label>
              <select name="type" id="activity_type" class="gui-input" required>
                @foreach (['call', 'email', 'meeting', 'visit', 'note'] as $t)
                  <option value="{{ $t }}">{{ \App\Models\LeadActivity::TYPES[$t] }}</option>
                @endforeach
              </select>
            </div>
            <div class="field col-9">
              <label for="activity_note">Note</label>
              <input type="text" name="note" id="activity_note" class="gui-input" maxlength="2000" placeholder=" ">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn" type="submit">Record Activity</button>
          </div>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Schedule Follow-up</h4>
        <form method="POST" action="{{ route('leads.follow-up', $lead->id) }}">
          @csrf
          <div class="form-grid">
            <div class="field col-4">
              <label for="follow_display">When <em>*</em></label>
              <input type="text" id="follow_display" class="gui-input js-dmy"
                     data-target="follow_at" data-with-time data-default-time="10:00"
                     inputmode="numeric" maxlength="14" autocomplete="off" placeholder=" ">
              <input type="hidden" name="next_follow_up_at" id="follow_at" required>
              <span class="hint">dd/mm/yy hh:ii</span>
            </div>
            <div class="field col-8">
              <label for="follow_note">Note</label>
              <input type="text" name="note" id="follow_note" class="gui-input" maxlength="1000" placeholder=" ">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn" type="submit">Schedule</button>
          </div>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Close the Deal</h4>

        @if (!$lead->quote_id)
          <p class="hint">Raise a quotation seeded from the interested plan, then edit its line items and send it. This moves the lead to Proposal Sent.</p>
          <form method="POST" action="{{ route('leads.quote', $lead->id) }}">
            @csrf
            <div class="form-actions">
              <button class="btn" type="submit">Raise Quotation</button>
            </div>
          </form>
        @endif

        <form method="POST" action="{{ route('leads.win', $lead->id) }}">
          @csrf
          <div class="form-grid">
            <div class="field col-5">
              <label for="win_subscriber">Linked Subscriber</label>
              <select name="subscriber_id" id="win_subscriber" class="gui-input">
                <option value="">— link later —</option>
                @foreach ($subscribers as $s)
                  <option value="{{ $s->id }}" @selected((int) $lead->subscriber_id === (int) $s->id)>{{ $s->username }}</option>
                @endforeach
              </select>
            </div>
            <div class="field col-7">
              <label for="win_note">Note</label>
              <input type="text" name="note" id="win_note" class="gui-input" maxlength="1000" placeholder=" ">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn" type="submit">Mark as Won</button>
          </div>
        </form>

        <form method="POST" action="{{ route('leads.lose', $lead->id) }}">
          @csrf
          <div class="form-grid">
            <div class="field col-12">
              <label for="lost_reason">Lost Reason <em>*</em></label>
              <input type="text" name="lost_reason" id="lost_reason" class="gui-input" maxlength="200" required placeholder=" ">
              <span class="hint">Recorded for funnel analysis, e.g. "price", "no coverage", "chose competitor".</span>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn danger" type="submit" onclick="return confirm('Mark this lead as lost?')">Mark as Lost</button>
          </div>
        </form>
      </div>
    </div>
  @else
    <div class="alert alert-info">
      This lead is {{ $lead->statusLabel() }}. Reopen it from <a href="{{ route('leads.edit', $lead->id) }}">Edit Lead</a> to work it again.
    </div>
  @endif

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Activity Trail</h4>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>When</th><th>Event</th><th>Detail</th><th>Note</th><th>By</th></tr>
          </thead>
          <tbody>
            @forelse ($lead->activities as $a)
              <tr>
                <td>{{ $a->created_at?->format('d/m/y H:i') ?? '—' }}</td>
                <td>{{ $a->typeLabel() }}</td>
                <td>{{ $a->summary() }}</td>
                <td>{{ $a->note ?: '—' }}</td>
                <td>{{ $a->actor ?: 'system' }}</td>
              </tr>
            @empty
              <tr><td colspan="5">No activity recorded yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @include('partials.dmy-date-script')
@endsection