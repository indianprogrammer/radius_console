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
    <label>Tax Rate (%)<input name="tax_rate" type="number" step="0.01" min="0" max="100" value="{{ old('tax_rate', '0') }}" placeholder="0.00"></label>
    <label>Managed Tax Rate
      <select name="tax_rate_id">
        <option value="">— none (use % above) —</option>
        @foreach ($taxes as $tr)
          <option value="{{ $tr->id }}">{{ $tr->name }} ({{ number_format($tr->rate, 2) }}{{ $tr->type === 'fixed' ? '' : '%' }}){{ $tr->isDefault ? ' ★' : '' }}</option>
        @endforeach
      </select>
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
