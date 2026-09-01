{{--
  Shared Quotation / Proforma Invoice form (create + edit).

  Line items are authored inline here — unlike invoices, which InvoiceService
  generates from a subscriber's billing items — because a quote is written before
  anything exists to generate from.

  Expects:
    $type        string  quotation|proforma
    $quote       Quote   (unsaved instance on create)
    $subscribers Collection
    $products    Collection  active catalogue, for the row picker
    $taxes       Collection  percentage tax rates, for the row picker
--}}
@php
  $isQuotation = $type === \App\Models\Quote::TYPE_QUOTATION;
  $old = fn ($k, $d = null) => old($k, $d);

  // Payloads for the inline item editor. Built here rather than inline in the
  // @json directives below: a Blade directive argument must be a SINGLE-LINE
  // expression, and a multi-line array closure there fails to compile.
  $productPayload = $products->map(fn ($p) => [
      'id'    => $p->id,
      'name'  => $p->name,
      'price' => (float) $p->default_amount,
  ])->values();

  $taxPayload = $taxes->map(fn ($t) => [
      'id'   => $t->id,
      'name' => $t->name,
      'rate' => (float) $t->rate,
  ])->values();

  // `$quote->items` on an unsaved model would query `quote_id is null`, so the
  // create path is short-circuited to an empty list.
  $itemPayload = $quote->exists
      ? $quote->items->map(fn ($i) => [
          'label'       => $i->label,
          'description' => $i->description,
          'qty'         => $i->qty,
          'unit_price'  => (float) $i->unit_price,
          'taxable'     => (bool) $i->taxable,
          'tax_rate'    => (float) $i->tax_rate,
          'product_id'  => $i->product_id,
        ])->values()
      : collect();
@endphp

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Document</h4>
    <div class="form-grid">

      <div class="field col-3">
        <label for="number">{{ $isQuotation ? 'Quotation No.' : 'Proforma No.' }}</label>
        <input type="text" name="number" id="number" class="gui-input" maxlength="40"
               value="{{ $old('number', $quote->number) }}" placeholder=" ">
        <span class="hint">Leave blank to auto-generate.</span>
      </div>

      <div class="field col-3">
        <label for="status">Status <em>*</em></label>
        <select name="status" id="status" class="gui-input" required>
          @foreach (\App\Models\Quote::STATUSES as $val => $label)
            {{-- `converted` is set by the conversion action, never by hand. --}}
            @continue($val === 'converted')
            <option value="{{ $val }}" @selected($old('status', $quote->status ?? 'draft') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="field col-3">
        <label for="issue_date_display">Issue Date</label>
        <input type="text" id="issue_date_display" class="gui-input js-dmy"
               data-target="issue_date" inputmode="numeric" maxlength="10"
               autocomplete="off" placeholder=" ">
        <input type="hidden" name="issue_date" id="issue_date"
               value="{{ $old('issue_date', $quote->issue_date?->format('Y-m-d')) }}">
        <span class="hint">dd/mm/yy</span>
      </div>

      <div class="field col-3">
        <label for="valid_until_display">Valid Until</label>
        <input type="text" id="valid_until_display" class="gui-input js-dmy"
               data-target="valid_until" inputmode="numeric" maxlength="10"
               autocomplete="off" placeholder=" ">
        <input type="hidden" name="valid_until" id="valid_until"
               value="{{ $old('valid_until', $quote->valid_until?->format('Y-m-d')) }}">
        <span class="hint">{{ $isQuotation ? 'After this date the quotation lapses.' : 'Optional for a proforma.' }}</span>
      </div>

    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Customer</h4>
    <p class="hint">Pick an existing subscriber, or fill in the details below for a prospect who is not on the books yet.</p>
    <div class="form-grid">

      <div class="field col-6">
        <label for="subscriber_id">Subscriber</label>
        <select name="subscriber_id" id="subscriber_id" class="gui-input">
          <option value="">— prospect (not a subscriber yet) —</option>
          @foreach ($subscribers as $s)
            <option value="{{ $s->id }}" @selected((int) $old('subscriber_id', $quote->subscriber_id) === (int) $s->id)>
              {{ $s->username }}{{ trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')) ? ' — ' . trim($s->first_name . ' ' . $s->last_name) : '' }}
            </option>
          @endforeach
        </select>
        <span class="hint">Required before the document can be converted to an invoice.</span>
      </div>

      <div class="field col-6">
        <label for="customer_name">Customer Name</label>
        <input type="text" name="customer_name" id="customer_name" class="gui-input" maxlength="150"
               value="{{ $old('customer_name', $quote->customer_name) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="customer_email">Email</label>
        <input type="email" name="customer_email" id="customer_email" class="gui-input" maxlength="150"
               value="{{ $old('customer_email', $quote->customer_email) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="customer_phone">Phone</label>
        <input type="text" name="customer_phone" id="customer_phone" class="gui-input" maxlength="20"
               value="{{ $old('customer_phone', $quote->customer_phone) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="customer_gstin">GSTIN</label>
        <input type="text" name="customer_gstin" id="customer_gstin" class="gui-input" maxlength="20"
               value="{{ $old('customer_gstin', $quote->customer_gstin) }}" placeholder=" ">
      </div>

      <div class="field col-6">
        <label for="customer_address">Address</label>
        <textarea name="customer_address" id="customer_address" class="gui-input" rows="2"
                  maxlength="500" placeholder=" ">{{ $old('customer_address', $quote->customer_address) }}</textarea>
      </div>

    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body">
    <div class="section-title-row">
      <h4 class="section-title">Line Items</h4>
      <div class="hint">Prices and taxes are snapshotted onto the document, so later catalogue changes never rewrite it.</div>
    </div>

    <div class="billing-items-toolbar">
      <button type="button" class="btn btn-primary btn-sm" id="add-quote-item">+ Add Item</button>
      <span class="muted-label" id="quote-items-total">Total: 0.00</span>
    </div>

    <div class="table-wrap">
      <table class="data-table" id="quote-items-table">
        <thead>
          <tr>
            <th style="min-width:200px;">Item</th>
            <th style="min-width:160px;">Description</th>
            <th class="num">Qty</th>
            <th class="num">Unit Price</th>
            <th>Taxable</th>
            <th class="num">Tax %</th>
            <th class="num">Line Total</th>
            <th>#</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <div class="form-grid" style="margin-top:1rem;">
      <div class="field col-3">
        <label for="discount_amount">Discount</label>
        <input type="number" step="0.01" min="0" name="discount_amount" id="discount_amount" class="gui-input"
               value="{{ $old('discount_amount', number_format((float) $quote->discount_amount, 2, '.', '')) }}" placeholder=" ">
        <span class="hint">Applied to the pre-tax subtotal.</span>
      </div>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Notes & Terms</h4>
    <div class="form-grid">
      <div class="field col-6">
        <label for="notes">Notes</label>
        <textarea name="notes" id="notes" class="gui-input" rows="3" maxlength="1000"
                  placeholder=" ">{{ $old('notes', $quote->notes) }}</textarea>
      </div>
      <div class="field col-6">
        <label for="terms">Terms & Conditions</label>
        <textarea name="terms" id="terms" class="gui-input" rows="3" maxlength="2000"
                  placeholder=" ">{{ $old('terms', $quote->terms) }}</textarea>
      </div>
    </div>
  </div>
</div>

@include('partials.dmy-date-script')

@push('scripts')
<script>
  // Inline line-item editor. Rows are plain form inputs named items[i][...] so
  // the controller can validate them with normal array rules; row indexes are
  // reassigned after every add/remove because PHP would otherwise see gaps.
  (function () {
    const products = @json($productPayload);
    const taxes = @json($taxPayload);
    const existing = @json($itemPayload);

    const tbody = document.querySelector('#quote-items-table tbody');
    const totalEl = document.getElementById('quote-items-total');
    const discountEl = document.getElementById('discount_amount');

    const money = (n) => (Math.round(n * 100) / 100).toFixed(2);

    function rowHtml(i, data) {
      const productOptions = ['<option value="">— none —</option>'].concat(
        products.map(p => `<option value="${p.id}" data-price="${p.price}" ${String(data.product_id) === String(p.id) ? 'selected' : ''}>${p.name}</option>`)
      ).join('');
      const taxOptions = taxes.map(t => `<option value="${t.rate}">${t.name} (${t.rate}%)</option>`).join('');

      return `
        <td>
          <input type="text" name="items[${i}][label]" class="gui-input" data-qi="label"
                 value="${data.label ?? ''}" placeholder="Item name" maxlength="200">
          <select class="gui-input" data-qi="product" style="margin-top:.25rem;">${productOptions}</select>
          <input type="hidden" name="items[${i}][product_id]" data-qi="product_id" value="${data.product_id ?? ''}">
        </td>
        <td><input type="text" name="items[${i}][description]" class="gui-input" value="${data.description ?? ''}" maxlength="500" placeholder="Optional"></td>
        <td><input type="number" name="items[${i}][qty]" class="gui-input" data-qi="qty" min="1" step="1" value="${data.qty ?? 1}" style="width:70px;"></td>
        <td><input type="number" name="items[${i}][unit_price]" class="gui-input" data-qi="price" min="0" step="0.01" value="${money(data.unit_price ?? 0)}" style="width:100px;"></td>
        <td>
          <input type="hidden" name="items[${i}][taxable]" value="0">
          <input type="checkbox" name="items[${i}][taxable]" value="1" data-qi="taxable" ${data.taxable ? 'checked' : ''}>
        </td>
        <td>
          <input type="number" name="items[${i}][tax_rate]" class="gui-input" data-qi="tax" min="0" max="100" step="0.01"
                 value="${money(data.tax_rate ?? 0)}" style="width:80px;">
          ${taxes.length ? `<select class="gui-input" data-qi="tax_pick" style="margin-top:.25rem;"><option value="">— pick —</option>${taxOptions}</select>` : ''}
        </td>
        <td class="num" data-qi="line">0.00</td>
        <td><button type="button" class="btn btn-danger btn-sm" data-qi="remove">×</button></td>
      `;
    }

    function reindex() {
      [...tbody.rows].forEach((tr, i) => {
        tr.querySelectorAll('[name]').forEach(el => {
          el.name = el.name.replace(/items\[\d+\]/, `items[${i}]`);
        });
      });
    }

    function recalc() {
      let subtotal = 0, tax = 0;
      [...tbody.rows].forEach(tr => {
        const qty = Math.max(1, parseInt(tr.querySelector('[data-qi="qty"]').value || '1', 10));
        const price = parseFloat(tr.querySelector('[data-qi="price"]').value || '0');
        const taxable = tr.querySelector('[data-qi="taxable"]').checked;
        const rate = parseFloat(tr.querySelector('[data-qi="tax"]').value || '0');
        const amount = Math.round(qty * price * 100) / 100;
        const taxAmount = taxable ? Math.round(amount * rate) / 100 : 0;
        tr.querySelector('[data-qi="line"]').textContent = money(amount + taxAmount);
        subtotal += amount;
        tax += taxAmount;
      });
      const discount = Math.min(parseFloat(discountEl.value || '0'), subtotal);
      totalEl.textContent = 'Total: ' + money(subtotal - discount + tax);
    }

    function addRow(data = {}) {
      const tr = document.createElement('tr');
      tr.innerHTML = rowHtml(tbody.rows.length, data);
      tbody.appendChild(tr);
      reindex();
      recalc();
    }

    document.getElementById('add-quote-item').addEventListener('click', () => addRow());

    tbody.addEventListener('click', (e) => {
      if (e.target.matches('[data-qi="remove"]')) {
        e.target.closest('tr').remove();
        reindex();
        recalc();
      }
    });

    tbody.addEventListener('input', recalc);
    discountEl.addEventListener('input', recalc);

    tbody.addEventListener('change', (e) => {
      const tr = e.target.closest('tr');
      // Choosing a catalogue product fills the label + price but leaves both
      // editable; the values are snapshots, not a live link.
      if (e.target.matches('[data-qi="product"]')) {
        const opt = e.target.selectedOptions[0];
        tr.querySelector('[data-qi="product_id"]').value = e.target.value;
        if (e.target.value) {
          tr.querySelector('[data-qi="label"]').value = opt.textContent.trim();
          tr.querySelector('[data-qi="price"]').value = money(parseFloat(opt.dataset.price || '0'));
        }
        recalc();
      }
      if (e.target.matches('[data-qi="tax_pick"]') && e.target.value) {
        tr.querySelector('[data-qi="tax"]').value = money(parseFloat(e.target.value));
        tr.querySelector('[data-qi="taxable"]').checked = true;
        recalc();
      }
    });

    // Seed with the saved rows, or one blank row on a fresh document.
    if (existing.length) {
      existing.forEach(addRow);
    } else {
      addRow();
    }
  })();
</script>
@endpush
