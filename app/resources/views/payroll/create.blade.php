@extends('layout', ['title' => 'Run Payroll'])
@section('content')
  <div class="page-header">
    <h1>Run Payroll</h1>
    <p class="muted-label">
      Generates a draft payslip per employee from the month's attendance.
      Re-running refreshes drafts and skips anything already marked paid.
    </p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('payroll.store') }}">
    @csrf

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Payroll Run</h4>
        <div class="form-grid">

          <div class="field col-4">
            <label for="period_month">Payroll Month <em>*</em></label>
            <input type="month" name="period_month" id="period_month" class="gui-input" required
                   value="{{ old('period_month', $month->format('Y-m')) }}">
            <span class="hint">The whole calendar month is used.</span>
          </div>

          <div class="field col-8">
            <label for="staff_id">Employee</label>
            <select name="staff_id" id="staff_id" class="gui-input">
              <option value="">— all active staff ({{ $staff->count() }}) —</option>
              @foreach ($staff as $s)
                <option value="{{ $s->id }}" @selected((int) old('staff_id') === (int) $s->id)>
                  {{ $s->code }} — {{ $s->name }} (basic {{ number_format($s->basic_salary, 2) }})
                </option>
              @endforeach
            </select>
            <span class="hint">Leave blank to run the whole tenant.</span>
          </div>

        </div>
      </div>
    </div>

    <div class="form-actions">
      <a class="btn" href="{{ route('payroll.index') }}">Cancel</a>
      <button class="btn" type="submit">Generate Payslips</button>
    </div>
  </form>
@endsection
