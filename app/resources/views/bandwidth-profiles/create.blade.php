@extends('layout', ['title' => 'New Bandwidth Profile'])
@section('content')
  <div class="page-header">
    <h1>New Bandwidth Profile</h1>
    <p class="muted-label">Values are pushed directly to the RADIUS server (system of record).</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('bandwidth-profiles.store') }}">
    @csrf

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Profile</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="name">Name <em>*</em></label>
            <input type="text" name="name" id="name" class="gui-input" required maxlength="120"
                   value="{{ old('name') }}" placeholder=" ">
            <span class="hint">e.g. Residential 50/10</span>
          </div>

          <div class="field col-3">
            <label for="download_mbps">Download (Mbps) <em>*</em></label>
            <input type="number" name="download_mbps" id="download_mbps" class="gui-input" required min="1"
                   value="{{ old('download_mbps') }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="upload_mbps">Upload (Mbps) <em>*</em></label>
            <input type="number" name="upload_mbps" id="upload_mbps" class="gui-input" required min="1"
                   value="{{ old('upload_mbps') }}" placeholder=" ">
          </div>

          <div class="field col-3">
            <label for="vlan_id">VLAN ID</label>
            <input type="number" name="vlan_id" id="vlan_id" class="gui-input" min="1" max="4094"
                   value="{{ old('vlan_id') }}" placeholder=" ">
            <span class="hint">Optional (1–4094)</span>
          </div>

          <div class="field col-3">
            <label for="interim_interval">Interim Interval (seconds)</label>
            <input type="number" name="interim_interval" id="interim_interval" class="gui-input" min="30"
                   value="{{ old('interim_interval', 30) }}" placeholder=" ">
            <span class="hint">Default 30</span>
          </div>

        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Fair Usage Policy (FUP)</h4>
        <div class="form-grid">

          <div class="field col-3">
            <label for="fup_threshold_gb">FUP Threshold (GB)</label>
            <input type="number" name="fup_threshold_gb" id="fup_threshold_gb" class="gui-input" step="0.01" min="0"
                   value="{{ old('fup_threshold_gb') }}" placeholder=" ">
            <span class="hint">Optional</span>
          </div>

          <div class="field col-3">
            <label for="fup_download_mbps">FUP Download (Mbps)</label>
            <input type="number" name="fup_download_mbps" id="fup_download_mbps" class="gui-input" min="0"
                   value="{{ old('fup_download_mbps') }}" placeholder=" ">
            <span class="hint">After threshold</span>
          </div>

          <div class="field col-3">
            <label for="fup_upload_mbps">FUP Upload (Mbps)</label>
            <input type="number" name="fup_upload_mbps" id="fup_upload_mbps" class="gui-input" min="0"
                   value="{{ old('fup_upload_mbps') }}" placeholder=" ">
            <span class="hint">After threshold</span>
          </div>

        </div>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn" type="submit">Create Bandwidth Profile</button>
    </div>
  </form>
@endsection
