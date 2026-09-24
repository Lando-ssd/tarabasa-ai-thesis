{{-- The part of the Activities screen that changes in place: the board or the list. --}}
@if ($view === 'board')
  @include('teacher.activities._board')
@else
  @include('teacher.activities._list')
@endif
