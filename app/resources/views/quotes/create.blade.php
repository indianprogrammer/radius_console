@extends('layout', ['title' => 'New ' . \App\Models\Quote::TYPES[$type]])
@section('content')
  @php $label = \App\Models\Quote::TYPES[$type]; @endphp

  <div class="page-header">
    <h1>New {{ $label }}</h1>
    <p class="muted-label">
      {{ $type === \App\Models\Quote::TYPE_QUOTATION
          ? 'Price up work for a customer or prospect. Convert it to an invoice once accepted.'
          : 'Request an advance payment. Convert it to an invoice once the customer commits.' }}
    </p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('quotes.store', $type) }}">
    @csrf
    @include('quotes._form')

    <div class="form-actions">
      <a class="btn" href="{{ route('quotes.index', $type) }}">Cancel</a>
      <button class="btn" type="submit">Save {{ $label }}</button>
    </div>
  </form>
@endsection
