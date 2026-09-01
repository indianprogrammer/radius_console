@extends('layout', ['title' => 'Edit Tax Rate'])
@section('content')
  <div class="page-header">
    <h1>Edit Tax Rate <span class="muted">#{{ $tax->id }}</span></h1>
    <p class="muted-label">Update this reusable tax applied to billing plans and invoices.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('tax-rates.update', $tax->id) }}">
    @csrf @method('PUT')

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Tax Details</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="name">Name <em>*</em></label>
            <input type="text" name="name" id="name" class="gui-input" required maxlength="120"
                   value="{{ old('name', $tax->name) }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="rate">Rate <em>*</em></label>
            <input type="number" name="rate" id="rate" class="gui-input" step="0.01" min="0" max="100" required
                   value="{{ old('rate', number_format($tax->rate, 2, '.', '')) }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="type">Type <em>*</em></label>
            <select name="type" id="type" class="gui-input">
              <option value="percentage" @selected(old('type', $tax->type) === 'percentage')>Percentage (%)</option>
              <option value="fixed" @selected(old('type', $tax->type) === 'fixed')>Fixed amount</option>
            </select>
          </div>

        </div>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn" type="submit">Save Changes</button>
    </div>
  </form>
@endsection
