@extends('layout', ['title' => 'New Team'])
@section('content')
  <div class="page-header">
    <h1>New Team</h1>
    <p class="muted-label">Group staff so a ticket can be assigned to the whole team at once.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('staff-groups.store') }}">
    @csrf
    @include('staff-groups._form')

    <div class="form-actions">
      <a class="btn" href="{{ route('staff-groups.index') }}">Cancel</a>
      <button class="btn" type="submit">Save Team</button>
    </div>
  </form>
@endsection
