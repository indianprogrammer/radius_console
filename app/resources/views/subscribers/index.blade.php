@extends('layout', ['title' => 'Subscribers'])
@section('content')
  <h1>Subscribers</h1>
  <a class="btn" href="{{ route('subscribers.create') }}">+ New Subscriber</a>
  <table>
    <thead><tr><th>Username</th><th>RADIUS User</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      @forelse ($subscribers as $s)
        <tr>
          <td>{{ $s->username }}</td>
          <td>{{ $s->radiusUsername }}</td>
          <td>{{ $s->status }}</td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('subscribers.edit', $s->id) }}'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
              Edit
            </button>
            <button class="btn danger" onclick="deleteSubscriber(event, '{{ route('subscribers.destroy', $s->id) }}')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
              Delete
            </button>
          </td>
        </tr>
      @empty
        <tr><td colspan="4">No subscribers yet.</td></tr>
      @endforelse
    </tbody>
  </table>
  @include('partials.pager', ['paginator' => $subscribers])
  <form class="per-page" method="get" action="{{ route('subscribers.index') }}" id="per-page-form">
    <label for="per_page">Rows per page</label>
    <select id="per_page" name="per_page" onchange="submitPerPage(this)">
      @foreach ([10, 25, 50, 100] as $opt)
        <option value="{{ $opt }}" @selected($subscribers->perPage() == $opt)>{{ $opt }}</option>
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
    function deleteSubscriber(event, url) {
      if (!confirm('Delete this subscriber? They will be removed from RADIUS as well.')) return;
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
          window.toast('Subscriber deleted.', 'success');
        } else {
          window.toast('Failed to delete subscriber.', 'error');
        }
      }).catch(() => {
        window.toast('Failed to delete subscriber.', 'error');
      });
    }
  </script>
@endsection
