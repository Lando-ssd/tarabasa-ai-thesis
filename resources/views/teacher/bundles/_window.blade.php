{{--
  One bundle's window: its activities, and the classes it reaches. Assigning a new class to the
  bundle happens from that class's own window (Bundles tab) — see BundleController::window().
  Expects: $bundle (with activities, classes), $locked.
--}}
<dialog id="bundle-{{ $bundle->id }}" class="win" aria-label="{{ $bundle->name }}" data-autoopen>
  <div class="win-in mid">
    <header class="win-head">
      <div class="win-titles">
        <div class="tags"><span class="pill blue">@include('learner._badge-icon', ['icon' => 'folders', 'class' => 'ico']) Bundle</span></div>
        <h2>{{ $bundle->name }}</h2>
        <p class="win-meta">{{ $bundle->activities->count() }} {{ $bundle->activities->count() === 1 ? 'activity' : 'activities' }}</p>
      </div>
      <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
    </header>
    <div class="win-body">
      <div class="block">
        <span class="eyebrow">Assigned to</span>
        @if ($bundle->classes->isEmpty())
          <p class="note">Not assigned to a class yet. Open a class and use its Bundles tab.</p>
        @else
          <div class="chips" style="margin-top:8px">
            @foreach ($bundle->classes as $class)
              <span class="chip plain" style="cursor:default">
                {{ $class->name }}
                @unless ($locked)
                  <form method="POST" action="{{ route('teacher.bundles.classes.remove', [$bundle, $class]) }}" style="display:inline" data-busy="Removing">
                    @csrf
                    <button type="submit" class="x" style="width:22px;height:22px;margin-left:6px" aria-label="Remove {{ $class->name }}">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
                  </form>
                @endunless
              </span>
            @endforeach
          </div>
        @endif
      </div>
      <div class="block">
        <span class="eyebrow">Activities in this bundle</span>
        @if ($bundle->activities->isEmpty())
          <div class="card empty-hero" style="box-shadow:none">
            <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'folders', 'class' => 'ico'])</div>
            <h2>Nothing here yet</h2>
            <p>Drag an approved activity onto this bundle, or use its "Add to bundle" menu.</p>
          </div>
        @else
          <div class="alist" style="margin-top:8px">
            @foreach ($bundle->activities as $a)
              <div class="arow static">
                <div class="atitle"><b>{{ $a->title }}</b><span>{{ $a->grade_level }} · {{ $a->typeLabel() }} · {{ $a->word_count }} words</span></div>
                <span class="pill t-{{ strtolower($a->difficulty_tier) }}">{{ $a->difficulty_tier }}</span>
                @unless ($locked)
                  <form method="POST" action="{{ route('teacher.bundles.activities.remove', [$bundle, $a]) }}" data-busy="Removing">
                    @csrf
                    <button type="submit" class="btn ghost small">Remove</button>
                  </form>
                @endunless
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>
    <footer class="win-foot">
      @unless ($locked)
        <form method="POST" action="{{ route('teacher.bundles.destroy', $bundle) }}" data-busy="Deleting">
          @csrf
          <button type="submit" class="btn danger ghost small" title="Activities stay approved; they just leave the bundle.">Delete bundle</button>
        </form>
      @endunless
      <span class="spacer"></span>
      <button type="button" class="btn ghost small" data-close>Close</button>
    </footer>
  </div>
</dialog>
