@extends('layout', ['title' => 'New Plan'])
@section('content')
  <h1>New Plan</h1>
  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <form method="POST" action="{{ route('plans.store') }}">
    @csrf
    <label>Name <span class="req">*</span><input name="name" required placeholder="Home 50 Mbps"></label>
    <label>Price <span class="req">*</span><input name="price" type="number" step="0.01" min="0" required placeholder="0.00"></label>
    <label>Cycle <span class="req">*</span><select name="cycle"><option value="monthly">monthly</option><option value="quarterly">quarterly</option><option value="yearly">yearly</option></select></label>
    <label>Taxes (apply multiple or none)
      <select name="tax_rate_ids[]" multiple size="{{ max(3, count($taxes)) }}">
        @foreach ($taxes as $tr)
          <option value="{{ $tr->id }}" {{ in_array($tr->id, old('tax_rate_ids', [])) ? 'selected' : '' }}>{{ $tr->name }} ({{ number_format($tr->rate, 2) }}{{ $tr->type === 'fixed' ? '' : '%' }}){{ $tr->isDefault ? ' ★' : '' }}</option>
        @endforeach
      </select>
      <span class="hint">Hold Ctrl/Cmd to select several. Leave empty for no tax.</span>
    </label>
    <label>Bandwidth Profile<select name="bandwidth_profile_id">
      <option value="">— none —</option>
      @foreach ($profiles as $bp)
        <option value="{{ $bp->id }}">{{ $bp->name }} ({{ $bp->downloadMbps }}/{{ $bp->uploadMbps }} Mbps)</option>
      @endforeach
    </select></label>
    <button class="btn" type="submit">Create Plan</button>
  </form>
@endsection
