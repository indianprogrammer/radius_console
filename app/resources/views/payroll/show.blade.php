@extends('layout', ['title' => 'Payslip ' . $payslip->number])
@section('content')
  <div class="page-header">
    <h1>Payslip {{ $payslip->number }}</h1>
    <p class="muted-label">
      {{ $payslip->staff->name ?? '—' }} ({{ $payslip->staff->code ?? '—' }}) ·
      {{ $payslip->periodLabel() }}
      <span class="pill pill-{{ $payslip->status === 'paid' ? 'paid' : ($payslip->status === 'cancelled' ? 'void' : ($payslip->status === 'approved' ? 'partial' : 'unpaid')) }}">
        {{ $payslip->statusLabel() }}
      </span>
      @if ($payslip->isLocked())<span class="pill pill-info">Locked</span>@endif
    </p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Payable Days</span>
      <span class="sc-value">{{ number_format($payslip->payable_days, 2) }} / {{ $payslip->working_days }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Loss of Pay</span>
      <span class="sc-value sc-warn">{{ number_format($payslip->lop_days, 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Gross</span>
      <span class="sc-value">{{ number_format($payslip->gross_earnings, 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Deductions</span>
      <span class="sc-value sc-warn">{{ number_format($payslip->total_deductions, 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Net Pay</span>
      <span class="sc-value sc-ok">{{ number_format($payslip->net_pay, 2) }}</span>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Earnings</h4>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Component</th><th class="num">Amount</th></tr></thead>
          <tbody>
            <tr><td>Basic (full month)</td><td class="num">{{ number_format($payslip->basic_salary, 2) }}</td></tr>
            <tr><td>Earned Basic <span class="muted-label">(prorated by payable days)</span></td><td class="num">{{ number_format($payslip->earned_basic, 2) }}</td></tr>
            <tr><td>HRA</td><td class="num">{{ number_format($payslip->hra, 2) }}</td></tr>
            <tr><td>Other Allowances</td><td class="num">{{ number_format($payslip->other_allowances, 2) }}</td></tr>
            <tr><td>Overtime <span class="muted-label">({{ number_format($payslip->overtime_hours, 2) }} h)</span></td><td class="num">{{ number_format($payslip->overtime_amount, 2) }}</td></tr>
            <tr><td>Bonus</td><td class="num">{{ number_format($payslip->bonus, 2) }}</td></tr>
            <tr><th>Gross Earnings</th><th class="num">{{ number_format($payslip->gross_earnings, 2) }}</th></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Deductions</h4>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Component</th><th class="num">Amount</th></tr></thead>
          <tbody>
            <tr><td>Provident Fund</td><td class="num">{{ number_format($payslip->pf_amount, 2) }}</td></tr>
            <tr><td>ESI</td><td class="num">{{ number_format($payslip->esi_amount, 2) }}</td></tr>
            <tr><td>Professional Tax</td><td class="num">{{ number_format($payslip->professional_tax, 2) }}</td></tr>
            <tr><td>TDS</td><td class="num">{{ number_format($payslip->tds, 2) }}</td></tr>
            <tr><td>Salary Advance</td><td class="num">{{ number_format($payslip->advance_deduction, 2) }}</td></tr>
            <tr><td>Other Deductions</td><td class="num">{{ number_format($payslip->other_deductions, 2) }}</td></tr>
            <tr><th>Total Deductions</th><th class="num">{{ number_format($payslip->total_deductions, 2) }}</th></tr>
            <tr><th>Net Pay</th><th class="num">{{ number_format($payslip->net_pay, 2) }}</th></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h4 class="section-title">Payment</h4>
      <div class="table-wrap">
        <table class="data-table">
          <tbody>
            <tr><th>Method</th><td>{{ $payslip->methodLabel() }}</td>
                <th>Reference</th><td>{{ $payslip->payment_reference ?: '—' }}</td></tr>
            <tr><th>Paid At</th><td>{{ $payslip->paid_at?->format('d/m/y H:i') ?? '—' }}</td>
                <th>Bank</th><td>{{ $payslip->staff->bank_account_number ? $payslip->staff->bank_ifsc . ' · ' . $payslip->staff->bank_account_number : '—' }}</td></tr>
          </tbody>
        </table>
      </div>
      @if ($payslip->notes)<p class="hint">{{ $payslip->notes }}</p>@endif
    </div>
  </div>

  <div class="form-actions">
    <a class="btn" href="{{ route('payroll.index', ['month' => $payslip->period_month->format('Y-m')]) }}">Back to Payroll</a>
    @unless ($payslip->isLocked())
      <a class="btn" href="{{ route('payroll.edit', $payslip->id) }}">Edit</a>
    @endunless
    <a class="btn" href="{{ route('attendance.sheet', ['staff' => $payslip->staff_id, 'month' => $payslip->period_month->format('Y-m')]) }}">Attendance Basis</a>
  </div>
@endsection
