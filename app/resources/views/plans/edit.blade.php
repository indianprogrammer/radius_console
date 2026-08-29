@extends('layout', ['title' => 'Edit Bandwidth Profile'])
@section('content')
  <h1>Edit Bandwidth Profile</h1>
  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <form method="POST" action="{{ route('plans.update', $id) }}">
    @csrf @method('PUT')
    <label>Name <span class="req">*</span><input name="name" required value="{{ old('name', $plan['name'] ?? $local->name ?? '') }}" placeholder="Home 50 Mbps"></label>
    <label>Price<input name="price" type="number" step="0.01" value="{{ old('price', $local->price ?? '0.00') }}"></label>
    <label>Cycle
      <select name="cycle">
        @foreach (['monthly','quarterly','yearly'] as $c)
          <option value="{{ $c }}" {{ (old('cycle', $local->cycle ?? 'monthly') === $c) ? 'selected' : '' }}>{{ $c }}</option>
        @endforeach
      </select>
    </label>
    <label>Download (Mbps) <span class="req">*</span><input name="download_mbps" type="number" required min="1" value="{{ old('download_mbps', $plan['bandwidth_download_mbps'] ?? $local->downloadMbps ?? '') }}" placeholder="50"></label>
    <label>Upload (Mbps) <span class="req">*</span><input name="upload_mbps" type="number" required min="1" value="{{ old('upload_mbps', $plan['bandwidth_upload_mbps'] ?? $local->uploadMbps ?? '') }}" placeholder="10"></label>
    <label>Data Limit (GB)<input name="data_limit_gb" type="number" step="0.01" value="{{ old('data_limit_gb', $plan['data_limit_gb'] ?? $local->dataLimitGb ?? '') }}" placeholder="500"></label>
    <label>Duration (days) <span class="req">*</span><input name="duration_days" type="number" required min="1" value="{{ old('duration_days', $plan['duration_days'] ?? $local->durationDays ?? '') }}" placeholder="30"></label>
    <label>FUP Threshold (GB)<input name="fup_threshold_gb" type="number" step="0.01" value="{{ old('fup_threshold_gb', $plan['fup_threshold_gb'] ?? $local->fupThresholdGb ?? '') }}" placeholder="optional"></label>
    <label>FUP Download (Mbps)<input name="fup_download_mbps" type="number" min="0" value="{{ old('fup_download_mbps', $plan['fup_download_mbps'] ?? $local->fupDownloadMbps ?? '') }}" placeholder="optional"></label>
    <label>FUP Upload (Mbps)<input name="fup_upload_mbps" type="number" min="0" value="{{ old('fup_upload_mbps', $plan['fup_upload_mbps'] ?? $local->fupUploadMbps ?? '') }}" placeholder="optional"></label>
    <label>Simultaneous Use<input name="simultaneous_use" type="number" min="1" value="{{ old('simultaneous_use', $plan['simultaneous_use'] ?? $local->simultaneousUse ?? '1') }}"></label>
    <button class="btn" type="submit">Save</button>
  </form>
@endsection
