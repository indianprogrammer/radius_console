{{--
  Shared dd/mm/yy date input behaviour (optionally with a hh:ii time part).

  Native <input type="date|datetime-local"> always renders in the BROWSER's
  locale, so it cannot be forced to dd/mm/yy. Instead each date field is a pair:

    <input type="text"   class="js-dmy" data-target="expiry" ...>  ← what the user sees/types
    <input type="hidden" name="expiry"  id="expiry" ...>           ← ISO value that is submitted

  The hidden field keeps the `Y-m-d H:i` format Laravel already validates with
  `nullable|date`, so no controller/DB changes are needed.

  Attributes on the visible input:
    data-target        (required) id of the hidden ISO input
    data-with-time     present  → mask/parse as "dd/mm/yy hh:ii"
                       absent   → mask/parse as "dd/mm/yy"
    data-default-time  time-of-day applied when the user omits the time
                       (default "00:00"; expiry uses "23:59" so a same-day
                       expiry lasts the whole day)

  Exposes window.dmySetDate(targetId, dateObj|null) so other scripts can push a
  computed date into a field and keep both halves in sync.
--}}
<script>
  (function () {
    const pad = n => String(n).padStart(2, '0');

    /** Date -> "dd/mm/yy" or "dd/mm/yy hh:ii" for display. */
    function toDmy(d, withTime) {
      const date = `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${pad(d.getFullYear() % 100)}`;
      return withTime ? `${date} ${pad(d.getHours())}:${pad(d.getMinutes())}` : date;
    }

    /** Date -> "YYYY-MM-DDTHH:mm" for the hidden (submitted) input. */
    function toIso(d) {
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
        + `T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    /** Parse whatever the server rendered ("Y-m-d H:i:s", ISO, etc.) -> Date|null. */
    function parseIso(value) {
      if (!value) return null;
      const m = String(value).trim()
        .match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/);
      if (!m) return null;
      const d = new Date(+m[1], +m[2] - 1, +m[3], +(m[4] ?? 0), +(m[5] ?? 0));
      return isNaN(d) ? null : d;
    }

    /**
     * Parse user input. Accepts "dd/mm/yy" and "dd/mm/yyyy" (also with - or .
     * separators) plus an optional " hh:ii" time, and rejects impossible values
     * such as 31/02/26 or 25:00.
     */
    function parseDmy(value, defaultTime) {
      const m = String(value).trim().match(
        /^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2}|\d{4})(?:[ T]+(\d{1,2}):(\d{1,2}))?$/
      );
      if (!m) return null;

      const day = +m[1], month = +m[2];
      let year = +m[3];
      if (m[3].length === 2) year += 2000;

      let hh, mm;
      if (m[4] !== undefined) {
        hh = +m[4];
        mm = +m[5];
        if (hh > 23 || mm > 59) return null;
      } else {
        [hh, mm] = (defaultTime || '00:00').split(':').map(Number);
      }

      const d = new Date(year, month - 1, day, hh || 0, mm || 0);
      // Reject roll-over (e.g. 31/02 becoming 03/03).
      if (d.getDate() !== day || d.getMonth() !== month - 1 || d.getFullYear() !== year) {
        return null;
      }
      return d;
    }

    /**
     * Insert separators as the user types.
     *   date only : 3108    -> 31/08          (year may be yy or yyyy)
     *   with time : 31082623 -> 31/08/26 23
     */
    function mask(value, withTime) {
      const digits = value.replace(/\D/g, '').slice(0, withTime ? 10 : 8);
      if (digits.length <= 2) return digits;
      if (digits.length <= 4) return `${digits.slice(0, 2)}/${digits.slice(2)}`;
      if (!withTime) return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;

      let out = `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4, 6)}`;
      if (digits.length > 6) out += ` ${digits.slice(6, 8)}`;
      if (digits.length > 8) out += `:${digits.slice(8, 10)}`;
      return out;
    }

    function init(display) {
      const hidden = document.getElementById(display.dataset.target);
      if (!hidden) return;
      const withTime = display.dataset.withTime !== undefined;

      // Seed the visible field from the hidden ISO value (old() / DB value).
      const seeded = parseIso(hidden.value);
      if (seeded) {
        display.value = toDmy(seeded, withTime);
        hidden.value = toIso(seeded);
      }

      display.addEventListener('input', () => {
        const atEnd = display.selectionStart === display.value.length;
        display.value = mask(display.value, withTime);
        if (atEnd) display.setSelectionRange(display.value.length, display.value.length);
        sync();
      });
      display.addEventListener('blur', () => {
        // Normalise a valid entry; leave an invalid one for the user to fix.
        const d = parseDmy(display.value, display.dataset.defaultTime);
        if (d) display.value = toDmy(d, withTime);
      });

      function sync() {
        if (display.value.trim() === '') {
          hidden.value = '';
          display.setCustomValidity('');
          return;
        }
        const d = parseDmy(display.value, display.dataset.defaultTime);
        if (d) {
          hidden.value = toIso(d);
          display.setCustomValidity('');
        } else {
          hidden.value = '';
          // Only nag once the field looks complete, so typing isn't interrupted.
          display.setCustomValidity(
            display.value.replace(/\D/g, '').length >= 6
              ? (withTime ? 'Please use dd/mm/yy hh:ii.' : 'Please use dd/mm/yy.')
              : ''
          );
        }
      }

      display.__dmySync = sync;
    }

    /** Push a computed Date (or null to clear) into a field pair. */
    window.dmySetDate = function (targetId, date) {
      const hidden = document.getElementById(targetId);
      const display = document.querySelector(`.js-dmy[data-target="${targetId}"]`);
      if (!hidden) return;
      if (!date) {
        hidden.value = '';
        if (display) display.value = '';
        return;
      }
      hidden.value = toIso(date);
      if (display) {
        display.value = toDmy(date, display.dataset.withTime !== undefined);
        display.setCustomValidity('');
      }
    };

    document.querySelectorAll('input.js-dmy').forEach(init);
  })();
</script>
