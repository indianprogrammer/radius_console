@extends('layout', ['title' => 'Edit Tax Rate'])
@section('content')
  <h1>Edit Tax Rate <span class="muted">#{{ $tax->id }}</span></h1>
  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <form method="POST" action="{{ route('tax-rates.update', $tax->id) }}">
    @csrf @method('PUT')
    <label>Name <span class="req">*</span>
      <input name="name" type="text" required maxlength="120" value="{{ old('name', $tax->name) }}">
    </label>
    <label>Rate <span class="req">*</span>
      <input name="rate" type="number" step="0.01" min="0" max="100" required value="{{ old('rate', number_format($tax->rate, 2, '.', '')) }}">
    </label>
    <label>Type <span class="req">*</span>
      <select name="type">
        <option value="percentage" {{ (old('type', $tax->type) === 'percentage') ? 'selected' : '' }}>Percentage (%)</option>
        <option value="fixed" {{ (old('type', $tax->type) === 'fixed') ? 'selected' : '' }}>Fixed amount</option>
      </select>
    </label>
    <button class="btn" type="submit">Save Changes</button>
  </form>
@endsection
