@extends('layout', ['title' => 'New Bandwidth Profile'])
@section('content')
  <h1>New Bandwidth Profile</h1>
  <p class="hint">Values are pushed directly to the RADIUS server (system of record).</p>
  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <form method="POST" action="{{ route('bandwidth-profiles.store') }}">
    @csrf
    <label>Name <span class="req">*</span>
      <input name="name" type="text" required maxlength="120" value="{{ old('name') }}" placeholder="e.g. Residential 50/10">
    </label>
    <label>Download (Mbps) <span class="req">*</span>
      <input name="download_mbps" type="number" required min="1" value="{{ old('download_mbps') }}" placeholder="50">
    </label>
    <label>Upload (Mbps) <span class="req">*</span>
      <input name="upload_mbps" type="number" required min="1" value="{{ old('upload_mbps') }}" placeholder="10">
    </label>
    <label>VLAN ID
      <input name="vlan_id" type="number" min="1" max="4094" value="{{ old('vlan_id') }}" placeholder="optional (1–4094)">
    </label>
    <label>FUP Threshold (GB)
      <input name="fup_threshold_gb" type="number" step="0.01" min="0" value="{{ old('fup_threshold_gb') }}" placeholder="optional">
    </label>
    <label>FUP Download (Mbps)
      <input name="fup_download_mbps" type="number" min="0" value="{{ old('fup_download_mbps') }}" placeholder="after threshold">
    </label>
    <label>FUP Upload (Mbps)
      <input name="fup_upload_mbps" type="number" min="0" value="{{ old('fup_upload_mbps') }}" placeholder="after threshold">
    </label>
    <label>Interim Interval (seconds)
      <input name="interim_interval" type="number" min="30" value="{{ old('interim_interval', 30) }}" placeholder="default 30">
    </label>
    <button class="btn" type="submit">Create Bandwidth Profile</button>
  </form>
@endsection
