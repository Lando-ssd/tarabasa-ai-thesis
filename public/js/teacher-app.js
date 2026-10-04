/*
 * The Teacher app's behavior. Pages are rendered by the server; this file only adds what a page
 * cannot do on its own:
 *   - windows: every action opens in a native <dialog> (data-open, data-close, data-goto)
 *   - the Activities board: filtering and paging in place, drag and drop, undo
 *   - the Generate window: a counter for each level, word ranges, what you already have
 *   - the class window: search, assigning an activity, the find box
 *   - small helpers: toast, a busy state on submit, pickers that filter a list as you type
 * Everything is driven by data-* attributes, so the Blade views stay the source of truth.
 */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (t) { return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var meta = $('meta[name="csrf-token"]');
  var csrf = meta ? meta.getAttribute('content') : '';
  var TB = window.TB = {};

  function readJson(id) {
    var el = document.getElementById(id);
    if (!el) { return null; }
    try { return JSON.parse(el.textContent); } catch (e) { return null; }
  }

  /* ---------- toast (a popover, so it shows above an open window) ---------- */
  var toastTimer, undoFn = null;
  function toast(msg, undo) {
    var t = $('#toast'); if (!t) { return; }
    t.textContent = msg; undoFn = undo || null;
    if (undo) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'toast-btn'; b.setAttribute('data-toast-undo', ''); b.textContent = undo.label || 'Undo';
      t.appendChild(b);
    }
    try { if (t.matches(':popover-open')) { t.hidePopover(); } t.showPopover(); } catch (x) { t.classList.add('on'); }
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { try { t.hidePopover(); } catch (x) { /* fine */ } t.classList.remove('on'); undoFn = null; }, undo ? 7000 : 4200);
  }
  TB.toast = toast;

  function post(url, body) {
    return fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body || {})
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, json: j }; });
    });
  }

  /* ---------- windows ---------- */
  function openDlg(d) {
    if (!d) { return; }
    if (!d.open) { d.showModal(); }
    var af = $('[data-af]:not([hidden])', d); if (af && !af.closest('[hidden]')) { af.focus(); }
  }
  function closeDlg(d) { if (d && d.open) { d.close(); } }
  TB.open = function (id) { openDlg(document.getElementById(id)); };

  // Show one named view of a window (a window can hold several: a class has Learners, Activities,
  // Add learner, Edit and one per learner).
  function goto(scope, name) {
    $$(':scope > .win-view', scope).forEach(function (v) { v.hidden = v.getAttribute('data-view') !== name; });
    var shown = $(':scope > .win-view[data-view="' + name + '"]', scope);
    if (shown) {
      var body = $('.win-body', shown); if (body) { body.scrollTop = 0; }
      var af = $('[data-af]', shown); if (af) { af.focus(); }
    }
  }
  TB.goto = goto;

  document.addEventListener('click', function (e) {
    var t = e.target;
    if (t.tagName === 'DIALOG' && t.classList.contains('win')) { closeDlg(t); return; }

    var opener = t.closest('[data-open]');
    if (opener) {
      e.preventDefault();
      var d = document.getElementById(opener.getAttribute('data-open'));
      var fill = opener.getAttribute('data-fill');
      if (fill && TB.fill[fill]) { TB.fill[fill](d, opener); }
      // A class window always opens on its learners, not on whichever view it was left on.
      if (d && d.id.indexOf('class-') === 0) { goto($('.win-in', d), 'learners'); }
      warm(d);
      openDlg(d);
      return;
    }

    var closer = t.closest('[data-close]');
    if (closer) { closeDlg(closer.closest('dialog')); return; }

    var go = t.closest('[data-goto]');
    if (go) { e.preventDefault(); goto(go.closest('.win-in'), go.getAttribute('data-goto')); return; }

    if (t.closest('[data-toast-undo]')) {
      var fn = undoFn; undoFn = null;
      try { $('#toast').hidePopover(); } catch (x) { $('#toast').classList.remove('on'); }
      if (fn && fn.run) { fn.run(); }
    }
  });
  TB.fill = {};

  // A busy state on a submit, so a second tap cannot send it twice (only after the browser has
  // accepted the form, so a missing required field is still reported normally).
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var label = f.getAttribute('data-busy');
    if (!label || !f.checkValidity()) { return; }
    var b = e.submitter || $('[type="submit"]', f);
    if (b) { setTimeout(function () { b.disabled = true; b.setAttribute('data-was', b.textContent); b.textContent = label; }, 0); }
  });

  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.hasAttribute && t.hasAttribute('data-autosubmit') && t.form) { t.form.submit(); }
  });

  // Filter a list as you type: <input data-filter="#listId">, items carry data-search text.
  document.addEventListener('input', function (e) {
    var t = e.target;
    var sel = t.getAttribute && t.getAttribute('data-filter');
    if (!sel) { return; }
    var box = $(sel); if (!box) { return; }
    var q = t.value.trim().toLowerCase(), any = false, limit = parseInt(t.getAttribute('data-limit') || '0', 10), shown = 0;
    $$('[data-search]', box).forEach(function (it) {
      var ok = !q || it.getAttribute('data-search').indexOf(q) > -1;
      if (limit && !q && shown >= limit) { ok = false; }
      it.hidden = !ok; if (ok) { any = true; shown++; }
    });
    var empty = $('[data-empty]', box.parentNode); if (empty) { empty.hidden = any; }
  });

  // The Generate window's AI service sleeps when idle; ask it to wake as the window opens.
  var warmed = false;
  function warm(d) {
    var url = d && d.getAttribute('data-warm-url');
    if (!url || warmed) { return; }
    warmed = true;
    try { fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }); } catch (x) { /* fine */ }
  }

  window.addEventListener('DOMContentLoaded', function () {
    var t = $('#toast');
    if (t && t.getAttribute('data-flash')) { toast(t.getAttribute('data-flash')); }
    $$('dialog[data-autoopen]').forEach(function (d) {
      var view = d.getAttribute('data-autoview');
      if (view) { goto($('.win-in', d), view); }
      if (view === 'assign' && TB.buildAssign) { TB.buildAssign($('.win-view[data-view="assign"]', d)); }
      warm(d);
      openDlg(d);
    });
  });

  /* =====================================================
     Activities: the board and the list
     ===================================================== */
  var main = $('#actMain');
  if (main) {
    var indexUrl = main.getAttribute('data-url');
    var state = readJson('actState') || {};

    var syncChips = function () {
      $$('[data-load][aria-pressed]').forEach(function (c) {
        var p; try { p = JSON.parse(c.getAttribute('data-load')); } catch (x) { return; }
        var on = Object.keys(p).every(function (k) { return String(state[k] == null ? '' : state[k]) === String(p[k]); });
        c.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    };

    var load = function (patch) {
      Object.keys(patch || {}).forEach(function (k) { state[k] = patch[k]; });
      var q = new URLSearchParams();
      Object.keys(state).forEach(function (k) { if (state[k] !== '' && state[k] !== null && state[k] !== undefined && state[k] !== 'All') { q.set(k, state[k]); } });
      var plain = indexUrl + (q.toString() ? '?' + q.toString() : '');
      q.set('fragment', '1');
      return fetch(indexUrl + '?' + q.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { if (!r.ok) { throw new Error('bad'); } return r.text(); })
        .then(function (html) { main.innerHTML = html; try { history.replaceState(null, '', plain); } catch (x) { /* fine */ } syncChips(); })
        .catch(function () { toast('Could not refresh the list. Reload the page and try again.'); });
    };
    TB.loadActivities = load;

    document.addEventListener('click', function (e) {
      var c = e.target.closest('[data-load]');
      if (!c || c.disabled) { return; }
      e.preventDefault();
      try { load(JSON.parse(c.getAttribute('data-load'))); } catch (x) { /* fine */ }
    });

    var qBox = $('#actQ'), qTimer;
    if (qBox) { qBox.addEventListener('input', function () { clearTimeout(qTimer); qTimer = setTimeout(function () { load({ q: qBox.value.trim(), page: 0, tray: 0 }); }, 220); }); }
    var gBox = $('#actGrade');
    if (gBox) { gBox.addEventListener('change', function () { load({ grade: gBox.value, page: 0, tray: 0 }); }); }

    // A phone gets the list: dragging a card does not work well on touch, and every card has a
    // "Move to" menu anyway.
    if (window.matchMedia('(max-width:900px)').matches && state.view === 'board') { load({ view: 'list', page: 0 }); }

    // Put an activity in a level (or reject it): drag and drop, the "Move to" menu, or Approve.
    var place = function (url, body) {
      return post(url, body).then(function (r) {
        if (!r.ok) { toast((r.json && r.json.message) || 'That could not be done.'); return load({}); }
        var undo = r.json.undo ? { label: r.json.undo.label, run: function () { post(r.json.undo.url, {}).then(function (u) { toast((u.json && u.json.message) || 'Restored.'); load({}); }); } } : null;
        toast(r.json.message || 'Done.', undo);
        return load({});
      });
    };

    var dragged = null;
    document.addEventListener('dragstart', function (e) {
      var c = e.target.closest ? e.target.closest('.bcard[draggable="true"]') : null;
      if (!c) { return; }
      dragged = { url: c.getAttribute('data-place-url'), status: c.getAttribute('data-status') };
      try { e.dataTransfer.setData('text/plain', dragged.url); e.dataTransfer.effectAllowed = 'move'; } catch (x) { /* fine */ }
      c.classList.add('drag');
    });
    document.addEventListener('dragend', function () { dragged = null; $$('.drag, .over').forEach(function (n) { n.classList.remove('drag', 'over'); }); });
    document.addEventListener('dragover', function (e) {
      var z = e.target.closest ? e.target.closest('[data-drop]') : null;
      if (!z || !dragged) { return; }
      var to = z.getAttribute('data-drop');
      if (to === 'tray' || (to === 'reject' && dragged.status !== 'Draft')) { return; }
      e.preventDefault();
      $$('.over').forEach(function (n) { if (n !== z) { n.classList.remove('over'); } });
      z.classList.add('over');
    });
    document.addEventListener('drop', function (e) {
      var z = e.target.closest ? e.target.closest('[data-drop]') : null;
      if (!z || !dragged) { return; }
      e.preventDefault();
      var d = dragged; dragged = null; $$('.over').forEach(function (n) { n.classList.remove('over'); });
      var to = z.getAttribute('data-drop');
      if (to !== 'tray') { place(d.url, { level: to }); }
    });
    document.addEventListener('change', function (e) {
      var s = e.target;
      if (s.tagName === 'SELECT' && s.getAttribute('data-place-url') && s.value) { var v = s.value; s.value = ''; place(s.getAttribute('data-place-url'), { level: v }); }
    });
    // A plain button that posts in the background (Approve on a row, Restore).
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-post]');
      if (!b || b.disabled) { return; }
      e.preventDefault();
      var body = {}; try { body = JSON.parse(b.getAttribute('data-body') || '{}'); } catch (x) { /* fine */ }
      place(b.getAttribute('data-post'), body);
    });
  }

  // An activity's window is fetched when a card opens (so the page stays small).
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-window-url]');
    if (!c) { return; }
    var inner = e.target.closest('button, a, select, input, textarea, form');
    if (inner && inner !== c) { return; }
    e.preventDefault();
    fetch(c.getAttribute('data-window-url'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { if (!r.ok) { throw new Error('bad'); } return r.text(); })
      .then(function (html) { var d = $('#dlg'); d.innerHTML = html; openDlg(d); })
      .catch(function () { toast('Could not open that. Please try again.'); });
  });

  // Assign (inside an activity's window): choose a class, a group or one learner.
  function syncAssign(form) {
    var active = $('[data-assign-type][aria-pressed="true"]', form).getAttribute('data-assign-type');
    $$('[data-panel]', form).forEach(function (p) {
      var on = p.getAttribute('data-panel') === active;
      p.hidden = !on;
      $$('input', p).forEach(function (i) { if (i.type === 'radio') { i.disabled = !on; if (!on) { i.checked = false; } } });
    });
    var go = $('[data-assign-go]', form.closest('.win-view') || document);
    if (go) { go.disabled = !$('input[type="radio"]:checked:not(:disabled)', form); }
  }
  document.addEventListener('click', function (e) {
    var chip = e.target.closest('[data-assign-type]');
    if (!chip) { return; }
    var form = chip.closest('form'); if (!form) { return; }
    $$('[data-assign-type]', form).forEach(function (c) { c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'); });
    syncAssign(form);
  });
  document.addEventListener('change', function (e) {
    var r = e.target;
    if (r.type === 'radio' && r.closest('[data-panel]')) { syncAssign(r.closest('form')); }
    if (r.name === 'price_type' && r.form) {
      var f = $('[data-price-field]', r.form); var inp = f && $('input', f);
      if (f) { f.hidden = r.value !== 'Paid'; if (inp) { inp.disabled = r.value !== 'Paid'; if (r.value === 'Paid') { inp.focus(); } } }
    }
  });

  /* =====================================================
     The "Generate activities" notice. A request is written in the background (it takes a minute
     to several minutes), so this follows it: it asks how far it has got every few seconds, shows
     the time so far, and when it ends says how, and refreshes the board.
     ===================================================== */
  var gs = $('#genStatus');
  if (gs) {
    var gsStarted = parseInt(gs.getAttribute('data-started') || '0', 10);
    var gsSprite = gs.getAttribute('data-sprite') || '';
    var gsTimer = null, gsClock = null;
    var gsFmt = function (sec) { sec = Math.max(0, sec); var m = Math.floor(sec / 60), s = sec % 60; return m + ':' + (s < 10 ? '0' : '') + s; };
    var gsTick = function () { var el = $('[data-gs-elapsed]', gs); if (el) { el.textContent = gsFmt(Math.floor(Date.now() / 1000) - gsStarted); } };
    var gsIcon = function (name) {
      $('[data-gs-ico]', gs).innerHTML = name === 'spin'
        ? '<span class="spin light"></span>'
        : '<svg class="ico" viewBox="0 0 256 256" aria-hidden="true" focusable="false"><use href="' + gsSprite + '#ph-' + name + '"></use></svg>';
    };
    var gsShow = function (kind, title, text) {
      gs.classList.toggle('blue', kind === 'work'); gs.classList.toggle('green', kind === 'done');
      $('[data-gs-title]', gs).textContent = title;
      $('[data-gs-text]', gs).textContent = text;
      $('[data-gs-elapsed]', gs).hidden = kind !== 'work';
      $('[data-gs-retry]', gs).hidden = kind !== 'fail';
      var d = $('[data-gs-dismiss]', gs); d.hidden = kind === 'work'; d.textContent = kind === 'done' ? 'Got it' : 'Dismiss';
      gsIcon(kind === 'work' ? 'spin' : (kind === 'done' ? 'check' : 'x'));
    };
    var gsStop = function () { clearTimeout(gsTimer); clearInterval(gsClock); };

    var gsApply = function (s) {
      if (s.startedAt) { gsStarted = s.startedAt; }
      gs.setAttribute('data-state', s.status);
      if (s.active) { return true; }
      gsStop();
      if (s.status === 'Done') {
        gsShow('done', 'Your activities are ready', s.message || 'Your activities were added to To review.');
        toast(s.message || 'Your activities are ready.');
        if (TB.loadActivities) { TB.loadActivities({}); }
      } else {
        gsShow('fail', 'The AI could not finish', s.message || 'Nothing was charged. Please try again.');
      }
      return false;
    };

    var gsPoll = function () {
      fetch(gs.getAttribute('data-url'), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { if (!r.ok) { throw new Error('bad'); } return r.json(); })
        .then(function (s) { if (gsApply(s)) { gsTimer = setTimeout(gsPoll, 4000); } })
        .catch(function () { gsTimer = setTimeout(gsPoll, 8000); });
    };

    document.addEventListener('click', function (e) {
      if (!e.target.closest('[data-gs-dismiss]') || !gs.contains(e.target)) { return; }
      post(gs.getAttribute('data-dismiss-url'), {});
      gs.hidden = true;
    });

    if (gs.getAttribute('data-state') === 'Queued' || gs.getAttribute('data-state') === 'Running') {
      gsTick(); gsClock = setInterval(gsTick, 1000); gsPoll();
    }
  }

  /* =====================================================
     Generate window: how many of each level
     ===================================================== */
  var gen = $('#genDlg');
  if (gen) {
    var GD = readJson('genData') || {};
    var TIERS = ['Easy', 'Medium', 'Hard'];
    var n = {};
    TIERS.forEach(function (t) { var i = $('input[name="levels[' + t + ']"]', gen); n[t] = i ? parseInt(i.value || '0', 10) : 0; });

    var gradeNum = function () { return parseInt($('#gGrade').value.replace('Grade ', ''), 10); };

    var fillTypes = function () {
      var comp = GD.competencies[$('#genComp').value], type = $('#genType'), keep = type.getAttribute('data-keep') || type.value;
      type.innerHTML = comp.activity_types.map(function (k) { return '<option value="' + k + '"' + (k === keep ? ' selected' : '') + '>' + esc(GD.typeLabels[k] || k) + '</option>'; }).join('');
      type.removeAttribute('data-keep');
      $('#genHint').textContent = comp.description;
      fillLevels();
    };

    var fillLevels = function () {
      var g = gradeNum(), type = $('#genType').value, total = 0;
      TIERS.forEach(function (t) {
        var row = $('[data-level="' + t + '"]', gen), band = GD.bands[type] && GD.bands[type][g] && GD.bands[type][g][t.toLowerCase()];
        var have = (GD.have[g] && GD.have[g][type] && GD.have[g][type][t]) || [0, 0];
        $('[data-range]', row).textContent = band ? band[0] + ' to ' + band[1] + ' words' : '';
        var h = $('[data-have]', row);
        h.textContent = (have[0] || have[1]) ? 'You have ' + have[0] + ' approved' + (have[1] ? ' and ' + have[1] + ' waiting' : '') : 'None yet for this skill';
        h.classList.toggle('none', have[0] === 0);
        $('output', row).textContent = n[t];
        $('input[type="hidden"]', row).value = n[t];
        $('[data-step="' + t + ':-1"]', row).disabled = n[t] <= 0;
        $('[data-step="' + t + ':1"]', row).disabled = n[t] >= GD.max;
        row.classList.toggle('on', n[t] > 0);
        total += n[t];
      });
      $('[data-gen-total]', gen).textContent = total;
      $('[data-gen-total-word]', gen).textContent = total === 1 ? 'activity' : 'activities';
      // The AI writes every level one after another and most of the wait is a fixed cost per
      // request, so it follows the largest number asked for in any one level, not the total.
      var most = Math.max(n.Easy, n.Medium, n.Hard), est = $('[data-gen-time]', gen);
      if (est) {
        var secs = (GD.baseSeconds || 90) + (GD.extraSeconds || 20) * Math.max(0, most - 1), mins = Math.max(1, Math.ceil(secs / 60));
        est.textContent = total ? 'About ' + mins + (mins === 1 ? ' minute' : ' minutes') : '';
      }
      var go = $('#genGo'); if (go && !go.hasAttribute('data-locked')) { go.disabled = total === 0; $('#genGoText').textContent = total ? 'Generate ' + total : 'Choose at least one'; }
    };

    gen.addEventListener('click', function (e) {
      var s = e.target.closest('[data-step]');
      if (s) { var p = s.getAttribute('data-step').split(':'); n[p[0]] = Math.max(0, Math.min(GD.max, n[p[0]] + parseInt(p[1], 10))); fillLevels(); return; }
      var pr = e.target.closest('[data-preset]');
      if (pr) { var v = pr.getAttribute('data-preset'); TIERS.forEach(function (t) { n[t] = (v === 'all' || v === t) ? 3 : 0; }); fillLevels(); }
    });
    gen.addEventListener('change', function (e) {
      if (e.target.id === 'genComp') { $('#genType').removeAttribute('data-keep'); fillTypes(); }
      if (e.target.id === 'gGrade' || e.target.id === 'genType') { fillLevels(); }
    });
    var form = $('#genForm');
    form.addEventListener('submit', function () {
      $('#genBusy').hidden = false; $('#genGo').disabled = true; $('#genGoText').textContent = 'Sending';
      $$('[data-gen-cancel]', gen).forEach(function (b) { b.disabled = true; });
    });
    fillTypes();
  }

  /* =====================================================
     Classes: the class windows, the find box, assigning an activity
     ===================================================== */
  var CD = readJson('classData');
  if (CD) {
    TB.openClass = function (id, view) {
      var d = document.getElementById('class-' + id); if (!d) { return; }
      goto($('.win-in', d), view || 'learners'); openDlg(d);
    };

    // Grade chips filter the class cards.
    document.addEventListener('click', function (e) {
      var c = e.target.closest('[data-grade-filter]'); if (!c) { return; }
      var g = c.getAttribute('data-grade-filter');
      $$('[data-grade-filter]').forEach(function (x) { x.setAttribute('aria-pressed', x === c ? 'true' : 'false'); });
      $$('.ctile').forEach(function (t) { t.hidden = !(g === 'All' || t.getAttribute('data-grade') === g); });
    });

    // The find box: any class or learner, by typing.
    var fb = $('#findBox'), fr = $('#findRes');
    if (fb && fr) {
      var render = function () {
        var q = fb.value.trim().toLowerCase();
        if (!q) { fr.hidden = true; return; }
        var cs = CD.classes.filter(function (c) { return (c.name + ' ' + c.section + ' ' + c.tag + ' ' + c.grade).toLowerCase().indexOf(q) > -1; }).slice(0, 4);
        var ls = CD.learners.filter(function (l) { return (l.name + ' ' + l.code).toLowerCase().indexOf(q) > -1; }).slice(0, 6);
        var h = '';
        if (cs.length) { h += '<div class="grp eyebrow">Classes</div>' + cs.map(function (c) { return '<button type="button" class="rowbtn" data-find-class="' + c.id + '"><span><b>' + esc(c.name) + '</b><small>' + esc(c.grade + ' · ' + c.section + ' · SY ' + c.sy) + '</small></span></button>'; }).join(''); }
        if (ls.length) { h += '<div class="grp eyebrow">Learners</div>' + ls.map(function (l) { return '<button type="button" class="rowbtn" data-find-class="' + l.classId + '" data-find-learner="' + l.id + '"><span><b>' + esc(l.name) + '</b><small>' + esc(l.code + ' · in ' + l.className + ' · SY ' + l.sy) + '</small></span></button>'; }).join(''); }
        fr.innerHTML = h || '<p class="note" style="margin:10px">Nothing matches "' + esc(fb.value) + '".</p>';
        fr.hidden = false;
      };
      fb.addEventListener('input', render);
      fb.addEventListener('focus', render);
      document.addEventListener('click', function (e) {
        var r = e.target.closest('[data-find-class]');
        if (r) { fr.hidden = true; TB.openClass(r.getAttribute('data-find-class'), r.getAttribute('data-find-learner') ? 'learner-' + r.getAttribute('data-find-learner') : 'learners'); return; }
        if (!e.target.closest('.find')) { fr.hidden = true; }
      });
    }

    // Assign an activity to a class: choose a level, then pick from a dropdown, then see it.
    var buildAssign = function (view) {
      var dlg = view.closest('dialog'), cid = dlg.getAttribute('data-class'), grade = dlg.getAttribute('data-grade');
      var level = view.getAttribute('data-level') || 'all', taken = (CD.taken[cid] || []).map(String);
      $$('[data-level-chip]', view).forEach(function (c) { c.setAttribute('aria-pressed', c.getAttribute('data-level-chip') === level ? 'true' : 'false'); });
      var pool = CD.approved.filter(function (a) { return taken.indexOf(String(a.id)) < 0 && (level === 'all' || a.tier === level); });
      var same = pool.filter(function (a) { return a.grade === grade; }), other = pool.filter(function (a) { return a.grade !== grade; });
      var sel = $('select[name="activity_id"]', view), keep = sel.value;
      var grp = function (label, list) { return list.length ? '<optgroup label="' + esc(label) + '">' + list.map(function (a) { return '<option value="' + a.id + '"' + (String(a.id) === keep ? ' selected' : '') + '>' + esc(a.title + ' (' + a.tier + ', ' + a.words + ' words)') + '</option>'; }).join('') + '</optgroup>' : ''; };
      sel.innerHTML = '<option value="">Choose an activity</option>' + grp(grade + ' activities', same) + grp('Other grades', other);
      $('[data-assign-hint]', view).textContent = pool.length ? pool.length + ' approved ' + (pool.length === 1 ? 'activity' : 'activities') + ' to choose from.' : 'Nothing left to assign at this level. Approve more drafts on the Activities screen.';
      showPreview(view);
    };
    TB.buildAssign = buildAssign;
    var showPreview = function (view) {
      var sel = $('select[name="activity_id"]', view), box = $('[data-preview]', view), go = $('[data-assign-go]', view);
      var a = CD.approved.filter(function (x) { return String(x.id) === sel.value; })[0];
      go.disabled = !a;
      box.innerHTML = a ? '<div class="card" style="box-shadow:none;padding:16px"><div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px"><span class="pill t-' + a.tier.toLowerCase() + '">' + esc(a.tier) + '</span><span class="pill">' + esc(a.type) + '</span><span class="pill">' + a.words + ' words</span></div><div class="block" style="margin-bottom:8px"><span class="eyebrow">Reading text</span><div class="passage" style="margin-top:6px;font-size:16px">' + esc(a.passage) + '</div></div><p class="note" style="margin:0"><b>' + esc(a.tier) + '</b>: ' + esc(a.long) + '</p></div>' : '';
    };
    document.addEventListener('click', function (e) {
      var opener = e.target.closest('[data-assign-open]');
      if (opener) { var view = opener.closest('.win-in').querySelector(':scope > .win-view[data-view="assign"]'); view.setAttribute('data-level', 'all'); buildAssign(view); }
      var chip = e.target.closest('[data-level-chip]');
      if (chip) { var v = chip.closest('.win-view'); v.setAttribute('data-level', chip.getAttribute('data-level-chip')); $('select[name="activity_id"]', v).value = ''; buildAssign(v); }
    });
    document.addEventListener('change', function (e) {
      var s = e.target;
      if (s.tagName === 'SELECT' && s.name === 'activity_id' && s.closest('.win-view[data-view="assign"]')) { showPreview(s.closest('.win-view')); }
    });
  }

  /* =====================================================
     Analytics, Promotions: pickers that filter as you go
     ===================================================== */
  // A class chip picks which rows show (and a typed search looks across every class).
  $$('[data-class-picker]').forEach(function (box) {
    var list = $(box.getAttribute('data-class-picker'));
    var input = box.querySelector('input[type="search"]');
    var chips = $$('[data-class-chip]', box);
    var cls = box.getAttribute('data-default') || '';
    var apply = function () {
      var q = input ? input.value.trim().toLowerCase() : '', any = false;
      chips.forEach(function (c) { c.setAttribute('aria-pressed', (!q && c.getAttribute('data-class-chip') === String(cls)) ? 'true' : 'false'); });
      $$('[data-search]', list).forEach(function (r) {
        var ok = q ? r.getAttribute('data-search').indexOf(q) > -1 : r.getAttribute('data-class') === String(cls);
        r.hidden = !ok; if (ok) { any = true; }
      });
      var empty = $('[data-empty]', list.parentNode); if (empty) { empty.hidden = any; }
      var note = $('[data-searching]', box); if (note) { note.hidden = !q; }
    };
    box.addEventListener('click', function (e) { var c = e.target.closest('[data-class-chip]'); if (c) { cls = c.getAttribute('data-class-chip'); if (input) { input.value = ''; } apply(); } });
    if (input) { input.addEventListener('input', apply); }
    apply();
  });

  // Release: fill one confirmation window from the learner's row.
  TB.fill.release = function (dlg, btn) {
    $('[data-f-name]', dlg).textContent = btn.getAttribute('data-name');
    $('[data-f-from]', dlg).textContent = btn.getAttribute('data-class');
    $('[data-f-next]', dlg).textContent = btn.getAttribute('data-next');
    $('[data-f-go]', dlg).textContent = 'Release to ' + btn.getAttribute('data-next');
    $('form', dlg).setAttribute('action', btn.getAttribute('data-action'));
  };
  // Claim: fill the claim window with this learner and the classes that fit.
  TB.fill.claim = function (dlg, btn) {
    $('[data-f-name]', dlg).textContent = btn.getAttribute('data-name');
    $('[data-f-meta]', dlg).textContent = btn.getAttribute('data-meta');
    $('form', dlg).setAttribute('action', btn.getAttribute('data-action'));
    var classes = []; try { classes = JSON.parse(btn.getAttribute('data-classes') || '[]'); } catch (x) { /* fine */ }
    $('select', dlg).innerHTML = classes.map(function (c) { return '<option value="' + c.id + '">' + esc(c.label) + '</option>'; }).join('');
  };
})();
