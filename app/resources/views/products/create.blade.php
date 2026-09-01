@extends('layout', ['title' => 'New Product / Service'])
@section('content')
  <div class="page-header">
    <h1>New Product / Service</h1>
    <p class="muted-label">Add a product or service with a default price for billing.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('products.store') }}">
    @csrf

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Product Details</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="name">Name <em>*</em></label>
            <input type="text" name="name" id="name" class="gui-input" required maxlength="150"
                   value="{{ old('name') }}" placeholder=" ">
            <span class="hint">e.g. Installation Charges, IP Rental, Router</span>
          </div>

          <div class="field col-3">
            <label for="category">Category <em>*</em></label>
            <select name="category" id="category" class="gui-input" required>
              <option value="one-time" @selected(old('category', 'one-time') === 'one-time')>One-time</option>
              <option value="recurring" @selected(old('category') === 'recurring')>Recurring</option>
            </select>
          </div>

          <div class="field col-3">
            <label for="default_amount">Default Amount <em>*</em></label>
            <input type="number" name="default_amount" id="default_amount" class="gui-input" step="0.01" min="0" required
                   value="{{ old('default_amount', '0') }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="unit">Unit</label>
            <input type="text" name="unit" id="unit" class="gui-input" maxlength="30"
                   value="{{ old('unit', 'pcs') }}" placeholder=" ">
            <span class="hint">e.g. pcs, meter, month</span>
          </div>

          <div class="field col-3">
            <label for="is_active">Active</label>
            <select name="is_active" id="is_active" class="gui-input">
              <option value="1" @selected(old('is_active', '1') === '1')>Yes</option>
              <option value="0" @selected(old('is_active') === '0')>No</option>
            </select>
          </div>

          <div class="field col-12">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="gui-input" rows="2" maxlength="1000"
                      placeholder=" ">{{ old('description') }}</textarea>
          </div>

        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Taxes</h4>
        <div class="tax-picker">
          @foreach ($taxes as $tr)
            @php $checked = in_array($tr->id, old('tax_rate_ids', [])); @endphp
            <label class="tax-pill">
              <input type="checkbox" name="tax_rate_ids[]" value="{{ $tr->id }}" {{ $checked ? 'checked' : '' }}>
              <span class="dot"></span>
              <span class="name">{{ $tr->name }}</span>
              <span class="rate">({{ number_format($tr->rate, 2) }}{{ $tr->type === 'fixed' ? '' : '%' }})</span>
            </label>
          @endforeach
        </div>
        <span class="hint">Tick as many as apply. Leave all unticked for no tax.</span>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn" type="submit">Save Product</button>
    </div>
  </form>
@endsection
