@extends('layout', ['title' => 'Edit ' . $ticket->number])
@section('content')
  <div class="page-header">
    <h1>Edit {{ $ticket->number }}</h1>
    <p class="muted-label">{{ $ticket->subject }}</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('tickets.update', $ticket->id) }}">
    @csrf
    @method('PUT')
    @include('tickets._form')

    <div class="form-actions">
      <a class="btn" href="{{ route('tickets.show', $ticket->id) }}">Cancel</a>
      <button class="btn" type="submit">Update Ticket</button>
    </div>
  </form>

  @include('partials.dmy-date-script')
@endsection
