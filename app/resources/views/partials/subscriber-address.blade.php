{{--
  Subscriber Address section — Billing Address + Installation Address.

  Usage (both create and edit):
    @include('partials.subscriber-address', ['value' => $v])

  `value` is the view's field accessor: a callable taking (key, default) and
  returning old() ?? model value. create.blade.php passes `$v`, edit passes
  `$get`, so this partial stays agnostic about where the data came from.

  The Installation Address block carries a Leaflet map:
    - typing in the search box queries our /geocode/search proxy (Google
      Geocoding API, or Nominatim when no Google key is configured) and shows
      matching places; picking one fills the address fields AND drops the pin
    - dragging the pin (or clicking the map) reverse-geocodes through
      /geocode/reverse so the fields follow the pin
    - latitude/longitude are stored in hidden inputs, which are the values that
      actually get submitted; the visible coordinate boxes are editable and
      write back to them
  EVERY field here is editable. The geocoder seeds values, it never owns them:
  search results and pin moves overwrite, typed values are preserved, and
  editing an installation field clears the "same as billing" flag.
--}}
@php
  // $value is $v (create) or $get (edit). Both are fn($key, $default = '').
  $addr = $value;

  $hasPin = $addr('latitude') !== '' && $addr('latitude') !== null
         && $addr('longitude') !== '' && $addr('longitude') !== null;
@endphp

{{-- ========================= BILLING ADDRESS ========================= --}}
<div class="panel">
  <div class="panel-body">
    <div class="section-title-row">
      <h4 class="section-title">Billing Address</h4>
      <div class="hint">Where invoices are raised. Often the customer's home or head office.</div>
    </div>
    <div class="form-grid">

      <div class="field col-12">
        <label for="billing_address">Street Address</label>
        <textarea name="billing_address" id="billing_address" class="gui-input" rows="2"
                  placeholder=" ">{{ $addr('billing_address') }}</textarea>
      </div>

      <div class="field col-3">
        <label for="billing_city">City</label>
        <input type="text" name="billing_city" id="billing_city"
               value="{{ $addr('billing_city') }}" class="gui-input" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="billing_state">State</label>
        <input type="text" name="billing_state" id="billing_state"
               value="{{ $addr('billing_state') }}" class="gui-input" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="billing_zip">PIN / ZIP</label>
        <input type="text" name="billing_zip" id="billing_zip"
               value="{{ $addr('billing_zip') }}" class="gui-input" maxlength="12" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="billing_country">Country</label>
        <input type="text" name="billing_country" id="billing_country"
               value="{{ $addr('billing_country', 'India') }}" class="gui-input" placeholder=" ">
      </div>

    </div>
  </div>
</div>

{{-- ===================== INSTALLATION ADDRESS + MAP ===================== --}}
<div class="panel">
  <div class="panel-body">
    <div class="section-title-row">
      <h4 class="section-title">Installation Address</h4>
      <div class="hint">Where the line is physically terminated. Search for the address, drag the pin, or type any field directly.</div>
    </div>

    <div class="form-grid">

      <div class="field col-12">
        <label class="switch-label" for="installation_same_as_billing">Same as billing address</label>
        <label class="switch">
          <input type="checkbox" name="installation_same_as_billing" id="installation_same_as_billing"
                 value="1" @checked($addr('installation_same_as_billing'))>
          <span data-on="Yes" data-off="No"></span>
        </label>
        <p class="hint">Copies the billing address below. Editing any installation field unlinks it again.</p>
      </div>

      {{-- Address search. Not submitted: it only drives the picker.
           The results list sits inside .addr-search-input (not the .field) so
           it lines up with the text box rather than spanning the row that also
           holds the "Locate me" button. --}}
      <div class="field col-12" id="addr-search-field">
        <label for="addr_search">Search Address</label>
        <div class="addr-search">
          <div class="addr-search-input">
            <input type="text" id="addr_search" class="gui-input"
                   placeholder="Start typing an address, area or landmark…"
                   autocomplete="off" role="combobox" aria-expanded="false"
                   aria-controls="addr-search-results" aria-autocomplete="list">
            <ul class="addr-results" id="addr-search-results" role="listbox" hidden></ul>
          </div>
          <button type="button" class="btn" id="addr_locate" title="Use my current location">
            Locate me
          </button>
        </div>
        <p class="hint" id="addr_search_hint">Pick a result to fill the fields below and plot the pin.</p>
      </div>

      <div class="field col-12">
        <label for="installation_address">Street Address</label>
        <textarea name="installation_address" id="installation_address" class="gui-input" rows="2"
                  placeholder=" ">{{ $addr('installation_address') }}</textarea>
      </div>

      <div class="field col-6">
        <label for="installation_landmark">Landmark</label>
        <input type="text" name="installation_landmark" id="installation_landmark"
               value="{{ $addr('installation_landmark') }}" class="gui-input"
               maxlength="200" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="city">City</label>
        <input type="text" name="city" id="city"
               value="{{ $addr('city') }}" class="gui-input" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="state">State</label>
        <input type="text" name="state" id="state"
               value="{{ $addr('state') }}" class="gui-input" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="zip">PIN / ZIP</label>
        <input type="text" name="zip" id="zip"
               value="{{ $addr('zip') }}" class="gui-input" maxlength="12" placeholder=" ">
      </div>

      <div class="field col-3">
        <label for="country">Country</label>
        <input type="text" name="country" id="country"
               value="{{ $addr('country', 'India') }}" class="gui-input" placeholder=" ">
      </div>

      {{-- The pin. The coordinate boxes are editable so a surveyed lat/long can
           be typed straight in; they write back to the hidden submitted values
           and move the marker. --}}
      <div class="field col-3">
        <label for="latitude_display">Latitude</label>
        <input type="text" id="latitude_display" class="gui-input" inputmode="decimal"
               placeholder=" " value="{{ $addr('latitude') }}">
        <input type="hidden" name="latitude" id="latitude" value="{{ $addr('latitude') }}">
      </div>

      <div class="field col-3">
        <label for="longitude_display">Longitude</label>
        <input type="text" id="longitude_display" class="gui-input" inputmode="decimal"
               placeholder=" " value="{{ $addr('longitude') }}">
        <input type="hidden" name="longitude" id="longitude" value="{{ $addr('longitude') }}">
      </div>

      <div class="field col-6">
        <label for="installation_place_label">Resolved Place</label>
        <input type="text" name="installation_place_label" id="installation_place_label"
               value="{{ $addr('installation_place_label') }}" class="gui-input"
               maxlength="255" placeholder=" ">
        <p class="hint">Filled by the address search — edit freely to override it.</p>
      </div>

      <div class="field col-12">
        <div class="addr-map-toolbar">
          <span class="muted-label" id="addr_map_status">
            {{ $hasPin ? 'Pin set from the saved location.' : 'No pin yet — search an address or click the map.' }}
          </span>
          <button type="button" class="btn btn-sm" id="addr_clear_pin">Clear pin</button>
        </div>
        <div id="addr_map" class="addr-map"
             data-lat="{{ $addr('latitude') }}"
             data-lon="{{ $addr('longitude') }}"
             data-search-url="{{ route('geocode.search') }}"
             data-reverse-url="{{ route('geocode.reverse') }}"
             data-icon-base="{{ asset('vendor/leaflet/images') }}/"
             role="application"
             aria-label="Installation location map. Click or drag the marker to set the customer location."></div>
        <p class="hint">Click anywhere on the map, or drag the marker, to move the customer's location. You can also type the coordinates above.</p>
      </div>

    </div>
  </div>
</div>

@once
  @push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
  @endpush

  @push('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script src="{{ asset('js/address-map.js') }}"></script>
  @endpush
@endonce
