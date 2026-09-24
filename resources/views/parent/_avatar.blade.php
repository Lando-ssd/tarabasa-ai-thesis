{{--
  A child's avatar: the picture the child chose (a short glyph), or the first letter of their name
  when the stored value is not a short glyph. An uploaded photo sits on top and hides itself if the
  file is gone (uploads live on the server's disk, which a redeploy can wipe).
  Expects: $learner. Optional: $class (defaults to "av").
--}}
@php
    $avatarGlyph = $learner->avatar_id ?? '';
    $avatarShown = ($avatarGlyph !== '' && mb_strlen($avatarGlyph) <= 4) ? $avatarGlyph : mb_strtoupper(mb_substr($learner->first_name, 0, 1));
@endphp
<span class="{{ $class ?? 'av' }}">{{ $avatarShown }}@if ($learner->avatar_photo_path)<img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="" onerror="this.style.display='none'">@endif</span>
