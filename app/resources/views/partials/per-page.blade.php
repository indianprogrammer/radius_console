{{--
  Shared "Rows per page" control.

  Usage: @include('partials.per-page', ['paginator' => $items, 'action' => route('x.index')])

  Navigates by *submitting the form* rather than assigning window.location.
  Chrome commits a native <select> selection and fires `change` only as the
  popup is closing; a deferred window.location.href assignment from that
  handler is the case where Chrome drops the navigation (the control feels
  "stuck"), while Firefox tolerates it. A real form submission is handled
  identically by both engines and closes the popup cleanly. The existing query
  string is preserved and the page resets to 1 on size change.
--}}
<form class="per-page" method="get" action="{{ $action }}">
  <label for="per_page">Rows per page</label>
  <select id="per_page" name="per_page" onchange="submitPerPage(this)">
    @foreach ([10, 25, 50, 100] as $opt)
      <option value="{{ $opt }}" @selected($paginator->perPage() == $opt)>{{ $opt }}</option>
    @endforeach
  </select>
</form>

@once
  <script>
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
@endonce
