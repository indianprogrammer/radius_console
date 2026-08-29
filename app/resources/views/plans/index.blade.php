@extends('layout', ['title' => 'Plans'])
@section('content')
  <h1>Plans</h1>
  <a class="btn" href="{{ route('plans.create') }}">+ New Plan</a>
  <table>
    <thead><tr><th>Name</th><th>Price</th><th>Cycle</th><th>Bandwidth Profile</th><th>Tax</th><th>Actions</th></tr></thead>
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
        @endphp
        <tr>
          <td>{{ $p->name }}</td>
          <td>{{ number_format($p->price, 2) }}</td>
          <td>{{ ucfirst($p->cycle) }}</td>
          <td>{{ $profile ? $profile->name : '—' }}</td>
          <td>{{ $taxLabel }}</td>
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
        <tr><td colspan="6">No plans created yet.</td></tr>
      @endforelse
    </tbody>
  </table>

  <script>
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
