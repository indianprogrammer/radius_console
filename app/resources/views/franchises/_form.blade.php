{{--
  Shared franchise form fields (create + edit).

  Expects:
    $franchise  App\Models\Franchise  (unsaved instance on create)
    $parents    Collection of franchises selectable as a parent
    $isEdit     bool — hides the seed-only Opening Balance field
--}}
@php $isEdit = $isEdit ?? false; @endphp

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Franchise Details</h4>
    <div class="form-grid">

      <div class="field col-3">
        <label for="code">Code</label>
        <input type="text" name="code" id="code" class="gui-input" maxlength="40"
               value="{{ old('code', $franchise->code) }}" placeholder=" ">
        <span class="hint">Leave blank to auto-generate (FR-0001).</span>
      </div>

      <div class="field col-6">
        <label for="name">Name <em>*</em></label>
        <input type="text" name="name" id="name" class="gui-input" required maxlength="150"
               value="{{ old('name', $franchise->name) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="type">Type <em>*</em></label>
        <select name="type" id="type" class="gui-input" required>
          @foreach (\App\Models\Franchise::TYPES as $val => $label)
            <option value="{{ $val }}" @selected(old('type', $franchise->type ?? 'franchise') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="field col-6">
        <label for="parent_id">Parent Franchise</label>
        <select name="parent_id" id="parent_id" class="gui-input">
          <option value="">— none (reports to ISP) —</option>
          @foreach ($parents as $p)
            <option value="{{ $p->id }}" @selected((int) old('parent_id', $franchise->parent_id) === (int) $p->id)>
              {{ $p->code }} — {{ $p->name }}
            </option>
          @endforeach
        </select>
        <span class="hint">Use for a distributor → franchise → LCO hierarchy.</span>
      </div>

      <div class="field col-3">
        <label for="status">Status <em>*</em></label>
        <select name="status" id="status" class="gui-input" required>
          @foreach (\App\Models\Franchise::STATUSES as $val => $label)
            <option value="{{ $val }}" @selected(old('status', $franchise->status ?? 'active') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Franchise Login</h4>
    <p class="hint">
      @if ($isEdit && $loginUser)
        Login username is <strong>{{ $loginUser->username }}</strong>. Leave password blank to keep it unchanged.
      @else
        Set credentials here so this franchise can sign in to the console. Both fields are required to create a login.
      @endif
    </p>
    <div class="form-grid">
      <div class="field col-6">
        <label for="login_username">Login Username</label>
        <input type="text" name="login_username" id="login_username" class="gui-input" minlength="3" maxlength="80"
               value="{{ old('login_username', $loginUser->username ?? '') }}" placeholder=" " autocomplete="username">
      </div>
      <div class="field col-6">
        <label for="login_password">{{ $isEdit && $loginUser ? 'New Password' : 'Login Password' }}</label>
        <input type="password" name="login_password" id="login_password" class="gui-input" minlength="8" maxlength="255"
               placeholder=" " autocomplete="new-password">
      </div>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Contact</h4>
    <div class="form-grid">

      <div class="field col-4">
        <label for="contact_person">Contact Person</label>
        <input type="text" name="contact_person" id="contact_person" class="gui-input" maxlength="150"
               value="{{ old('contact_person', $franchise->contact_person) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="phone">Phone</label>
        <input type="text" name="phone" id="phone" class="gui-input" maxlength="20"
               value="{{ old('phone', $franchise->phone) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" class="gui-input" maxlength="150"
               value="{{ old('email', $franchise->email) }}" placeholder=" ">
      </div>

      <div class="field col-12">
        <label for="address">Address</label>
        <textarea name="address" id="address" class="gui-input" rows="2" maxlength="500"
                  placeholder=" ">{{ old('address', $franchise->address) }}</textarea>
      </div>

      <div class="field col-4">
        <label for="city">City</label>
        <input type="text" name="city" id="city" class="gui-input" maxlength="100"
               value="{{ old('city', $franchise->city) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="state">State</label>
        <input type="text" name="state" id="state" class="gui-input" maxlength="100"
               value="{{ old('state', $franchise->state) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="pincode">PIN Code</label>
        <input type="text" name="pincode" id="pincode" class="gui-input" maxlength="12"
               value="{{ old('pincode', $franchise->pincode) }}" placeholder=" ">
      </div>

    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Commercials &amp; Compliance</h4>
    <div class="form-grid">

      <div class="field col-3">
        <label for="commission_type">Commission Type <em>*</em></label>
        <select name="commission_type" id="commission_type" class="gui-input" required>
          @foreach (\App\Models\Franchise::COMMISSION_TYPES as $val => $label)
            <option value="{{ $val }}" @selected(old('commission_type', $franchise->commission_type ?? 'percentage') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="field col-3">
        <label for="commission_rate">Commission Rate <em>*</em></label>
        <input type="number" name="commission_rate" id="commission_rate" class="gui-input" step="0.01" min="0" required
               value="{{ old('commission_rate', $franchise->commission_rate ?? 0) }}" placeholder=" ">
        <span class="hint">Percentage of plan price, or a flat amount.</span>
      </div>

      <div class="field col-3">
        <label for="credit_limit">Credit Limit <em>*</em></label>
        <input type="number" name="credit_limit" id="credit_limit" class="gui-input" step="0.01" min="0" required
               value="{{ old('credit_limit', $franchise->credit_limit ?? 0) }}" placeholder=" ">
        <span class="hint">Allowed overdraft beyond the wallet balance.</span>
      </div>

      @if ($isEdit)
        <div class="field col-3">
          <label for="balance_display">Wallet Balance</label>
          <input type="text" id="balance_display" class="gui-input is-locked" readonly
                 value="{{ number_format((float) $franchise->balance, 2) }}" placeholder=" ">
          <span class="hint">System-maintained — changed by deposits &amp; usage.</span>
        </div>
      @else
        <div class="field col-3">
          <label for="balance">Opening Balance</label>
          <input type="number" name="balance" id="balance" class="gui-input" step="0.01"
                 value="{{ old('balance', $franchise->balance ?? 0) }}" placeholder=" ">
          <span class="hint">Seeds the prepaid wallet; afterwards it is system-maintained.</span>
        </div>
      @endif

      <div class="field col-3">
        <label for="gst_number">GST Number</label>
        <input type="text" name="gst_number" id="gst_number" class="gui-input" maxlength="20"
               value="{{ old('gst_number', $franchise->gst_number) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="pan_number">PAN Number</label>
        <input type="text" name="pan_number" id="pan_number" class="gui-input" maxlength="20"
               value="{{ old('pan_number', $franchise->pan_number) }}" placeholder=" ">
      </div>

      <div class="field col-12">
        <label for="notes">Notes</label>
        <textarea name="notes" id="notes" class="gui-input" rows="2" maxlength="1000"
                  placeholder=" ">{{ old('notes', $franchise->notes) }}</textarea>
      </div>

    </div>
  </div>
</div>
