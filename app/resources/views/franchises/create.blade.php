@extends('layout', ['title' => 'New Franchise'])
@section('content')
  <div class="page-header">
    <h1>New Franchise</h1>
    <p class="muted-label">Add a franchise / LCO with its commission terms and prepaid wallet limits.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('franchises.store') }}">
    @csrf
    @include('franchises._form', ['isEdit' => false])

    <div class="form-actions">
      <a class="btn" href="{{ route('franchises.index') }}">Cancel</a>
      <button class="btn" type="submit">Save Franchise</button>
    </div>
  </form>
@endsection
