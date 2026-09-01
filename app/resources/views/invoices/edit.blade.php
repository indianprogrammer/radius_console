@extends('layout', ['title' => 'Edit Invoice ' . $invoice->number])
@section('content')
  <div class="page-header">
    <h1>Edit Invoice {{ $invoice->number }}</h1>
    <p class="muted-label">
      {{ $invoice->subscriber->username ?? '—' }} ·
      Total {{ number_format($invoice->payableAmount(), 2) }} ·
      Paid {{ number_format($invoice->paid_amount, 2) }} ·
      Balance {{ number_format($invoice->balance(), 2) }}
    </p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('invoices.update', $invoice->id) }}">
    @csrf
    @method('PUT')

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Invoice Status</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="status">Status <em>*</em></label>
            <select name="status" id="status" class="gui-input" required>
              @foreach (\App\Models\Invoice::STATUSES as $val => $label)
                <option value="{{ $val }}" @selected(old('status', $invoice->status) === $val)>{{ $label }}</option>
              @endforeach
            </select>
            <span class="hint">Unpaid / Partial / Paid are re-derived from payments; Void and Draft stick.</span>
          </div>

          <div class="field col-3">
            <label for="due_display">Due Date</label>
            <input type="text" class="gui-input js-dmy" id="due_display" data-target="due_date"
                   placeholder=" " autocomplete="off" inputmode="numeric">
            <input type="hidden" name="due_date" id="due_date"
                   value="{{ old('due_date', $invoice->due_date?->format('Y-m-d\TH:i')) }}">
            <span class="hint">dd/mm/yy</span>
          </div>

          <div class="field col-12">
            <label for="notes">Notes</label>
            <textarea name="notes" id="notes" class="gui-input" rows="2" placeholder=" ">{{ old('notes', $invoice->notes) }}</textarea>
          </div>

        </div>
      </div>
    </div>

    <div class="form-actions">
      <a class="btn" href="{{ route('invoices.show', $invoice->id) }}">Cancel</a>
      <button class="btn" type="submit">Save Invoice</button>
    </div>
  </form>

  @include('partials.dmy-date-script')
@endsection
