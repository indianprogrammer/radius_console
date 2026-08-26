@extends('layout', ['title' => 'Plans'])
@section('content')
  <h1>Plans</h1>
  <a class="btn" href="{{ route('plans.create') }}">+ New Plan</a>
  <table>
    <thead><tr><th>Name</th><th>Download</th><th>Upload</th><th>Data Limit</th><th>Duration</th><th>RADIUS ID</th></tr></thead>
    <tbody>
      @forelse ($plans as $p)
        <tr>
          <td>{{ $p->name }}</td>
          <td>{{ $p->downloadMbps }} Mbps</td>
          <td>{{ $p->uploadMbps }} Mbps</td>
          <td>{{ $p->dataLimitGb ?? '—' }} GB</td>
          <td>{{ $p->durationDays }} d</td>
          <td>{{ $p->radiusProfileId ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="6">No plans created yet.</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection
