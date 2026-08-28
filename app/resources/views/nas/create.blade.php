@extends('layout', ['title' => 'Register NAS Device'])
@section('content')
  <h1>Register NAS Device</h1>
  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <form method="POST" action="{{ route('nas.store') }}">
    @csrf
    <label>Label (optional)<input name="name" placeholder="POP-1 / Building-A AP"></label>
    <label>NAS IP <span class="req">*</span><input name="nas_ip" required placeholder="10.0.0.1"></label>
    <label>Shared Secret <span class="req">*</span><input name="shared_secret" required type="text" placeholder="RADIUS/CoA secret"></label>
    <label>NAS Identifier<input name="nas_identifier" placeholder="defaults to NAS IP"></label>
    <label>Type
      <select name="type">
        <option value="">—</option>
        <option value="mikrotik">mikrotik</option>
        <option value="cisco">cisco</option>
        <option value="ubiquiti">ubiquiti</option>
        <option value="aruba">aruba</option>
        <option value="other">other</option>
      </select>
    </label>
    <label class="checkbox"><input type="checkbox" name="api_enabled" value="1" id="api_enabled" onchange="toggleApi()"> API enabled</label>

    <fieldset id="api_fields" class="api-fields" disabled style="display:none">
      <legend>Device API connection (sent to RADIUS)</legend>
      <label>API Host<input name="api_host" type="text" placeholder="10.0.0.1"></label>
      <label>API Port<input name="api_port" type="text" placeholder="8728"></label>
      <label>API Username<input name="api_username" type="text" placeholder="admin"></label>
      <label>API Password<input name="api_password" type="password" placeholder="device API password"></label>
    </fieldset>

    <label>Description<textarea name="description" rows="2" placeholder="optional"></textarea></label>
    <button class="btn" type="submit">Register</button>
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
