@extends('layout', ['title' => 'Edit NAS Device'])
@section('content')
  <h1>Edit NAS Device</h1>
  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <form method="POST" action="{{ route('nas.update', $id) }}">
    @csrf @method('PUT')
    <label>Label (optional)<input name="name" value="{{ old('name', $name ?? '') }}" placeholder="POP-1 / Building-A AP"></label>
    <label>NAS IP <span class="req">*</span><input name="nas_ip" required value="{{ old('nas_ip', $nas['nas_ip'] ?? '') }}"></label>
    <label>Shared Secret <span class="req">*</span><input name="shared_secret" required type="text" value="{{ old('shared_secret', $nas['shared_secret'] ?? '') }}" placeholder="RADIUS/CoA secret"></label>
    <label>NAS Identifier<input name="nas_identifier" value="{{ old('nas_identifier', $nas['nas_identifier'] ?? '') }}" placeholder="defaults to NAS IP"></label>
    <label>Type
      <select name="type">
        <option value="">—</option>
        @foreach (['mikrotik','cisco','ubiquiti','aruba','other'] as $t)
          <option value="{{ $t }}" {{ (old('type', $nas['type'] ?? '') === $t) ? 'selected' : '' }}>{{ $t }}</option>
        @endforeach
      </select>
    </label>
    <label class="checkbox"><input type="checkbox" name="api_enabled" value="1" id="api_enabled" onchange="toggleApi()" {{ (!empty($nas['api_enabled']) || old('api_enabled')) ? 'checked' : '' }}> API enabled</label>

    <fieldset id="api_fields" class="api-fields" disabled style="display:{{ (!empty($nas['api_enabled']) || old('api_enabled')) ? 'block' : 'none' }}">
      <legend>Device API connection (sent to RADIUS)</legend>
      <label>API Host<input name="api_host" type="text" value="{{ old('api_host', $nas['api_host'] ?? '') }}" placeholder="10.0.0.1"></label>
      <label>API Port<input name="api_port" type="text" value="{{ old('api_port', $nas['api_port'] ?? '') }}" placeholder="8728"></label>
      <label>API Username<input name="api_username" type="text" value="{{ old('api_username', $nas['api_username'] ?? '') }}" placeholder="admin"></label>
      <label>API Password<input name="api_password" type="password" placeholder="leave blank to keep current" autocomplete="new-password"></label>
    </fieldset>

    <label>Description<textarea name="description" rows="2" placeholder="optional">{{ old('description', $nas['description'] ?? '') }}</textarea></label>
    <button class="btn" type="submit">Save</button>
  </form>

  <script>
    function toggleApi() {
      const on = document.getElementById('api_enabled').checked;
      const box = document.getElementById('api_fields');
      box.style.display = on ? 'block' : 'none';
      box.disabled = !on;
    }
  </script>
@endsection
