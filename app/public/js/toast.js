// Lightweight toast notifications. Call window.toast(message, type) from anywhere.
//   type: 'success' | 'error' | 'info'  (defaults to 'info')
// Toasts auto-dismiss, are accessible (aria-live), and stack vertically.
(function () {
  function ensureContainer() {
    let c = document.getElementById('toast-container');
    if (!c) {
      c = document.createElement('div');
      c.id = 'toast-container';
      c.className = 'toast-container';
      c.setAttribute('aria-live', 'polite');
      c.setAttribute('aria-atomic', 'true');
      document.body.appendChild(c);
    }
    return c;
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, ch => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[ch]));
  }

  // Auto-derived titles per type when none is supplied.
  const DEFAULT_TITLES = {
    success: 'Success',
    error: 'Error',
    info: 'Notice'
  };

  window.toast = function (message, type = 'info', durationMs = 4000) {
    const container = ensureContainer();
    const el = document.createElement('div');
    el.className = 'toast toast-' + type;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = '<span class="toast-icon" aria-hidden="true"></span>' +
                   '<span class="toast-msg">' + escapeHtml(message) + '</span>';

    // Dismiss on click.
    el.addEventListener('click', () => dismiss(el));

    container.appendChild(el);
    // Trigger enter transition on next frame.
    requestAnimationFrame(() => el.classList.add('toast-show'));

    const timer = setTimeout(() => dismiss(el), durationMs);
    el._toastTimer = timer;
    return el;
  };

  function dismiss(el) {
    if (!el || el._toastDismissed) return;
    el._toastDismissed = true;
    clearTimeout(el._toastTimer);
    el.classList.remove('toast-show');
    el.classList.add('toast-hide');
    el.addEventListener('transitionend', () => el.remove(), { once: true });
    // Fallback removal in case transitionend doesn't fire.
    setTimeout(() => el.remove(), 500);
  }

  // Expose for manual use if needed.
  window.dismissToast = dismiss;
})();
