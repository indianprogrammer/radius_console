{{--
  RADIUS Auth Logs — fetched live from the external RADIUS management server
  via RadiusClient::listAuthLogs() (SRD §5.0). Not stored locally; this is a
  read-through proxy that pulls a window of recent auth attempts and renders
  them with the same column structure as an `auth`-style channel page.

  Expects:
    $logs     LengthAwarePaginator<array{id,username,ip_address,reply,status,timestamp,...}>
    $totals   array<total, failed, today, week, last>
    $perPage  int
--}}
@extends('layout', ['title' => 'Auth Logs'])
@section('content')

  <div class="page-header">
    <h1>Auth Logs</h1>
    <p class="muted-label">
      Live authentication attempts from the RADIUS server, fetched on each request.
      Each row is an Access-Accept (success) or Access-Reject (failure) emitted by
      the NAS / RADIUS core.
    </p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Total Entries</span>
      <span class="sc-value">{{ number_format($totals['total']) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Today</span>
      <span class="sc-value">{{ number_format($totals['today']) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Last 7 Days</span>
      <span class="sc-value">{{ number_format($totals['week']) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Failures</span>
      <span class="sc-value {{ $totals['failed'] > 0 ? 'sc-bad' : 'sc-ok' }}">{{ number_format($totals['failed']) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Latest Entry</span>
      <span class="sc-value">
        {{ $totals['last'] ? \Illuminate\Support\Carbon::parse($totals['last'])->format('d/m/y H:i') : '—' }}
      </span>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>When</th>
        <th>Username</th>
        <th>Reply</th>
        <th>Source IP</th>
        <th>MAC / Calling-Station-Id</th>
        <th>NAS</th>
        <th>Raw</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($logs as $log)
        @php
          $isAccept = ($log['status'] ?? '') === 'success';
          $ts = $log['timestamp'] ?? null;
          $tsFmt = $ts ? \Illuminate\Support\Carbon::parse($ts)->format('d/m/y H:i:s') : '—';
          $tsRel = $ts ? \Illuminate\Support\Carbon::parse($ts)->diffForHumans() : null;
        @endphp
        <tr>
          <td>
            {{ $tsFmt }}
            @if ($tsRel)<div class="muted-label">{{ $tsRel }}</div>@endif
          </td>
          <td>
            <code>{{ $log['username'] ?? '—' }}</code>
          </td>
          <td>
            <span class="pill pill-{{ $isAccept ? 'success' : 'failed' }}">
              {{ $log['reply'] ?? ($isAccept ? 'Access-Accept' : 'Access-Reject') }}
            </span>
          </td>
          <td>{{ $log['ip_address'] ?: '—' }}</td>
          <td>
            @if ($log['mac_address'])
              <code>{{ $log['mac_address'] }}</code>
            @else
              <span class="muted-label">—</span>
            @endif
          </td>
          <td>
            {{ $log['nas'] ?: '—' }}
          </td>
          <td>
            <details class="log-payload">
              <summary>View</summary>
              <pre>{{ json_encode($log['raw'] ?? [], JSON_PRETTY_PRINT) }}</pre>
            </details>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7">
            @if ($totals['total'] === 0)
              No authentication attempts available from the server yet.
              This is normal for a freshly deployed RADIUS core.
            @else
              No entries on this page.
            @endif
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $logs])
  @include('partials.per-page', ['paginator' => $logs, 'action' => route('logs.radius')])
@endsection