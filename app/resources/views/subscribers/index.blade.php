@extends('layout', ['title' => 'Subscribers'])
@section('content')
  <h1>Subscribers</h1>
  <a class="btn" href="{{ route('subscribers.create') }}">+ New Subscriber</a>
  <table>
    <thead><tr><th>Username</th><th>RADIUS User</th><th>Status</th></tr></thead>
    <tbody>
      @forelse ($subscribers as $s)
        <tr><td>{{ $s->username }}</td><td>{{ $s->radiusUsername }}</td><td>{{ $s->status }}</td></tr>
      @empty
        <tr><td colspan="3">No subscribers yet.</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection
