{{-- A small tag next to a choice in an Assign window: whether the learners it is for can read this activity (ActivityFit). Expects $verdict. --}}
@if ($verdict === \App\Support\ActivityFit::BLOCKED)<span class="fitpill blocked">Too long</span>@elseif ($verdict === \App\Support\ActivityFit::CAUTION)<span class="fitpill caution">A stretch</span>@endif
