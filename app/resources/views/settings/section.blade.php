{{--
  Settings — one section per page, with a shared tab strip.

  Company Profile posts to the `tenants` row; every other section is rendered
  from `App\Models\Setting::SCHEMA` so a new preference needs no view change.

  Expects:
    $section      string  active section key
    $sections     array   key => label for the tab strip
    $schema       ?array  the active section definition (null for `profile`)
    $values       array   effective key => value for this tenant
    $tenantModel  Tenant  Eloquent tenant row (profile form)
--}}
@extends('layout', ['title' => 'Settings — ' . ($schema['label'] ?? $sections[$section] ?? 'Settings')])
@section('content')
  @php
    // A standalone section (e.g. RADIUS API) is reached from its own menu entry,
    // so it drops the Settings tab strip and titles itself instead.
    $standalone = !empty($schema['standalone']);
  @endphp

  <div class="page-header">
    <h1>{{ $standalone ? $schema['label'] : 'Settings' }}</h1>
    <p class="muted-label">
      {{ $standalone
          ? 'Connection settings for the external RADIUS management server.'
          : 'Tenant-wide preferences. Changes apply to new records; existing ones are untouched.' }}
    </p>
  </div>

  @unless ($standalone)
    <nav class="settings-tabs" aria-label="Settings sections">
      @foreach ($sections as $key => $label)
        <a class="settings-tab {{ $key === $section ? 'active' : '' }}"
           href="{{ route('settings.section', $key) }}"
           @if ($key === $section) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    </nav>
  @endunless

  @if ($errors->any())
    <div class="alert alert-error">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.update', $section) }}">
    @csrf
    @method('PUT')

    @if ($section === 'profile')
      <div class="panel">
        <div class="panel-body">
          <h4 class="section-title">Company Profile</h4>
          <p class="hint">Shown in the sidebar, the top bar and on generated invoices.</p>
          <div class="form-grid">

            <div class="field col-6">
              <label for="name">Company Name <em>*</em></label>
              <input type="text" name="name" id="name" class="gui-input" required maxlength="150"
                     value="{{ old('name', $tenantModel->name) }}" placeholder=" ">
            </div>

            <div class="field col-3">
              <label for="theme_default">Default Theme <em>*</em></label>
              <select name="theme_default" id="theme_default" class="gui-input" required>
                @foreach (['light' => 'Light', 'dark' => 'Dark'] as $val => $label)
                  <option value="{{ $val }}" @selected(old('theme_default', $tenantModel->theme_default) === $val)>{{ $label }}</option>
                @endforeach
              </select>
              <span class="hint">Users can still override this per login.</span>
            </div>

            <div class="field col-3">
              <label for="domain_display">Domain</label>
              <input type="text" id="domain_display" class="gui-input" value="{{ $tenantModel->domain }}"
                     placeholder=" " disabled>
              <span class="hint">Read-only — the host identifies the tenant.</span>
            </div>

            <div class="field col-6">
              <label for="logo_url">Logo URL</label>
              <input type="url" name="logo_url" id="logo_url" class="gui-input" maxlength="500"
                     value="{{ old('logo_url', $tenantModel->logo_url) }}" placeholder=" ">
            </div>

            <div class="field col-3">
              <label for="slug_display">Slug</label>
              <input type="text" id="slug_display" class="gui-input" value="{{ $tenantModel->slug }}"
                     placeholder=" " disabled>
              <span class="hint">Read-only — namespaces RADIUS usernames.</span>
            </div>

          </div>
        </div>
      </div>
    @else
      <div class="panel">
        <div class="panel-body">
          <h4 class="section-title">{{ $schema['label'] }}</h4>
          @if (!empty($schema['hint']))
            <p class="hint">{{ $schema['hint'] }}</p>
          @endif
          <div class="form-grid">
            @foreach ($schema['fields'] as $key => $def)
              @php
                $type  = $def['type'] ?? 'text';
                $safe  = \App\Models\Setting::safeKey($key);
                $name  = "settings[{$safe}]";
                $id    = $safe;
                // old() reads the nested name, so the dotted path is settings.<safe>.
                $value = old("settings.{$safe}", $values[$key] ?? \App\Models\Setting::defaultFor($key));
              @endphp

              <div class="field col-{{ $def['col'] ?? 3 }}">
                @if ($type === 'toggle')
                  <label class="switch-label" for="{{ $id }}">{{ $def['label'] }}</label>
                  <label class="switch">
                    <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="1"
                           @checked(filter_var($value, FILTER_VALIDATE_BOOLEAN))>
                    <span data-on="ON" data-off="OFF"></span>
                  </label>
                @elseif ($type === 'select')
                  <label for="{{ $id }}">{{ $def['label'] }}</label>
                  <select name="{{ $name }}" id="{{ $id }}" class="gui-input">
                    @foreach ($def['options'] as $val => $label)
                      <option value="{{ $val }}" @selected((string) $value === (string) $val)>{{ $label }}</option>
                    @endforeach
                  </select>
                @elseif ($type === 'textarea')
                  <label for="{{ $id }}">{{ $def['label'] }}</label>
                  <textarea name="{{ $name }}" id="{{ $id }}" class="gui-input" rows="3"
                            maxlength="500" placeholder=" ">{{ $value }}</textarea>
                @else
                  <label for="{{ $id }}">{{ $def['label'] }}</label>
                  <input type="{{ $type === 'number' ? 'number' : 'text' }}" name="{{ $name }}" id="{{ $id }}"
                         class="gui-input" value="{{ $value }}" placeholder=" ">
                @endif

                @if (!empty($def['hint']))
                  <span class="hint">{{ $def['hint'] }}</span>
                @endif
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    <div class="form-actions">
      <a class="btn" href="{{ route('dashboard') }}">Cancel</a>
      <button class="btn" type="submit">Save Settings</button>
    </div>
  </form>
@endsection
