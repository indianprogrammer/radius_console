@extends('layout', ['title' => 'New Ticket'])
@section('content')
  <div class="page-header">
    <h1>New Ticket</h1>
    <p class="muted-label">Raise a helpdesk ticket and assign it to staff or a team.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('tickets.store') }}">
    @csrf
    @include('tickets._form')

    <div class="form-actions">
      <a class="btn" href="{{ route('tickets.index') }}">Cancel</a>
      <button class="btn" type="submit">Save Ticket</button>
    </div>
  </form>

  @include('partials.dmy-date-script')
@endsection
