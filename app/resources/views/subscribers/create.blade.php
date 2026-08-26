@extends('layout', ['title' => 'New Subscriber'])
@section('content')
  <h1>New Subscriber</h1>
  <form method="POST" action="{{ route('subscribers.store') }}">
    @csrf
    <label>Username<input name="username" required></label>
    <label>Password<input type="password" name="password" required></label>
    <label>Plan ID<input name="plan_id" type="number"></label>
    <label>MAC<input name="mac"></label>
    <label>Static IP<input name="static_ip"></label>
    <label>Expiry<input type="date" name="expiry"></label>
    <button class="btn" type="submit">Provision</button>
  </form>
@endsection
