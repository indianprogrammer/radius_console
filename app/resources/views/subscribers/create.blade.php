@extends('layout', ['title' => 'New Subscriber'])
@section('content')
  @php
    $services      = [1=>'Broadband',2=>'Cable',3=>'IPTV',4=>'Telephone',5=>'OTT'];
    $billingTypes  = [1=>'Prepaid',2=>'Postpaid',3=>'Demo',4=>'Free'];
    $userTypes     = [1=>'Individual',2=>'Business'];
    $ipModes       = [2=>'DHCP',1=>'Static Ip',3=>'Pool Name',4=>'Pool Name + Static Ip'];
    $accessTypes   = ['pppoe'=>'PPPoE','ipoe'=>'IPoE'];
    $v = fn($k,$d='') => old($k, $d);
  @endphp

  <div class="page-header">
    <h1>Register New Subscriber</h1>
    <p class="muted-label">Complete the customer's CAF (Customer Application Form) below.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('subscribers.store') }}" enctype="multipart/form-data" id="subscriber-form">
    @csrf

    {{-- ========================= BASIC INFORMATION ========================= --}}
    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Basic Information</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="first_name">First Name <em>*</em></label>
            <input type="text" name="first_name" id="first_name" value="{{ $v('first_name') }}" class="gui-input" required placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="last_name">Last Name <em>*</em></label>
            <input type="text" name="last_name" id="last_name" value="{{ $v('last_name') }}" class="gui-input" required placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="father_or_company">Father Name or Company Name</label>
            <input type="text" name="father_or_company" id="father_or_company"
                   value="{{ $v('father_or_company') }}" class="gui-input" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="access_type">Access Type <em>*</em></label>
            <select name="access_type" id="access_type" class="gui-input" required>
              @foreach ($accessTypes as $val => $label)
                <option value="{{ $val }}" @selected($v('access_type','pppoe')===$val)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="field col-3 pppoe-field">
            <label for="pppoe_username">PPPoE Username <em>*</em></label>
            <input type="text" name="pppoe_username" id="pppoe_username"
                   value="{{ $v('pppoe_username') }}" class="gui-input" autocomplete="off" placeholder=" ">
          </div>

          <div class="field col-3 pppoe-field">
            <label for="pppoe_password">PPPoE Password <em>*</em></label>
            <input type="password" name="pppoe_password" id="pppoe_password"
                   value="{{ $v('pppoe_password') }}" class="gui-input" autocomplete="new-password" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="mobile">Register Mobile <em>*</em></label>
            <input type="text" name="mobile" id="mobile" value="{{ $v('mobile') }}" class="gui-input" required placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="email">Register Email</label>
            <input type="email" name="email" id="email" value="{{ $v('email') }}" class="gui-input" placeholder=" ">
          </div>

        </div>
      </div>
    </div>

    {{-- ========================= BILLING INFORMATION ========================= --}}
    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Billing Information</h4>
        <div class="form-grid">
          <div class="field col-3">
            <label for="plan_id">Package <em>*</em></label>
            <select name="plan_id" id="plan_id" class="gui-input" required>
              <option value="">Select Package</option>
              @foreach ($plans as $pl)
                <option value="{{ $pl->id }}"
                        data-duration="{{ $pl->duration }}"
                        data-duration-unit="{{ $pl->durationUnit }}"
                        @selected($v('plan_id')==$pl->id)>
                  {{ $pl->name }} — {{ number_format($pl->price, 2) }}/{{ $pl->durationLabel() }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="field col-3">
            <label for="billing_type">Billing Type <em>*</em></label>
            <select name="billing_type" id="billing_type" class="gui-input" required>
              <option value="">Select Billing Type</option>
              @foreach ($billingTypes as $val => $label)
                <option value="{{ $val }}" @selected($v('billing_type')==$val)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="field col-3">
            <label for="gstin">GSTIN</label>
            <input type="text" name="gstin" id="gstin" value="{{ $v('gstin') }}" class="gui-input" placeholder=" ">
          </div>
          <div class="field col-3">
            <label for="po_number">PO Number</label>
            <input type="text" name="po_number" id="po_number" value="{{ $v('po_number') }}" class="gui-input" placeholder=" ">
          </div>
          <div class="field col-3">
            <label for="po_date_display">PO Date</label>
            <input type="text" id="po_date_display" class="gui-input js-dmy"
                   data-target="po_date" inputmode="numeric" maxlength="10"
                   autocomplete="off" placeholder=" ">
            <input type="hidden" name="po_date" id="po_date" value="{{ $v('po_date') }}">
            <p class="hint">dd/mm/yy</p>
          </div>
          <div class="field col-3">
            <label for="expiry_display">Expiry Date</label>
            <input type="text" id="expiry_display" class="gui-input js-dmy"
                   data-target="expiry" data-with-time data-default-time="23:59"
                   inputmode="numeric" maxlength="14"
                   autocomplete="off" placeholder=" ">
            <input type="hidden" name="expiry" id="expiry" value="{{ $v('expiry') }}">
            <p class="hint">dd/mm/yy hh:ii — auto-filled from the package duration</p>
          </div>
        </div>
      </div>
    </div>

    {{-- ========================= BILLING ITEMS (DYNAMIC) ========================= --}}
    <div class="panel">
      <div class="panel-body">
        <div class="section-title-row">
          <h4 class="section-title">Billing Items</h4>
          <div class="hint">Add refundable, one-time, or recurring line items. They will be auto-added to the subscriber's invoice.</div>
        </div>
        <div class="billing-items-toolbar">
          <button type="button" class="btn btn-primary btn-sm" id="add-billing-item">+ Add Billing Item</button>
          <span class="muted-label" id="billing-items-total">Total: 0.00</span>
        </div>
        <div class="table-wrap">
          <table class="data-table" id="billing-items-table">
            <thead>
              <tr>
                <th style="min-width:200px;">Product / Label</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Qty</th>
                <th>Taxable</th>
                <th>Cycle</th>
                <th>Refundable</th>
                <th>Status</th>
                <th>#</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- ========================= NETWORK INFORMATION ========================= --}}
    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Network Information</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="ip_mode">IP Address Mode <em>*</em></label>
            <select name="ip_mode" id="ip_mode" class="gui-input ipmodeDiv" required>
              @foreach ($ipModes as $val => $label)
                <option value="{{ $val }}" @selected($v('ip_mode','2')==$val)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="field col-3" id="static-ip-wrap">
            <label for="static_ip">Static IP</label>
            <div class="ip-input-group">
              <input type="text" name="static_ip" id="static_ip" value="{{ $v('static_ip') }}" class="gui-input" placeholder=" ">
              <button type="button" class="btn btn-icon" onclick="selip()" title="Pick from pool">
                <i>+</i>
              </button>
            </div>
          </div>

          <div class="field col-3" id="pool-name-wrap" style="display:none;">
            <label for="pool_name">Pool Name</label>
            <input type="text" name="pool_name" id="pool_name" value="{{ $v('pool_name') }}" class="gui-input" placeholder=" ">
          </div>

          <div class="field col-2">
            <label class="switch-label">Auto Renew</label>
            <label class="switch">
              {{-- Default comes from Settings > Subscribers; old() wins on redisplay. --}}
              <input type="checkbox" name="auto_renew" id="auto_renew" value="1"
                     @checked($v('auto_renew', \App\Models\Setting::bool('subscribers.auto_renew_default') ? '1' : ''))>
              <span data-on="Yes" data-off="No"></span>
            </label>
          </div>

          <div class="field col-2">
            <label class="switch-label">Bind MAC</label>
            <label class="switch">
              <input type="checkbox" name="bind_mac" id="bind_mac" value="1" @checked($v('bind_mac'))>
              <span data-on="Yes" data-off="No"></span>
            </label>
          </div>

          <div class="field col-2" id="bind-static-ip-wrap" style="display:none;">
            <label class="switch-label">Bind Static IP</label>
            <label class="switch">
              <input type="checkbox" name="bind_static_ip" id="bind_static_ip" value="1" @checked($v('bind_static_ip'))>
              <span data-on="Yes" data-off="No"></span>
            </label>
          </div>

          <div class="field col-2">
            <label class="switch-label">Exclude MAC Bind</label>
            <label class="switch">
              <input type="checkbox" name="exclude_mac_bind" id="exclude_mac_bind" value="1" @checked($v('exclude_mac_bind'))>
              <span data-on="Yes" data-off="No"></span>
            </label>
          </div>

          <div class="field col-3">
            <label class="switch-label">Don't Suspend</label>
            <label class="switch">
              <input type="checkbox" name="dont_suspend" id="dont_suspend" value="1" @checked($v('dont_suspend'))>
              <span data-on="Yes" data-off="No"></span>
            </label>
          </div>

        </div>
      </div>
    </div>

    {{-- ========================= UPLOAD FILES ========================= --}}
    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Upload Files</h4>
        <div class="form-grid">
          <div class="field col-3">
            <label for="caf_form">CAF Form</label>
            <input type="file" name="caf_form" id="caf_form" class="gui-input" accept=".jpg,.jpeg,.png,.gif,.pdf">
          </div>
          <div class="field col-3">
            <label for="address_proof">Address Proof</label>
            <input type="file" name="address_proof" id="address_proof" class="gui-input" accept=".jpg,.jpeg,.png,.gif,.pdf">
          </div>
          <div class="field col-3">
            <label for="identity_proof">Identity Proof</label>
            <input type="file" name="identity_proof" id="identity_proof" class="gui-input" accept=".jpg,.jpeg,.png,.gif,.pdf">
          </div>
          <div class="field col-3">
            <label for="customer_pic">Customer Pic</label>
            <input type="file" name="customer_pic" id="customer_pic" class="gui-input" accept=".jpg,.jpeg,.png,.gif,.pdf">
          </div>
          <div class="field col-12">
            <p class="hint">Note : Allowed Types (jpeg, jpg, png, gif, pdf), Max size of file : 4MB</p>
          </div>
        </div>
      </div>
    </div>

    <div class="form-actions">
      <a class="btn" href="{{ route('subscribers.index') }}">Cancel</a>
      <button class="btn btn-primary" type="submit">Provision Subscriber</button>
    </div>
  </form>

  @include('partials.dmy-date-script')

  <script>
    function applyIpMode() {
      const mode = document.getElementById('ip_mode').value;
      const showStatic = mode === '1' || mode === '4';
      const showPool   = mode === '3' || mode === '4';
      document.getElementById('static-ip-wrap').style.display      = showStatic ? '' : 'none';
      document.getElementById('pool-name-wrap').style.display      = showPool   ? '' : 'none';
      document.getElementById('bind-static-ip-wrap').style.display = showStatic ? '' : 'none';
    }
    document.getElementById('ip_mode').addEventListener('change', applyIpMode);
    applyIpMode();

    // ── Access type (PPPoE / IPoE) ─────────────────────────────────────
    // PPPoE credentials only make sense for PPPoE sessions; hide + disable
    // them for IPoE so they are never submitted.
    function applyAccessType() {
      const isPppoe = document.getElementById('access_type').value === 'pppoe';
      document.querySelectorAll('.pppoe-field').forEach(wrap => {
        wrap.style.display = isPppoe ? '' : 'none';
        wrap.querySelectorAll('input').forEach(input => {
          input.disabled = !isPppoe;
          input.required = isPppoe;
        });
      });
    }
    document.getElementById('access_type').addEventListener('change', applyAccessType);
    applyAccessType();

    // ── Auto expiry from plan duration ─────────────────────────────────
    // Selecting a package derives Expiry Date = now + plan duration
    // (days|months). Stops auto-deriving once the user edits expiry by hand.
    (function () {
      const planEl   = document.getElementById('plan_id');
      const expiryEl = document.getElementById('expiry');            // hidden ISO
      const displayEl = document.getElementById('expiry_display');   // dd/mm/yy
      if (!planEl || !expiryEl) return;

      let manualExpiry = expiryEl.value !== '';
      if (displayEl) displayEl.addEventListener('input', () => { manualExpiry = true; });

      function applyPlanExpiry() {
        if (manualExpiry) return;
        const opt = planEl.selectedOptions[0];
        const n = parseInt(opt?.dataset.duration ?? '', 10);
        const unit = opt?.dataset.durationUnit;
        if (!opt?.value || !Number.isFinite(n) || n <= 0) {
          window.dmySetDate('expiry', null);
          return;
        }

        const d = new Date();
        if (unit === 'months') {
          const day = d.getDate();
          d.setMonth(d.getMonth() + n);
          // Clamp overflow (e.g. Jan 31 + 1 month) back to end of target month.
          if (d.getDate() < day) d.setDate(0);
        } else {
          d.setDate(d.getDate() + n);
        }
        // Time-of-day is inherited from `new Date()`, i.e. the moment of
        // provisioning, so the subscriber gets the full duration to the minute.
        d.setSeconds(0, 0);
        window.dmySetDate('expiry', d);
      }

      planEl.addEventListener('change', applyPlanExpiry);
      applyPlanExpiry();
    })();

    // GSTIN is always visible now (user_type field removed)
    function checkUserExist(v) {
      document.getElementById('user_avail_msg').textContent = v ? 'Checking…' : '';
    }
    function checkMblExist(v) {
      document.getElementById('mbl_avail_msg').textContent = v ? 'Checking…' : '';
    }
    function selip() {
      const bid = document.getElementById('branch_id').value;
      if (!bid) { alert('Please select branch.'); return; }
      window.open('about:blank#selip-branch-' + bid, 'selip', 'width=600,height=500');
    }

    // ── Billing Items (refundable | one-time | recurring) ──────────────
    let biCount = 0;
    const biTbody = document.querySelector('#billing-items-table tbody');
    const biTotalEl = document.getElementById('billing-items-total');
    const PRODUCT_AUTOCOMPLETE_URL = "{{ route('products.autocomplete') }}";

    // Cache products so we can autofill the rest of the row on pick.
    // Load all products immediately on page load.
    let productCache = (async () => {
      try {
        const res = await fetch(PRODUCT_AUTOCOMPLETE_URL, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (res.ok) return await res.json();
      } catch (e) {}
      return [];
    })();

    async function biLoadProducts(q = '') {
      // If query is empty, return the preloaded cache
      if (!q) return await productCache;
      try {
        const url = new URL(PRODUCT_AUTOCOMPLETE_URL, window.location.origin);
        url.searchParams.set('q', q);
        const res = await fetch(url.toString(), {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) return [];
        return await res.json();
      } catch (e) {
        return [];
      }
    }

    function biRecalcTotal() {
      let total = 0;
      biTbody.querySelectorAll('tr').forEach(tr => {
        const amt = parseFloat(tr.querySelector('[data-bi="amount"]')?.value) || 0;
        const qty = parseInt(tr.querySelector('[data-bi="qty"]')?.value) || 1;
        total += amt * qty;
      });
      if (biTotalEl) biTotalEl.textContent = 'Total: ' + total.toFixed(2);
    }

    function biApplyProduct(row, product) {
      // Fill fields from a product row. Existing values are kept when the
      // product is missing fields.
      const labelInput = row.querySelector('[data-bi="label"]');
      if (labelInput) labelInput.value = product.name;
      const descInput = row.querySelector('[data-bi="description"]');
      if (descInput && product.description) descInput.value = product.description;
      const amountInput = row.querySelector('[data-bi="amount"]');
      if (amountInput && product.default_amount !== undefined) amountInput.value = product.default_amount;
      const typeSelect = row.querySelector('[data-bi="type"]');
      if (typeSelect && product.category) {
        // Map product category to billing_item type
        const map = { 'one-time': 'one-time', 'recurring': 'recurring' };
        typeSelect.value = map[product.category] || 'one-time';
      }
      const productIdInput = row.querySelector('[data-bi="product_id"]');
      if (productIdInput) productIdInput.value = product.id;
      biRecalcTotal();
    }

    function biAddRow(data = {}) {
      const i = biCount++;
      const typeOpt = (val) => data.type === val ? 'selected' : '';
      const cycleOpt = (val) => data.billing_cycle === val ? 'selected' : '';
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>
          <input type="text" name="billing_items[${i}][label]" data-bi="label"
                 value="${data.label ?? ''}" class="gui-input"
                 list="bi-products-list-${i}"
                 placeholder="e.g. Security Deposit, IP Charge" autocomplete="off" required>
          <input type="hidden" name="billing_items[${i}][product_id]" data-bi="product_id" value="${data.product_id ?? ''}">
          <input type="hidden" name="billing_items[${i}][description]" data-bi="description" value="${(data.description ?? '').replace(/"/g,'&quot;')}">
          <datalist id="bi-products-list-${i}"></datalist>
        </td>
        <td>
          <select name="billing_items[${i}][type]" data-bi="type" class="gui-input">
            <option value="one-time"   ${typeOpt('one-time')}>one-time</option>
            <option value="recurring"  ${typeOpt('recurring')}>recurring</option>
            <option value="refundable" ${typeOpt('refundable')}>refundable</option>
          </select>
        </td>
        <td><input type="number" step="0.01" min="0" name="billing_items[${i}][amount]" data-bi="amount" value="${data.amount ?? ''}" class="gui-input" style="width:100px;" placeholder="0.00"></td>
        <td><input type="number" min="1" name="billing_items[${i}][qty]" data-bi="qty" value="${data.qty ?? 1}" class="gui-input" style="width:60px;" placeholder=" "></td>
        <td>
          <select name="billing_items[${i}][taxable]" data-bi="taxable" class="gui-input" style="width:80px;">
            <option value="1" ${data.taxable !== '0' ? 'selected' : ''}>Yes</option>
            <option value="0" ${data.taxable === '0' ? 'selected' : ''}>No</option>
          </select>
        </td>
        <td>
          <select name="billing_items[${i}][billing_cycle]" data-bi="billing_cycle" class="gui-input" style="width:110px;">
            <option value="">—</option>
            <option value="monthly"   ${cycleOpt('monthly')}>Monthly</option>
            <option value="quarterly" ${cycleOpt('quarterly')}>Quarterly</option>
            <option value="yearly"    ${cycleOpt('yearly')}>Yearly</option>
          </select>
        </td>
        <td>
          <select name="billing_items[${i}][is_refundable]" data-bi="is_refundable" class="gui-input" style="width:90px;">
            <option value="0" ${data.is_refundable === '1' ? 'selected' : ''}>No</option>
            <option value="1" ${data.is_refundable === '1' ? 'selected' : ''}>Yes</option>
          </select>
        </td>
        <td>
          <select name="billing_items[${i}][status]" data-bi="status" class="gui-input" style="width:90px;">
            <option value="active"   ${data.status !== 'inactive' ? 'selected' : ''}>Active</option>
            <option value="inactive" ${data.status === 'inactive' ? 'selected' : ''}>Inactive</option>
          </select>
        </td>
        <td><button type="button" class="btn btn-danger btn-sm remove-bi">X</button></td>
      `;
      biTbody.appendChild(row);
      biRecalcTotal();

      // Simple datalist autocomplete: type to search, picks autofill the row
      const labelInput = row.querySelector('[data-bi="label"]');
      const datalist   = row.querySelector('datalist');
      let debounce;
      labelInput.addEventListener('input', () => {
        clearTimeout(debounce);
        const term = labelInput.value;
        debounce = setTimeout(async () => {
          const products = await biLoadProducts(term);
          datalist.innerHTML = products.map(p =>
            `<option value="${p.name.replace(/"/g,'&quot;')}">`
          ).join('');
        }, 200);
      });
      labelInput.addEventListener('change', async () => {
        const typed = labelInput.value;
        let products = productCache;
        if (!products.length) products = await biLoadProducts('');
        const match = products.find(p => p.name === typed);
        if (match) biApplyProduct(row, match);
      });
    }

    document.getElementById('add-billing-item').addEventListener('click', () => {
      if (biCount >= 50) return;
      biAddRow();
    });

    biTbody.addEventListener('input', e => biRecalcTotal());

    biTbody.addEventListener('click', e => {
      if (e.target.classList.contains('remove-bi')) {
        e.target.closest('tr').remove();
        biRecalcTotal();
      }
    });

    // Re-hydrate from old() on validation failure
    @if (old('billing_items'))
      @foreach (old('billing_items') as $row)
        biAddRow(@json($row));
      @endforeach
    @endif
  </script>
@endsection
