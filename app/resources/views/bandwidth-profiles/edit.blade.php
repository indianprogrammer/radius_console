@extends('layout', ['title' => 'Edit Bandwidth Profile'])
@section('content')
  <h1>Edit Bandwidth Profile <span class="muted">#{{ $id }}</span></h1>
  <p class="hint">Changes are pushed directly to the RADIUS server (system of record).</p>
  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <form method="POST" action="{{ route('bandwidth-profiles.update', $id) }}">
    @csrf @method('PUT')
    <label>Name <span class="req">*</span>
      <input name="name" type="text" required maxlength="120"
        value="{{ old('name', $profile['name'] ?? ($local->name ?? '')) }}">
    </label>
    <label>Download (Mbps) <span class="req">*</span>
      <input name="download_mbps" type="number" required min="1"
        value="{{ old('download_mbps', $profile['bandwidth_download_mbps'] ?? '') }}">
    </label>
    <label>Upload (Mbps) <span class="req">*</span>
      <input name="upload_mbps" type="number" required min="1"
        value="{{ old('upload_mbps', $profile['bandwidth_upload_mbps'] ?? '') }}">
    </label>
    <label>VLAN ID
      <input name="vlan_id" type="number" min="1" max="4094"
        value="{{ old('vlan_id', $profile['vlan_id'] ?? '') }}" placeholder="optional (1–4094)">
    </label>
    <label>FUP Threshold (GB)
      <input name="fup_threshold_gb" type="number" step="0.01" min="0"
        value="{{ old('fup_threshold_gb', $profile['fup_threshold_gb'] ?? '') }}" placeholder="optional">
    </label>
    <label>FUP Download (Mbps)
      <input name="fup_download_mbps" type="number" min="0"
        value="{{ old('fup_download_mbps', $profile['fup_download_mbps'] ?? '') }}" placeholder="after threshold">
    </label>
    <label>FUP Upload (Mbps)
      <input name="fup_upload_mbps" type="number" min="0"
        value="{{ old('fup_upload_mbps', $profile['fup_upload_mbps'] ?? '') }}" placeholder="after threshold">
    </label>
    <label>Interim Interval (seconds)
      <input name="interim_interval" type="number" min="30"
        value="{{ old('interim_interval', $profile['interim_interval'] ?? 30) }}">
    </label>
    <button class="btn" type="submit">Save Changes</button>
  </form>
@endsection
