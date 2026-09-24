{{--
  My Badges: every badge, earned ones lit up, locked ones with real progress
  where there is a count to show. Filters and the detail card run in the
  browser over the data below; nothing here is decorative.
--}}
@extends('layouts.learner-shell')

@section('title', 'My Badges — TaraBasa AI')
@section('page', 'badges')

@php
    $badgeRows = collect($badges)->map(function ($b) {
        return [
            'cat' => $b['category'],
            'icon' => config('badge_icons.icons.'.$b['code'], config('badge_icons.fallback')),
            'name' => $b['name'],
            'desc' => $b['description'],
            'earned' => $b['earned'] ? $b['earnedAt']->format('M j') : null,
            'earnedFull' => $b['earned'] ? $b['earnedAt']->format('M j, Y') : null,
            'soon' => (bool) $b['comingSoon'],
            'cur' => $b['current'],
            'target' => $b['target'],
        ];
    })->values();
    $categoryRows = collect($categories)->map(fn ($label, $key) => [$key, $label])->values();
@endphp

@section('content')
<div class="subpage" id="page-badges">
  <div class="bdg-head"><h1 class="bdg-title">My Badges</h1><span class="bdg-count" id="bdgCount"></span></div>

  <div class="bdg-summary">
    <div class="bdg-summary-top"><span class="bdg-summary-label">Badge collection</span><span class="bdg-summary-val" id="bdgSummaryVal"></span></div>
    <div class="bdg-meter"><div class="bdg-meter-fill" id="bdgMeterFill"></div></div>
  </div>

  <div class="bdg-filters" id="bdgStatusChips"></div>
  <div class="bdg-filters bdg-cats" id="bdgCatChips"></div>

  <div id="bdgGridHost"></div>
</div>

<div class="bdg-modal" id="bdgModal" hidden>
  <div class="bdg-modal-card" role="dialog" aria-modal="true" aria-labelledby="bdgModalName">
    <button class="bdg-modal-x" id="bdgModalX" aria-label="Close">&times;</button>
    <div class="bdg-modal-icon" id="bdgModalIcon"></div>
    <div class="bdg-modal-cat" id="bdgModalCat"></div>
    <h3 class="bdg-modal-name" id="bdgModalName"></h3>
    <p class="bdg-modal-desc" id="bdgModalDesc"></p>
    <div id="bdgModalStatus"></div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  var BDG = @json($badgeRows);
  var BDG_CATS = @json($categoryRows);
  // Each badge shows a picture of what it means (public/icons/badges.svg, Phosphor Icons), chosen in config/badge_icons.php.
  var BDG_ICONS = @json(asset('icons/badges.svg'));
  function bdgSvg(name) { return '<svg class="badge-svg" viewBox="0 0 256 256" aria-hidden="true" focusable="false"><use href="' + BDG_ICONS + '#ph-' + name + '"></use></svg>'; }

  var bdgStatus = 'all', bdgCat = 'all';
  function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
  function bdgCatName(k) { for (var i = 0; i < BDG_CATS.length; i++) if (BDG_CATS[i][0] === k) return BDG_CATS[i][1]; return k; }
  function pct(b) { return Math.min(100, Math.round(b.cur / b.target * 100)); }

  function bdgTile(i) {
    var b = BDG[i], earned = !!b.earned;
    var sub = earned ? 'Earned ' + b.earned : (b.soon ? 'Coming soon' : (b.target ? b.cur + ' of ' + b.target : 'Not yet'));
    var prog = (!earned && !b.soon && b.target) ? '<div class="bdg-prog"><i style="width:' + pct(b) + '%"></i></div>' : '';
    return '<button type="button" class="bdg-tile ' + (earned ? 'earned' : 'locked') + '" data-i="' + i + '"><div class="bdg-icon">' + bdgSvg(b.icon) + '</div><div class="bdg-name">' + esc(b.name) + '</div><div class="bdg-sub">' + sub + '</div>' + prog + '</button>';
  }

  function renderBadges() {
    var total = BDG.length, earnedN = BDG.filter(function (b) { return !!b.earned; }).length;
    document.getElementById('bdgCount').textContent = earnedN + ' of ' + total;
    document.getElementById('bdgSummaryVal').textContent = earnedN + ' / ' + total;
    document.getElementById('bdgMeterFill').style.width = Math.max(4, earnedN / total * 100) + '%';
    var st = [['all', 'All', total], ['earned', 'Earned', earnedN], ['locked', 'Locked', total - earnedN]];
    document.getElementById('bdgStatusChips').innerHTML = st.map(function (c) { return '<button type="button" class="bdg-chip' + (bdgStatus === c[0] ? ' active' : '') + '" data-status="' + c[0] + '">' + c[1] + '<span class="n">' + c[2] + '</span></button>'; }).join('');
    document.getElementById('bdgCatChips').innerHTML = [['all', 'All types']].concat(BDG_CATS).map(function (c) { return '<button type="button" class="bdg-chip' + (bdgCat === c[0] ? ' active' : '') + '" data-cat="' + c[0] + '">' + c[1] + '</button>'; }).join('');
    var html = '';
    BDG_CATS.forEach(function (c) {
      if (bdgCat !== 'all' && bdgCat !== c[0]) return;
      var idxs = [];
      BDG.forEach(function (b, i) {
        if (b.cat !== c[0]) return;
        if (bdgStatus === 'earned' && !b.earned) return;
        if (bdgStatus === 'locked' && b.earned) return;
        idxs.push(i);
      });
      if (!idxs.length) return;
      var e = idxs.filter(function (i) { return !!BDG[i].earned; }).length;
      html += '<div class="bdg-section-title">' + c[1] + '<small>' + e + ' of ' + idxs.length + '</small></div><div class="bdg-grid">' + idxs.map(bdgTile).join('') + '</div>';
    });
    document.getElementById('bdgGridHost').innerHTML = html || '<p class="pg-sub" style="margin-top:24px">No badges here yet. Keep reading!</p>';
  }

  function openBadge(i) {
    var b = BDG[i], earned = !!b.earned;
    document.querySelector('#bdgModal .bdg-modal-card').className = 'bdg-modal-card ' + (earned ? 'earned' : 'locked');
    document.getElementById('bdgModalIcon').innerHTML = bdgSvg(b.icon);
    document.getElementById('bdgModalCat').textContent = bdgCatName(b.cat);
    document.getElementById('bdgModalName').textContent = b.name;
    document.getElementById('bdgModalDesc').textContent = b.desc;
    var status;
    if (earned) status = '<span class="bdg-status earned">Earned ' + esc(b.earnedFull) + '</span>';
    else if (b.soon) status = '<span class="bdg-status locked">Coming soon</span>';
    else if (b.target) status = '<div class="bdg-modal-prog"><div class="bdg-prog"><i style="width:' + pct(b) + '%"></i></div></div><span class="bdg-status locked">' + b.cur + ' of ' + b.target + ' so far</span>';
    else status = '<span class="bdg-status locked">Not earned yet</span>';
    document.getElementById('bdgModalStatus').innerHTML = status;
    document.getElementById('bdgModal').hidden = false;
    document.getElementById('bdgModalX').focus();
  }
  function closeBadge() { document.getElementById('bdgModal').hidden = true; }

  document.getElementById('page-badges').addEventListener('click', function (e) {
    var chip = e.target.closest('.bdg-chip'), tile = e.target.closest('.bdg-tile');
    if (chip) { if (chip.dataset.status) bdgStatus = chip.dataset.status; if (chip.dataset.cat) bdgCat = chip.dataset.cat; renderBadges(); }
    else if (tile) openBadge(+tile.dataset.i);
  });
  document.getElementById('bdgModal').addEventListener('click', function (e) { if (e.target === this) closeBadge(); });
  document.getElementById('bdgModalX').addEventListener('click', closeBadge);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeBadge(); });
  renderBadges();
</script>
@endpush
