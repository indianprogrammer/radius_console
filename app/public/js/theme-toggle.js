// Theme toggle: persisted per-user via localStorage + server profile (SRD §3.2).
(function () {
  const KEY = 'radius_console_theme';
  const saved = localStorage.getItem(KEY);
  if (saved) document.documentElement.setAttribute('data-theme', saved);

  window.setTheme = function (theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem(KEY, theme);
    // Persist server-side via profile endpoint (best-effort).
    fetch('/profile/theme', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({ theme })
    }).then(() => {
      window.toast(theme === 'dark' ? 'Switched to dark theme.' : 'Switched to light theme.', 'info');
    }).catch(() => {
      window.toast('Theme changed locally; could not save preference.', 'error');
    });
  };

  document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      const current = document.documentElement.getAttribute('data-theme') || 'light';
      window.setTheme(current === 'dark' ? 'light' : 'dark');
    });
  });

  // Collapsible menu groups (e.g. Radius Control). State is route-driven:
  // the server renders a group "open" only when one of its child pages is active,
  // so navigating to any other menu collapses it. This handler only toggles the
  // current view locally; no persisted open state (keeps "closed by default").
  document.querySelectorAll('[data-group-toggle]').forEach(btn => {
    const sub = btn.parentElement.querySelector('.menu-sublist');
    btn.addEventListener('click', () => {
      const nowOpen = btn.classList.toggle('open');
      sub?.classList.toggle('open', nowOpen);
    });
  });
})();
