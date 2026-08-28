  <nav class="pager" aria-label="Pagination">
    <div class="pager-meta">
      @if ($paginator->total() > 0)
        Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
      @else
        No results
      @endif
    </div>
    <ul class="pagination">
      {{-- Previous --}}
      @if ($paginator->onFirstPage())
        <li class="disabled" aria-disabled="true"><span aria-hidden="true">‹ Prev</span></li>
      @else
        <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Prev</a></li>
      @endif

      {{-- Numbered links --}}
      @php
        $cur = $paginator->currentPage();
        $last = $paginator->lastPage();
        $window = 2;
        $pages = [];
        for ($p = 1; $p <= $last; $p++) {
          if ($p == 1 || $p == $last || ($p >= $cur - $window && $p <= $cur + $window)) {
            $pages[] = $p;
          } elseif (end($pages) !== '...') {
            $pages[] = '...';
          }
        }
      @endphp
      @foreach ($pages as $p)
        @if ($p === '...')
          <li class="disabled"><span>…</span></li>
        @elseif ($p == $cur)
          <li class="active" aria-current="page"><span>{{ $p }}</span></li>
        @else
          <li><a href="{{ $paginator->url($p) }}">{{ $p }}</a></li>
        @endif
      @endforeach

      {{-- Next --}}
      @if ($paginator->hasMorePages())
        <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a></li>
      @else
        <li class="disabled" aria-disabled="true"><span aria-hidden="true">Next ›</span></li>
      @endif
    </ul>
  </nav>
