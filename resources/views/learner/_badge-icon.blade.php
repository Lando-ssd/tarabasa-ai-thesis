{{--
  A badge's icon: an inline SVG that points at one symbol in public/icons/badges.svg
  (Phosphor Icons, MIT, see public/icons/LICENSE-phosphor.txt), so the badge shows a
  picture of what it means (a flame for a streak, a rocket for speed) instead of an
  emoji. The icon is chosen per badge in config/badge_icons.php.

  Expects: $code (the badge's code) or $icon (a symbol name to use directly).
  Optional: $class (defaults to "badge-svg"). The colour comes from the parent's
  CSS `color`, since the symbols fill with currentColor.
--}}
@php
    $badgeIconName = $icon ?? config('badge_icons.icons.'.($code ?? ''), config('badge_icons.fallback'));
@endphp
<svg class="{{ $class ?? 'badge-svg' }}" viewBox="0 0 256 256" aria-hidden="true" focusable="false"><use href="{{ asset('icons/badges.svg') }}#ph-{{ $badgeIconName }}"></use></svg>
