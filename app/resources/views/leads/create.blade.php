@extends('layout', ['title' => 'New Lead'])
@section('content')
  <div class="page-header">
    <h1>New Lead</h1>
    <p class="muted-label">Capture a prospect and work them through the pipeline.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('leads.store') }}">
    @csrf
    @include('leads._form')

    <div class="form-actions">
      <a class="btn" href="{{ route('leads.index') }}">Cancel</a>
      <button class="btn" type="submit">Save Lead</button>
    </div>
  </form>
@endsection