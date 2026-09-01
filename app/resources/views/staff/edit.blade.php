@extends('layout', ['title' => 'Edit Staff'])
@section('content')
  <div class="page-header">
    <h1>Edit {{ $member->name }}</h1>
    <p class="muted-label">{{ $member->code }} · {{ $member->roleLabel() }}</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('staff.update', $member->id) }}">
    @csrf
    @method('PUT')
    @include('staff._form')

    <div class="form-actions">
      <a class="btn" href="{{ route('staff.index') }}">Cancel</a>
      <button class="btn" type="submit">Update Staff</button>
    </div>
  </form>

  @include('partials.dmy-date-script')
@endsection
