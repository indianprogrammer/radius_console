<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Sign in — {{ $tenant->name ?? 'Radius Console' }}</title>
  <link rel="stylesheet" href="{{ asset('css/themes/tokens-light.css') }}">
  <link rel="stylesheet" href="{{ asset('css/themes/tokens-dark.css') }}">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <link rel="stylesheet" href="{{ asset('css/forms.css') }}">
</head>
<body>
  <main class="auth-page">
    <section class="auth-panel" aria-labelledby="login-title">
      <div class="auth-mark">{{ $tenant->name ?? 'Radius Console' }}</div>
      <h1 id="login-title">Sign in</h1>
      <p class="muted-label">Use the account assigned to your organisation.</p>

      @if ($errors->any())
        <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="field">
          <label for="username">Username or email <em>*</em></label>
          <input type="text" name="username" id="username" class="gui-input" value="{{ old('username') }}" required autofocus autocomplete="username" placeholder=" ">
        </div>
        <div class="field">
          <label for="password">Password <em>*</em></label>
          <input type="password" name="password" id="password" class="gui-input" required autocomplete="current-password" placeholder=" ">
        </div>
        <label class="auth-remember"><input type="checkbox" name="remember" value="1"> Remember me</label>
        <button class="btn auth-submit" type="submit">Sign in</button>
      </form>
    </section>
  </main>
</body>
</html>
