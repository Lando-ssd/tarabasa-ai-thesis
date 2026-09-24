{{--
  My Bookshelf: one book per activity the child has really finished (times
  read, best score, last read). "Read Again" is free practice: it is never
  scored, so it can't change points, streak or level.
--}}
@extends('layouts.learner-shell')

@section('title', 'My Bookshelf — TaraBasa AI')
@section('page', 'bookshelf')

@php
    $covers = [['bs-blue', '📘'], ['bs-green', '📗']];
    // Always show a couple of empty slots so the shelf looks like it has room to grow.
    $emptySlots = $books->isEmpty() ? 4 : 2;
@endphp

@section('content')
<div class="subpage" id="page-bookshelf">
  <div class="pg-head">
    <h1 class="pg-title">My Bookshelf</h1>
    <span class="pg-count">{{ $books->count() }} {{ $books->count() === 1 ? 'book' : 'books' }}</span>
  </div>
  <p class="pg-sub">Read a book again to beat your best score. It is free practice!</p>

  <div class="bs-shelf">
    <div class="bs-books">
      @foreach ($books as $i => $book)
        @php [$tone, $emoji] = $covers[$i % 2]; @endphp
        <div class="bs-book {{ $tone }}">
          <div class="bs-cover"><span class="bs-emoji">{{ $emoji }}</span><span class="bs-times">Read {{ $book['timesRead'] }} {{ $book['timesRead'] === 1 ? 'time' : 'times' }}</span></div>
          <h3>{{ $book['activity']->title }}</h3>
          <div class="bs-best"><span>Best</span><div class="bs-tube"><i style="width:{{ min(100, (int) round($book['bestAccuracy'])) }}%"></i></div><b>{{ (int) round($book['bestAccuracy']) }}%</b></div>
          <div class="bs-last">Last read {{ \App\Support\LearnerClock::local($book['mostRecentDate'])->format('M j') }}</div>
          <a class="clay-btn orange bs-btn" href="{{ route('learner.bookshelf.reread', $book['activity']) }}">Read Again</a>
        </div>
      @endforeach

      @for ($i = 0; $i < $emptySlots; $i++)
        <div class="bs-book bs-empty">
          <div class="bs-cover"><span class="bs-plus">+</span></div>
          <h3>Your next book</h3>
          <div class="bs-last">Finish a reading and it lands here</div>
        </div>
      @endfor
    </div>
    <div class="bs-plank"></div>
  </div>

  <div class="bs-flow">
    <div class="bs-flow-title">Free practice</div>
    <p>No points and no streak changes. When you finish, you will see how this try compares to your best score for the book.</p>
  </div>
</div>
@endsection
