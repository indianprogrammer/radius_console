{{--
  Shared staff group form (create + edit).

  Expects:
    $group          App\Models\StaffGroup
    $staff          Collection of assignable staff
    $selectedStaff  array<int> of currently selected staff ids
--}}
@php $selectedStaff = $selectedStaff ?? []; @endphp

<div class="panel">
  <div class="panel-body">
    <h4 class="section-title">Team Details</h4>
    <div class="form-grid">

      <div class="field col-6">
        <label for="name">Team Name <em>*</em></label>
        <input type="text" name="name" id="name" class="gui-input" required maxlength="150"
               value="{{ old('name', $group->name) }}" placeholder=" ">
        <span class="hint">e.g. "Field Technicians — North".</span>
      </div>

      <div class="field col-6">
        <label for="description">Description</label>
        <input type="text" name="description" id="description" class="gui-input" maxlength="500"
               value="{{ old('description', $group->description) }}" placeholder=" ">
      </div>

      <div class="field col-6">
        <label for="member_ids">Members</label>
        <select name="member_ids[]" id="member_ids" class="gui-input" multiple size="10">
          @foreach ($staff as $s)
            <option value="{{ $s->id }}" @selected(in_array($s->id, old('member_ids', $selectedStaff) ?? []))>
              {{ $s->code }} — {{ $s->name }}{{ $s->designation ? ' (' . $s->designation . ')' : '' }}
            </option>
          @endforeach
        </select>
        <span class="hint">Ctrl / Cmd click for multiple. Assigning a ticket to this team expands to these members.</span>
      </div>

      <div class="field col-6">
        <label for="is_active">Active</label>
        <select name="is_active" id="is_active" class="gui-input">
          <option value="1" @selected((bool) old('is_active', $group->is_active ?? true))>Yes</option>
          <option value="0" @selected(!(bool) old('is_active', $group->is_active ?? true))>No</option>
        </select>
        <span class="hint">Inactive teams are hidden from ticket assignment.</span>
      </div>

    </div>
  </div>
</div>
