@extends('layout', ['title' => 'New Tax Rate'])
@section('content')
  <h1>New Tax Rate</h1>
  <p class="hint">Define a reusable tax to apply to billing plans and invoices.</p>
  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <form method="POST" action="{{ route('tax-rates.store') }}">
    @csrf
    <label>Name <span class="req">*</span>
      <input name="name" type="text" required maxlength="120" value="{{ old('name') }}" placeholder="e.g. VAT, GST, Service Tax">
    </label>
    <label>Rate <span class="req">*</span>
      <input name="rate" type="number" step="0.01" min="0" max="100" required value="{{ old('rate') }}" placeholder="18.00">
    </label>
    <label>Type <span class="req">*</span>
      <select name="type">
        <option value="percentage" {{ old('type', 'percentage') === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
        <option value="fixed" {{ old('type') === 'fixed' ? 'selected' : '' }}>Fixed amount</option>
      </select>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}>
      Use as default tax for new plans
    </label>
    <button class="btn" type="submit">Create Tax Rate</button>
  </form>
@endsection
