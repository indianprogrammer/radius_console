@extends('layout', ['title' => 'Payroll'])
@section('content')
  <div class="page-header">
    <h1>Payroll — {{ $month->format('F Y') }}</h1>
    <p class="muted-label">Payslips are computed from attendance. A paid payslip is frozen and will not be recalculated.</p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Payslips</span>
      <span class="sc-value">{{ $totals['count'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Gross</span>
      <span class="sc-value">{{ number_format($totals['gross'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Deductions</span>
      <span class="sc-value sc-warn">{{ number_format($totals['deductions'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Net Payable</span>
      <span class="sc-value">{{ number_format($totals['net'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Paid</span>
      <span class="sc-value sc-ok">{{ number_format($totals['paid'], 2) }}</span>
    </div>
  </div>

  <a class="btn" href="{{ route('payroll.create', ['month' => $month->toDateString()]) }}">▸ Run Payroll</a>
  <a class="btn" href="{{ route('attendance.index') }}">Attendance</a>
  <a class="btn" href="{{ route('staff.index') }}">Staff</a>

  <form class="search-form" method="get" action="{{ route('payroll.index') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search payslip number or staff…">
    <input type="month" name="month" value="{{ $month->format('Y-m') }}">
    <select name="status">
      <option value="">All Statuses</option>
      @foreach (\App\Models\Payslip::STATUSES as $val => $label)
        <option value="{{ $val }}" @selected(($status ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn">Search</button>
    @if ($search || $status)
      <a href="{{ route('payroll.index', ['month' => $month->format('Y-m')]) }}" class="btn">Clear</a>
    @endif
  </form>

  <table>
    <thead>
      <tr>
        <th>Number</th>
        <th>Staff</th>
        <th class="num">Payable / Working</th>
        <th class="num">Earned Basic</th>
        <th class="num">Gross</th>
        <th class="num">Deductions</th>
        <th class="num">Net Pay</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($payslips as $p)
        <tr>
          <td><a href="{{ route('payroll.show', $p->id) }}">{{ $p->number }}</a></td>
          <td>
            {{ $p->staff->name ?? '—' }}
            <div class="muted-label">{{ $p->staff->code ?? '' }}</div>
          </td>
          <td class="num">{{ number_format($p->payable_days, 2) }} / {{ $p->working_days }}</td>
          <td class="num">{{ number_format($p->earned_basic, 2) }}</td>
          <td class="num">{{ number_format($p->gross_earnings, 2) }}</td>
          <td class="num">{{ number_format($p->total_deductions, 2) }}</td>
          <td class="num">{{ number_format($p->net_pay, 2) }}</td>
          <td>
            <span class="pill pill-{{ $p->status === 'paid' ? 'paid' : ($p->status === 'cancelled' ? 'void' : ($p->status === 'approved' ? 'partial' : 'unpaid')) }}">
              {{ $p->statusLabel() }}
            </span>
          </td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('payroll.show', $p->id) }}'">View</button>
            @unless ($p->isLocked())
              <button class="btn" onclick="window.location.href='{{ route('payroll.edit', $p->id) }}'">Edit</button>
              <button class="btn danger" onclick="deletePayslip(event, '{{ route('payroll.destroy', $p->id) }}')">Delete</button>
            @endunless
          </td>
        </tr>
      @empty
        <tr><td colspan="9">No payslips for {{ $month->format('F Y') }}. Click <em>Run Payroll</em> to generate them.</td></tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $payslips])
  @include('partials.per-page', ['paginator' => $payslips, 'action' => route('payroll.index')])

  <script>
    function deletePayslip(event, url) {
      if (!confirm('Delete this payslip?')) return;
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
          window.toast && window.toast(body.message || 'Payslip deleted.', 'success');
        } else {
          window.toast && window.toast(body.message || 'Failed to delete payslip.', 'error');
        }
      }).catch(() => window.toast && window.toast('Failed to delete payslip.', 'error'));
    }
  </script>
@endsection
