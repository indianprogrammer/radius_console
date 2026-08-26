@extends('layout', ['title' => 'NAS / Devices'])
@section('content')
  <h1>NAS / Devices</h1>
  <a class="btn" href="{{ route('nas.create') }}">+ Register Device</a>
  <table>
    <thead><tr><th>Label</th><th>NAS IP</th><th>Type</th><th>RADIUS ID</th></tr></thead>
    <tbody>
      @forelse ($nas as $n)
        <tr>
          <td>{{ $n->name ?? $n->nasIp }}</td>
          <td>{{ $n->nasIp }}</td>
          <td>{{ $n->type ?? '—' }}</td>
          <td>{{ $n->radiusNasId ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="4">No devices registered yet.</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection
