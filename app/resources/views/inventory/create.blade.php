@extends('layout', ['title' => 'New Inventory Item'])
@section('content')
  <div class="page-header">
    <h1>New Inventory Item</h1>
    <p class="muted-label">Track physical stock, digital goods, services or accessories.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('inventory.store') }}">
    @csrf

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Item Details</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="sku">SKU <em>*</em></label>
            <input type="text" name="sku" id="sku" class="gui-input" required maxlength="100"
                   value="{{ old('sku') }}" placeholder=" ">
            <span class="hint">Unique stock-keeping unit code.</span>
          </div>

          <div class="field col-3">
            <label for="name">Name <em>*</em></label>
            <input type="text" name="name" id="name" class="gui-input" required maxlength="200"
                   value="{{ old('name') }}" placeholder=" ">
            <span class="hint">e.g. CAT6 Cable Roll, WiFi USB Adapter, Router Setup</span>
          </div>

          <div class="field col-3">
            <label for="category">Category <em>*</em></label>
            <select name="category" id="category" class="gui-input" required>
              @foreach (\App\Models\Inventory::CATEGORIES as $val => $label)
                <option value="{{ $val }}" @selected(old('category', 'physical') === $val)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="field col-3">
            <label for="unit">Unit</label>
            <input type="text" name="unit" id="unit" class="gui-input" maxlength="30"
                   value="{{ old('unit', 'pcs') }}" placeholder=" ">
            <span class="hint">e.g. pcs, meter, license, set</span>
          </div>

          <div class="field col-3">
            <label for="stock_quantity">Opening Stock <em>*</em></label>
            <input type="number" name="stock_quantity" id="stock_quantity" class="gui-input"
                   step="0.01" min="0" required value="{{ old('stock_quantity', '0') }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="reorder_point">Reorder Point <em>*</em></label>
            <input type="number" name="reorder_point" id="reorder_point" class="gui-input"
                   step="0.01" min="0" required value="{{ old('reorder_point', '0') }}" placeholder=" ">
            <span class="hint">Alert when stock falls to this level.</span>
          </div>

          <div class="field col-3">
            <label for="cost_price">Cost Price <em>*</em></label>
            <input type="number" name="cost_price" id="cost_price" class="gui-input"
                   step="0.01" min="0" required value="{{ old('cost_price', '0') }}" placeholder=" ">
            <span class="hint">Your purchase / acquisition cost.</span>
          </div>

          <div class="field col-3">
            <label for="sale_price">Sale Price <em>*</em></label>
            <input type="number" name="sale_price" id="sale_price" class="gui-input"
                   step="0.01" min="0" required value="{{ old('sale_price', '0') }}" placeholder=" ">
            <span class="hint">Selling price to customers.</span>
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

    <div class="form-actions">
      <button class="btn" type="submit">Save Item</button>
    </div>
  </form>
@endsection