@extends('layout', ['title' => 'Attendance Sheet'])
@section('content')
  <div class="page-header">
    <h1>{{ $member->name }} — {{ $month->format('F Y') }}</h1>
    <p class="muted-label">{{ $member->code }} · {{ $member->roleLabel() }} · monthly attendance and payroll basis.</p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Working Days</span>
      <span class="sc-value">{{ $summary['working_days'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Payable Days</span>
      <span class="sc-value sc-ok">{{ number_format($summary['payable_days'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Loss of Pay</span>
      <span class="sc-value sc-warn">{{ number_format($summary['working_days'] - $summary['payable_days'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Overtime (h)</span>
      <span class="sc-value">{{ number_format($summary['overtime_hours'], 2) }}</span>
    </div>
  </div>

  <form class="search-form" method="get" action="{{ route('attendance.sheet', $member->id) }}">
    <label for="month" class="muted-label">Month</label>
    <input type="month" name="month" id="month" value="{{ $month->format('Y-m') }}">
    <button type="submit" class="btn">Load</button>
    <a class="btn" href="{{ route('attendance.sheet', ['staff' => $member->id, 'month' => $month->copy()->subMonth()->format('Y-m')]) }}">‹ Prev</a>
    <a class="btn" href="{{ route('attendance.sheet', ['staff' => $member->id, 'month' => $month->copy()->addMonth()->format('Y-m')]) }}">Next ›</a>
  </form>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Daily Register</h4>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Date</th><th>Day</th><th>Status</th><th>In</th><th>Out</th>
              <th class="num">Hours</th><th class="num">OT</th><th class="num">Payable</th><th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            @for ($day = $start->copy(); $day->lte($end); $day->addDay())
              @php
                $row = $rows->get($day->toDateString());
                $implied = $day->isSunday() ? 'week_off' : 'present';
              @endphp
              <tr>
                <td>{{ $day->format('d/m/Y') }}</td>
                <td>{{ $day->format('D') }}</td>
                <td>
                  {{ $row ? $row->statusLabel() : \App\Models\Attendance::STATUSES[$implied] }}
                  @unless ($row)<span class="muted-label">(implied)</span>@endunless
                </td>
                <td>{{ $row?->check_in ?: '—' }}</td>
                <td>{{ $row?->check_out ?: '—' }}</td>
                <td class="num">{{ $row ? number_format($row->hours_worked, 2) : '—' }}</td>
                <td class="num">{{ $row ? number_format($row->overtime_hours, 2) : '—' }}</td>
                <td class="num">{{ number_format($row ? $row->payable_days : \App\Models\Attendance::weightFor($implied), 2) }}</td>
                <td>{{ $row?->remarks ?: '—' }}</td>
              </tr>
            @endfor
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <a class="btn" href="{{ route('staff.show', $member->id) }}">Back to Profile</a>
    <a class="btn" href="{{ route('attendance.index') }}">Attendance Register</a>
    <a class="btn" href="{{ route('payroll.create', ['month' => $month->toDateString()]) }}">Run Payroll</a>
  </div>
@endsection
