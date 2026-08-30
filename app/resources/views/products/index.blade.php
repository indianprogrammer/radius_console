@extends('layout', ['title' => 'Products & Services'])
@section('content')
  <h1>Products &amp; Services</h1>
  <a class="btn" href="{{ route('products.create') }}">+ New Product / Service</a>
  <form class="search-form" method="get" action="{{ route('products.index') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search name or description…">
    <select name="category">
      <option value="">All Categories</option>
      <option value="one-time" @selected(($category ?? '') === 'one-time')>One-time</option>
      <option value="recurring" @selected(($category ?? '') === 'recurring')>Recurring</option>
    </select>
    <button type="submit" class="btn">Search</button>
    @if ($search || $category)
      <a href="{{ route('products.index') }}" class="btn">Clear</a>
    @endif
  </form>
  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Category</th>
        <th>Default Amount</th>
        <th>Unit</th>
        <th>Tax</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($products as $p)
        @php
          $taxesList = $p->taxes ?? collect();
          if (is_array($taxesList)) {
              $taxesList = collect($taxesList);
          }
          $taxLabel = $taxesList->isEmpty() ? '—' : $taxesList->map(fn($t) => $t->name . ' (' . number_format($t->rate, 2) . ($t->type === 'fixed' ? '' : '%') . ')')->implode(', ');
        @endphp
        <tr>
          <td>{{ $p->name }}</td>
          <td>{{ ucfirst(str_replace('-', ' ', $p->category)) }}</td>
          <td>{{ number_format($p->default_amount, 2) }}</td>
          <td>{{ $p->unit }}</td>
          <td>{{ $taxLabel }}</td>
          <td>{{ $p->is_active ? 'Active' : 'Inactive' }}</td>
          <td>
            @if ($p->id)
              <button class="btn" onclick="window.location.href='{{ route('products.edit', $p->id) }}'">Edit</button>
              <button class="btn danger" onclick="deleteProduct(event, '{{ route('products.destroy', $p->id) }}')">Delete</button>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7">No products / services found.</td></tr>
      @endforelse
    </tbody>
  </table>
  @include('partials.pager', ['paginator' => $products])

  <script>
    function deleteProduct(event, url) {
      if (!confirm('Delete this product / service?')) return;
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
          window.toast && window.toast('Product / service deleted.', 'success');
        } else {
          window.toast && window.toast('Failed to delete.', 'error');
        }
      }).catch(() => {
        window.toast && window.toast('Failed to delete.', 'error');
      });
    }
  </script>
@endsection
