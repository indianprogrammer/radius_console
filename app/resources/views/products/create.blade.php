@extends('layout', ['title' => 'New Product / Service'])
@section('content')
  <h1>New Product / Service</h1>
  <p class="hint">Add a product or service with a default price for billing.</p>
  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <form method="POST" action="{{ route('products.store') }}">
    @csrf
    <label>Name <span class="req">*</span>
      <input name="name" type="text" required maxlength="150" value="{{ old('name') }}" placeholder="e.g. Installation Charges, IP Rental, Router">
    </label>
    <label>Category <span class="req">*</span>
      <select name="category" required>
        <option value="one-time" @selected(old('category', 'one-time') === 'one-time')>One-time</option>
        <option value="recurring" @selected(old('category') === 'recurring')>Recurring</option>
      </select>
    </label>
    <label>Default Amount <span class="req">*</span>
      <input name="default_amount" type="number" step="0.01" min="0" required value="{{ old('default_amount', '0') }}" placeholder="0.00">
    </label>
    <label>Unit
      <input name="unit" type="text" maxlength="30" value="{{ old('unit', 'pcs') }}" placeholder="e.g. pcs, meter, month">
    </label>
    <label>Taxes (apply multiple or none)
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
    </label>
    <label>Active
      <select name="is_active">
        <option value="1" @selected(old('is_active', '1') === '1')>Yes</option>
        <option value="0" @selected(old('is_active') === '0')>No</option>
      </select>
    </label>
    <label>Description
      <textarea name="description" class="gui-input" rows="2" maxlength="1000" placeholder="Optional description…">{{ old('description') }}</textarea>
    </label>
    <button class="btn" type="submit">Save Product</button>
  </form>
@endsection
