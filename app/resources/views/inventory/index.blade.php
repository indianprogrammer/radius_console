@extends('layout', ['title' => 'Inventory Management'])
@section('content')
  <h1>Inventory Management</h1>
  <a class="btn" href="{{ route('inventory.create') }}">+ New Inventory Item</a>
  <form class="search-form" method="get" action="{{ route('inventory.index') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search name or SKU…">
    <select name="category">
      <option value="">All Categories</option>
      <option value="physical" @selected(($category ?? '') === 'physical')>Physical</option>
      <option value="digital" @selected(($category ?? '') === 'digital')>Digital</option>
      <option value="service" @selected(($category ?? '') === 'service')>Service</option>
      <option value="accessory" @selected(($category ?? '') === 'accessory')>Accessory</option>
    </select>
    <label style="display:flex;align-items:center;gap:.5rem;margin-top:.5rem">
      <input type="checkbox" name="low_stock" value="1" {{ $lowStock ? 'checked' : '' }}>
      Low Stock Only
    </label>
    <button type="submit" class="btn">Search</button>
    @if ($search || $category || $lowStock)
      <a href="{{ route('inventory.index') }}" class="btn">Clear</a>
    @endif
  </form>
  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <div class="inventory-grid">
    @forelse ($items as $i)
      <div class="inventory-card {{ $i->stock_quantity <= $i->reorder_point ? 'low-stock' : '' }}">
        <div class="card-header">
          <h3>{{ $i->name }}</h3>
          <span class="sku">{{ $i->sku }}</span>
        </div>
        <div class="card-body">
          <p class="description">{{ $i->description ?? 'No description' }}</p>
          <div class="stats">
            <div class="stat">
              <label>Category:</label>
              <span class="category">{{ ucfirst($i->category) }}</span>
            </div>
            <div class="stat">
              <label>Stock:</label>
              <span class="stock">{{ number_format($i->stock_quantity, 2) }} {{ $i->unit }}</span>
            </div>
            <div class="stat">
              <label>Reorder Point:</label>
              <span>{{ number_format($i->reorder_point, 2) }} {{ $i->unit }}</span>
            </div>
            <div class="stat price">
              <label>Cost:</label>
              <span class="cost">${{ number_format($i->cost_price, 2) }}</span>
            </div>
            <div class="stat price">
              <label>Sale:</label>
              <span class="sale">${{ number_format($i->sale_price, 2) }}</span>
            </div>
          </div>
        </div>
        <div class="card-footer">
          <button class="btn" onclick="window.location.href='{{ route('inventory.edit', $i->id) }}'">Edit</button>
          <button class="btn danger" onclick="deleteItem(event, '{{ route('inventory.destroy', $i->id) }}')">Delete</button>
        </div>
      </div>
    @empty
      <div class="no-items">No inventory items found.</div>
    @endforelse
  </div>
  @include('partials.pager', ['paginator' => $items])

  <style>
    .inventory-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }
    .inventory-card {
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 1rem;
      background: white;
      transition: transform 0.2s;
    }
    .inventory-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .inventory-card.low-stock {
      border-color: #dc3545;
      background: #fff5f5;
    }
    .card-header {
      margin-bottom: 0.5rem;
    }
    .card-header h3 {
      margin: 0 0 0.25rem 0;
      font-size: 1.1rem;
    }
    .sku {
      font-size: 0.85rem;
      color: #666;
    }
    .description {
      margin: 0.5rem 0;
      color: #555;
      font-size: 0.9rem;
    }
    .stats {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.5rem;
      margin: 0.5rem 0;
    }
    .stat {
      display: flex;
      justify-content: space-between;
      font-size: 0.85rem;
    }
    .stat label {
      font-weight: 600;
      color: #333;
    }
    .card-footer {
      margin-top: 1rem;
      display: flex;
      gap: 0.5rem;
    }
    .no-items {
      text-align: center;
      padding: 2rem;
      color: #666;
      font-style: italic;
    }
  </style>

  <script>
    function deleteItem(event, url) {
      if (!confirm('Delete this inventory item?')) return;
      const card = event.currentTarget.closest('.inventory-card');
      fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json'
        }
      }).then(response => {
        if (response.ok) {
          if (card) card.remove();
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