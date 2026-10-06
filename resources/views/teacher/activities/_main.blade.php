{{-- The part of the Activities screen that changes in place: the two tabs and the one that is open. --}}
<div class="tabsrow">
  <div class="seg" role="tablist" aria-label="Activities">
    <button type="button" role="tab" aria-selected="{{ $tab === 'review' ? 'true' : 'false' }}" data-load='{"tab":"review","page":0,"tray":0}'>To review <span class="n {{ $counts['Draft'] ? 'amber' : '' }}">{{ $counts['Draft'] }}</span></button>
    <button type="button" role="tab" aria-selected="{{ $tab === 'mine' ? 'true' : 'false' }}" data-load='{"tab":"mine","page":0,"tray":0}'>My activities <span class="n">{{ $counts['Approved'] }}</span></button>
  </div>
</div>
@if ($tab === 'review')
  @include('teacher.activities._review')
@else
  @include('teacher.activities._mine')
@endif
