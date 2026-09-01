@php
  // §5.0 — 17 functional menu items. Items not yet implemented show "Coming Soon".
  // Groupable items (e.g. RADIUS Control) are nested under a named group.
  $groups = [
    '' => [ // ungrouped / top-level
      ['label' => 'Dashboard', 'route' => 'dashboard', 'ready' => true],
      ['label' => 'Subscribers', 'route' => 'subscribers.index', 'ready' => true],
      ['label' => 'Wallets / Credit', 'route' => '#', 'ready' => false],
      ['label' => 'Live Sessions', 'route' => '#', 'ready' => false],
      ['label' => 'Disconnect / Bandwidth', 'route' => '#', 'ready' => false],
      ['label' => 'Auth Logs', 'route' => '#', 'ready' => false],
      ['label' => 'KYC / Verification', 'route' => '#', 'ready' => false],
      ['label' => 'Notifications', 'route' => '#', 'ready' => false],
      ['label' => 'Reports', 'route' => '#', 'ready' => false],
      ['label' => 'IPDR / Compliance', 'route' => '#', 'ready' => false, 'note' => 'Pending IPDR Server (#7) — IpdrClient adapter'],
      ['label' => 'Cron / Automation', 'route' => '#', 'ready' => false],
      ['label' => 'Audit Log', 'route' => '#', 'ready' => false],
    ],
    'Billing & Invoices' => [
      ['label' => 'Quotations', 'route' => 'quotes.index', 'params' => 'quotation', 'ready' => true],
      ['label' => 'Proforma Invoices', 'route' => 'quotes.index', 'params' => 'proforma', 'ready' => true],
      ['label' => 'Invoices', 'route' => 'invoices.index', 'ready' => true],
      ['label' => 'Payments', 'route' => 'payments.index', 'ready' => true],
      ['label' => 'Ledger', 'route' => 'ledger.index', 'ready' => true],
      ['label' => 'Tax Rates', 'route' => 'tax-rates.index', 'ready' => true],
      ['label' => 'Plans', 'route' => 'plans.index', 'ready' => true],
      ['label' => 'Products & Services', 'route' => 'products.index', 'ready' => true],
    ],
    'Franchise Management' => [
      ['label' => 'Franchise', 'route' => 'franchises.index', 'ready' => true],
      ['label' => 'Branch', 'route' => 'branch.index', 'ready' => false],
    ],
    'Staff & HR' => [
      ['label' => 'Staff', 'route' => 'staff.index', 'ready' => true],
      ['label' => 'Teams / Groups', 'route' => 'staff-groups.index', 'ready' => true],
      ['label' => 'Attendance', 'route' => 'attendance.index', 'ready' => true],
      ['label' => 'Payroll', 'route' => 'payroll.index', 'ready' => true],
    ],
    'Support Tickets' => [
      ['label' => 'Tickets', 'route' => 'tickets.index', 'ready' => true],
    ],
    'Radius Control' => [
      ['label' => 'Bandwidth Control', 'route' => 'bandwidth-profiles.index', 'ready' => true],
      ['label' => 'NAS', 'route' => 'nas.index', 'ready' => true],
      // Standalone Settings section — see Setting::SCHEMA['radius'].
      ['label' => 'RADIUS API', 'route' => 'settings.section', 'params' => 'radius', 'ready' => true],
    ],
    'Settings' => [
      // Sits last, directly below Radius Control. Each item deep-links to a
      // Settings section; they all share the "settings" route prefix, so the
      // `params` key is what distinguishes the active one (see $renderItem).
      // Order mirrors the tab strip on the Settings page.
      ['label' => 'Company Profile', 'route' => 'settings.section', 'params' => 'profile', 'ready' => true],
      ['label' => 'General', 'route' => 'settings.section', 'params' => 'general', 'ready' => true],
      ['label' => 'Localization', 'route' => 'settings.section', 'params' => 'localization', 'ready' => true],
      ['label' => 'Billing Settings', 'route' => 'settings.section', 'params' => 'billing', 'ready' => true],
      ['label' => 'Tickets & SLA', 'route' => 'settings.section', 'params' => 'tickets', 'ready' => true],
      ['label' => 'Notifications', 'route' => 'settings.section', 'params' => 'notifications', 'ready' => true],
      ['label' => 'Subscriber Defaults', 'route' => 'settings.section', 'params' => 'subscribers', 'ready' => true],
    ],
  ];
  $active = request()->route()?->getName();

  // A resource's all actions (index/create/edit/...) share the same first two
  // route-name segments (e.g. "bandwidth-profiles"). Match on that prefix so
  // create/edit pages still highlight their parent menu item. Defined as a
  // real function so it is visible inside every @php block below.
  if (!function_exists('menu_section_prefix')) {
      function menu_section_prefix(?string $name): string {
          // Route names are "resource.action" (e.g. bandwidth-profiles.create);
          // take only the resource segment so every action highlights the same
          // menu item. Non-resource names (e.g. "dashboard") pass through.
          return $name ? explode('.', $name)[0] : '';
      }
  }
  $activePrefix = menu_section_prefix($active);

  // Several items can share one route name and differ only by a leading route
  // parameter: every Settings section is `settings.section` and both quotation
  // and proforma are `quotes.index`. When `params` is set, the item is active
  // only if the request carries the same discriminator — otherwise all of them
  // light up at once and their group reports itself open on unrelated pages
  // (RADIUS API lives under Radius Control, not Settings).
  //
  // The discriminator is always the FIRST route parameter (`{section}`, `{type}`);
  // matching on that rather than on any parameter avoids colliding with `{id}`.
  $routeParams = request()->route()?->parameters() ?? [];
  $activeParam = $routeParams ? (string) reset($routeParams) : null;

  $isItemActive = function (array $it) use ($activePrefix, $activeParam): bool {
    if (!$it['ready'] || !$activePrefix || $activePrefix !== menu_section_prefix($it['route'])) {
      return false;
    }
    return !isset($it['params']) || $activeParam === $it['params'];
  };

  // Render a single menu row.
  $renderItem = function (array $it) use ($isItemActive) {
    $isActive = $isItemActive($it);
    $liClass = trim(($it['ready'] ? '' : 'muted') . ' ' . ($isActive ? 'active' : ''));
    if ($it['ready']) {
      $href = route($it['route'], $it['params'] ?? []);
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
        // A group is "open" if one of its own items is the active one.
        $groupOpen = collect($items)->contains($isItemActive);
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
