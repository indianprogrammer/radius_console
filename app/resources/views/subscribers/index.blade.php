@extends('layout', ['title' => 'Subscribers'])
@section('content')
  <h1>Subscribers</h1>
  <a class="btn" href="{{ route('subscribers.create') }}">+ New Subscriber</a>
  <table>
    <thead><tr><th>Username</th><th>RADIUS User</th><th>Status</th></tr></thead>
    <tbody>
      @forelse ($subscribers as $s)
        <tr><td>{{ $s->username }}</td><td>{{ $s->radiusUsername }}</td><td>{{ $s->status }}</td></tr>
      @empty
        <tr><td colspan="3">No subscribers yet.</td></tr>
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
  </script>
@endsection
