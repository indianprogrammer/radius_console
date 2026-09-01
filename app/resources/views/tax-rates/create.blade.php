@extends('layout', ['title' => 'New Tax Rate'])
@section('content')
  <div class="page-header">
    <h1>New Tax Rate</h1>
    <p class="muted-label">Define a reusable tax to apply to billing plans and invoices.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('tax-rates.store') }}">
    @csrf

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Tax Details</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="name">Name <em>*</em></label>
            <input type="text" name="name" id="name" class="gui-input" required maxlength="120"
                   value="{{ old('name') }}" placeholder=" ">
            <span class="hint">e.g. VAT, GST, Service Tax</span>
          </div>

          <div class="field col-3">
            <label for="rate">Rate <em>*</em></label>
            <input type="number" name="rate" id="rate" class="gui-input" step="0.01" min="0" max="100" required
                   value="{{ old('rate') }}" placeholder=" ">
            <span class="hint">e.g. 18.00</span>
          </div>

          <div class="field col-3">
            <label for="type">Type <em>*</em></label>
            <select name="type" id="type" class="gui-input">
              <option value="percentage" @selected(old('type', 'percentage') === 'percentage')>Percentage (%)</option>
              <option value="fixed" @selected(old('type') === 'fixed')>Fixed amount</option>
            </select>
          </div>

        </div>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn" type="submit">Create Tax Rate</button>
    </div>
  </form>
@endsection
