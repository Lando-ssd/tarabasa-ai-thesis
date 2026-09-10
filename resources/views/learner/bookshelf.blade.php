@extends('layouts.learner-app')

@section('title', 'My Bookshelf — TaraBasa AI')

@section('content')
<style>
  .book-list{ display:flex; flex-direction:column; gap:12px; }
  @media (min-width:820px){ .book-list{ display:grid; grid-template-columns:1fr 1fr; gap:14px; } }
  .book-card{
    display:flex; align-items:center; gap:14px; background:var(--surface); border:1px solid var(--line);
    border-radius:18px; padding:14px 16px; box-shadow:var(--shadow-sm);
  }
  .book-icon{
    width:46px;height:46px;border-radius:14px; flex-shrink:0; font-size:22px;
    background:linear-gradient(155deg, var(--sky-100), var(--blue-500)); color:#fff;
    display:flex;align-items:center;justify-content:center;
  }
  .book-info{ flex:1; min-width:0; }
  .book-title{ font-family:'Baloo 2',sans-serif; font-size:16px; font-weight:700; margin:0 0 3px; }
  .book-meta{ font-size:13px; font-weight:600; color:var(--slate-600); line-height:1.4; }
  .book-meta strong{ color:var(--teal); }
  .reread-btn{
    flex-shrink:0; display:inline-flex; align-items:center; gap:5px; font:800 13px/1 'Baloo 2',sans-serif;
    color:#fff; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); padding:10px 14px;
    border-radius:12px; text-decoration:none; transition:transform .15s ease;
  }
  .reread-btn:hover{ transform:translateY(-1px); }
  .empty-note{ font-size:15px; color:var(--slate-600); font-weight:600; line-height:1.5; }
</style>

<h2 class="greet">My Bookshelf</h2>

@if ($books->isEmpty())
  <p class="empty-note">Nothing here yet — finish a reading activity and it'll show up on your shelf!</p>
@else
  <div class="book-list">
    @foreach ($books as $book)
      <div class="book-card">
        <div class="book-icon">📖</div>
        <div class="book-info">
          <p class="book-title">{{ $book['activity']->title }}</p>
          <p class="book-meta">
            Read {{ $book['timesRead'] }} {{ $book['timesRead'] === 1 ? 'time' : 'times' }} ·
            Best: <strong>{{ round($book['bestAccuracy']) }}%</strong> ·
            {{ $book['mostRecentDate']->format('M j') }}
          </p>
        </div>
        <a href="{{ route('learner.bookshelf.reread', $book['activity']) }}" class="reread-btn">Read Again</a>
      </div>
    @endforeach
  </div>
@endif
@endsection
