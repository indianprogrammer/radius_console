@extends('layout', ['title' => 'Register NAS Device'])
@section('content')
  <div class="page-header">
    <h1>Register NAS Device</h1>
    <p class="muted-label">Add a NAS/BRAS device so RADIUS accepts its requests.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('nas.store') }}">
    @csrf

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Device Details</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="name">Label</label>
            <input type="text" name="name" id="name" class="gui-input" value="{{ old('name') }}" placeholder=" ">
            <span class="hint">Optional, e.g. POP-1 / Building-A AP</span>
          </div>

          <div class="field col-3">
            <label for="nas_ip">NAS IP <em>*</em></label>
            <input type="text" name="nas_ip" id="nas_ip" class="gui-input" required value="{{ old('nas_ip') }}" placeholder=" ">
            <span class="hint">e.g. 10.0.0.1</span>
          </div>

          <div class="field col-3">
            <label for="shared_secret">Shared Secret <em>*</em></label>
            <input type="text" name="shared_secret" id="shared_secret" class="gui-input" required
                   value="{{ old('shared_secret') }}" placeholder=" ">
            <span class="hint">RADIUS/CoA secret</span>
          </div>

          <div class="field col-3">
            <label for="nas_identifier">NAS Identifier</label>
            <input type="text" name="nas_identifier" id="nas_identifier" class="gui-input"
                   value="{{ old('nas_identifier') }}" placeholder=" ">
            <span class="hint">Defaults to NAS IP</span>
          </div>

          <div class="field col-3">
            <label for="type">Type</label>
            <select name="type" id="type" class="gui-input">
              <option value="">—</option>
              @foreach (['mikrotik','cisco','ubiquiti','aruba','other'] as $t)
                <option value="{{ $t }}" @selected(old('type') === $t)>{{ $t }}</option>
              @endforeach
            </select>
          </div>

          <div class="field col-3">
            <label class="switch-label">API Enabled</label>
            <label class="switch">
              <input type="checkbox" name="api_enabled" value="1" id="api_enabled" onchange="toggleApi()" @checked(old('api_enabled'))>
              <span data-on="ON" data-off="OFF"></span>
            </label>
          </div>

          <div class="field col-12">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="gui-input" rows="2" placeholder=" ">{{ old('description') }}</textarea>
          </div>

        </div>
      </div>
    </div>

    <div class="panel" id="api_panel" style="display:{{ old('api_enabled') ? 'block' : 'none' }}">
      <div class="panel-body">
        <h4 class="section-title">Device API Connection <span class="hint">(sent to RADIUS)</span></h4>
        <fieldset id="api_fields" @disabled(!old('api_enabled'))>
          <div class="form-grid">

            <div class="field col-3">
              <label for="api_host">API Host</label>
              <input type="text" name="api_host" id="api_host" class="gui-input" value="{{ old('api_host') }}" placeholder=" ">
              <span class="hint">e.g. 10.0.0.1</span>
            </div>

            <div class="field col-3">
              <label for="api_port">API Port</label>
              <input type="text" name="api_port" id="api_port" class="gui-input" value="{{ old('api_port') }}" placeholder=" ">
              <span class="hint">e.g. 8728</span>
            </div>

            <div class="field col-3">
              <label for="api_username">API Username</label>
              <input type="text" name="api_username" id="api_username" class="gui-input" value="{{ old('api_username') }}" placeholder=" ">
            </div>

            <div class="field col-3">
              <label for="api_password">API Password</label>
              <input type="password" name="api_password" id="api_password" class="gui-input" placeholder=" " autocomplete="new-password">
            </div>

          </div>
        </fieldset>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn" type="submit">Register</button>
    </div>
  </form>

  <script>
    function toggleApi() {
      const on = document.getElementById('api_enabled').checked;
      document.getElementById('api_panel').style.display = on ? 'block' : 'none';
      document.getElementById('api_fields').disabled = !on;
    }
  </script>
@endsection
