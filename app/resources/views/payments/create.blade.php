@extends('layout', ['title' => 'Record Payment'])
@section('content')
  <div class="page-header">
    <h1>Record Payment</h1>
    <p class="muted-label">Log a receipt against an invoice, or on account when no invoice applies.</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('payments.store') }}">
    @csrf

    @include('payments._form', [
      'payment' => new \App\Models\Payment([
        'invoice_id'    => $invoice?->id,
        'subscriber_id' => $invoice?->subscriber_id,
        'amount'        => $invoice ? number_format($invoice->balance(), 2, '.', '') : null,
        'method'        => 'cash',
        'status'        => 'completed',
      ]),
    ])

    <div class="form-actions">
      <a class="btn" href="{{ route('payments.index') }}">Cancel</a>
      <button class="btn" type="submit">Record Payment</button>
    </div>
  </form>

  @include('partials.dmy-date-script')
@endsection
