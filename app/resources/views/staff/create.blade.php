@extends('layout', ['title' => 'New Staff'])
@section('content')
  <div class="page-header">
    <h1>New Staff</h1>
    <p class="muted-label">Add an employee with their salary structure and team membership.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('staff.store') }}">
    @csrf
    @include('staff._form')

    <div class="form-actions">
      <a class="btn" href="{{ route('staff.index') }}">Cancel</a>
      <button class="btn" type="submit">Save Staff</button>
    </div>
  </form>

  @include('partials.dmy-date-script')
@endsection
