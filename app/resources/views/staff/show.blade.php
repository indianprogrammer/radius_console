@extends('layout', ['title' => $member->name])
@section('content')
  <div class="page-header">
    <h1>{{ $member->name }}</h1>
    <p class="muted-label">
      {{ $member->code }} · {{ $member->roleLabel() }}
      @if ($member->designation) · {{ $member->designation }} @endif
      · {{ $member->employmentTypeLabel() }}
      <span class="pill pill-{{ $member->status === 'active' ? 'paid' : (in_array($member->status, ['on_leave', 'suspended']) ? 'partial' : 'void') }}">
        {{ $member->statusLabel() }}
      </span>
    </p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Basic</span>
      <span class="sc-value">{{ number_format($member->basic_salary, 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Gross / Month</span>
      <span class="sc-value">{{ number_format($member->grossSalary(), 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Open Tickets</span>
      <span class="sc-value sc-warn">{{ $member->ownedTickets()->whereNotIn('status', \App\Models\Ticket::CLOSED_STATUSES)->count() }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Payslips</span>
      <span class="sc-value">{{ $member->payslips()->count() }}</span>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Profile</h4>
      <div class="table-wrap">
        <table class="data-table">
          <tbody>
            <tr><th>Department</th><td>{{ $member->department ?: '—' }}</td>
                <th>Franchise</th><td>{{ $member->franchise->name ?? 'Head Office' }}</td></tr>
            <tr><th>Reports To</th><td>{{ $member->manager->name ?? '—' }}</td>
                <th>Teams</th><td>{{ $member->groups->pluck('name')->implode(', ') ?: '—' }}</td></tr>
            <tr><th>Phone</th><td>{{ $member->phone ?: '—' }}</td>
                <th>Email</th><td>{{ $member->email ?: '—' }}</td></tr>
            <tr><th>Joined</th><td>{{ $member->date_of_joining?->format('d/m/Y') ?? '—' }}</td>
                <th>Left</th><td>{{ $member->date_of_leaving?->format('d/m/Y') ?? '—' }}</td></tr>
            <tr><th>PF %</th><td>{{ number_format($member->pf_percent, 2) }}</td>
                <th>ESI %</th><td>{{ number_format($member->esi_percent, 2) }}</td></tr>
            <tr><th>Professional Tax</th><td>{{ number_format($member->professional_tax, 2) }}</td>
                <th>Overtime / Hour</th><td>{{ number_format($member->overtime_rate_per_hour, 2) }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Recent Attendance</h4>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Date</th><th>Status</th><th>In</th><th>Out</th><th class="num">Hours</th><th class="num">OT</th><th class="num">Payable</th><th>Remarks</th></tr>
          </thead>
          <tbody>
            @forelse ($recent as $a)
              <tr>
                <td>{{ $a->work_date->format('d/m/Y') }}</td>
                <td>{{ $a->statusLabel() }}</td>
                <td>{{ $a->check_in ?: '—' }}</td>
                <td>{{ $a->check_out ?: '—' }}</td>
                <td class="num">{{ number_format($a->hours_worked, 2) }}</td>
                <td class="num">{{ number_format($a->overtime_hours, 2) }}</td>
                <td class="num">{{ number_format($a->payable_days, 2) }}</td>
                <td>{{ $a->remarks ?: '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="8">No attendance marked yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <p class="hint"><a href="{{ route('attendance.sheet', $member->id) }}">Open the monthly attendance sheet →</a></p>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Payslips</h4>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Number</th><th>Period</th><th class="num">Payable Days</th><th class="num">Gross</th><th class="num">Deductions</th><th class="num">Net Pay</th><th>Status</th></tr>
          </thead>
          <tbody>
            @forelse ($payslips as $p)
              <tr>
                <td><a href="{{ route('payroll.show', $p->id) }}">{{ $p->number }}</a></td>
                <td>{{ $p->periodLabel() }}</td>
                <td class="num">{{ number_format($p->payable_days, 2) }} / {{ $p->working_days }}</td>
                <td class="num">{{ number_format($p->gross_earnings, 2) }}</td>
                <td class="num">{{ number_format($p->total_deductions, 2) }}</td>
                <td class="num">{{ number_format($p->net_pay, 2) }}</td>
                <td><span class="pill pill-{{ $p->status === 'paid' ? 'paid' : ($p->status === 'cancelled' ? 'void' : 'unpaid') }}">{{ $p->statusLabel() }}</span></td>
              </tr>
            @empty
              <tr><td colspan="7">No payslips generated yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Assigned Tickets</h4>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Number</th><th>Subject</th><th>Priority</th><th>Status</th><th>Due</th></tr>
          </thead>
          <tbody>
            @forelse ($tickets as $t)
              <tr>
                <td><a href="{{ route('tickets.show', $t->id) }}">{{ $t->number }}</a></td>
                <td>{{ $t->subject }}</td>
                <td>{{ $t->priorityLabel() }}</td>
                <td><span class="pill pill-{{ $t->statusPill() }}">{{ $t->statusLabel() }}</span></td>
                <td>{{ $t->due_at?->format('d/m/y H:i') ?? '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="5">No tickets assigned.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <a class="btn" href="{{ route('staff.index') }}">Back to Staff</a>
    <a class="btn" href="{{ route('staff.edit', $member->id) }}">Edit</a>
    <a class="btn" href="{{ route('attendance.sheet', $member->id) }}">Attendance Sheet</a>
  </div>
@endsection
