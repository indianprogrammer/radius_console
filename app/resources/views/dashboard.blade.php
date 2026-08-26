@extends('layout', ['title' => 'Dashboard'])
@section('content')
  <h1>Dashboard</h1>
  <div class="cards">
    <div class="card"><div class="num">{{ $stats['subscribers'] }}</div><div>Subscribers</div></div>
    <div class="card"><div class="num">{{ $stats['active'] }}</div><div>Active</div></div>
  </div>
@endsection
