{{--
  Shared ticket form fields (create + edit).

  Expects:
    $ticket             App\Models\Ticket (unsaved instance on create)
    $staff              Collection of assignable staff
    $groups             Collection of active staff groups
    $subscribers        Collection of subscribers
    $franchises         Collection of franchises
    $selectedAssignees  array<int> of currently selected assignee ids
--}}
@php $selectedAssignees = $selectedAssignees ?? []; @endphp

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Ticket Details</h4>
    <div class="form-grid">

      <div class="field col-3">
        <label for="number">Ticket No.</label>
        <input type="text" name="number" id="number" class="gui-input" maxlength="40"
               value="{{ old('number', $ticket->number) }}" placeholder=" ">
        <span class="hint">Leave blank to auto-generate (TKT-000001).</span>
      </div>

      <div class="field col-9">
        <label for="subject">Subject <em>*</em></label>
        <input type="text" name="subject" id="subject" class="gui-input" required maxlength="200"
               value="{{ old('subject', $ticket->subject) }}" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="category">Category <em>*</em></label>
        <select name="category" id="category" class="gui-input" required>
          @foreach (\App\Models\Ticket::CATEGORIES as $val => $label)
            <option value="{{ $val }}" @selected(old('category', $ticket->category ?? 'fault') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="field col-3">
        <label for="priority">Priority <em>*</em></label>
        <select name="priority" id="priority" class="gui-input" required>
          @foreach (\App\Models\Ticket::PRIORITIES as $val => $label)
            <option value="{{ $val }}" @selected(old('priority', $ticket->priority ?? 'medium') === $val)>
              {{ $label }} (SLA {{ \App\Models\Ticket::SLA_HOURS[$val] }}h)
            </option>
          @endforeach
        </select>
      </div>

      <div class="field col-3">
        <label for="status">Status <em>*</em></label>
        <select name="status" id="status" class="gui-input" required>
          @foreach (\App\Models\Ticket::STATUSES as $val => $label)
            <option value="{{ $val }}" @selected(old('status', $ticket->status ?? 'open') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="field col-3">
        <label for="source">Source <em>*</em></label>
        <select name="source" id="source" class="gui-input" required>
          @foreach (\App\Models\Ticket::SOURCES as $val => $label)
            <option value="{{ $val }}" @selected(old('source', $ticket->source ?? 'phone') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="field col-12">
        <label for="description">Description</label>
        <textarea name="description" id="description" class="gui-input" rows="3" maxlength="5000"
                  placeholder=" ">{{ old('description', $ticket->description) }}</textarea>
      </div>

    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Assignment</h4>
    <div class="form-grid">

      <div class="field col-4">
        <label for="assigned_staff_id">Owner <span class="muted-label">(accountable)</span></label>
        <select name="assigned_staff_id" id="assigned_staff_id" class="gui-input">
          <option value="">— unassigned —</option>
          @foreach ($staff as $s)
            <option value="{{ $s->id }}" @selected((int) old('assigned_staff_id', $ticket->assigned_staff_id) === (int) $s->id)>
              {{ $s->code }} — {{ $s->name }}{{ $s->designation ? ' (' . $s->designation . ')' : '' }}
            </option>
          @endforeach
        </select>
        <span class="hint">Always included in the assignees below.</span>
      </div>

      <div class="field col-4">
        <label for="staff_group_id">Assign to Team</label>
        <select name="staff_group_id" id="staff_group_id" class="gui-input">
          <option value="">— no team —</option>
          @foreach ($groups as $g)
            <option value="{{ $g->id }}" @selected((int) old('staff_group_id', $ticket->staff_group_id) === (int) $g->id)>
              {{ $g->name }} ({{ $g->members_count }} members)
            </option>
          @endforeach
        </select>
        <span class="hint">Members are expanded into the assignee list on save.</span>
      </div>

      <div class="field col-4">
        <label for="assignee_ids">Additional Assignees</label>
        <select name="assignee_ids[]" id="assignee_ids" class="gui-input" multiple size="6">
          @foreach ($staff as $s)
            <option value="{{ $s->id }}" @selected(in_array($s->id, old('assignee_ids', $selectedAssignees) ?? []))>
              {{ $s->code }} — {{ $s->name }}
            </option>
          @endforeach
        </select>
        <span class="hint">Ctrl / Cmd click for multiple staff.</span>
      </div>

      <div class="field col-4">
        <label for="due_display">Due At</label>
        <input type="text" class="gui-input js-dmy" id="due_display" data-target="due_at" data-with-time placeholder=" ">
        <input type="hidden" name="due_at" id="due_at"
               value="{{ old('due_at', $ticket->due_at?->format('Y-m-d\TH:i')) }}">
        <span class="hint">Blank = derived from the priority SLA.</span>
      </div>

    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Customer &amp; Contact</h4>
    <div class="form-grid">

      <div class="field col-4">
        <label for="subscriber_id">Subscriber</label>
        <select name="subscriber_id" id="subscriber_id" class="gui-input">
          <option value="">— none / internal —</option>
          @foreach ($subscribers as $s)
            <option value="{{ $s->id }}" @selected((int) old('subscriber_id', $ticket->subscriber_id) === (int) $s->id)>
              {{ $s->username }}{{ trim($s->first_name . ' ' . $s->last_name) ? ' — ' . trim($s->first_name . ' ' . $s->last_name) : '' }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="field col-4">
        <label for="franchise_id">Franchise / LCO</label>
        <select name="franchise_id" id="franchise_id" class="gui-input">
          <option value="">— head office —</option>
          @foreach ($franchises as $f)
            <option value="{{ $f->id }}" @selected((int) old('franchise_id', $ticket->franchise_id) === (int) $f->id)>
              {{ $f->code }} — {{ $f->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="field col-4">
        <label for="contact_name">Contact Name</label>
        <input type="text" name="contact_name" id="contact_name" class="gui-input" maxlength="150"
               value="{{ old('contact_name', $ticket->contact_name) }}" placeholder=" ">
      </div>

      <div class="field col-4">
        <label for="contact_phone">Contact Phone</label>
        <input type="text" name="contact_phone" id="contact_phone" class="gui-input" maxlength="20"
               value="{{ old('contact_phone', $ticket->contact_phone) }}" placeholder=" ">
      </div>

      <div class="field col-8">
        <label for="address">Site Address</label>
        <textarea name="address" id="address" class="gui-input" rows="2" maxlength="500"
                  placeholder=" ">{{ old('address', $ticket->address) }}</textarea>
      </div>

      <div class="field col-12">
        <label for="resolution">Resolution</label>
        <textarea name="resolution" id="resolution" class="gui-input" rows="2" maxlength="5000"
                  placeholder=" ">{{ old('resolution', $ticket->resolution) }}</textarea>
        <span class="hint">Filled in when the ticket is resolved or closed.</span>
      </div>

    </div>
  </div>
</div>
