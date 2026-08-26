@php
  // §5.0 — 17 functional menu items. Items not yet implemented show "Coming Soon".
  // Groupable items (e.g. RADIUS Control) are nested under a named group.
  $groups = [
    '' => [ // ungrouped / top-level
      ['label' => 'Dashboard', 'route' => 'dashboard', 'ready' => true],
      ['label' => 'Subscribers', 'route' => 'subscribers.index', 'ready' => true],
      ['label' => 'Billing & Invoices', 'route' => '#', 'ready' => false],
      ['label' => 'Wallets / Credit', 'route' => '#', 'ready' => false],
      ['label' => 'Live Sessions', 'route' => '#', 'ready' => false],
      ['label' => 'Disconnect / Bandwidth', 'route' => '#', 'ready' => false],
      ['label' => 'Auth Logs', 'route' => '#', 'ready' => false],
      ['label' => 'KYC / Verification', 'route' => '#', 'ready' => false],
      ['label' => 'Notifications', 'route' => '#', 'ready' => false],
      ['label' => 'Reports', 'route' => '#', 'ready' => false],
      ['label' => 'IPDR / Compliance', 'route' => '#', 'ready' => false, 'note' => 'Pending IPDR Server (#7) — IpdrClient adapter'],
      ['label' => 'Cron / Automation', 'route' => '#', 'ready' => false],
      ['label' => 'Tenant Settings', 'route' => '#', 'ready' => false],
      ['label' => 'RBAC / Staff', 'route' => '#', 'ready' => false],
      ['label' => 'Audit Log', 'route' => '#', 'ready' => false],
    ],
    'Radius Control' => [
      ['label' => 'Plans', 'route' => 'plans.index', 'ready' => true],
      ['label' => 'NAS / Devices', 'route' => 'nas.index', 'ready' => true],
    ],
  ];
  $active = request()->route()?->getName();

  // Render a single menu row.
  $renderItem = function (array $it) use ($active) {
    $isActive = ($it['ready'] && $active && str_starts_with($active, $it['route']));
    $liClass = trim(($it['ready'] ? '' : 'muted') . ' ' . ($isActive ? 'active' : ''));
    if ($it['ready']) {
      $href = route($it['route']);
      $inner = '<span class="dot"></span><span class="label">' . e($it['label']) . '</span>';
      return '<li class="' . $liClass . '"><a href="' . $href . '">' . $inner . '</a></li>';
    }
    $title = $it['note'] ?? 'Coming soon';
    return '<li class="' . $liClass . '"><span class="disabled" title="' . e($title) . '">'
      . '<span class="dot"></span><span class="label">' . e($it['label']) . '</span>'
      . '<em class="soon">(soon)</em></span></li>';
  };
@endphp

<ul class="menu-list">
  @foreach ($groups as $groupName => $items)
    @if ($groupName === '')
      @foreach ($items as $it)
        {!! $renderItem($it) !!}
      @endforeach
    @else
      @php
        // A group is "open" if any child route is the current active route.
        $groupOpen = collect($items)->contains(fn($it) => $it['ready'] && $active && str_starts_with($active, $it['route']));
      @endphp
      <li class="menu-group">
        <button type="button" class="group-toggle {{ $groupOpen ? 'open' : '' }}" data-group-toggle>
          <span class="group-caret">▸</span>
          <span class="group-title">{{ $groupName }}</span>
        </button>
        <ul class="menu-sublist {{ $groupOpen ? 'open' : '' }}">
          @foreach ($items as $it)
            {!! $renderItem($it) !!}
          @endforeach
        </ul>
      </li>
    @endif
  @endforeach
</ul>
