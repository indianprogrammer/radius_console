{{--
  Shared staff form fields (create + edit).

  Expects:
    $member          App\Models\Staff (unsaved instance on create)
    $managers        Collection of staff selectable as reporting manager
    $franchises      Collection of franchises
    $groups          Collection of staff groups
    $selectedGroups  array<int> of currently selected group ids
--}}
@php $selectedGroups = $selectedGroups ?? []; @endphp

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Employee Details</h4>
    <div class="form-grid">

      <div class="field col-3">
        <label for="code">Employee Code</label>
        <input type="text" name="code" id="code" class="gui-input" maxlength="40"
               value="{{ old('code', $member->code) }}" placeholder=" ">
        <span class="hint">Leave blank to auto-generate (ST-0001).</span>
      </div>

      <div class="field col-5">
        <label for="name">Full Name <em>*</em></label>
        <input type="text" name="name" id="name" class="gui-input" required maxlength="150"
               value="{{ old('name', $member->name) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="designation">Designation</label>
        <input type="text" name="designation" id="designation" class="gui-input" maxlength="100"
               value="{{ old('designation', $member->designation) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="role">Role <em>*</em></label>
        <select name="role" id="role" class="gui-input" required>
          @foreach (\App\Models\Staff::ROLES as $val => $label)
            <option value="{{ $val }}" @selected(old('role', $member->role ?? 'technician') === $val)>{{ $label }}</option>
          @endforeach
        </select>
        <span class="hint">Mirrors the RBAC ladder.</span>
      </div>

      <div class="field col-3">
        <label for="department">Department</label>
        <input type="text" name="department" id="department" class="gui-input" maxlength="100"
               value="{{ old('department', $member->department) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="employment_type">Employment Type <em>*</em></label>
        <select name="employment_type" id="employment_type" class="gui-input" required>
          @foreach (\App\Models\Staff::EMPLOYMENT_TYPES as $val => $label)
            <option value="{{ $val }}" @selected(old('employment_type', $member->employment_type ?? 'full_time') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="field col-3">
        <label for="status">Status <em>*</em></label>
        <select name="status" id="status" class="gui-input" required>
          @foreach (\App\Models\Staff::STATUSES as $val => $label)
            <option value="{{ $val }}" @selected(old('status', $member->status ?? 'active') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="field col-4">
        <label for="franchise_id">Franchise / LCO</label>
        <select name="franchise_id" id="franchise_id" class="gui-input">
          <option value="">— head office —</option>
          @foreach ($franchises as $f)
            <option value="{{ $f->id }}" @selected((int) old('franchise_id', $member->franchise_id) === (int) $f->id)>
              {{ $f->code }} — {{ $f->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="field col-4">
        <label for="reports_to_id">Reports To</label>
        <select name="reports_to_id" id="reports_to_id" class="gui-input">
          <option value="">— none —</option>
          @foreach ($managers as $m)
            <option value="{{ $m->id }}" @selected((int) old('reports_to_id', $member->reports_to_id) === (int) $m->id)>
              {{ $m->code }} — {{ $m->name }}{{ $m->designation ? ' (' . $m->designation . ')' : '' }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="field col-4">
        <label for="group_ids">Teams / Groups</label>
        <select name="group_ids[]" id="group_ids" class="gui-input" multiple size="4">
          @foreach ($groups as $g)
            <option value="{{ $g->id }}" @selected(in_array($g->id, old('group_ids', $selectedGroups) ?? []))>{{ $g->name }}</option>
          @endforeach
        </select>
        <span class="hint">Ctrl / Cmd click for multiple. Used for ticket assignment.</span>
      </div>

    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Contact</h4>
    <div class="form-grid">

      <div class="field col-4">
        <label for="phone">Phone</label>
        <input type="text" name="phone" id="phone" class="gui-input" maxlength="20"
               value="{{ old('phone', $member->phone) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" class="gui-input" maxlength="150"
               value="{{ old('email', $member->email) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="emergency_contact">Emergency Contact</label>
        <input type="text" name="emergency_contact" id="emergency_contact" class="gui-input" maxlength="20"
               value="{{ old('emergency_contact', $member->emergency_contact) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="dob_display">Date of Birth</label>
        <input type="text" class="gui-input js-dmy" id="dob_display" data-target="date_of_birth" placeholder=" ">
        <input type="hidden" name="date_of_birth" id="date_of_birth"
               value="{{ old('date_of_birth', $member->date_of_birth?->format('Y-m-d')) }}">
        <span class="hint">dd/mm/yy</span>
      </div>

      <div class="field col-3">
        <label for="doj_display">Date of Joining</label>
        <input type="text" class="gui-input js-dmy" id="doj_display" data-target="date_of_joining" placeholder=" ">
        <input type="hidden" name="date_of_joining" id="date_of_joining"
               value="{{ old('date_of_joining', $member->date_of_joining?->format('Y-m-d') ?? $member->date_of_joining) }}">
        <span class="hint">dd/mm/yy</span>
      </div>

      <div class="field col-3">
        <label for="dol_display">Date of Leaving</label>
        <input type="text" class="gui-input js-dmy" id="dol_display" data-target="date_of_leaving" placeholder=" ">
        <input type="hidden" name="date_of_leaving" id="date_of_leaving"
               value="{{ old('date_of_leaving', $member->date_of_leaving?->format('Y-m-d')) }}">
        <span class="hint">Clips the payroll period.</span>
      </div>

      <div class="field col-3">
        <label for="pincode">PIN Code</label>
        <input type="text" name="pincode" id="pincode" class="gui-input" maxlength="12"
               value="{{ old('pincode', $member->pincode) }}" placeholder=" ">
      </div>

      <div class="field col-12">
        <label for="address">Address</label>
        <textarea name="address" id="address" class="gui-input" rows="2" maxlength="500"
                  placeholder=" ">{{ old('address', $member->address) }}</textarea>
      </div>

      <div class="field col-6">
        <label for="city">City</label>
        <input type="text" name="city" id="city" class="gui-input" maxlength="100"
               value="{{ old('city', $member->city) }}" placeholder=" ">
      </div>

      <div class="field col-6">
        <label for="state">State</label>
        <input type="text" name="state" id="state" class="gui-input" maxlength="100"
               value="{{ old('state', $member->state) }}" placeholder=" ">
      </div>

    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Salary Structure <span class="muted-label">(monthly)</span></h4>
    <div class="form-grid">

      <div class="field col-3">
        <label for="basic_salary">Basic Salary <em>*</em></label>
        <input type="number" name="basic_salary" id="basic_salary" class="gui-input" step="0.01" min="0" required
               value="{{ old('basic_salary', $member->basic_salary ?? 0) }}" placeholder=" ">
        <span class="hint">Prorated by payable attendance days.</span>
      </div>

      <div class="field col-3">
        <label for="hra">HRA</label>
        <input type="number" name="hra" id="hra" class="gui-input" step="0.01" min="0"
               value="{{ old('hra', $member->hra ?? 0) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="other_allowances">Other Allowances</label>
        <input type="number" name="other_allowances" id="other_allowances" class="gui-input" step="0.01" min="0"
               value="{{ old('other_allowances', $member->other_allowances ?? 0) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="overtime_rate_per_hour">Overtime Rate / Hour</label>
        <input type="number" name="overtime_rate_per_hour" id="overtime_rate_per_hour" class="gui-input" step="0.01" min="0"
               value="{{ old('overtime_rate_per_hour', $member->overtime_rate_per_hour ?? 0) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="pf_percent">PF %</label>
        <input type="number" name="pf_percent" id="pf_percent" class="gui-input" step="0.01" min="0" max="100"
               value="{{ old('pf_percent', $member->pf_percent ?? 0) }}" placeholder=" ">
        <span class="hint">On earned basic, capped at 15,000.</span>
      </div>

      <div class="field col-3">
        <label for="esi_percent">ESI %</label>
        <input type="number" name="esi_percent" id="esi_percent" class="gui-input" step="0.01" min="0" max="100"
               value="{{ old('esi_percent', $member->esi_percent ?? 0) }}" placeholder=" ">
        <span class="hint">On gross earnings.</span>
      </div>

      <div class="field col-3">
        <label for="professional_tax">Professional Tax</label>
        <input type="number" name="professional_tax" id="professional_tax" class="gui-input" step="0.01" min="0"
               value="{{ old('professional_tax', $member->professional_tax ?? 0) }}" placeholder=" ">
        <span class="hint">Flat, per month.</span>
      </div>

    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Compliance &amp; Bank</h4>
    <div class="form-grid">

      <div class="field col-3">
        <label for="pan_number">PAN Number</label>
        <input type="text" name="pan_number" id="pan_number" class="gui-input" maxlength="20"
               value="{{ old('pan_number', $member->pan_number) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="aadhaar_number">Aadhaar Number</label>
        <input type="text" name="aadhaar_number" id="aadhaar_number" class="gui-input" maxlength="20"
               value="{{ old('aadhaar_number', $member->aadhaar_number) }}" placeholder=" ">
      </div>

      <div class="field col-6">
        <label for="bank_account_name">Bank Account Name</label>
        <input type="text" name="bank_account_name" id="bank_account_name" class="gui-input" maxlength="150"
               value="{{ old('bank_account_name', $member->bank_account_name) }}" placeholder=" ">
      </div>

      <div class="field col-6">
        <label for="bank_account_number">Bank Account Number</label>
        <input type="text" name="bank_account_number" id="bank_account_number" class="gui-input" maxlength="40"
               value="{{ old('bank_account_number', $member->bank_account_number) }}" placeholder=" ">
      </div>

      <div class="field col-6">
        <label for="bank_ifsc">IFSC</label>
        <input type="text" name="bank_ifsc" id="bank_ifsc" class="gui-input" maxlength="20"
               value="{{ old('bank_ifsc', $member->bank_ifsc) }}" placeholder=" ">
      </div>

      <div class="field col-12">
        <label for="notes">Notes</label>
        <textarea name="notes" id="notes" class="gui-input" rows="2" maxlength="1000"
                  placeholder=" ">{{ old('notes', $member->notes) }}</textarea>
      </div>

    </div>
  </div>
</div>
