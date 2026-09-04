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

    {{-- ================= BILLING + INSTALLATION ADDRESS ================= --}}
    @include('partials.subscriber-address', ['value' => $v])

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
  </script>
@endsection
