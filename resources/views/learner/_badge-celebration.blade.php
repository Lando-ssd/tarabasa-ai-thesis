{{--
  Shared real-badge celebration — shown only when $newBadges (an array of
  ['code','name','description','emoji'], from BadgeService) is non-empty.
  Each badge shows its own icon (see _badge-icon and config/badge_icons.php),
  never the emoji. Handles more than one badge earned in the same moment (a
  real possibility on reading-results.blade.php, e.g. a 10th reading landing on
  the same session as a level-up).

  Markup only — each including page defines .badge-celebration/.badge-pop
  styles in its own <style> block, consistent with this app's
  standalone-per-view CSS convention.
--}}
@if (! empty($newBadges))
  <div class="badge-celebration">
    @foreach ($newBadges as $badge)
      <div class="badge-pop">
        <span class="badge-pop-medal">@include('learner._badge-icon', ['code' => $badge['code'] ?? null])</span>
        <div class="badge-pop-text">
          <div class="badge-pop-label">New Badge!</div>
          <div class="badge-pop-name">{{ $badge['name'] }}</div>
        </div>
      </div>
    @endforeach
  </div>
@endif
