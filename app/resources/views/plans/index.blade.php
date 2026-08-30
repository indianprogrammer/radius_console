@extends('layout', ['title' => 'Plans'])
@section('content')
  <h1>Plans</h1>
  <a class="btn" href="{{ route('plans.create') }}">+ New Plan</a>
  <table>
    <thead><tr><th>Name</th><th>Price</th><th>Tax</th><th>Total</th><th>Duration</th><th>Bandwidth Profile</th><th>Bandwidth (GB)</th><th>Actions</th></tr></thead>
    <tbody>
      @forelse ($plans as $p)
        @php
          $profile = $p->bandwidthProfileId !== null
            ? collect($profiles)->firstWhere('id', $p->bandwidthProfileId)
            : null;
          $taxes = $p->taxRates ?? [];
          if (!empty($taxes)) {
              $taxLabel = collect($taxes)->map(fn($t) => $t->name . ' (' . number_format($t->rate, 2) . ($t->type === 'fixed' ? '' : '%') . ')')->implode(', ');
          } else {
              $taxLabel = '—';
          }
          $total = $p->total !== null ? (float) $p->total : $p->totalRounded((float) $p->price);
        @endphp
        <tr>
          <td>{{ $p->name }}</td>
          <td>{{ number_format($p->price, 2) }}</td>
          <td>{{ $taxLabel }}</td>
          <td><strong>{{ number_format($total, 2) }}</strong></td>
          <td>{{ $p->durationLabel() }}</td>
          <td>{{ $profile ? $profile->name : '—' }}</td>
          <td>{{ $p->dataLimitGb ? number_format($p->dataLimitGb, 0) . ' GB' : 'Unlimited' }}</td>
          <td>
            @if ($p->id)
              <button class="btn" onclick="window.location.href='{{ route('plans.edit', $p->id) }}'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                Edit
              </button>
              <button class="btn danger" onclick="deletePlan(event, '{{ route('plans.destroy', $p->id) }}')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                Delete
              </button>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="8">No plans created yet.</td></tr>
      @endforelse
    </tbody>
  </table>
  @include('partials.pager', ['paginator' => $plans])
  <form class="per-page" method="get" action="{{ route('plans.index') }}" id="per-page-form">
    <label for="per_page">Rows per page</label>
    <select id="per_page" name="per_page" onchange="submitPerPage(this)">
      @foreach ([10, 25, 50, 100] as $opt)
        <option value="{{ $opt }}" @selected($plans->perPage() == $opt)>{{ $opt }}</option>
      @endforeach
    </select>
  </form>

  <script>
    // Navigate by *submitting the form* rather than assigning window.location.
    // Chrome commits a native <select> selection and fires `change` only as the
    // popup is closing; a deferred window.location.href assignment from that
    // handler is the case where Chrome drops the navigation (the control feels
    // "stuck"), while Firefox tolerates it. A real form submission is handled
    // identically by both engines and closes the popup cleanly. We preserve the
    // existing query string (e.g. ?page=) and reset to page 1 on size change.
    function submitPerPage(sel) {
      const form = sel.form;
      const url = new URL(form.action, window.location.origin);
      const params = new URLSearchParams(window.location.search);
      params.set('per_page', sel.value);
      params.set('page', '1');
      url.search = params.toString();
      form.action = url.toString();
      form.submit();
    }
    function deletePlan(event, url) {
      if (!confirm('Delete this plan? This is a local billing record only.')) return;
      const row = event.currentTarget.closest('tr');
      fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json'
        }
      }).then(response => {
        if (response.ok) {
          if (row) row.remove();
          window.toast('Plan deleted.', 'success');
        } else {
          window.toast('Failed to delete plan.', 'error');
        }
      }).catch(() => {
        window.toast('Failed to delete plan.', 'error');
      });
    }
  </script>
@endsection
