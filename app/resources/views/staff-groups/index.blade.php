@extends('layout', ['title' => 'Teams'])
@section('content')
  <div class="page-header">
    <h1>Teams / Staff Groups</h1>
    <p class="muted-label">Named groups of staff. A ticket assigned to a team is expanded to its members.</p>
  </div>

  <a class="btn" href="{{ route('staff-groups.create') }}">+ New Team</a>
  <a class="btn" href="{{ route('staff.index') }}">Back to Staff</a>

  <form class="search-form" method="get" action="{{ route('staff-groups.index') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search team name…">
    <button type="submit" class="btn">Search</button>
    @if ($search)
      <a href="{{ route('staff-groups.index') }}" class="btn">Clear</a>
    @endif
  </form>

  <table>
    <thead>
      <tr>
        <th>Team</th>
        <th>Description</th>
        <th class="num">Members</th>
        <th>Active</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($groups as $g)
        <tr>
          <td>{{ $g->name }}</td>
          <td>{{ $g->description ?: '—' }}</td>
          <td class="num">{{ $g->members_count }}</td>
          <td><span class="pill pill-{{ $g->is_active ? 'paid' : 'void' }}">{{ $g->is_active ? 'Yes' : 'No' }}</span></td>
          <td>
            <button class="btn" onclick="window.location.href='{{ route('staff-groups.edit', $g->id) }}'">Edit</button>
            <button class="btn danger" onclick="deleteGroup(event, '{{ route('staff-groups.destroy', $g->id) }}')">Delete</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="5">No teams yet. Click <em>+ New Team</em> to create one.</td></tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $groups])
  @include('partials.per-page', ['paginator' => $groups, 'action' => route('staff-groups.index')])

  <script>
    function deleteGroup(event, url) {
      if (!confirm('Delete this team? Tickets keep the assignees already expanded from it.')) return;
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
          window.toast && window.toast(body.message || 'Team deleted.', 'success');
        } else {
          window.toast && window.toast(body.message || 'Failed to delete team.', 'error');
        }
      }).catch(() => window.toast && window.toast('Failed to delete team.', 'error'));
    }
  </script>
@endsection
