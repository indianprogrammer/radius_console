@extends('layout', ['title' => 'Edit ' . \App\Models\Quote::TYPES[$type] . ' ' . $quote->number])
@section('content')
  @php $label = \App\Models\Quote::TYPES[$type]; @endphp

  <div class="page-header">
    <h1>Edit {{ $label }} {{ $quote->number }}</h1>
    <p class="muted-label">
      {{ $quote->customerLabel() }} ·
      Total {{ number_format($quote->payableAmount(), 2) }}
      <span class="pill pill-{{ $quote->statusPill() }}">{{ $quote->statusLabel() }}</span>
    </p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('quotes.update', [$type, $quote->id]) }}">
    @csrf
    @method('PUT')
    @include('quotes._form')

    <div class="form-actions">
      <a class="btn" href="{{ route('quotes.show', [$type, $quote->id]) }}">Cancel</a>
      <button class="btn" type="submit">Save {{ $label }}</button>
    </div>
  </form>
@endsection
