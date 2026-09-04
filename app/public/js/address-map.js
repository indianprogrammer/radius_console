/**
 * Installation Address map picker.
 *
 * Progressive enhancement for `partials/subscriber-address.blade.php`:
 *   - address search box  -> GET /geocode/search  (fills fields + moves pin)
 *   - click / drag on map -> GET /geocode/reverse (fields follow the pin)
 *   - "Locate me"         -> browser geolocation, then reverse geocode
 *   - "Same as billing"   -> mirrors the billing fields into installation
 *
 * The form works without any of this: every field is still typeable and the
 * hidden latitude/longitude inputs are what actually get submitted.
 */
(function () {
  'use strict';

  var mapEl = document.getElementById('addr_map');
  if (!mapEl || typeof L === 'undefined') {
    return; // Section not on this page, or Leaflet failed to load.
  }

  var SEARCH_URL = mapEl.dataset.searchUrl;
  var REVERSE_URL = mapEl.dataset.reverseUrl;

  // Fallback view: centre of India at country zoom, used until a pin exists.
  var DEFAULT_CENTER = [20.5937, 78.9629];
  var DEFAULT_ZOOM = 4;
  var PIN_ZOOM = 17;

  var el = {
    search: document.getElementById('addr_search'),
    results: document.getElementById('addr-search-results'),
    searchHint: document.getElementById('addr_search_hint'),
    locate: document.getElementById('addr_locate'),
    clearPin: document.getElementById('addr_clear_pin'),
    status: document.getElementById('addr_map_status'),

    street: document.getElementById('installation_address'),
    landmark: document.getElementById('installation_landmark'),
    city: document.getElementById('city'),
    state: document.getElementById('state'),
    zip: document.getElementById('zip'),
    country: document.getElementById('country'),
    placeLabel: document.getElementById('installation_place_label'),

    lat: document.getElementById('latitude'),
    lon: document.getElementById('longitude'),
    latDisplay: document.getElementById('latitude_display'),
    lonDisplay: document.getElementById('longitude_display'),

    sameAsBilling: document.getElementById('installation_same_as_billing'),
    searchField: document.getElementById('addr-search-field'),
  };

  // Leaflet resolves its marker icons relative to its own CSS by default, which
  // breaks under our asset layout — point it at the vendored copies explicitly.
  // The base comes from the view via asset() so a sub-directory install works.
  var iconBase = mapEl.dataset.iconBase || '/vendor/leaflet/images/';
  var markerIcon = L.icon({
    iconUrl: iconBase + 'marker-icon.png',
    iconRetinaUrl: iconBase + 'marker-icon-2x.png',
    shadowUrl: iconBase + 'marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
  });

  var startLat = parseFloat(mapEl.dataset.lat);
  var startLon = parseFloat(mapEl.dataset.lon);
  var hasStartPin = !isNaN(startLat) && !isNaN(startLon);

  var map = L.map(mapEl, { scrollWheelZoom: true }).setView(
    hasStartPin ? [startLat, startLon] : DEFAULT_CENTER,
    hasStartPin ? PIN_ZOOM : DEFAULT_ZOOM
  );

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map);

  var marker = null;

  function setStatus(message) {
    if (el.status) {
      el.status.textContent = message;
    }
  }

  /** Write the pin coordinates into the hidden (submitted) + display inputs. */
  function writeCoords(lat, lon) {
    var latStr = lat === null ? '' : Number(lat).toFixed(7);
    var lonStr = lon === null ? '' : Number(lon).toFixed(7);

    el.lat.value = latStr;
    el.lon.value = lonStr;
    if (el.latDisplay) el.latDisplay.value = latStr;
    if (el.lonDisplay) el.lonDisplay.value = lonStr;
  }

  /** Place (or move) the draggable marker and keep the hidden inputs in sync. */
  function placePin(lat, lon, options) {
    var opts = options || {};

    if (marker) {
      marker.setLatLng([lat, lon]);
    } else {
      marker = L.marker([lat, lon], { draggable: true, icon: markerIcon }).addTo(map);
      marker.on('dragend', function () {
        var pos = marker.getLatLng();
        writeCoords(pos.lat, pos.lng);
        reverseGeocode(pos.lat, pos.lng);
      });
    }

    writeCoords(lat, lon);

    if (opts.recenter !== false) {
      map.setView([lat, lon], Math.max(map.getZoom(), PIN_ZOOM));
    }
  }

  function clearPin() {
    if (marker) {
      map.removeLayer(marker);
      marker = null;
    }
    writeCoords(null, null);
    if (el.placeLabel) el.placeLabel.value = '';
    setStatus('Pin cleared. The subscriber has no plotted location.');
  }

  /**
   * Fill the address fields from a geocoder result.
   *
   * `force` decides what happens to fields that already hold text:
   *  - true  (an explicit pick / pin drag): overwrite, the operator asked for it
   *  - false (a passive fill): only populate blanks, so typed values survive
   *
   * Either way the field stays editable — the geocoder seeds it, it does not
   * own it.
   */
  function applyPlace(place, force) {
    var assign = function (input, value) {
      if (!input || !value) return;
      if (force || !input.value.trim()) {
        input.value = value;
      }
    };

    assign(el.street, place.street);
    assign(el.city, place.city);
    assign(el.state, place.state);
    assign(el.zip, place.zip);
    assign(el.country, place.country);

    if (el.placeLabel && place.label && (force || !el.placeLabel.value.trim())) {
      el.placeLabel.value = place.label;
    }
  }

  function hideResults() {
    if (!el.results) return;
    el.results.hidden = true;
    el.results.innerHTML = '';
    if (el.search) el.search.setAttribute('aria-expanded', 'false');
  }

  function renderResults(places) {
    if (!el.results) return;

    el.results.innerHTML = '';

    if (!places.length) {
      var empty = document.createElement('li');
      empty.className = 'addr-result-empty';
      empty.textContent = 'No matching address found.';
      el.results.appendChild(empty);
      el.results.hidden = false;
      return;
    }

    places.forEach(function (place) {
      var li = document.createElement('li');
      li.className = 'addr-result';
      li.setAttribute('role', 'option');
      li.tabIndex = 0;
      li.textContent = place.label;

      var choose = function () {
        applyPlace(place, true);
        if (place.lat !== null && place.lon !== null) {
          placePin(place.lat, place.lon);
          setStatus('Pin plotted from the selected address.');
        }
        if (el.search) el.search.value = place.label;
        hideResults();
      };

      li.addEventListener('click', choose);
      li.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          choose();
        }
      });

      el.results.appendChild(li);
    });

    el.results.hidden = false;
    if (el.search) el.search.setAttribute('aria-expanded', 'true');
  }

  function requestJson(url) {
    return fetch(url, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (response) {
      return response.json().then(function (body) {
        if (!response.ok) {
          throw new Error(body && body.error ? body.error : 'Request failed.');
        }
        return body;
      });
    });
  }

  function searchAddress(term) {
    var url = new URL(SEARCH_URL, window.location.origin);
    url.searchParams.set('q', term);

    requestJson(url.toString())
      .then(renderResults)
      .catch(function (error) {
        hideResults();
        if (el.searchHint) {
          el.searchHint.textContent = error.message;
        }
      });
  }

  /**
   * Resolve a pin to an address. `force` is passed through to applyPlace():
   * a pin drag or map click overwrites the fields, whereas a coordinate typed
   * by hand only fills the blanks.
   */
  function reverseGeocode(lat, lon, force) {
    var overwrite = force !== false;
    var url = new URL(REVERSE_URL, window.location.origin);
    url.searchParams.set('lat', lat);
    url.searchParams.set('lon', lon);

    setStatus('Looking up the address for the pin…');

    requestJson(url.toString())
      .then(function (place) {
        applyPlace(place, overwrite);
        setStatus('Pin set — address resolved from the map.');
      })
      .catch(function (error) {
        // Coordinates are already stored; only the text lookup failed.
        setStatus(error.message);
      });
  }

  // ── Search box ────────────────────────────────────────────────────────
  if (el.search) {
    var debounce;

    el.search.addEventListener('input', function () {
      clearTimeout(debounce);
      var term = el.search.value.trim();

      if (term.length < 3) {
        hideResults();
        return;
      }

      debounce = setTimeout(function () {
        searchAddress(term);
      }, 350);
    });

    // Enter must not submit the whole subscriber form from the search box.
    el.search.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        clearTimeout(debounce);
        var term = el.search.value.trim();
        if (term.length >= 3) searchAddress(term);
      } else if (event.key === 'Escape') {
        hideResults();
      }
    });

    document.addEventListener('click', function (event) {
      if (el.searchField && !el.searchField.contains(event.target)) {
        hideResults();
      }
    });
  }

  // ── Map interactions ──────────────────────────────────────────────────
  map.on('click', function (event) {
    placePin(event.latlng.lat, event.latlng.lng, { recenter: false });
    reverseGeocode(event.latlng.lat, event.latlng.lng);
  });

  if (hasStartPin) {
    placePin(startLat, startLon, { recenter: false });
  }

  if (el.clearPin) {
    el.clearPin.addEventListener('click', clearPin);
  }

  // ── Typed coordinates ─────────────────────────────────────────────────
  // The lat/long boxes are editable so a surveyed coordinate can be pasted in.
  // Committing one (blur or Enter) moves the pin instead of the other way round.
  (function wireCoordEntry() {
    if (!el.latDisplay || !el.lonDisplay) return;

    var commit = function () {
      var lat = parseFloat(el.latDisplay.value.trim());
      var lon = parseFloat(el.lonDisplay.value.trim());

      // Both blank = the operator is clearing the pin.
      if (el.latDisplay.value.trim() === '' && el.lonDisplay.value.trim() === '') {
        clearPin();
        return;
      }

      if (isNaN(lat) || isNaN(lon)) {
        setStatus('Latitude and longitude must both be numbers.');
        return;
      }

      if (lat < -90 || lat > 90 || lon < -180 || lon > 180) {
        setStatus('Coordinates out of range (latitude ±90, longitude ±180).');
        return;
      }

      placePin(lat, lon);
      setStatus('Pin moved to the coordinates you entered.');
      // Fill the address from the new pin, but do not clobber anything the
      // operator has already typed.
      reverseGeocode(lat, lon, false);
    };

    [el.latDisplay, el.lonDisplay].forEach(function (input) {
      input.addEventListener('change', commit);
      input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault(); // Must not submit the subscriber form.
          commit();
        }
      });
    });
  })();

  // ── Locate me ─────────────────────────────────────────────────────────
  if (el.locate) {
    el.locate.addEventListener('click', function () {
      if (!navigator.geolocation) {
        setStatus('This browser does not support location lookup.');
        return;
      }

      setStatus('Requesting your current location…');

      navigator.geolocation.getCurrentPosition(
        function (position) {
          var lat = position.coords.latitude;
          var lon = position.coords.longitude;
          placePin(lat, lon);
          reverseGeocode(lat, lon);
        },
        function () {
          setStatus('Location permission denied. Search the address or click the map instead.');
        },
        { enableHighAccuracy: true, timeout: 10000 }
      );
    });
  }

  // ── Same as billing ───────────────────────────────────────────────────
  // The switch is a live mirror, NOT a lock: the installation fields stay
  // editable throughout. Typing into one means the two addresses are no longer
  // the same, so the switch turns itself off and the typed value is kept. This
  // also keeps the flag honest for the server-side mirror in
  // SubscriberController::normaliseAddress().
  if (el.sameAsBilling) {
    var billing = {
      street: document.getElementById('billing_address'),
      city: document.getElementById('billing_city'),
      state: document.getElementById('billing_state'),
      zip: document.getElementById('billing_zip'),
      country: document.getElementById('billing_country'),
    };

    var copyPairs = [
      [billing.street, el.street],
      [billing.city, el.city],
      [billing.state, el.state],
      [billing.zip, el.zip],
      [billing.country, el.country],
    ];

    // Set while the script itself is writing, so the resulting `input` events
    // are not mistaken for the operator typing.
    var mirroring = false;

    var mirrorBilling = function () {
      mirroring = true;
      copyPairs.forEach(function (pair) {
        var from = pair[0];
        var to = pair[1];
        if (from && to) to.value = from.value;
      });
      mirroring = false;
    };

    var applySameAsBilling = function () {
      var on = el.sameAsBilling.checked;

      copyPairs.forEach(function (pair) {
        var to = pair[1];
        if (to) to.classList.toggle('is-mirrored', on);
      });

      if (on) {
        mirrorBilling();
        setStatus('Installation address mirrors the billing address. Edit any field to unlink it. The map pin is set separately.');
      }
    };

    el.sameAsBilling.addEventListener('change', applySameAsBilling);

    // Keep the mirror live while the flag is on.
    Object.keys(billing).forEach(function (key) {
      var input = billing[key];
      if (!input) return;
      input.addEventListener('input', function () {
        if (el.sameAsBilling.checked) mirrorBilling();
      });
    });

    // Editing an installation field breaks the mirror.
    copyPairs.forEach(function (pair) {
      var to = pair[1];
      if (!to) return;
      to.addEventListener('input', function () {
        if (mirroring || !el.sameAsBilling.checked) return;

        el.sameAsBilling.checked = false;
        copyPairs.forEach(function (p) {
          if (p[1]) p[1].classList.remove('is-mirrored');
        });
        setStatus('Installation address unlinked from billing — it is now edited independently.');
      });
    });

    applySameAsBilling();
  }

  // The map is created while the panel may still be laid out; recalculating
  // once on load avoids the grey-tile / half-rendered tile bug.
  window.addEventListener('load', function () {
    map.invalidateSize();
  });
})();
