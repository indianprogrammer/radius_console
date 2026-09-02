@extends('layout', ['title' => 'Inventory'])
@section('content')
  <div class="page-header">
    <h1>Inventory</h1>
    <p class="muted-label">Stock items with reorder thresholds and cost / sale pricing.</p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Items</span>
      <span class="sc-value">{{ $totals['total'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Active</span>
      <span class="sc-value sc-ok">{{ $totals['active'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Low Stock</span>
      <span class="sc-value {{ $totals['low'] > 0 ? 'sc-bad' : 'sc-ok' }}">{{ $totals['low'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Stock Value (cost)</span>
      <span class="sc-value">{{ number_format($totals['value'], 2) }}</span>
    </div>
  </div>

  <a class="btn" href="{{ route('inventory.create') }}">+ New Item</a>
  <a class="btn" href="{{ route('products.index') }}">Products &amp; Services</a>

  <form class="search-form" method="get" action="{{ route('inventory.index') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search SKU, name or description…">
    <select name="category">
      <option value="">All Categories</option>
      @foreach (\App\Models\Inventory::CATEGORIES as $val => $label)
        <option value="{{ $val }}" @selected(($category ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="status">
      <option value="">All Statuses</option>
      <option value="active" @selected(($status ?? '') === 'active')>Active</option>
      <option value="inactive" @selected(($status ?? '') === 'inactive')>Inactive</option>
    </select>
    <select name="low_stock">
      <option value="">All Stock Levels</option>
      <option value="1" @selected($lowStock)>Low stock only</option>
    </select>
    <button type="submit" class="btn">Search</button>
    @if ($search || $category || $status || $lowStock)
      <a href="{{ route('inventory.index') }}" class="btn">Clear</a>
    @endif
  </form>

  <table>
    <thead>
      <tr>
        <th>SKU</th>
        <th>Name</th>
        <th>Category</th>
        <th class="num">In Stock</th>
        <th class="num">Reorder At</th>
        <th class="num">Cost</th>
        <th class="num">Sale</th>
        <th>Stock</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($items as $i)
        <tr>
          <td>{{ $i->sku }}</td>
          <td>
            {{ $i->name }}
            @if ($i->description)<div class="muted-label">{{ Str::limit($i->description, 60) }}</div>@endif
          </td>
          <td>{{ $i->categoryLabel() }}</td>
          <td class="num">{{ number_format($i->stock_quantity, 2) }} {{ $i->unit }}</td>
          <td class="num">{{ number_format($i->reorder_point, 2) }}</td>
          <td class="num">{{ number_format($i->cost_price, 2) }}</td>
          <td class="num">{{ number_format($i->sale_price, 2) }}</td>
          <td>
            <span class="pill pill-{{ $i->isOutOfStock() ? 'failed' : ($i->isLowStock() ? 'overdue' : 'paid') }}">
              {{ $i->isOutOfStock() ? 'Out of stock' : ($i->isLowStock() ? 'Low' : 'OK') }}
            </span>
          </td>
          <td>
            <span class="pill pill-{{ $i->is_active ? 'paid' : 'void' }}">
              {{ $i->is_active ? 'Active' : 'Inactive' }}
            </span>
          </td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('inventory.edit', $i->id) }}'">Edit</button>
            <button class="btn danger" onclick="deleteInventoryItem(event, '{{ route('inventory.destroy', $i->id) }}')">Delete</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="10">No inventory items yet. Click <em>+ New Item</em> to add stock.</td></tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $items])
  @include('partials.per-page', ['paginator' => $items, 'action' => route('inventory.index')])

  <script>
    function deleteInventoryItem(event, url) {
      if (!confirm('Delete this inventory item?')) return;
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
          window.toast && window.toast('Inventory item deleted.', 'success');
        } else {
          window.toast && window.toast('Failed to delete.', 'error');
        }
      }).catch(() => {
        window.toast && window.toast('Failed to delete.', 'error');
      });
    }
  </script>
@endsection