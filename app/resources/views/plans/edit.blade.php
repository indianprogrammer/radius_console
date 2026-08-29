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
        <option value="{{ $bp->id }}" {{ (old('bandwidth_profile_id', $plan->bandwidthProfileId) == $bp->id) ? 'selected' : '' }}>{{ $bp->name }} ({{ $bp->downloadMbps }}/{{ $bp->uploadMbps }} Mbps, {{ $bp->dataLimitGb ? number_format($bp->dataLimitGb, 0) . ' GB' : 'Unlimited' }})</option>
      @endforeach
    </select></label>
    <label>Total Bandwidth (GB) <input name="data_limit_gb" type="number" min="0" step="1" value="{{ old('data_limit_gb', $plan->dataLimitGb ?? '') }}" placeholder="e.g. 500 (leave blank for unlimited)"></label>
    @php
      $attachedIds = old('tax_rate_ids', collect($plan->taxRates ?? [])->map(fn($t) => $t->id)->all());
    @endphp
    <label>Taxes (apply multiple or none)
      <div class="tax-picker">
        @foreach ($taxes as $tr)
          @php $checked = in_array($tr->id, $attachedIds); @endphp
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
    <div class="plan-total" aria-live="polite">
      <span class="pt-label">Total (after tax)</span>
      <span class="pt-amount" id="plan-total-amount">0.00</span>
      <span class="pt-breakdown" id="plan-total-breakdown"></span>
    </div>
    <button class="btn" type="submit">Save</button>
  </form>
@endsection

@push('scripts')
<script>
  const TAX_RATES = @json(collect($taxes)->mapWithKeys(fn($t) => [$t->id => ['rate' => (float) $t->rate, 'type' => $t->type]])->all());
  (function () {
    const price = document.querySelector('input[name="price"]');
    const boxes = document.querySelectorAll('input[name="tax_rate_ids[]"]');
    const amount = document.getElementById('plan-total-amount');
    const breakdown = document.getElementById('plan-total-breakdown');
    function recalc() {
      const sub = parseFloat(price.value) || 0;
      let tax = 0, parts = [];
      boxes.forEach(b => {
        if (!b.checked) return;
        const t = TAX_RATES[b.value];
        if (!t) return;
        const amt = t.type === 'fixed' ? t.rate : sub * (t.rate / 100);
        tax += amt;
        parts.push(t.rate + (t.type === 'fixed' ? '' : '%'));
      });
      tax = Math.round(tax * 100) / 100;
      const total = Math.ceil(sub + tax);
      amount.textContent = total.toFixed(2);
      breakdown.textContent = parts.length
        ? '(' + sub.toFixed(2) + ' + ' + tax.toFixed(2) + ' tax [' + parts.join(' + ') + '] = ' + total.toFixed(2) + ')'
        : '(no tax)';
    }
    price.addEventListener('input', recalc);
    boxes.forEach(b => b.addEventListener('change', recalc));
    recalc();
  })();
</script>
@endpush
