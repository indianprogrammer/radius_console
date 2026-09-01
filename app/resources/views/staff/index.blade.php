@extends('layout', ['title' => 'Staff'])
@section('content')
  <div class="page-header">
    <h1>Staff</h1>
    <p class="muted-label">Employee master for Staff &amp; HR — salary structure, teams, attendance and payroll.</p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Employees</span>
      <span class="sc-value">{{ $totals['total'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Active</span>
      <span class="sc-value sc-ok">{{ $totals['active'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">On Leave</span>
      <span class="sc-value sc-warn">{{ $totals['on_leave'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Monthly Payroll</span>
      <span class="sc-value">{{ number_format($totals['payroll'], 2) }}</span>
    </div>
  </div>

  <a class="btn" href="{{ route('staff.create') }}">+ New Staff</a>
  <a class="btn" href="{{ route('staff-groups.index') }}">Manage Teams</a>
  <a class="btn" href="{{ route('attendance.index') }}">Attendance</a>
  <a class="btn" href="{{ route('payroll.index') }}">Payroll</a>

  <form class="search-form" method="get" action="{{ route('staff.index') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search code, name, designation or phone…">
    <select name="role">
      <option value="">All Roles</option>
      @foreach (\App\Models\Staff::ROLES as $val => $label)
        <option value="{{ $val }}" @selected(($role ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="status">
      <option value="">All Statuses</option>
      @foreach (\App\Models\Staff::STATUSES as $val => $label)
        <option value="{{ $val }}" @selected(($status ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn">Search</button>
    @if ($search || $role || $status)
      <a href="{{ route('staff.index') }}" class="btn">Clear</a>
    @endif
  </form>

  <table>
    <thead>
      <tr>
        <th>Code</th>
        <th>Name</th>
        <th>Role</th>
        <th>Department</th>
        <th>Franchise</th>
        <th>Contact</th>
        <th class="num">Gross / Month</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($staff as $m)
        <tr>
          <td>{{ $m->code }}</td>
          <td>
            <a href="{{ route('staff.show', $m->id) }}">{{ $m->name }}</a>
            @if ($m->designation)<div class="muted-label">{{ $m->designation }}</div>@endif
          </td>
          <td>{{ $m->roleLabel() }}</td>
          <td>{{ $m->department ?: '—' }}</td>
          <td>{{ $m->franchise->name ?? 'Head Office' }}</td>
          <td>
            {{ $m->phone ?: '—' }}
            @if ($m->email)<div class="muted-label">{{ $m->email }}</div>@endif
          </td>
          <td class="num">{{ number_format($m->grossSalary(), 2) }}</td>
          <td>
            <span class="pill pill-{{ $m->status === 'active' ? 'paid' : (in_array($m->status, ['on_leave', 'suspended']) ? 'partial' : 'void') }}">
              {{ $m->statusLabel() }}
            </span>
          </td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('staff.show', $m->id) }}'">View</button>
            <button class="btn" onclick="window.location.href='{{ route('staff.edit', $m->id) }}'">Edit</button>
            <button class="btn danger" onclick="deleteStaff(event, '{{ route('staff.destroy', $m->id) }}')">Delete</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="9">No staff yet. Click <em>+ New Staff</em> to add an employee.</td></tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $staff])
  @include('partials.per-page', ['paginator' => $staff, 'action' => route('staff.index')])

  <script>
    function deleteStaff(event, url) {
      if (!confirm('Delete this staff member?')) return;
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
          window.toast && window.toast(body.message || 'Staff deleted.', 'success');
        } else {
          window.toast && window.toast(body.message || 'Failed to delete staff.', 'error');
        }
      }).catch(() => window.toast && window.toast('Failed to delete staff.', 'error'));
    }
  </script>
@endsection
