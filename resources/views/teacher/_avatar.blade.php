{{--
  A learner on a Teacher screen: initials on a soft colour, never the emoji the child picked for
  themselves (initials scan faster down a roster of 40, and the child's own picture stays on the
  child's own screens). Expects $learner; optional $class (defaults to "av").
  $class is only used when it is text: inside a class window the name already holds the whole
  class record, and printing that into the attribute once made every roster page 1.6 MB.
--}}
@php
    $avClass = (isset($class) && is_string($class)) ? $class : 'av';
    $initials = mb_strtoupper(mb_substr($learner->first_name, 0, 1).mb_substr($learner->last_name, 0, 1));
    $tone = ($learner->id % 5) + 1;
@endphp
<span class="{{ $avClass }} init c{{ $tone }}" aria-hidden="true">{{ $initials }}</span>
