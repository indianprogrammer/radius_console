@extends('layout', ['title' => 'Edit Subscriber'])
@section('content')
  @php
    $e   = $eloquent;
    $get = fn($k, $d = '') => old($k, $e->{$k} ?? $d);
    $services      = [1=>'Broadband',2=>'Cable',3=>'IPTV',4=>'Telephone',5=>'OTT'];
    $billingTypes  = [1=>'Prepaid',2=>'Postpaid',3=>'Demo',4=>'Free'];
    $userTypes     = [1=>'Individual',2=>'Business'];
    $ipModes       = [2=>'DHCP',1=>'Static Ip',3=>'Pool Name',4=>'Pool Name + Static Ip'];
    $accessTypes   = ['pppoe'=>'PPPoE','ipoe'=>'IPoE'];
  @endphp

  <div class="page-header">
    <h1>Edit Subscriber</h1>
    <p class="muted-label">Update the customer's CAF (Customer Application Form) below.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('subscribers.update', $subscriber->id) }}" enctype="multipart/form-data" id="subscriber-form">
    @csrf @method('PUT')

    {{-- ========================= BASIC INFORMATION ========================= --}}
    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Basic Information</h4>
        <div class="form-grid">
          <div class="field col-3">
            <label for="first_name">First Name</label>
            <input type="text" name="first_name" id="first_name" value="{{ $get('first_name') }}" class="gui-input" placeholder=" ">
          </div>
          <div class="field col-3">
            <label for="last_name">Last Name</label>
            <input type="text" name="last_name" id="last_name" value="{{ $get('last_name') }}" class="gui-input" placeholder=" ">
          </div>
          <div class="field col-3">
            <label for="father_or_company">Father Name or Company Name</label>
            <input type="text" name="father_or_company" id="father_or_company" value="{{ $get('father_or_company') }}" class="gui-input" placeholder=" ">
          </div>
          <div class="field col-3">
            <label for="access_type">Access Type</label>
            <select name="access_type" id="access_type" class="gui-input">
              @foreach ($accessTypes as $val => $label)
                <option value="{{ $val }}" @selected($get('access_type','pppoe')===$val)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="field col-3 pppoe-field">
            <label for="pppoe_username">PPPoE Username</label>
            <input type="text" name="pppoe_username" id="pppoe_username" value="{{ $get('pppoe_username') }}" class="gui-input" autocomplete="off" placeholder=" ">
          </div>
          <div class="field col-3 pppoe-field">
            <label for="pppoe_password">PPPoE Password</label>
            <input type="password" name="pppoe_password" id="pppoe_password" value="" class="gui-input" autocomplete="new-password" placeholder=" ">
            <p class="hint">Leave blank to keep the current password.</p>
          </div>
          <div class="field col-3">
            <label for="mobile">Register Mobile</label>
            <input type="text" name="mobile" id="mobile" value="{{ $get('mobile') }}" class="gui-input" placeholder=" ">
          </div>
          <div class="field col-3">
            <label for="email">Register Email</label>
            <input type="email" name="email" id="email" value="{{ $get('email') }}" class="gui-input" placeholder=" ">
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
            <label for="plan_id">Package</label>
            <select name="plan_id" id="plan_id" class="gui-input">
              <option value="">— none —</option>
              @foreach ($plans as $pl)
                <option value="{{ $pl->id }}" @selected(old('plan_id', $subscriber->planId)==$pl->id)>
                  {{ $pl->name }} — {{ number_format($pl->price, 2) }}/{{ $pl->durationLabel() }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="field col-3">
            <label for="billing_type">Billing Type</label>
            <select name="billing_type" id="billing_type" class="gui-input">
              <option value="">Select Billing Type</option>
              @foreach ($billingTypes as $val => $label)
                <option value="{{ $val }}" @selected($get('billing_type')==$val)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="field col-3">
            <label for="gstin">GSTIN</label>
            <input type="text" name="gstin" id="gstin" value="{{ $get('gstin') }}" class="gui-input" placeholder=" ">
          </div>
          <div class="field col-3">
            <label for="po_number">PO Number</label>
            <input type="text" name="po_number" id="po_number" value="{{ $get('po_number') }}" class="gui-input" placeholder=" ">
          </div>
          <div class="field col-3">
            <label for="po_date_display">PO Date</label>
            <input type="text" id="po_date_display" class="gui-input js-dmy"
                   data-target="po_date" inputmode="numeric" maxlength="10"
                   autocomplete="off" placeholder=" ">
            <input type="hidden" name="po_date" id="po_date" value="{{ $get('po_date') ? \Carbon\Carbon::parse($get('po_date'))->format('Y-m-d\TH:i') : '' }}">
            <p class="hint">dd/mm/yy</p>
          </div>
          <div class="field col-3">
            <label for="expiry_display">Expiry Date</label>
            <input type="text" id="expiry_display" class="gui-input js-dmy"
                   data-target="expiry" data-with-time data-default-time="23:59"
                   inputmode="numeric" maxlength="14"
                   autocomplete="off" placeholder=" ">
            <input type="hidden" name="expiry" id="expiry" value="{{ old('expiry', $subscriber->expiry ? \Carbon\Carbon::parse($subscriber->expiry)->format('Y-m-d\TH:i') : '') }}">
            <p class="hint">dd/mm/yy hh:ii</p>
          </div>
          <div class="field col-3">
            <label for="status">Status</label>
            <select name="status" id="status" class="gui-input">
              @foreach (['PROSPECT','KYC_PENDING','READY','ACTIVE','SUSPENDED','EXPIRED','DELETED'] as $st)
                <option value="{{ $st }}" @selected(old('status', $subscriber->status)===$st)>{{ $st }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
    </div>

    {{-- ================= BILLING + INSTALLATION ADDRESS ================= --}}
    @include('partials.subscriber-address', ['value' => $get])

    {{-- ========================= NETWORK INFORMATION ========================= --}}
    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Network Information</h4>
        <div class="form-grid">
          <div class="field col-3">
            <label for="ip_mode">IP Address Mode</label>
            <select name="ip_mode" id="ip_mode" class="gui-input ipmodeDiv">
              @foreach ($ipModes as $val => $label)
                <option value="{{ $val }}" @selected($get('ip_mode','2')==$val)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="field col-3" id="static-ip-wrap">
            <label for="static_ip">Static IP</label>
            <div class="ip-input-group">
              <input type="text" name="static_ip" id="static_ip" value="{{ old('static_ip', $subscriber->staticIp) }}" class="gui-input">
              <button type="button" class="btn btn-icon" onclick="selip()" title="Pick from pool"><i>+</i></button>
            </div>
          </div>
          <div class="field col-3" id="pool-name-wrap" style="display:none;">
            <label for="pool_name">Pool Name</label>
            <input type="text" name="pool_name" id="pool_name" value="{{ $get('pool_name') }}" class="gui-input" placeholder=" ">
          </div>
          <div class="field col-3">
            <label for="mac">MAC Address</label>
            <input type="text" name="mac" id="mac" value="{{ old('mac', $subscriber->mac) }}" class="gui-input">
          </div>
          <div class="field col-2">
            <label class="switch-label">Auto Renew</label>
            <label class="switch">
              <input type="checkbox" name="auto_renew" id="auto_renew" value="1" @checked($get('auto_renew'))>
              <span data-on="Yes" data-off="No"></span>
            </label>
          </div>
          <div class="field col-2">
            <label class="switch-label">Bind MAC</label>
            <label class="switch">
              <input type="checkbox" name="bind_mac" id="bind_mac" value="1" @checked($get('bind_mac'))>
              <span data-on="Yes" data-off="No"></span>
            </label>
          </div>
          <div class="field col-2" id="bind-static-ip-wrap" style="display:none;">
            <label class="switch-label">Bind Static IP</label>
            <label class="switch">
              <input type="checkbox" name="bind_static_ip" id="bind_static_ip" value="1" @checked($get('bind_static_ip'))>
              <span data-on="Yes" data-off="No"></span>
            </label>
          </div>
          <div class="field col-2">
            <label class="switch-label">Exclude MAC Bind</label>
            <label class="switch">
              <input type="checkbox" name="exclude_mac_bind" id="exclude_mac_bind" value="1" @checked($get('exclude_mac_bind'))>
              <span data-on="Yes" data-off="No"></span>
            </label>
          </div>
          <div class="field col-3">
            <label class="switch-label">Don't Suspend</label>
            <label class="switch">
              <input type="checkbox" name="dont_suspend" id="dont_suspend" value="1" @checked($get('dont_suspend'))>
              <span data-on="Yes" data-off="No"></span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="form-actions">
      <a class="btn" href="{{ route('subscribers.index') }}">Cancel</a>
      <button class="btn btn-primary" type="submit">Save Changes</button>
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

    // ── Access type (PPPoE / IPoE) ──────────────────────────────────────
    // PPPoE credentials only apply to PPPoE sessions; hide + disable them
    // for IPoE so they are never submitted.
    function applyAccessType() {
      const isPppoe = document.getElementById('access_type').value === 'pppoe';
      document.querySelectorAll('.pppoe-field').forEach(wrap => {
        wrap.style.display = isPppoe ? '' : 'none';
        wrap.querySelectorAll('input').forEach(input => {
          input.disabled = !isPppoe;
        });
      });
    }
    document.getElementById('access_type').addEventListener('change', applyAccessType);
    applyAccessType();

    // GSTIN is always visible now (user_type field removed)
    function applyUserType() {
      // No-op: GSTIN always visible
    }

    function selip() {
      const bid = document.getElementById('branch_id').value;
      if (!bid) { alert('Please select branch.'); return; }
      window.open('about:blank#selip-branch-' + bid, 'selip', 'width=600,height=500');
    }
  </script>
@endsection
