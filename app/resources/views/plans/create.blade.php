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
      <div class="tax-picker">
        @foreach ($taxes as $tr)
          @php $checked = in_array($tr->id, old('tax_rate_ids', [])); @endphp
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
    <label>Bandwidth Profile<select name="bandwidth_profile_id">
      <option value="">— none —</option>
      @foreach ($profiles as $bp)
        <option value="{{ $bp->id }}">{{ $bp->name }} ({{ $bp->downloadMbps }}/{{ $bp->uploadMbps }} Mbps, {{ $bp->dataLimitGb ? number_format($bp->dataLimitGb, 0) . ' GB' : 'Unlimited' }})</option>
      @endforeach
    </select></label>
    <label>Total Bandwidth (GB) <input name="data_limit_gb" type="number" min="0" step="1" value="{{ old('data_limit_gb', '') }}" placeholder="e.g. 500 (leave blank for unlimited)"></label>
    <button class="btn" type="submit">Create Plan</button>
  </form>
@endsection

@push('scripts')
<script>
  // Tax rate lookup (id -> {rate, type}) for live total computation.
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
