@extends('layout', ['title' => 'Pipeline Board'])
@section('content')
  <div class="page-header">
    <h1>Pipeline Board</h1>
    <p class="muted-label">Every deal still in play. Drag a card to move it, or use the stage box on the card. Won and lost leads leave the board.</p>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="sc-label">Open Leads</span>
      <span class="sc-value">{{ $totals['open'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Follow-ups Due</span>
      <span class="sc-value {{ $totals['due'] > 0 ? 'sc-bad' : 'sc-ok' }}">{{ $totals['due'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Unassigned</span>
      <span class="sc-value sc-warn">{{ $totals['unassigned'] }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Open Pipeline</span>
      <span class="sc-value">{{ number_format($totals['pipeline'], 2) }}</span>
    </div>
    <div class="stat-card">
      <span class="sc-label">Win Rate</span>
      <span class="sc-value sc-ok">{{ number_format($totals['win_rate'], 1) }}%</span>
    </div>
  </div>

  <a class="btn" href="{{ route('leads.create') }}">+ New Lead</a>
  <a class="btn" href="{{ route('leads.index') }}">List View</a>
  <a class="btn" href="{{ route('leads.board', ['unassigned' => 1]) }}">Unassigned</a>

  <form class="search-form" method="get" action="{{ route('leads.board') }}">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search number, name, company or phone…">
    <select name="rating">
      <option value="">Any Rating</option>
      @foreach (\App\Models\Lead::RATINGS as $val => $label)
        <option value="{{ $val }}" @selected(($rating ?? '') === $val)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="staff_id">
      <option value="">Any Owner</option>
      @foreach ($staff as $s)
        <option value="{{ $s->id }}" @selected((int) ($staffId ?? 0) === (int) $s->id)>{{ $s->name }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn">Search</button>
    @if ($search || $rating || $staffId || $unassigned)
      <a href="{{ route('leads.board') }}" class="btn">Clear</a>
    @endif
  </form>

  <div class="board" id="lead-board" data-stage-url="{{ route('leads.stage', ['id' => '__ID__']) }}">
    @foreach ($columns as $stage => $col)
      <section class="board-col" data-stage="{{ $stage }}" aria-label="{{ $col['label'] }}">
        <header class="board-col-head">
          <span class="board-col-title">{{ $col['label'] }}</span>
          <span class="board-col-count" data-count>{{ $col['count'] }}</span>
          <span class="board-col-value">{{ number_format($col['value'], 2) }}</span>
        </header>
        <div class="board-col-body" data-drop>
          @forelse ($col['leads'] as $l)
            {{-- The GRIP is draggable, not the whole card. A card carries its own
                 stage <select>, and a card-wide drag region lets a press that
                 lands on (or sweeps over) that control fire a spurious change,
                 silently restaging a different lead. --}}
            <article class="board-card rating-{{ $l->rating }}"
                     data-lead="{{ $l->id }}" data-stage="{{ $l->status }}"
                     data-value="{{ (float) $l->estimated_value }}"
                     aria-label="{{ $l->number }} {{ $l->displayName() }}">
              <div class="board-card-top">
                <span class="board-card-num">{{ $l->number }}</span>
                <span class="board-card-grip" draggable="true" role="presentation"
                      title="Drag to another stage">⠿</span>
              </div>

              <a class="board-card-name" href="{{ route('leads.show', $l->id) }}">{{ $l->name }}</a>
              @if ($l->company)
                <span class="board-card-company">{{ $l->company }}</span>
              @endif

              <div class="board-card-meta">
                <span class="board-card-value">{{ number_format($l->estimated_value, 2) }}</span>
                <span>{{ $l->owner->name ?? 'unassigned' }}</span>
              </div>
              @if ($l->plan)
                <div class="board-card-meta"><span>{{ $l->plan->name }}</span></div>
              @endif
              @if ($l->next_follow_up_at)
                <div class="board-card-meta">
                  <span>Follow-up {{ $l->next_follow_up_at->format('d/m/y H:i') }}</span>
                </div>
              @endif

              <div class="board-card-pills">
                <span class="pill pill-{{ $l->ratingPill() }}">{{ $l->ratingLabel() }}</span>
                @if ($l->isFollowUpDue())<span class="pill pill-overdue">Due</span>@endif
                @if ($l->quote)<span class="pill pill-info">{{ $l->quote->number }}</span>@endif
              </div>

              {{-- Keyboard / no-drag path: HTML5 drag-and-drop cannot be operated
                   without a pointer, so the same move is available as a select. --}}
              <div class="board-card-foot">
                <label class="visually-hidden" for="stage-{{ $l->id }}">Stage for {{ $l->number }}</label>
                <select class="gui-input" id="stage-{{ $l->id }}" data-stage-select>
                  @foreach (\App\Models\Lead::ORDERED_STAGES as $s)
                    <option value="{{ $s }}" @selected($l->status === $s)>{{ \App\Models\Lead::STATUSES[$s] }}</option>
                  @endforeach
                </select>
                <a class="btn" href="{{ route('leads.show', $l->id) }}">Open</a>
              </div>
            </article>
          @empty
            <p class="board-col-empty">Nothing here.</p>
          @endforelse
        </div>
      </section>
    @endforeach
  </div>

  <script>
    (function () {
      const board = document.getElementById('lead-board');
      if (!board) return;

      const token = document.querySelector('meta[name="csrf-token"]').content;
      const urlTemplate = board.dataset.stageUrl;
      let dragged = null;
      // A <select> only ever commits on mouse-UP or via the keyboard. A change
      // seen while the button is still down therefore isn't a user choosing a
      // stage — it's a press-and-sweep passing over the control, which would
      // silently restage a lead nobody touched.
      let buttonDown = false;

      document.addEventListener('pointerdown', e => { if (e.button === 0) buttonDown = true; }, true);
      document.addEventListener('pointerup', () => { buttonDown = false; }, true);
      document.addEventListener('pointercancel', () => { buttonDown = false; }, true);

      const money = n => n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      /** Recount every column so the header totals match what is on screen. */
      function refreshTotals() {
        board.querySelectorAll('.board-col').forEach(col => {
          const cards = col.querySelectorAll('.board-card');
          const sum = Array.from(cards).reduce((t, c) => t + parseFloat(c.dataset.value || 0), 0);
          col.querySelector('[data-count]').textContent = cards.length;
          col.querySelector('.board-col-value').textContent = money(sum);

          // Keep the placeholder in step with the column being emptied/filled.
          const body = col.querySelector('[data-drop]');
          const empty = body.querySelector('.board-col-empty');
          if (cards.length === 0 && !empty) {
            const p = document.createElement('p');
            p.className = 'board-col-empty';
            p.textContent = 'Nothing here.';
            body.appendChild(p);
          } else if (cards.length > 0 && empty) {
            empty.remove();
          }
        });
      }

      /** Persist a move. Reverts the card on failure so the board never lies. */
      function persist(card, stage, revert) {
        fetch(urlTemplate.replace('__ID__', card.dataset.lead), {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ status: stage })
        }).then(r => r.json().then(data => ({ ok: r.ok, data })))
          .then(({ ok, data }) => {
            if (!ok) throw new Error(data.message || 'Failed to move the lead.');
            card.dataset.stage = stage;
            card.querySelector('[data-stage-select]').value = stage;
            refreshTotals();
            window.toast && window.toast(data.message, 'success');
          })
          .catch(err => {
            revert();
            card.querySelector('[data-stage-select]').value = card.dataset.stage;
            refreshTotals();
            window.toast && window.toast(err.message || 'Failed to move the lead.', 'error');
          });
      }

      function moveCard(card, stage) {
        // A drop back onto the same column is not a change.
        if (card.dataset.stage === stage) return;

        const from = card.closest('[data-drop]');
        const next = card.nextSibling;
        const target = board.querySelector(`.board-col[data-stage="${stage}"] [data-drop]`);
        if (!target) return;

        target.appendChild(card);
        refreshTotals();
        persist(card, stage, () => from.insertBefore(card, next));
      }

      board.addEventListener('dragstart', e => {
        // Only the grip starts a drag (see the card markup for why).
        if (!e.target.matches('.board-card-grip')) return;
        dragged = e.target.closest('.board-card');
        dragged.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        // Firefox ignores a drag with no payload set.
        e.dataTransfer.setData('text/plain', dragged.dataset.lead);
      });

      board.addEventListener('dragend', () => {
        if (dragged) dragged.classList.remove('is-dragging');
        board.querySelectorAll('.is-drop-target').forEach(c => c.classList.remove('is-drop-target'));
        dragged = null;
      });

      /**
       * Column under the pointer, by geometry.
       *
       * `event.target.closest('.board-col')` is not enough: the columns are laid
       * out with a gap, so a pointer in that gutter (or over the board padding)
       * targets the board itself and resolves to null — which previously meant
       * `preventDefault()` was skipped and the browser suppressed `drop`
       * entirely. Snapping to the nearest column by x makes the whole board a
       * valid drop surface.
       */
      function columnFromPoint(x) {
        const cols = Array.from(board.querySelectorAll('.board-col'));
        let nearest = null;
        let bestGap = Infinity;

        for (const col of cols) {
          const r = col.getBoundingClientRect();
          if (x >= r.left && x <= r.right) return col;

          const gap = x < r.left ? r.left - x : x - r.right;
          if (gap < bestGap) {
            bestGap = gap;
            nearest = col;
          }
        }

        return nearest;
      }

      function highlight(col) {
        board.querySelectorAll('.is-drop-target').forEach(c => {
          if (c !== col) c.classList.remove('is-drop-target');
        });
        if (col) col.classList.add('is-drop-target');
      }

      board.addEventListener('dragover', e => {
        if (!dragged) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        highlight(columnFromPoint(e.clientX));
      });

      board.addEventListener('dragleave', e => {
        // Only clear when the pointer actually leaves the board, not on the
        // constant enter/leave churn between the cards inside it.
        if (!dragged || board.contains(e.relatedTarget)) return;
        highlight(null);
      });

      board.addEventListener('drop', e => {
        if (!dragged) return;
        e.preventDefault();
        const col = columnFromPoint(e.clientX);
        highlight(null);
        if (col) moveCard(dragged, col.dataset.stage);
      });

      board.addEventListener('change', e => {
        if (!e.target.matches('[data-stage-select]')) return;
        // Ignore selection changes while a drag is in flight. If a native drag
        // fails to engage, a press-and-sweep degrades to an ordinary mouse
        // gesture, and sweeping across a <select> can silently alter it —
        // restaging a lead the user never touched.
        if (dragged) {
          e.target.value = e.target.closest('.board-card').dataset.stage;
          return;
        }
        moveCard(e.target.closest('.board-card'), e.target.value);
      });
    })();
  </script>
@endsection