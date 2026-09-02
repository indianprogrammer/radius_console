@extends('layout', ['title' => 'Edit Lead ' . $lead->number])
@section('content')
  <div class="page-header">
    <h1>Edit Lead {{ $lead->number }}</h1>
    <p class="muted-label">{{ $lead->displayName() }} · {{ $lead->statusLabel() }}</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('leads.update', $lead->id) }}">
    @csrf
    @method('PUT')
    @include('leads._form')

    <div class="form-actions">
      <a class="btn" href="{{ route('leads.show', $lead->id) }}">Cancel</a>
      <button class="btn" type="submit">Save Changes</button>
    </div>
  </form>
@endsection