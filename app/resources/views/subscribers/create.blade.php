@extends('layout', ['title' => 'New Subscriber'])
@section('content')
  <h1>New Subscriber</h1>
  <form method="POST" action="{{ route('subscribers.store') }}">
    @csrf
    <label>Username<input name="username" required></label>
    <label>Password<input type="password" name="password" required></label>
    <label>Bandwidth Profile<select name="bandwidth_profile_id">
      <option value="">— none —</option>
      @foreach ($profiles as $bp)
        <option value="{{ $bp->id }}">{{ $bp->name }} ({{ $bp->downloadMbps }}/{{ $bp->uploadMbps }} Mbps)</option>
      @endforeach
    </select></label>
    <label>Billing Plan<select name="plan_id">
      <option value="">— none —</option>
      @foreach ($plans as $pl)
        <option value="{{ $pl->id }}">{{ $pl->name }} — {{ number_format($pl->price, 2) }}/{{ $pl->cycle }}</option>
      @endforeach
    </select></label>
    <label>MAC<input name="mac"></label>
    <label>Static IP<input name="static_ip"></label>
    <label>Expiry<input type="date" name="expiry"></label>
    <button class="btn" type="submit">Provision</button>
  </form>
@endsection
