@extends('layout', ['title' => 'Tax Rates'])
@section('content')
  <h1>Tax Rates</h1>
  <p class="hint">Create reusable taxes (e.g. VAT, GST) and attach them to billing plans. Used when generating invoices.</p>
  <a class="btn" href="{{ route('tax-rates.create') }}">+ New Tax Rate</a>
  <table>
    <thead><tr><th>Name</th><th>Rate</th><th>Type</th><th>Default</th><th>Actions</th></tr></thead>
    <tbody>
      @forelse ($taxes as $t)
        <tr>
          <td>{{ $t->name }}</td>
          <td>{{ number_format($t->rate, 2) }}{{ $t->type === 'fixed' ? ' (fixed)' : '%' }}</td>
          <td>{{ ucfirst($t->type) }}</td>
          <td>{{ $t->isDefault ? '★ Default' : '—' }}</td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('tax-rates.edit', $t->id) }}'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
              Edit
            </button>
            <button class="btn danger" onclick="deleteTax(event, '{{ route('tax-rates.destroy', $t->id) }}')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
              Delete
            </button>
          </td>
        </tr>
      @empty
        <tr><td colspan="5">No tax rates yet. Click <em>+ New Tax Rate</em> to create one.</td></tr>
      @endforelse
    </tbody>
  </table>

  <script>
    function deleteTax(event, url) {
      if (!confirm('Delete this tax rate? Plans using it will keep no tax until reassigned.')) return;
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
          window.toast('Tax rate deleted.', 'success');
        } else {
          window.toast('Failed to delete tax rate.', 'error');
        }
      }).catch(() => window.toast('Failed to delete tax rate.', 'error'));
    }
  </script>
@endsection
