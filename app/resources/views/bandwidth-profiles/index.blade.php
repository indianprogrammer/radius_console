@extends('layout', ['title' => 'Bandwidth Control'])
@section('content')
  <h1>Bandwidth Control</h1>
  <a class="btn" href="{{ route('bandwidth-profiles.create') }}">+ New Bandwidth Profile</a>
  <table>
    <thead><tr><th>Name</th><th>RADIUS Plan</th><th>Download</th><th>Upload</th><th>VLAN</th><th>FUP (GB)</th><th>Interim (s)</th><th>Actions</th></tr></thead>
    <tbody>
      @forelse ($profiles as $p)
        <tr>
          <td>{{ $p['name'] }}</td>
          <td>{{ $p['radius_plan_id'] ?? '—' }}</td>
          <td>{{ $p['bandwidth_download_mbps'] }} Mbps</td>
          <td>{{ $p['bandwidth_upload_mbps'] }} Mbps</td>
          <td>{{ $p['vlan_id'] ?? '—' }}</td>
          <td>{{ $p['fup_threshold_gb'] ?? '—' }}@if (!empty($p['fup_threshold_gb'])) <span class="muted">→ {{ $p['fup_download_mbps'] ?? '—' }}/{{ $p['fup_upload_mbps'] ?? '—' }} Mbps</span>@endif</td>
          <td>{{ $p['interim_interval'] ?? '—' }}</td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('bandwidth-profiles.edit', $p['id']) }}'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
              Edit
            </button>
            <button class="btn danger" onclick="deleteProfile(event, '{{ route('bandwidth-profiles.destroy', $p['id']) }}')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
              Delete
            </button>
          </td>
        </tr>
      @empty
        <tr><td colspan="8">No bandwidth profiles yet. Click <em>+ New Bandwidth Profile</em> to create one — it will sync to RADIUS.</td></tr>
      @endforelse
    </tbody>
  </table>
  @include('partials.pager', ['paginator' => $profiles])
  <form class="per-page" method="get" action="{{ route('bandwidth-profiles.index') }}" id="per-page-form">
    <label for="per_page">Rows per page</label>
    <select id="per_page" name="per_page" onchange="submitPerPage(this)">
      @foreach ([10, 25, 50, 100] as $opt)
        <option value="{{ $opt }}" @selected($profiles->perPage() == $opt)>{{ $opt }}</option>
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
    function deleteProfile(event, url) {
      if (!confirm('Delete this bandwidth profile? It will also be removed from RADIUS.')) return;
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
          window.toast('Bandwidth profile deleted and removed from RADIUS.', 'success');
        } else {
          window.toast('Failed to delete bandwidth profile.', 'error');
        }
      }).catch(() => window.toast('Failed to delete bandwidth profile.', 'error'));
    }
  </script>
@endsection
