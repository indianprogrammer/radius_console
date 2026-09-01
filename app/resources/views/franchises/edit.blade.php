@extends('layout', ['title' => 'Edit Franchise ' . $franchise->code])
@section('content')
  <div class="page-header">
    <h1>Edit Franchise {{ $franchise->code }}</h1>
    <p class="muted-label">
      {{ $franchise->typeLabel() }} ·
      Balance {{ number_format((float) $franchise->balance, 2) }} ·
      Available {{ number_format($franchise->availableCredit(), 2) }}
    </p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('franchises.update', $franchise->id) }}">
    @csrf
    @method('PUT')
    @include('franchises._form', ['isEdit' => true])

    <div class="form-actions">
      <a class="btn" href="{{ route('franchises.index') }}">Cancel</a>
      <button class="btn" type="submit">Save Franchise</button>
    </div>
  </form>
@endsection
