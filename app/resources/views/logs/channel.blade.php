{{--
  Logs — one page per channel (Audit, Login History, SMS, …).

  Every log page is this single view: the rows are the same shape and differ
  only in emphasis, so the column set is switched on the channel rather than
  duplicated into nine near-identical files.

  Expects:
    $channel     string  active channel key (ActivityLog::CHANNELS)
    $label       string  page title
    $logs        paginator of ActivityLog
    $actions     array   action => label, only those present in this channel
    $totals      array   header card counts
    $search, $action, $status, $from, $to, $failedOnly  active filters
--}}
@extends('layout', ['title' => $label])
@section('content')
  @php
    // A message channel reads as "who was it sent to, did it arrive"; an auth
    // channel as "who tried, from where". Everything else is a change trail.
    $isMessage = in_array($channel, \App\Models\ActivityLog::MESSAGE_CHANNELS, true);
    $isAuth    = in_array($channel, \App\Models\ActivityLog::AUTH_CHANNELS, true);
    $subjectHeading = $isMessage ? 'Recipient' : ($isAuth ? 'Source IP' : 'Object');
    $columnCount = 7;
  @endphp

  <div class="page-header">
    <h1>{{ $label }}</h1>
    <p class="muted-label">
      @if ($isMessage)
        Delivery record for every {{ strtolower($label) }} sent from this console.
      @elseif ($channel === 'login_fail')
        Rejected sign-in attempts. Repeated failures from one address are worth investigating.
      @elseif ($channel === 'login')
        Successful sign-ins, with the address and client they came from.
      @elseif ($channel === 'syslog')
        Subscriber-facing system events (provisioning, suspension, disconnects).
      @else
        Immutable record of every action taken in this console — actor, object and timestamp.
      @endif
      Entries are append-only and cannot be edited or deleted.
    </p>
  </div>

  {{-- Channel switcher. Doubles as the menu group's own navigation, so a log
       page is reachable from any other without going back to the sidebar. --}}
  <nav class="settings-tabs" aria-label="Log channels">
    @foreach (\App\Models\ActivityLog::CHANNELS as $key => $name)
      <a class="settings-tab {{ $key === $channel ? 'active' : '' }}"
         href="{{ route('logs.channel', $key) }}"
         @if ($key === $channel) aria-current="page" @endif>{{ $name }}</a>
    @endforeach
  </nav>

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

  @if ($totals['failed'] > 0 && !$failedOnly)
    <a class="btn" href="{{ route('logs.channel', [$channel, 'failed' => 1]) }}">Show Failures Only</a>
  @endif

  <form class="search-form" method="get" action="{{ route('logs.channel', $channel) }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search actor, action, object, message or IP…">
    @if (!empty($actions))
      <select name="action">
        <option value="">All Actions</option>
        @foreach ($actions as $val => $name)
          <option value="{{ $val }}" @selected(($action ?? '') === $val)>{{ $name }}</option>
        @endforeach
      </select>
    @endif
    <select name="status">
      <option value="">Any Outcome</option>
      @foreach (\App\Models\ActivityLog::STATUSES as $val => $name)
        <option value="{{ $val }}" @selected(($status ?? '') === $val)>{{ $name }}</option>
      @endforeach
    </select>
    <input type="date" name="from" value="{{ $from ?? '' }}" title="From date">
    <input type="date" name="to" value="{{ $to ?? '' }}" title="To date">
    <button type="submit" class="btn">Search</button>
    @if ($search || $action || $status || $from || $to || $failedOnly)
      <a href="{{ route('logs.channel', $channel) }}" class="btn">Clear</a>
    @endif
  </form>

  <table>
    <thead>
      <tr>
        <th>When</th>
        <th>Actor</th>
        <th>Action</th>
        <th>{{ $subjectHeading }}</th>
        <th>Details</th>
        <th>Outcome</th>
        <th>Context</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($logs as $log)
        <tr>
          <td>
            {{ $log->created_at?->format('d/m/y H:i:s') ?? '—' }}
            <div class="muted-label">{{ $log->created_at?->diffForHumans() }}</div>
          </td>
          <td>
            {{ $log->actor ?: 'system' }}
            @if ($log->user_id)<div class="muted-label">user #{{ $log->user_id }}</div>@endif
          </td>
          <td>{{ $log->actionLabel() }}</td>
          <td>
            @if ($isAuth)
              {{ $log->ip_address ?: '—' }}
            @else
              {{ $log->objectSummary() }}
            @endif
          </td>
          <td>
            {{ $log->message ?: '—' }}
            {{-- The IP is the subject column for auth logs, so only repeat it
                 here for the channels that do not already show it. --}}
            @if (!$isAuth && $log->ip_address)
              <div class="muted-label">from {{ $log->ip_address }}</div>
            @endif
          </td>
          <td><span class="pill pill-{{ $log->statusPill() }}">{{ $log->statusLabel() }}</span></td>
          <td>
            @php $payload = $log->payloadJson(); @endphp
            @if ($payload)
              {{-- <details> rather than a modal: the payload is occasional
                   reference material, and this needs no JavaScript. --}}
              <details class="log-payload">
                <summary>View</summary>
                <pre>{{ $payload }}</pre>
              </details>
            @else
              <span class="muted-label">—</span>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="{{ $columnCount }}">
            @if ($search || $action || $status || $from || $to || $failedOnly)
              No entries match these filters.
            @else
              Nothing logged in {{ $label }} yet. Entries appear here as the console is used.
            @endif
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  @include('partials.pager', ['paginator' => $logs])
  @include('partials.per-page', ['paginator' => $logs, 'action' => route('logs.channel', $channel)])
@endsection
