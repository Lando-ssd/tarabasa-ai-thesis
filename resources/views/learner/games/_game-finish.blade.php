{{--
  Two small helpers both Practice Games use:
    tarabasaBadgeSvg(name)             an inline badge icon (games/_game-look styles it)
    tarabasaReportGame(payload, mount) tell the server a session ended, then show any badges
                                       it earned inside the element `mount`

  The game runs entirely in the browser and stays free play; the one thing it reports is
  a game_plays row so the ten Games badges can be earned (GameController::finish). The
  report can never break a game: it is fire and forget and every failure is swallowed.

  Expects: $game ('word-builder' | 'letter-match').
--}}
<script>
  (function () {
    var ICONS = @json(asset('icons/badges.svg'));

    window.tarabasaBadgeSvg = function (name) {
      return '<svg class="badge-svg" viewBox="0 0 256 256" aria-hidden="true" focusable="false"><use href="' + ICONS + '#ph-' + name + '"></use></svg>';
    };

    window.tarabasaReportGame = function (payload, mount) {
      try {
        var tokenEl = document.querySelector('meta[name="csrf-token"]');
        fetch(@json(route('learner.games.finish', $game)), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': tokenEl ? tokenEl.content : ''
          },
          body: JSON.stringify(payload)
        })
          .then(function (response) { return response.ok ? response.json() : null; })
          .then(function (data) {
            if (!data || !data.newBadges || !data.newBadges.length || !mount) { return; }
            data.newBadges.forEach(function (badge) {
              var row = document.createElement('div');
              row.className = 'gm-newbadge';
              row.innerHTML = '<span class="gm-medal">' + window.tarabasaBadgeSvg(badge.icon) + '</span>' +
                '<div><div class="gm-newbadge-label">New badge</div><div class="gm-newbadge-name"></div></div>';
              row.querySelector('.gm-newbadge-name').textContent = badge.name;
              mount.appendChild(row);
            });
          })
          .catch(function () { /* a badge is a bonus, never allowed to break the game */ });
      } catch (e) { /* same */ }
    };
  })();
</script>
