@extends('layout', ['title' => 'Generate Invoice'])
@section('content')
  <div class="page-header">
    <h1>Generate Invoice</h1>
    <p class="muted-label">Builds an invoice from the selected subscriber&rsquo;s saved billing items (installation, deposits, recurring charges).</p>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('invoices.store') }}">
    @csrf

    <div class="panel">
      <div class="panel-body">
        <h4 class="section-title">Invoice Details</h4>
        <div class="form-grid">

          <div class="field col-6">
            <label for="subscriber_id">Subscriber <em>*</em></label>
            <select name="subscriber_id" id="subscriber_id" class="gui-input" required>
              <option value="">— select —</option>
              @foreach ($subscribers as $s)
                <option value="{{ $s->id }}" @selected(old('subscriber_id') == $s->id)>
                  {{ $s->username }}{{ trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')) ? ' — ' . trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')) : '' }}
                </option>
              @endforeach
            </select>
            <span class="hint">Line items come from the subscriber&rsquo;s billing items.</span>
          </div>

          <div class="field col-3">
            <label for="due_display">Due Date</label>
            <input type="text" class="gui-input js-dmy" id="due_display" data-target="due_date"
                   placeholder=" " autocomplete="off" inputmode="numeric">
            <input type="hidden" name="due_date" id="due_date" value="{{ old('due_date', now()->addDays(15)->format('Y-m-d\TH:i')) }}">
            <span class="hint">dd/mm/yy — defaults to 15 days out</span>
          </div>

          <div class="field col-12">
            <label for="notes">Notes</label>
            <textarea name="notes" id="notes" class="gui-input" rows="2" placeholder=" ">{{ old('notes') }}</textarea>
          </div>

        </div>
      </div>
    </div>

    <div class="form-actions">
      <a class="btn" href="{{ route('invoices.index') }}">Cancel</a>
      <button class="btn" type="submit">Generate Invoice</button>
    </div>
  </form>

  @include('partials.dmy-date-script')
@endsection
