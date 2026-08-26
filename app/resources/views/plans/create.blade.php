@extends('layout', ['title' => 'New Plan'])
@section('content')
  <h1>New Plan</h1>
  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <form method="POST" action="{{ route('plans.store') }}">
    @csrf
    <label>Name <span class="req">*</span><input name="name" required placeholder="Home 50 Mbps"></label>
    <label>Price<input name="price" type="number" step="0.01" placeholder="0.00"></label>
    <label>Cycle<select name="cycle"><option value="monthly">monthly</option><option value="quarterly">quarterly</option><option value="yearly">yearly</option></select></label>
    <label>Download (Mbps) <span class="req">*</span><input name="download_mbps" type="number" required min="1" placeholder="50"></label>
    <label>Upload (Mbps) <span class="req">*</span><input name="upload_mbps" type="number" required min="1" placeholder="10"></label>
    <label>Data Limit (GB)<input name="data_limit_gb" type="number" step="0.01" placeholder="500"></label>
    <label>Duration (days) <span class="req">*</span><input name="duration_days" type="number" required min="1" placeholder="30"></label>
    <label>FUP Threshold (GB)<input name="fup_threshold_gb" type="number" step="0.01" placeholder="optional"></label>
    <label>FUP Download (Mbps)<input name="fup_download_mbps" type="number" min="0" placeholder="optional"></label>
    <label>FUP Upload (Mbps)<input name="fup_upload_mbps" type="number" min="0" placeholder="optional"></label>
    <label>Simultaneous Use<input name="simultaneous_use" type="number" min="1" value="1"></label>
    <button class="btn" type="submit">Create Plan</button>
  </form>
@endsection
