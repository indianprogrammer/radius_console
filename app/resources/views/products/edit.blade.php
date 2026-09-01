@extends('layout', ['title' => 'Edit Product / Service'])
@section('content')
  <div class="page-header">
    <h1>Edit Product / Service <span class="muted">#{{ $product->id }}</span></h1>
    <p class="muted-label">Update the product or service details.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('products.update', $product->id) }}">
    @csrf @method('PUT')

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Product Details</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="name">Name <em>*</em></label>
            <input type="text" name="name" id="name" class="gui-input" required maxlength="150"
                   value="{{ old('name', $product->name) }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="category">Category <em>*</em></label>
            <select name="category" id="category" class="gui-input" required>
              <option value="one-time" @selected(old('category', $product->category) === 'one-time')>One-time</option>
              <option value="recurring" @selected(old('category', $product->category) === 'recurring')>Recurring</option>
            </select>
          </div>

          <div class="field col-3">
            <label for="default_amount">Default Amount <em>*</em></label>
            <input type="number" name="default_amount" id="default_amount" class="gui-input" step="0.01" min="0" required
                   value="{{ old('default_amount', $product->default_amount) }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="unit">Unit</label>
            <input type="text" name="unit" id="unit" class="gui-input" maxlength="30"
                   value="{{ old('unit', $product->unit) }}" placeholder=" ">
            <span class="hint">e.g. pcs, meter, month</span>
          </div>

          <div class="field col-3">
            <label for="is_active">Active</label>
            <select name="is_active" id="is_active" class="gui-input">
              <option value="1" @selected(old('is_active', $product->is_active ? '1' : '0') === '1')>Yes</option>
              <option value="0" @selected(old('is_active', $product->is_active ? '1' : '0') === '0')>No</option>
            </select>
          </div>

          <div class="field col-12">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="gui-input" rows="2" maxlength="1000"
                      placeholder=" ">{{ old('description', $product->description) }}</textarea>
          </div>

        </div>
      </div>
    </div>

    @php
      $selectedTaxIds = old('tax_rate_ids', $product->taxes->pluck('id')->toArray());
    @endphp
    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Taxes</h4>
        <div class="tax-picker">
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
      </div>
    </div>

    <div class="form-actions">
      <button class="btn" type="submit">Update Product</button>
    </div>
  </form>
@endsection
