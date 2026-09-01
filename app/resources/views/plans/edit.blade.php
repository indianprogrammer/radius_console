@extends('layout', ['title' => 'Edit Plan'])
@php $id = $plan->id; @endphp
@section('content')
  <div class="page-header">
    <h1>Edit Plan <span class="muted">#{{ $id }}</span></h1>
    <p class="muted-label">Update pricing, duration, taxes and bandwidth for this package.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('plans.update', $id) }}">
    @csrf @method('PUT')

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Plan Details</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="name">Name <em>*</em></label>
            <input type="text" name="name" id="name" class="gui-input" required
                   value="{{ old('name', $plan->name ?? '') }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="price">Price <em>*</em></label>
            <input type="number" name="price" id="price" class="gui-input" step="0.01" min="0" required
                   value="{{ old('price', number_format($plan->price ?? 0, 2, '.', '')) }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="duration">Duration <em>*</em></label>
            <input type="number" name="duration" id="duration" class="gui-input" min="1" step="1" required
                   value="{{ old('duration', $plan->duration ?? 1) }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="duration_unit">Duration Unit <em>*</em></label>
            <select name="duration_unit" id="duration_unit" class="gui-input">
              @foreach (['days' => 'Days', 'months' => 'Months'] as $val => $label)
                <option value="{{ $val }}" @selected(old('duration_unit', $plan->durationUnit ?? 'months') === $val)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

        </div>
      </div>
    </div>

    @php
      $attachedIds = old('tax_rate_ids', collect($plan->taxRates ?? [])->map(fn($t) => $t->id)->all());
    @endphp
    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Taxes</h4>
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
        <div class="plan-total" aria-live="polite">
          <span class="pt-label">Total (after tax)</span>
          <span class="pt-amount" id="plan-total-amount">0.00</span>
          <span class="pt-breakdown" id="plan-total-breakdown"></span>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Bandwidth</h4>
        <div class="form-grid">

          <div class="field col-6">
            <label for="bandwidth_profile_id">Bandwidth Profile</label>
            <select name="bandwidth_profile_id" id="bandwidth_profile_id" class="gui-input">
              <option value="">— none —</option>
              @foreach ($profiles as $bp)
                <option value="{{ $bp->id }}" {{ (old('bandwidth_profile_id', $plan->bandwidthProfileId) == $bp->id) ? 'selected' : '' }}>{{ $bp->name }} ({{ $bp->downloadMbps }}/{{ $bp->uploadMbps }} Mbps, {{ $bp->dataLimitGb ? number_format($bp->dataLimitGb, 0) . ' GB' : 'Unlimited' }})</option>
              @endforeach
            </select>
          </div>

          <div class="field col-3">
            <label for="data_limit_gb">Total Bandwidth (GB)</label>
            <input type="number" name="data_limit_gb" id="data_limit_gb" class="gui-input" min="0" step="1"
                   value="{{ old('data_limit_gb', $plan->dataLimitGb ?? '') }}" placeholder=" ">
            <span class="hint">Leave blank for unlimited</span>
          </div>

        </div>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn" type="submit">Save</button>
    </div>
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
