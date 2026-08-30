@extends('layout', ['title' => 'Edit Product / Service'])
@section('content')
  <h1>Edit Product / Service</h1>
  <p class="hint">Update the product or service details.</p>
  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <form method="POST" action="{{ route('products.update', $product->id) }}">
    @csrf @method('PUT')
    <label>Name <span class="req">*</span>
      <input name="name" type="text" required maxlength="150" value="{{ old('name', $product->name) }}" placeholder="e.g. Installation Charges, IP Rental, Router">
    </label>
    <label>Category <span class="req">*</span>
      <select name="category" required>
        <option value="one-time" @selected(old('category', $product->category) === 'one-time')>One-time</option>
        <option value="recurring" @selected(old('category', $product->category) === 'recurring')>Recurring</option>
      </select>
    </label>
    <label>Default Amount <span class="req">*</span>
      <input name="default_amount" type="number" step="0.01" min="0" required value="{{ old('default_amount', $product->default_amount) }}" placeholder="0.00">
    </label>
    <label>Unit
      <input name="unit" type="text" maxlength="30" value="{{ old('unit', $product->unit) }}" placeholder="e.g. pcs, meter, month">
    </label>
    <label>Taxes (apply multiple or none)
      <div class="tax-picker">
        @php
          $selectedTaxIds = old('tax_rate_ids', $product->taxes->pluck('id')->toArray());
        @endphp
        @foreach ($taxes as $tr)
          <label class="tax-pill">
            <input type="checkbox" name="tax_rate_ids[]" value="{{ $tr->id }}" {{ in_array($tr->id, $selectedTaxIds) ? 'checked' : '' }}>
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
        <option value="1" @selected(old('is_active', $product->is_active ? '1' : '0') === '1')>Yes</option>
        <option value="0" @selected(old('is_active', $product->is_active ? '1' : '0') === '0')>No</option>
      </select>
    </label>
    <label>Description
      <textarea name="description" class="gui-input" rows="2" maxlength="1000" placeholder="Optional description…">{{ old('description', $product->description) }}</textarea>
    </label>
    <button class="btn" type="submit">Update Product</button>
  </form>
@endsection
