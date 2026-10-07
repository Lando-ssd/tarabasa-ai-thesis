{{--
  A learner on a Teacher screen, as their profile says: the photo the parent uploaded, else the picture the child
  chose, else their initials on a soft colour. A photo sits on top and hides itself if the file is gone (uploads live on
  the server's disk, which a redeploy can wipe), so the picture or the initials underneath always show.
  Expects $learner; optional $class (defaults to "av").
  $class is only used when it is text: inside a class window the name already holds the whole
  class record, and printing that into the attribute once made every roster page 1.6 MB.
--}}
@php
    $avClass = (isset($class) && is_string($class)) ? $class : 'av';
    $initials = mb_strtoupper(mb_substr($learner->first_name, 0, 1).mb_substr($learner->last_name, 0, 1));
    $tone = ($learner->id % 5) + 1;
    $glyph = (string) ($learner->avatar_id ?? '');
    // The picture a child picks is one short character (an animal); anything longer is not a picture.
    $hasGlyph = $glyph !== '' && mb_strlen($glyph) <= 2 && ! preg_match('/^[A-Za-z0-9]$/u', $glyph);
@endphp
<span class="{{ $avClass }}{{ $hasGlyph ? '' : ' init c'.$tone }}" aria-hidden="true">{{ $hasGlyph ? $glyph : $initials }}@if ($learner->avatar_photo_path)<img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="" onerror="this.style.display='none'">@endif</span>
