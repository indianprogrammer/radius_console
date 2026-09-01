@extends('layout', ['title' => 'Franchises'])
@section('content')
  <div class="page-header">
    <h1>Franchises</h1>
    <p class="muted-label">Resellers / LCOs in the franchise hierarchy, with commission terms and prepaid wallet limits.</p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Franchises</span>
      <span class="sc-value">{{ $totals['total'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Active</span>
      <span class="sc-value sc-ok">{{ $totals['active'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Wallet Balance</span>
      <span class="sc-value">{{ number_format($totals['balance'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Credit Exposure</span>
      <span class="sc-value sc-warn">{{ number_format($totals['exposure'], 2) }}</span>
    </div>
  </div>

  <a class="btn" href="{{ route('franchises.create') }}">+ New Franchise</a>

  <form class="search-form" method="get" action="{{ route('franchises.index') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search code, name, contact or phone…">
    <select name="type">
      <option value="">All Types</option>
      @foreach (\App\Models\Franchise::TYPES as $val => $label)
        <option value="{{ $val }}" @selected(($type ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="status">
      <option value="">All Statuses</option>
      @foreach (\App\Models\Franchise::STATUSES as $val => $label)
        <option value="{{ $val }}" @selected(($status ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn">Search</button>
    @if ($search || $type || $status)
      <a href="{{ route('franchises.index') }}" class="btn">Clear</a>
    @endif
  </form>

  <table>
    <thead>
      <tr>
        <th>Code</th>
        <th>Name</th>
        <th>Type</th>
        <th>Parent</th>
        <th>Contact</th>
        <th class="num">Commission</th>
        <th class="num">Balance</th>
        <th class="num">Credit Limit</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($franchises as $f)
        <tr>
          <td>{{ $f->code }}</td>
          <td>{{ $f->name }}</td>
          <td>{{ $f->typeLabel() }}</td>
          <td>{{ $f->parent->name ?? '—' }}</td>
          <td>
            {{ $f->contact_person ?: '—' }}
            @if ($f->phone)
              <br><span class="muted-label">{{ $f->phone }}</span>
            @endif
          </td>
          <td class="num">{{ $f->commissionLabel() }}</td>
          <td class="num">{{ number_format((float) $f->balance, 2) }}</td>
          <td class="num">{{ number_format((float) $f->credit_limit, 2) }}</td>
          <td><span class="pill pill-{{ $f->status === 'active' ? 'paid' : ($f->status === 'suspended' ? 'partial' : 'void') }}">{{ $f->statusLabel() }}</span></td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('franchises.edit', $f->id) }}'">Edit</button>
            <button class="btn danger" onclick="deleteFranchise(event, '{{ route('franchises.destroy', $f->id) }}')">Delete</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="10">No franchises yet. Click <em>+ New Franchise</em> to add one.</td></tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $franchises])
  @include('partials.per-page', ['paginator' => $franchises, 'action' => route('franchises.index')])

  <script>
    function deleteFranchise(event, url) {
      if (!confirm('Delete this franchise?')) return;
      const row = event.currentTarget.closest('tr');
      fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json'
        }
      }).then(async response => {
        const body = await response.json().catch(() => ({}));
        if (response.ok) {
          if (row) row.remove();
          window.toast && window.toast(body.message || 'Franchise deleted.', 'success');
        } else {
          window.toast && window.toast(body.message || 'Failed to delete franchise.', 'error');
        }
      }).catch(() => window.toast && window.toast('Failed to delete franchise.', 'error'));
    }
  </script>
@endsection
