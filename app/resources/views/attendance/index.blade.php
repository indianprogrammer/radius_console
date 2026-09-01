@extends('layout', ['title' => 'Attendance'])
@section('content')
  <div class="page-header">
    <h1>Attendance Register</h1>
    <p class="muted-label">
      Mark the whole day in one save. Staff left unmarked count as <em>Present</em> in payroll
      (Sundays as <em>Week Off</em>), so only exceptions need entering.
    </p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Headcount</span>
      <span class="sc-value">{{ $totals['headcount'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Marked</span>
      <span class="sc-value sc-ok">{{ $totals['marked'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Absent / Unpaid</span>
      <span class="sc-value sc-warn">{{ $totals['absent'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Paid Leave</span>
      <span class="sc-value">{{ $totals['leave'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Unmarked</span>
      <span class="sc-value">{{ $totals['unmarked'] }}</span>
    </div>
  </div>

  <form class="search-form" method="get" action="{{ route('attendance.index') }}">
    <label for="date_display" class="muted-label">Date</label>
    <input type="text" class="js-dmy" id="date_display" data-target="date" placeholder="dd/mm/yy">
    <input type="hidden" name="date" id="date" value="{{ $date->toDateString() }}">
    <button type="submit" class="btn">Load Day</button>
    <a class="btn" href="{{ route('attendance.index', ['date' => $date->copy()->subDay()->toDateString()]) }}">‹ Prev Day</a>
    <a class="btn" href="{{ route('attendance.index', ['date' => $date->copy()->addDay()->toDateString()]) }}">Next Day ›</a>
    <a class="btn" href="{{ route('attendance.index') }}">Today</a>
  </form>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('attendance.bulk-store') }}">
    @csrf
    <input type="hidden" name="date" value="{{ $date->toDateString() }}">

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">{{ $date->format('l, d/m/Y') }}</h4>

        <div class="form-actions" style="justify-content:flex-start">
          <button type="button" class="btn" onclick="markAll('present')">All Present</button>
          <button type="button" class="btn" onclick="markAll('week_off')">All Week Off</button>
          <button type="button" class="btn" onclick="markAll('holiday')">All Holiday</button>
        </div>

        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Status</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th class="num">Overtime (h)</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($staff as $i => $m)
                @php $row = $rows->get($m->id); @endphp
                <tr>
                  <td>
                    {{ $m->code }}
                    <input type="hidden" name="rows[{{ $i }}][staff_id]" value="{{ $m->id }}">
                  </td>
                  <td>
                    <a href="{{ route('staff.show', $m->id) }}">{{ $m->name }}</a>
                    @if ($m->designation)<div class="muted-label">{{ $m->designation }}</div>@endif
                  </td>
                  <td>
                    <select name="rows[{{ $i }}][status]" class="gui-input js-att-status">
                      @foreach (\App\Models\Attendance::STATUSES as $val => $label)
                        <option value="{{ $val }}"
                          @selected(($row->status ?? ($date->isSunday() ? 'week_off' : 'present')) === $val)>{{ $label }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <input type="time" name="rows[{{ $i }}][check_in]" class="gui-input"
                           value="{{ $row?->check_in ? substr($row->check_in, 0, 5) : '' }}">
                  </td>
                  <td>
                    <input type="time" name="rows[{{ $i }}][check_out]" class="gui-input"
                           value="{{ $row?->check_out ? substr($row->check_out, 0, 5) : '' }}">
                  </td>
                  <td class="num">
                    <input type="number" name="rows[{{ $i }}][overtime_hours]" class="gui-input"
                           step="0.25" min="0" max="24" value="{{ $row?->overtime_hours ?? 0 }}">
                  </td>
                  <td>
                    <input type="text" name="rows[{{ $i }}][remarks]" class="gui-input" maxlength="500"
                           value="{{ $row?->remarks }}" placeholder=" ">
                  </td>
                </tr>
              @empty
                <tr><td colspan="7">No assignable staff for this date. Add staff first.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    @if ($staff->isNotEmpty())
      <div class="form-actions">
        <a class="btn" href="{{ route('staff.index') }}">Back to Staff</a>
        <button class="btn" type="submit">Save Attendance</button>
      </div>
    @endif
  </form>

  @include('partials.dmy-date-script')

  <script>
    function markAll(status) {
      document.querySelectorAll('.js-att-status').forEach(sel => { sel.value = status; });
    }
  </script>
@endsection
