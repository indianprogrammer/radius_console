@extends('layout', ['title' => 'Edit Payment ' . $payment->number])
@section('content')
  <div class="page-header">
    <h1>Edit Payment {{ $payment->number }}</h1>
    <p class="muted-label">Changing the amount, status or linked invoice recalculates the affected invoice balances.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('payments.update', $payment->id) }}">
    @csrf
    @method('PUT')

    @include('payments._form', ['payment' => $payment])

    <div class="form-actions">
      <a class="btn" href="{{ route('payments.index') }}">Cancel</a>
      <button class="btn" type="submit">Save Payment</button>
    </div>
  </form>

  @include('partials.dmy-date-script')
@endsection
