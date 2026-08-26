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
    <label>Type<input name="type" placeholder="mikrotik"></label>
    <label class="checkbox"><input type="checkbox" name="api_enabled" value="1"> API enabled</label>
    <label>Description<textarea name="description" rows="2" placeholder="optional"></textarea></label>
    <button class="btn" type="submit">Register</button>
  </form>
@endsection
