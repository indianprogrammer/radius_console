<!DOCTYPE html>
<html lang="en" data-theme="{{ $tenant->theme_default ?? 'light' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? 'Radius Console' }} — {{ $tenant->name ?? 'Console' }}</title>
  <link rel="stylesheet" href="{{ asset('css/themes/tokens-light.css') }}">
  <link rel="stylesheet" href="{{ asset('css/themes/tokens-dark.css') }}">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <script src="{{ asset('js/theme-toggle.js') }}" defer></script>
  <script src="{{ asset('js/toast.js') }}" defer></script>
</head>
<body>
  <header class="topbar">
    <div class="topbar-left">
      <span class="topbar-brand">{{ $tenant->name ?? 'Radius Console' }}</span>
    </div>
    <div class="topbar-center">
      @yield('topbar', '')
    </div>
    <div class="topbar-right">
      <button class="theme-btn" data-theme-toggle title="Toggle theme">◐</button>
      @yield('topbar-right', '')
    </div>
  </header>

  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">{{ $tenant->name ?? 'Radius Console' }}</div>
      <nav class="menu">@include('partials.menu')</nav>
      <div class="sidebar-footer">
        <span class="muted-label">v2.0 · Control Plane</span>
      </div>
    </aside>
    <main class="content">
      @yield('content')
    </main>
  </div>

  <footer class="bottombar">
    <div class="bottombar-left">
      @yield('bottombar-left', 'Radius Console · Multi-tenant ISP Subscriber Management')
    </div>
    <div class="bottombar-center">
      @yield('bottombar', '')
    </div>
    <div class="bottombar-right">
      @yield('bottombar-right', 'Env: ' . (app()->environment()))
    </div>
  </footer>

  <!-- Toast notifications (one per user action). -->
  <div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>
  <script>
    // Surface any server-flashed status as a toast (replaces the old inline
    // alert so every action gets a consistent notification).
    @if (session('status'))
      window.addEventListener('DOMContentLoaded', () => window.toast('{{ addslashes(session('status')) }}', 'success'));
    @endif
    // Surface any validation / server errors as an error toast too, so every
    // failing operation also gets a notification (inline alert remains as the
    // persistent reference).
    @if ($errors->any())
      window.addEventListener('DOMContentLoaded', () => window.toast('{{ addslashes($errors->first()) }}', 'error'));
    @endif
  </script>
  @stack('scripts')
</body>
</html>
