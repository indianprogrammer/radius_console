@extends('layout', ['title' => 'New Subscriber'])
@section('content')
  @php
    $states = [
      'Andaman and Nicobar Islands','Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chandigarh',
      'Chhattisgarh','Dadra and Nagar Haveli','Daman and Diu','Delhi','Goa','Gujarat','Haryana',
      'Himachal Pradesh','Jammu and Kashmir','Jharkhand','Karnataka','Kerala','Lakshadweep',
      'Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha',
      'Pondicherry','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura',
      'Uttar Pradesh','Uttarakhand','West Bengal',
    ];
    $services      = [1=>'Broadband',2=>'Cable',3=>'IPTV',4=>'Telephone',5=>'OTT'];
    $billingTypes  = [1=>'Prepaid',2=>'Postpaid',3=>'Demo',4=>'Free'];
    $userTypes     = [1=>'Individual',2=>'Business'];
    $ipModes       = [2=>'DHCP',1=>'Static Ip',3=>'Pool Name',4=>'Pool Name + Static Ip'];
    $connTypes     = [1=>'Fiber Connection',2=>'Cat5 Connection',3=>'Wireless',4=>'GPON'];
    $authProtocols = [1=>'Hotspot (IPOE)',2=>'PPPOE'];
    $houseTypes    = ['Rented House with Family','Own House with Family','Rented House with Bachelor'];
    $paymentTypes  = [
      1=>'Cash Payment',2=>'Cheque Payment',3=>'Online Transfer',4=>'Payment Gateway',
      5=>'EDC Machine',6=>'Wallet',7=>'Paytm',8=>'TDS Payment',10=>'PayU Payment',
      11=>'Adjustment Discount',9=>'Other Payment',12=>'Package Adjustment',
      13=>'Api Transaction',14=>'Google Pay',15=>'Phonepe',16=>'Other UPI',17=>'Other',
    ];
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
            <input type="text" name="first_name" id="first_name" value="{{ $v('first_name') }}" class="gui-input" required>
          </div>

          <div class="field col-3">
            <label for="last_name">Last Name <em>*</em></label>
            <input type="text" name="last_name" id="last_name" value="{{ $v('last_name') }}" class="gui-input" required>
          </div>

          <div class="field col-3">
            <label for="father_or_company">Father Name or Company Name</label>
            <input type="text" name="father_or_company" id="father_or_company"
                   value="{{ $v('father_or_company') }}" class="gui-input">
          </div>

          <div class="field col-3">
            <label for="mobile">Register Mobile <em>*</em></label>
            <input type="text" name="mobile" id="mobile" value="{{ $v('mobile') }}" class="gui-input" required>
          </div>

          <div class="field col-3">
            <label for="email">Register Email</label>
            <input type="email" name="email" id="email" value="{{ $v('email') }}" class="gui-input">
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
                <option value="{{ $pl->id }}" @selected($v('plan_id')==$pl->id)>
                  {{ $pl->name }} — {{ number_format($pl->price, 2) }}/{{ $pl->cycle }}
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
            <input type="text" name="gstin" id="gstin" value="{{ $v('gstin') }}" class="gui-input">
          </div>
          <div class="field col-3">
            <label for="po_number">PO Number</label>
            <input type="text" name="po_number" id="po_number" value="{{ $v('po_number') }}" class="gui-input">
          </div>
          <div class="field col-3">
            <label for="po_date">PO Date</label>
            <input type="datetime-local" name="po_date" id="po_date" value="{{ $v('po_date') }}" class="gui-input">
          </div>
          <div class="field col-3">
            <label for="expiry">Expiry Date</label>
            <input type="datetime-local" name="expiry" id="expiry" value="{{ $v('expiry') }}" class="gui-input">
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
              <input type="text" name="static_ip" id="static_ip" value="{{ $v('static_ip') }}" class="gui-input">
              <button type="button" class="btn btn-icon" onclick="selip()" title="Pick from pool">
                <i>+</i>
              </button>
            </div>
          </div>

          <div class="field col-3" id="pool-name-wrap" style="display:none;">
            <label for="pool_name">Pool Name</label>
            <input type="text" name="pool_name" id="pool_name" value="{{ $v('pool_name') }}" class="gui-input">
          </div>

          <div class="field col-3">
            <label for="node_id">Node</label>
            <select name="node_id" id="node_id" class="gui-input">
              <option value="">Select Node</option>
            </select>
          </div>

          <div class="field col-3">
            <label for="pop_id">POP</label>
            <select name="pop_id" id="pop_id" class="gui-input">
              <option value="">Select Pop</option>
            </select>
          </div>

          <div class="field col-3">
            <label for="switch_id">Switch</label>
            <select name="switch_id" id="switch_id" class="gui-input">
              <option value="">Select Switch</option>
            </select>
          </div>

          <div class="field col-3">
            <label for="switch_port">Switch Port</label>
            <select name="switch_port" id="switch_port" class="gui-input">
              <option value="">Select Switch Port</option>
            </select>
          </div>

          <div class="field col-3">
            <label for="connection_type">Connection Type</label>
            <select name="connection_type" id="connection_type" class="gui-input">
              <option value="">Select Connection Type</option>
              @foreach ($connTypes as $val => $label)
                <option value="{{ $val }}" @selected($v('connection_type')==$val)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="field col-3">
            <label for="cable_length">Fiber or Cat5 Length</label>
            <input type="number" min="0" name="cable_length" id="cable_length" value="{{ $v('cable_length') }}"
                   class="gui-input" placeholder="Enter in meters">
          </div>

          <div class="field col-3">
            <label for="domain">Domain Name</label>
            <select name="domain" id="domain" class="gui-input">
              <option value="">Select Domain</option>
            </select>
          </div>

          <div class="field col-3">
            <label for="auth_protocol">Authentication Protocol</label>
            <select name="auth_protocol" id="auth_protocol" class="gui-input">
              <option value="">Select Protocol Type</option>
              @foreach ($authProtocols as $val => $label)
                <option value="{{ $val }}" @selected($v('auth_protocol')==$val)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="field col-2">
            <label class="switch-label">Auto Renew</label>
            <label class="switch">
              <input type="checkbox" name="auto_renew" id="auto_renew" value="1" @checked($v('auto_renew'))>
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

          <div class="field col-3">
            <label for="circuit_id">Circuit Id</label>
            <input type="text" name="circuit_id" id="circuit_id" value="{{ $v('circuit_id') }}" class="gui-input">
          </div>

        </div>
      </div>
    </div>

    {{-- ========================= LOCATION INFORMATION ========================= --}}
    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Location Information</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="country">Country <em>*</em></label>
            <select name="country" id="country" class="gui-input" required>
              <option value="">Select Country</option>
              <option value="India" @selected($v('country','India')=='India')>India (+91)</option>
            </select>
          </div>

          <div class="field col-3">
            <label for="state">State <em>*</em></label>
            <select name="state" id="state" class="gui-input" required>
              <option value="">Select State</option>
              @foreach ($states as $st)
                <option value="{{ $st }}" @selected($v('state')==$st)>{{ $st }}</option>
              @endforeach
            </select>
          </div>

          <div class="field col-3">
            <label for="city">City <em>*</em></label>
            <input type="text" name="city" id="city" value="{{ $v('city') }}" class="gui-input" required>
          </div>

          <div class="field col-3">
            <label for="zip">Zip <em>*</em></label>
            <input type="text" name="zip" id="zip" value="{{ $v('zip') }}" class="gui-input" required>
          </div>

          <div class="field col-3">
            <label for="door_no">Door No</label>
            <input type="text" name="door_no" id="door_no" value="{{ $v('door_no') }}" class="gui-input">
          </div>

          <div class="field col-3">
            <label for="area">Area</label>
            <select name="area" id="area" class="gui-input">
              <option value="">Select Area</option>
            </select>
          </div>

          <div class="field col-3">
            <label for="colony">Colony</label>
            <select name="colony" id="colony" class="gui-input">
              <option value="">Select Colony</option>
            </select>
          </div>

          <div class="field col-3">
            <label for="building">Building</label>
            <select name="building" id="building" class="gui-input">
              <option value="">Select Building</option>
            </select>
          </div>

          <div class="field col-3">
            <label for="billing_address">
              Billing Address <em>*</em>
              <span class="inline-check">
                <input type="checkbox" id="copy_to_install" onclick="copyTextAdr('billing_address','installation_address')">
                Same as Installation Address
              </span>
            </label>
            <textarea name="billing_address" id="billing_address" class="gui-input" rows="3" required>{{ $v('billing_address') }}</textarea>
          </div>

          <div class="field col-3">
            <label for="installation_address">
              Installation Address <em>*</em>
              <span class="inline-check">
                <input type="checkbox" id="copy_to_billing" onclick="copyTextAdr('installation_address','billing_address')">
                Same as Billing Address
              </span>
            </label>
            <textarea name="installation_address" id="installation_address" class="gui-input" rows="3" required>{{ $v('installation_address') }}</textarea>
          </div>

          <div class="field col-3">
            <label for="house_type">House Type</label>
            <select name="house_type" id="house_type" class="gui-input">
              <option value="">Select Connection Type</option>
              @foreach ($houseTypes as $h)
                <option value="{{ $h }}" @selected($v('house_type')==$h)>{{ $h }}</option>
              @endforeach
            </select>
          </div>

          <div class="field col-3">
            <label for="connection_location">Connection Location</label>
            <input type="text" name="connection_location" id="connection_location"
                   value="{{ $v('connection_location') }}" class="gui-input geo_loc">
          </div>

          <div class="field col-3">
            <label for="latitude">Latitude</label>
            <input type="text" name="latitude" id="latitude" value="{{ $v('latitude') }}" class="gui-input latvalue">
          </div>

          <div class="field col-3">
            <label for="longitude">Longitude</label>
            <input type="text" name="longitude" id="longitude" value="{{ $v('longitude') }}" class="gui-input langval">
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

    {{-- ========================= SPECIAL DISCOUNT & ADDITIONAL CHARGES ========================= --}}
    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Special Discount &amp; Additional Charges</h4>
        <button type="button" class="btn btn-primary btn-sm" id="add-special">+ Add Special</button>
        <div class="table-wrap">
          <table class="data-table" id="special-table">
            <thead>
              <tr>
                <th>Reason</th>
                <th>Description</th>
                <th>Approved By</th>
                <th>Amount</th>
                <th>Type</th>
                <th>#</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- ========================= PAYMENTS ========================= --}}
    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Payments</h4>
        <div class="form-grid">
          <div class="field col-3">
            <label for="advance_payment">Advance Payment</label>
            <input type="number" step="0.01" name="advance_payment" id="advance_payment"
                   value="{{ $v('advance_payment') }}" class="gui-input number">
          </div>
          <div class="field col-3">
            <label for="payment_ref_no">Ref No</label>
            <input type="text" name="payment_ref_no" id="payment_ref_no" value="{{ $v('payment_ref_no') }}" class="gui-input">
          </div>
          <div class="field col-3">
            <label for="payment_type">Payment Type</label>
            <select name="payment_type" id="payment_type" class="gui-input">
              <option value="">Select Payment Type</option>
              @foreach ($paymentTypes as $val => $label)
                <option value="{{ $val }}" @selected($v('payment_type')==$val)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="field col-3">
            <label for="payment_comment">Comment</label>
            <input type="text" name="payment_comment" id="payment_comment"
                   value="{{ $v('payment_comment') }}" class="gui-input">
          </div>
        </div>
      </div>
    </div>

    <div class="form-actions">
      <a class="btn" href="{{ route('subscribers.index') }}">Cancel</a>
      <button class="btn btn-primary" type="submit">Provision Subscriber</button>
    </div>
  </form>

  <script>
    function copyTextAdr(fromId, toId) {
      const from = document.getElementById(fromId);
      const to   = document.getElementById(toId);
      if (from && to) to.value = from.value;
    }
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
    // GSTIN is always visible now (user_type field removed)
    function applyUserType() {
      // No-op: GSTIN always visible
    }
    let specialCount = 0;
    const specialTbody = document.querySelector('#special-table tbody');
    document.getElementById('add-special').addEventListener('click', () => {
      if (specialCount >= 10) return;
      specialCount++;
      const row = document.createElement('tr');
      row.innerHTML = `
        <td><input type="text" name="special[${specialCount}][reason]" class="gui-input"></td>
        <td><input type="text" name="special[${specialCount}][desc]"  class="gui-input"></td>
        <td><select name="special[${specialCount}][approved_by]" class="gui-input"><option value="">Select Approved By</option><option value="336">mayank</option></select></td>
        <td><input type="number" step="0.01" name="special[${specialCount}][amount]" class="gui-input" style="width:90px;"></td>
        <td><select name="special[${specialCount}][type]" class="gui-input"><option value="1">Special Discount</option><option value="2">Additional Charges</option></select></td>
        <td><button type="button" class="btn btn-danger btn-sm remove-special">X</button></td>
      `;
      specialTbody.appendChild(row);
    });
    specialTbody.addEventListener('click', e => {
      if (e.target.classList.contains('remove-special')) {
        e.target.closest('tr').remove();
        specialCount = Math.max(0, specialCount - 1);
      }
    });

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
    let productCache = [];

    async function biLoadProducts(q = '') {
      try {
        const url = new URL(PRODUCT_AUTOCOMPLETE_URL, window.location.origin);
        if (q) url.searchParams.set('q', q);
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
          <div class="bi-product-picker">
            <input type="text" name="billing_items[${i}][label]" data-bi="label"
                   value="${data.label ?? ''}" class="gui-input"
                   list="bi-products-list-${i}"
                   placeholder="Type to search products…" autocomplete="off" required>
            <input type="hidden" name="billing_items[${i}][product_id]" data-bi="product_id" value="${data.product_id ?? ''}">
            <input type="hidden" name="billing_items[${i}][description]" data-bi="description" value="${(data.description ?? '').replace(/"/g,'&quot;')}">
            <datalist id="bi-products-list-${i}"></datalist>
            <button type="button" class="btn btn-icon bi-pick-product" title="Pick from Products">🔍</button>
          </div>
        </td>
        <td>
          <select name="billing_items[${i}][type]" data-bi="type" class="gui-input">
            <option value="one-time"   ${typeOpt('one-time')}>one-time</option>
            <option value="recurring"  ${typeOpt('recurring')}>recurring</option>
            <option value="refundable" ${typeOpt('refundable')}>refundable</option>
          </select>
        </td>
        <td><input type="number" step="0.01" min="0" name="billing_items[${i}][amount]" data-bi="amount" value="${data.amount ?? ''}" class="gui-input" style="width:100px;" placeholder="0.00"></td>
        <td><input type="number" min="1" name="billing_items[${i}][qty]" data-bi="qty" value="${data.qty ?? 1}" class="gui-input" style="width:60px;"></td>
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

      // Live search → populate the datalist for this row
      const labelInput = row.querySelector('[data-bi="label"]');
      const datalist = row.querySelector('datalist');
      let debounce;
      labelInput.addEventListener('input', () => {
        clearTimeout(debounce);
        const term = labelInput.value;
        debounce = setTimeout(async () => {
          const products = await biLoadProducts(term);
          datalist.innerHTML = products.map(p =>
            `<option value="${p.name.replace(/"/g,'&quot;')}" data-pid="${p.id}">`
          ).join('');
        }, 200);
      });

      // When the user picks a value, autofill the row from cache if available
      labelInput.addEventListener('change', async () => {
        const typed = labelInput.value;
        let products = productCache;
        if (!products.length) products = await biLoadProducts('');
        const match = products.find(p => p.name === typed);
        if (match) biApplyProduct(row, match);
      });

      // Picker button → opens dropdown menu to pick from full product list
      row.querySelector('.bi-pick-product').addEventListener('click', async () => {
        const products = await biLoadProducts('');
        if (!products.length) { alert('No active products found. Create one under Products & Services first.'); return; }
        const menu = document.createElement('select');
        menu.size = Math.min(products.length, 8);
        menu.style.position = 'absolute';
        menu.style.zIndex = 9999;
        menu.innerHTML = '<option value="">— Pick a product —</option>' + products.map(p =>
          `<option value="${p.id}">${p.name} (${p.category} — ${p.default_amount})</option>`
        ).join('');
        menu.addEventListener('change', () => {
          if (!menu.value) return;
          const picked = products.find(p => String(p.id) === menu.value);
          if (picked) biApplyProduct(row, picked);
          menu.remove();
        });
        menu.addEventListener('blur', () => setTimeout(() => menu.remove(), 150));
        const btn = row.querySelector('.bi-pick-product');
        btn.parentElement.appendChild(menu);
        menu.focus();
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

@push('styles')
  <style>
    .page-header { margin: 0 0 1rem; }
    .page-header h1 { margin: 0 0 .25rem; font-size: 1.4rem; }
    /* ── Form Layout ─────────────────────────────────────────── */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 0.85rem 1rem;
      align-items: start;
    }

    /* Default column spans */
    .field { display: flex; flex-direction: column; }
    .field.col-1  { grid-column: span 1; }
    .field.col-2  { grid-column: span 2; }
    .field.col-3  { grid-column: span 3; }
    .field.col-4  { grid-column: span 4; }
    .field.col-5  { grid-column: span 5; }
    .field.col-6  { grid-column: span 6; }
    .field.col-7  { grid-column: span 7; }
    .field.col-8  { grid-column: span 8; }
    .field.col-9  { grid-column: span 9; }
    .field.col-10 { grid-column: span 10; }
    .field.col-11 { grid-column: span 11; }
    .field.col-12 { grid-column: span 12; }

    /* Full width on narrow containers (panels) */
    .panel-body .form-grid { grid-template-columns: repeat(6, 1fr); }
    .panel-body .field.col-3  { grid-column: span 2; } /* 2 per row */
    .panel-body .field.col-2  { grid-column: span 1; } /* 1 per row */
    .panel-body .field.col-12 { grid-column: span 6; }

    /* ── Field Elements ─────────────────────────────────────── */
    .field label {
      font-weight: 600;
      font-size: .8rem;
      margin-bottom: .3rem;
      color: var(--color-text);
      display: block;
      line-height: 1.3;
    }
    .field label em { color: #c0392b; font-style: normal; }
    .field .required-star { color: #c0392b; margin-left: 2px; }

    .gui-input {
      width: 100%;
      padding: .55rem .65rem;
      border: 1px solid var(--color-border);
      border-radius: 6px;
      background: var(--color-bg);
      color: var(--color-text);
      font: inherit;
      font-size: .9rem;
      transition: border-color .15s, box-shadow .15s;
    }
    .gui-input:focus {
      outline: none;
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 20%, transparent);
    }
    .gui-input::placeholder { color: var(--color-text-muted); opacity: .6; }

    /* Textarea */
    textarea.gui-input { resize: vertical; min-height: 80px; }

    /* ── Hints & inline checks ─────────────────────────────── */
    .hint { font-size: .73rem; color: var(--color-text-muted); margin-top: .2rem; }
    .inline-check {
      font-weight: 400;
      font-size: .73rem;
      display: inline-flex;
      align-items: center;
      gap: .25rem;
      margin-left: .5rem;
      color: var(--color-text-muted);
    }
    .inline-check input { width: auto; }

    /* ── IP input group ────────────────────────────────────── */
    .ip-input-group { display: flex; }
    .ip-input-group .gui-input {
      border-right: 0;
      border-top-right-radius: 0;
      border-bottom-right-radius: 0;
    }
    .btn-icon {
      border: 1px solid var(--color-border);
      border-left: 0;
      border-top-left-radius: 0;
      border-bottom-left-radius: 0;
      padding: 0 .7rem;
      cursor: pointer;
      background: var(--color-surface-2);
      font-size: 1rem;
      color: var(--color-text);
      white-space: nowrap;
    }
    .btn-icon:hover { background: var(--color-border); }

    /* ── Toggle Switches ────────────────────────────────────── */
    .switch-label {
      font-weight: 600;
      font-size: .8rem;
      margin-bottom: .3rem;
      display: block;
      color: var(--color-text);
    }
    .switch { position: relative; display: inline-block; width: 52px; height: 28px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .switch > span {
      position: absolute; cursor: pointer; inset: 0;
      background: #ccc; border-radius: 30px; transition: .25s;
    }
    .switch > span::before {
      content: ""; position: absolute;
      height: 20px; width: 20px; left: 4px; top: 4px;
      background: #fff; border-radius: 50%; transition: .25s;
    }
    .switch input:checked + span { background: var(--color-primary); }
    .switch input:checked + span::before { transform: translateX(24px); }
    .switch > span::after {
      content: attr(data-off); position: absolute;
      top: 50%; transform: translateY(-50%);
      right: 7px; color: #fff; font-size: .62rem; font-weight: 700;
    }
    .switch input:checked + span::after { content: attr(data-on); right: auto; left: 7px; }

    /* ── Data Table (Special Charges) ─────────────────────── */
    .table-wrap { overflow-x: auto; margin-top: .75rem; border-radius: 6px; border: 1px solid var(--color-border); }
    .data-table { width: 100%; border-collapse: collapse; font-size: .83rem; }
    .data-table th, .data-table td { padding: .5rem .6rem; border: 1px solid var(--color-border); text-align: left; vertical-align: middle; }
    .data-table thead th { background: var(--color-primary); color: #fff; font-weight: 600; white-space: nowrap; }
    .data-table tbody tr:nth-child(even) { background: color-mix(in srgb, var(--color-primary) 4%, transparent); }
    .data-table .gui-input { padding: .4rem .5rem; font-size: .83rem; }
    .section-title-row { display: flex; align-items: baseline; gap: 1rem; margin-bottom: .5rem; flex-wrap: wrap; }
    .section-title-row h4 { margin: 0; }
    .billing-items-toolbar { display: flex; align-items: center; gap: 1rem; margin-bottom: .75rem; }
    #billing-items-total { color: var(--color-text-muted); font-size: .85rem; }
    .bi-product-picker { display: flex; align-items: center; gap: .25rem; }
    .bi-product-picker input[type="text"] { flex: 1 1 auto; min-width: 0; }
    .bi-product-picker .bi-pick-product { padding: .3rem .5rem; font-size: .85rem; }

    /* ── Panels ───────────────────────────────────────────── */
    .panel { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 10px; margin-bottom: 1rem; }
    .panel-body { padding: 1rem 1.25rem; }
    .section-title { margin: 0 0 1rem; padding-bottom: .5rem; border-bottom: 2px solid var(--color-primary); font-size: 1rem; font-weight: 700; color: var(--color-primary); }

    /* ── Form Actions ─────────────────────────────────────── */
    .form-actions { display: flex; gap: .6rem; justify-content: flex-end; padding: 1.5rem 0 2rem; align-items: center; }
    .form-actions .btn { min-width: 110px; }

    /* ── Alerts ───────────────────────────────────────────── */
    .alert { padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
    .alert-error { background: #fdecea; color: #c0392b; border: 1px solid #f5b7b1; }
    .alert ul { margin: .25rem 0 0 1.25rem; }
    .alert ul li { margin-bottom: .15rem; }

    /* ── Buttons ───────────────────────────────────────────── */
    .btn-sm { padding: .3rem .55rem; font-size: .8rem; }
    .btn-danger { background: #c0392b; color: #fff; border: none; }
    .btn-danger:hover { background: #a93226; }

    /* ── Responsive Breakpoints ────────────────────────────── */
    @media (max-width: 1280px) {
      .panel-body .form-grid { grid-template-columns: repeat(4, 1fr); }
      .panel-body .field.col-3 { grid-column: span 2; }
      .panel-body .field.col-2 { grid-column: span 1; }
    }

    @media (max-width: 1024px) {
      .panel-body .form-grid { grid-template-columns: repeat(3, 1fr); }
      .panel-body .field.col-3 { grid-column: span 1; }
      .panel-body .field.col-2 { grid-column: span 1; }
    }

    @media (max-width: 768px) {
      .panel-body { padding: .75rem 1rem; }
      .panel-body .form-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem .75rem; }
      .panel-body .field.col-3,
      .panel-body .field.col-2,
      .panel-body .field.col-12 { grid-column: span 1; }
      .section-title { font-size: .9rem; }
    }

    @media (max-width: 540px) {
      .panel-body .form-grid { grid-template-columns: 1fr; }
      .panel-body .field,
      .panel-body .field.col-1,
      .panel-body .field.col-2,
      .panel-body .field.col-3,
      .panel-body .field.col-4,
      .panel-body .field.col-5,
      .panel-body .field.col-6,
      .panel-body .field.col-12 { grid-column: span 1; }
      .form-actions { flex-direction: column-reverse; align-items: stretch; }
      .form-actions .btn { width: 100%; }
      .table-wrap { font-size: .78rem; }
      .data-table th, .data-table td { padding: .4rem .45rem; }
    }
  </style>
@endpush
