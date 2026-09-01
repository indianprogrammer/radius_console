{{--
  Shared payment form fields (create + edit).

  Expects:
    $payment      App\Models\Payment  (unsaved instance on create)
    $invoices     Collection of open invoices (with subscriber loaded)
    $subscribers  Collection of tenant subscribers

  Picking an invoice locks the subscriber (the controller always takes the
  invoice's subscriber) and prefills the amount with the invoice balance.
--}}
<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Receipt Details</h4>
    <div class="form-grid">

      <div class="field col-6">
        <label for="invoice_id">Invoice</label>
        <select name="invoice_id" id="invoice_id" class="gui-input">
          <option value="">— on account (no invoice) —</option>
          @foreach ($invoices as $inv)
            <option value="{{ $inv->id }}"
                    data-subscriber="{{ $inv->subscriber_id }}"
                    data-balance="{{ number_format($inv->balance(), 2, '.', '') }}"
                    @selected((int) old('invoice_id', $payment->invoice_id) === (int) $inv->id)>
              {{ $inv->number }} — {{ $inv->subscriber->username ?? '—' }} (balance {{ number_format($inv->balance(), 2) }})
            </option>
          @endforeach
        </select>
        <span class="hint">Leave empty to record an advance / on-account receipt.</span>
      </div>

      <div class="field col-6">
        <label for="subscriber_id">Subscriber <em>*</em></label>
        <select name="subscriber_id" id="subscriber_id" class="gui-input">
          <option value="">— select —</option>
          @foreach ($subscribers as $s)
            <option value="{{ $s->id }}" @selected((int) old('subscriber_id', $payment->subscriber_id) === (int) $s->id)>
              {{ $s->username }}{{ trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')) ? ' — ' . trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')) : '' }}
            </option>
          @endforeach
        </select>
        <span class="hint">Set automatically when an invoice is selected.</span>
      </div>

      <div class="field col-3">
        <label for="amount">Amount <em>*</em></label>
        <input type="number" name="amount" id="amount" class="gui-input" step="0.01" min="0.01" required
               value="{{ old('amount', $payment->amount) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="method">Method <em>*</em></label>
        <select name="method" id="method" class="gui-input" required>
          @foreach (\App\Models\Payment::METHODS as $val => $label)
            <option value="{{ $val }}" @selected(old('method', $payment->method ?? 'cash') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="field col-3">
        <label for="status">Status</label>
        <select name="status" id="status" class="gui-input">
          @foreach (\App\Models\Payment::STATUSES as $val => $label)
            <option value="{{ $val }}" @selected(old('status', $payment->status ?? 'completed') === $val)>{{ $label }}</option>
          @endforeach
        </select>
        <span class="hint">Only completed payments reduce the invoice balance.</span>
      </div>

      <div class="field col-3">
        <label for="paid_display">Paid At</label>
        <input type="text" class="gui-input js-dmy" id="paid_display" data-target="paid_at" data-with-time
               placeholder=" " autocomplete="off" inputmode="numeric">
        <input type="hidden" name="paid_at" id="paid_at"
               value="{{ old('paid_at', ($payment->paid_at ?? now())->format('Y-m-d\TH:i')) }}">
        <span class="hint">dd/mm/yy hh:ii</span>
      </div>

      <div class="field col-6">
        <label for="reference">Reference</label>
        <input type="text" name="reference" id="reference" class="gui-input" maxlength="120"
               value="{{ old('reference', $payment->reference) }}" placeholder=" ">
        <span class="hint">UTR / cheque no. / gateway transaction id</span>
      </div>

      <div class="field col-12">
        <label for="notes">Notes</label>
        <textarea name="notes" id="notes" class="gui-input" rows="2" placeholder=" ">{{ old('notes', $payment->notes) }}</textarea>
      </div>

    </div>
  </div>
</div>

@push('scripts')
<script>
  (function () {
    const invoiceSel = document.getElementById('invoice_id');
    const subscriberSel = document.getElementById('subscriber_id');
    const amount = document.getElementById('amount');

    function applyInvoice(prefillAmount) {
      const opt = invoiceSel.selectedOptions[0];
      const subscriberId = opt?.dataset.subscriber;

      if (subscriberId) {
        subscriberSel.value = subscriberId;
        // The invoice's subscriber always wins server-side; make that visible.
        subscriberSel.setAttribute('readonly', 'readonly');
        subscriberSel.classList.add('is-locked');
        if (prefillAmount && !amount.value) amount.value = opt.dataset.balance || '';
      } else {
        subscriberSel.removeAttribute('readonly');
        subscriberSel.classList.remove('is-locked');
      }
    }

    // Keep the select enabled (a disabled control is not submitted) but block
    // interaction while it is driven by the invoice choice.
    subscriberSel.addEventListener('mousedown', e => {
      if (subscriberSel.hasAttribute('readonly')) e.preventDefault();
    });
    subscriberSel.addEventListener('keydown', e => {
      if (subscriberSel.hasAttribute('readonly')) e.preventDefault();
    });

    invoiceSel.addEventListener('change', () => applyInvoice(true));
    applyInvoice(false);
  })();
</script>
@endpush
