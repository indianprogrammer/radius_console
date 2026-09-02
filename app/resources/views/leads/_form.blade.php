{{--
  Shared lead form fields (create + edit).

  Expects:
    $lead        App\Models\Lead (unsaved instance on create)
    $staff       Collection of assignable staff (sales first)
    $plans       Collection of billing plans
    $franchises  Collection of franchises
    $subscribers Collection of subscribers (for a won lead's account link)
--}}
<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Prospect</h4>
    <div class="form-grid">

      <div class="field col-3">
        <label for="number">Lead No.</label>
        <input type="text" name="number" id="number" class="gui-input" maxlength="40"
               value="{{ old('number', $lead->number) }}" placeholder=" ">
        <span class="hint">Leave blank to auto-generate (LEAD-000001).</span>
      </div>

      <div class="field col-5">
        <label for="name">Contact Name <em>*</em></label>
        <input type="text" name="name" id="name" class="gui-input" required maxlength="150"
               value="{{ old('name', $lead->name) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="company">Company</label>
        <input type="text" name="company" id="company" class="gui-input" maxlength="150"
               value="{{ old('company', $lead->company) }}" placeholder=" ">
        <span class="hint">Leave blank for a residential prospect.</span>
      </div>

      <div class="field col-4">
        <label for="phone">Phone</label>
        <input type="text" name="phone" id="phone" class="gui-input" maxlength="20"
               value="{{ old('phone', $lead->phone) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="alternate_phone">Alternate Phone</label>
        <input type="text" name="alternate_phone" id="alternate_phone" class="gui-input" maxlength="20"
               value="{{ old('alternate_phone', $lead->alternate_phone) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" class="gui-input" maxlength="150"
               value="{{ old('email', $lead->email) }}" placeholder=" ">
      </div>

      <div class="field col-12">
        <label for="address">Address</label>
        <textarea name="address" id="address" class="gui-input" rows="2" maxlength="500"
                  placeholder=" ">{{ old('address', $lead->address) }}</textarea>
      </div>

      <div class="field col-4">
        <label for="city">City</label>
        <input type="text" name="city" id="city" class="gui-input" maxlength="100"
               value="{{ old('city', $lead->city) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="state">State</label>
        <input type="text" name="state" id="state" class="gui-input" maxlength="100"
               value="{{ old('state', $lead->state) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="pincode">Pincode</label>
        <input type="text" name="pincode" id="pincode" class="gui-input" maxlength="12"
               value="{{ old('pincode', $lead->pincode) }}" placeholder=" ">
      </div>

    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Pipeline</h4>
    <div class="form-grid">

      <div class="field col-3">
        <label for="source">Source <em>*</em></label>
        <select name="source" id="source" class="gui-input" required>
          @foreach (\App\Models\Lead::SOURCES as $val => $label)
            <option value="{{ $val }}" @selected(old('source', $lead->source ?? 'phone') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="field col-3">
        <label for="status">Stage <em>*</em></label>
        <select name="status" id="status" class="gui-input" required>
          @foreach (\App\Models\Lead::STATUSES as $val => $label)
            <option value="{{ $val }}" @selected(old('status', $lead->status ?? 'new') === $val)>{{ $label }}</option>
          @endforeach
        </select>
        <span class="hint">Won / Lost also stamp their date and clear the follow-up.</span>
      </div>

      <div class="field col-3">
        <label for="rating">Rating <em>*</em></label>
        <select name="rating" id="rating" class="gui-input" required>
          @foreach (\App\Models\Lead::RATINGS as $val => $label)
            <option value="{{ $val }}" @selected(old('rating', $lead->rating ?? 'warm') === $val)>{{ $label }}</option>
          @endforeach
        </select>
        <span class="hint">Hot leads sort to the top of the list.</span>
      </div>

      <div class="field col-3">
        <label for="estimated_value">Estimated Value</label>
        <input type="number" name="estimated_value" id="estimated_value" class="gui-input"
               step="0.01" min="0" value="{{ old('estimated_value', $lead->estimated_value ?? 0) }}" placeholder=" ">
        <span class="hint">Counts toward the open pipeline total.</span>
      </div>

      <div class="field col-4">
        <label for="plan_id">Interested Plan</label>
        <select name="plan_id" id="plan_id" class="gui-input">
          <option value="">— not decided —</option>
          @foreach ($plans as $p)
            <option value="{{ $p->id }}" @selected((int) old('plan_id', $lead->plan_id) === (int) $p->id)>
              {{ $p->name }} ({{ number_format($p->price, 2) }})
            </option>
          @endforeach
        </select>
        <span class="hint">Prices the quotation line when you raise one.</span>
      </div>

      <div class="field col-4">
        <label for="assigned_staff_id">Owner</label>
        <select name="assigned_staff_id" id="assigned_staff_id" class="gui-input">
          <option value="">— unassigned —</option>
          @foreach ($staff as $s)
            <option value="{{ $s->id }}" @selected((int) old('assigned_staff_id', $lead->assigned_staff_id) === (int) $s->id)>
              {{ $s->code }} — {{ $s->name }}{{ $s->designation ? ' (' . $s->designation . ')' : '' }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="field col-4">
        <label for="franchise_id">Franchise / LCO</label>
        <select name="franchise_id" id="franchise_id" class="gui-input">
          <option value="">— head office —</option>
          @foreach ($franchises as $f)
            <option value="{{ $f->id }}" @selected((int) old('franchise_id', $lead->franchise_id) === (int) $f->id)>
              {{ $f->code }} — {{ $f->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="field col-4">
        <label for="next_follow_up_at_display">Next Follow-up</label>
        <input type="text" id="next_follow_up_at_display" class="gui-input js-dmy"
               data-target="next_follow_up_at" data-with-time data-default-time="10:00"
               inputmode="numeric" maxlength="14" autocomplete="off" placeholder=" ">
        <input type="hidden" name="next_follow_up_at" id="next_follow_up_at"
               value="{{ old('next_follow_up_at', $lead->next_follow_up_at?->format('Y-m-d\TH:i')) }}">
        <span class="hint">dd/mm/yy hh:ii — drives the Follow-ups Due queue.</span>
      </div>

      <div class="field col-8">
        <label for="subscriber_id">Linked Subscriber</label>
        <select name="subscriber_id" id="subscriber_id" class="gui-input">
          <option value="">— not a subscriber yet —</option>
          @foreach ($subscribers as $s)
            <option value="{{ $s->id }}" @selected((int) old('subscriber_id', $lead->subscriber_id) === (int) $s->id)>
              {{ $s->username }}{{ trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')) ? ' — ' . trim($s->first_name . ' ' . $s->last_name) : '' }}
            </option>
          @endforeach
        </select>
        <span class="hint">Set once the prospect is onboarded; required to convert their quotation to an invoice.</span>
      </div>

      <div class="field col-12">
        <label for="notes">Notes</label>
        <textarea name="notes" id="notes" class="gui-input" rows="3" maxlength="5000"
                  placeholder=" ">{{ old('notes', $lead->notes) }}</textarea>
      </div>

    </div>
  </div>
</div>

@include('partials.dmy-date-script')