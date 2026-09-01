@extends('layout', ['title' => 'Edit Payslip'])
@section('content')
  <div class="page-header">
    <h1>Edit {{ $payslip->number }}</h1>
    <p class="muted-label">
      {{ $payslip->staff->name ?? '—' }} · {{ $payslip->periodLabel() }} ·
      earnings are recomputed from attendance on save.
    </p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('payroll.update', $payslip->id) }}">
    @csrf
    @method('PUT')

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Discretionary Amounts</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="bonus">Bonus</label>
            <input type="number" name="bonus" id="bonus" class="gui-input" step="0.01" min="0"
                   value="{{ old('bonus', $payslip->bonus) }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="tds">TDS</label>
            <input type="number" name="tds" id="tds" class="gui-input" step="0.01" min="0"
                   value="{{ old('tds', $payslip->tds) }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="advance_deduction">Salary Advance</label>
            <input type="number" name="advance_deduction" id="advance_deduction" class="gui-input" step="0.01" min="0"
                   value="{{ old('advance_deduction', $payslip->advance_deduction) }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="other_deductions">Other Deductions</label>
            <input type="number" name="other_deductions" id="other_deductions" class="gui-input" step="0.01" min="0"
                   value="{{ old('other_deductions', $payslip->other_deductions) }}" placeholder=" ">
          </div>

        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Status &amp; Payment</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="status">Status <em>*</em></label>
            <select name="status" id="status" class="gui-input" required>
              @foreach (\App\Models\Payslip::STATUSES as $val => $label)
                <option value="{{ $val }}" @selected(old('status', $payslip->status) === $val)>{{ $label }}</option>
              @endforeach
            </select>
            <span class="hint">Marking it Paid freezes the payslip.</span>
          </div>

          <div class="field col-3">
            <label for="payment_method">Payment Method</label>
            <select name="payment_method" id="payment_method" class="gui-input">
              <option value="">— none —</option>
              @foreach (\App\Models\Payslip::PAYMENT_METHODS as $val => $label)
                <option value="{{ $val }}" @selected(old('payment_method', $payslip->payment_method) === $val)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="field col-3">
            <label for="payment_reference">Payment Reference</label>
            <input type="text" name="payment_reference" id="payment_reference" class="gui-input" maxlength="100"
                   value="{{ old('payment_reference', $payslip->payment_reference) }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="paid_display">Paid At</label>
            <input type="text" class="gui-input js-dmy" id="paid_display" data-target="paid_at" data-with-time placeholder=" ">
            <input type="hidden" name="paid_at" id="paid_at"
                   value="{{ old('paid_at', $payslip->paid_at?->format('Y-m-d\TH:i')) }}">
            <span class="hint">dd/mm/yy hh:ii — defaults to now when marked Paid.</span>
          </div>

          <div class="field col-12">
            <label for="notes">Notes</label>
            <textarea name="notes" id="notes" class="gui-input" rows="2" maxlength="1000"
                      placeholder=" ">{{ old('notes', $payslip->notes) }}</textarea>
          </div>

        </div>
      </div>
    </div>

    <div class="form-actions">
      <a class="btn" href="{{ route('payroll.show', $payslip->id) }}">Cancel</a>
      <button class="btn" type="submit">Update Payslip</button>
    </div>
  </form>

  @include('partials.dmy-date-script')
@endsection
