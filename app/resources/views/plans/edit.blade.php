@extends('layout', ['title' => 'Edit Plan'])
@php $id = $plan->id; @endphp
@section('content')
  <h1>Edit Plan</h1>
  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <form method="POST" action="{{ route('plans.update', $id) }}">
    @csrf @method('PUT')
    <label>Name <span class="req">*</span><input name="name" required value="{{ old('name', $plan->name ?? '') }}" placeholder="Home 50 Mbps"></label>
    <label>Price <span class="req">*</span><input name="price" type="number" step="0.01" min="0" required value="{{ old('price', number_format($plan->price ?? 0, 2, '.', '')) }}"></label>
    <label>Cycle <span class="req">*</span>
      <select name="cycle">
        @foreach (['monthly','quarterly','yearly'] as $c)
          <option value="{{ $c }}" {{ (old('cycle', $plan->cycle ?? 'monthly') === $c) ? 'selected' : '' }}>{{ $c }}</option>
        @endforeach
      </select>
    </label>
    <label>Bandwidth Profile<select name="bandwidth_profile_id">
      <option value="">— none —</option>
      @foreach ($profiles as $bp)
        <option value="{{ $bp->id }}" {{ (old('bandwidth_profile_id', $plan->bandwidthProfileId) == $bp->id) ? 'selected' : '' }}>{{ $bp->name }} ({{ $bp->downloadMbps }}/{{ $bp->uploadMbps }} Mbps)</option>
      @endforeach
    </select></label>
    <label>Tax Rate (%)<input name="tax_rate" type="number" step="0.01" min="0" max="100" value="{{ old('tax_rate', number_format($plan->taxRate ?? 0, 2, '.', '')) }}" placeholder="0.00"></label>
    <label>Managed Tax Rate
      <select name="tax_rate_id">
        <option value="">— none (use % above) —</option>
        @foreach ($taxes as $tr)
          <option value="{{ $tr->id }}" {{ (old('tax_rate_id', $plan->taxRateId) == $tr->id) ? 'selected' : '' }}>{{ $tr->name }} ({{ number_format($tr->rate, 2) }}{{ $tr->type === 'fixed' ? '' : '%' }}){{ $tr->isDefault ? ' ★' : '' }}</option>
        @endforeach
      </select>
    </label>
    <button class="btn" type="submit">Save</button>
  </form>
@endsection
