@extends('layout', ['title' => 'Edit Team'])
@section('content')
  <div class="page-header">
    <h1>Edit {{ $group->name }}</h1>
    <p class="muted-label">Team membership drives ticket assignment.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('staff-groups.update', $group->id) }}">
    @csrf
    @method('PUT')
    @include('staff-groups._form')

    <div class="form-actions">
      <a class="btn" href="{{ route('staff-groups.index') }}">Cancel</a>
      <button class="btn" type="submit">Update Team</button>
    </div>
  </form>
@endsection
