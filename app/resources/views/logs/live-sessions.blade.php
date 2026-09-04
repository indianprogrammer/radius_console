{{--
  Live Sessions — who is online right now, collected from the external RADIUS
  server on every request (SRD §5.3). Nothing on this page is stored locally:
  the RADIUS server is the network truth for session state, so a cached copy
  could only ever be a stale second opinion.

  Sits in the Logs menu group next to Auth Logs, because both answer "what is
  happening on the network" rather than "what did someone change in here".

  Expects:
    $sessions    LengthAwarePaginator<array>  collected + filtered sessions
    $totals      array                        tenant-wide summary (not the page)
    $matched     int                          rows after filtering
    $foreign     int                          rows dropped as another tenant's
    $error       string|null                  RADIUS fetch failure, if any
    $nasOptions  array<ip, label>             NAS filter options
    $search, $nasIp, $health, $sort, $perPage active controls
--}}
@extends('layout', ['title' => 'Live Sessions'])
@section('content')

  <div class="page-header">
    <h1>Live Sessions</h1>
    <p class="muted-label">
      Sessions currently open on the RADIUS server — subscriber, NAS, assigned IP,
      uptime and traffic. Collected live from the session API on each load and not
      stored locally, so this is the network's own view rather than a cached one.
    </p>
  </div>

  @if ($error)
    <div class="alert alert-error">
      Could not read sessions from the RADIUS server: {{ $error }}
      <div class="muted-label">
        The list below may be incomplete. Check that the server is reachable at
        <a href="{{ route('settings.section', 'radius') }}">the configured RADIUS API address</a>.
      </div>
    </div>
  @endif

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Active Sessions</span>
      <span class="sc-value">{{ number_format($totals['total']) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Online</span>
      <span class="sc-value sc-ok">{{ number_format($totals['online']) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Idle / Stale</span>
      <span class="sc-value {{ $totals['stale'] > 0 ? 'sc-warn' : '' }}">{{ number_format($totals['stale']) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Subscribers</span>
      <span class="sc-value">{{ number_format($totals['subscribers']) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">NAS Devices</span>
      <span class="sc-value">{{ number_format($totals['nas']) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Session Volume</span>
      <span class="sc-value">{{ \App\Services\LiveSessionCollector::bytesLabel($totals['volume']) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Longest Uptime</span>
      <span class="sc-value">{{ $totals['longest'] }}</span>
    </div>
  </div>

  {{-- Channel switcher, same strip as the stored log pages: Live Sessions and
       Auth Logs both live in this menu group, so navigating between them should
       not require the sidebar. --}}
  <nav class="settings-tabs" aria-label="Log channels">
    <a class="settings-tab" href="{{ route('logs.radius') }}">Auth Logs</a>
    <a class="settings-tab active" href="{{ route('logs.live-sessions') }}" aria-current="page">Live Sessions</a>
    @foreach (\App\Models\ActivityLog::CHANNELS as $key => $name)
      <a class="settings-tab" href="{{ route('logs.channel', $key) }}">{{ $name }}</a>
    @endforeach
  </nav>

  <form class="search-form" method="get" action="{{ route('logs.live-sessions') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}"
           placeholder="Search subscriber, IP, MAC, NAS or session id…">
    @if (!empty($nasOptions))
      <select name="nas" aria-label="NAS device">
        <option value="">All NAS Devices</option>
        @foreach ($nasOptions as $ip => $label)
          <option value="{{ $ip }}" @selected(($nasIp ?? '') === (string) $ip)>{{ $label }}</option>
        @endforeach
      </select>
    @endif
    <select name="health" aria-label="Session health">
      <option value="">Any State</option>
      <option value="online" @selected(($health ?? '') === 'online')>Online</option>
      <option value="stale" @selected(($health ?? '') === 'stale')>Idle / Stale</option>
      <option value="unknown" @selected(($health ?? '') === 'unknown')>No Accounting</option>
    </select>
    <select name="sort" aria-label="Sort order">
      <option value="recent" @selected($sort === 'recent')>Newest First</option>
      <option value="longest" @selected($sort === 'longest')>Longest Uptime</option>
      <option value="volume" @selected($sort === 'volume')>Most Traffic</option>
      <option value="username" @selected($sort === 'username')>Subscriber A–Z</option>
    </select>
    <button type="submit" class="btn">Search</button>
    @if ($search || $nasIp || $health || $sort !== 'recent')
      <a href="{{ route('logs.live-sessions') }}" class="btn">Clear</a>
    @endif
    <a href="{{ request()->fullUrl() }}" class="btn">Refresh</a>
  </form>

  <table>
    <thead>
      <tr>
        <th>Subscriber</th>
        <th>State</th>
        <th>NAS</th>
        <th>IP Address</th>
        <th>MAC</th>
        <th>Started</th>
        <th>Uptime</th>
        <th class="num">Upload</th>
        <th class="num">Download</th>
        <th>Session</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($sessions as $s)
        <tr>
          <td>
            @if ($s['subscriber_id'])
              <a href="{{ route('subscribers.edit', $s['subscriber_id']) }}">{{ $s['local_username'] }}</a>
            @else
              <code>{{ $s['local_username'] }}</code>
            @endif
            @if ($s['subscriber_name'])
              <div class="muted-label">{{ $s['subscriber_name'] }}</div>
            @endif
            @if ($s['plan_name'])
              <div class="muted-label">{{ $s['plan_name'] }}</div>
            @elseif (!$s['subscriber_id'])
              {{-- Present on the RADIUS server under our namespace but with no
                   local row: provisioned outside the console, or the local
                   record was deleted while the session stayed up. --}}
              <div class="muted-label">Not in subscriber list</div>
            @endif
          </td>
          <td>
            @php
              $pill = ['online' => 'completed', 'idle' => 'pending', 'stale' => 'failed'][$s['health']] ?? 'draft';
              $stateLabel = ['online' => 'Online', 'idle' => 'Idle', 'stale' => 'Stale'][$s['health']] ?? 'No Data';
            @endphp
            <span class="pill pill-{{ $pill }}">{{ $stateLabel }}</span>
            @if ($s['source'] === 'accounting')
              {{-- Not in the server's active list; reconstructed from an
                   accounting record with no Acct-Stop. --}}
              <div class="muted-label" title="Recovered from an accounting record with no stop time">unconfirmed</div>
            @endif
            @if ($s['last_seen'])
              <div class="muted-label">seen {{ $s['last_seen']->diffForHumans() }}</div>
            @endif
          </td>
          <td>
            {{ $s['nas_name'] ?: ($s['nas_identifier'] ?: '—') }}
            @if ($s['nas_ip'])
              <div class="muted-label"><code>{{ $s['nas_ip'] }}</code></div>
            @endif
          </td>
          <td>
            @if ($s['framed_ip'])<code>{{ $s['framed_ip'] }}</code>@else<span class="muted-label">—</span>@endif
            @if ($s['framed_ipv6'])
              <div class="muted-label"><code>{{ $s['framed_ipv6'] }}</code></div>
            @endif
          </td>
          <td>
            @if ($s['mac_address'])<code>{{ $s['mac_address'] }}</code>@else<span class="muted-label">—</span>@endif
          </td>
          <td>
            {{ $s['start_time']?->format('d/m/y H:i:s') ?? '—' }}
            @if ($s['start_time'])
              <div class="muted-label">{{ $s['start_time']->diffForHumans() }}</div>
            @endif
          </td>
          <td>{{ $s['duration_label'] }}</td>
          <td class="num">{{ \App\Services\LiveSessionCollector::bytesLabel($s['input_octets']) }}</td>
          <td class="num">{{ \App\Services\LiveSessionCollector::bytesLabel($s['output_octets']) }}</td>
          <td>
            <details class="log-payload">
              <summary>{{ \Illuminate\Support\Str::limit($s['session_id'], 12) ?: 'View' }}</summary>
              <pre>{{ json_encode($s['raw'], JSON_PRETTY_PRINT) }}</pre>
            </details>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="10">
            @if ($error)
              No sessions could be read from the RADIUS server.
            @elseif ($totals['total'] === 0 && $foreign > 0)
              {{-- Sessions exist upstream, they just are not this company's. Saying
                   "nobody is online" here would look like a broken feed. --}}
              The RADIUS server has {{ number_format($foreign) }} open session(s), but none
              belong to this company. Sessions appear here once a subscriber provisioned
              from this console connects.
            @elseif ($totals['total'] === 0)
              Nobody is online at the moment. Sessions appear here as soon as the
              NAS sends an accounting start for one of this company's subscribers.
            @else
              No sessions match the current filters.
            @endif
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  @if ($totals['total'] > 0 && $matched !== $totals['total'])
    <p class="muted-label">Showing {{ number_format($matched) }} of {{ number_format($totals['total']) }} sessions.</p>
  @endif

  @if ($foreign > 0)
    {{-- The RADIUS core is single-tenant and returns every company's sessions;
         SRD §4.1.1 makes filtering them out this platform's job. Saying so
         explains why a count here can be lower than the server's own. --}}
    <p class="muted-label">
      {{ number_format($foreign) }} session(s) belonging to other companies on this
      RADIUS server were excluded.
    </p>
  @endif

  @include('partials.pager', ['paginator' => $sessions])
  @include('partials.per-page', ['paginator' => $sessions, 'action' => route('logs.live-sessions')])
@endsection
