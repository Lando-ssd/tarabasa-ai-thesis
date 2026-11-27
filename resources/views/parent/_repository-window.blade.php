{{--
  One shared activity, opened from its card (parent/repository.blade.php): what it is, the words once it is unlocked for this child,
  the receipt of this parent's payment, or the step that unlocks it. The same forms and rules as the card; nothing here unlocks on its own.
  Uses the loop's $row, $listing, $activity and the page's $selectedLearner, $paymentsOn, $paymentsReady, $paymentsDemo, $paymentWord.
--}}
<dialog class="ra-win" id="ra-{{ $listing->id }}" aria-labelledby="ra-name-{{ $listing->id }}">
  <div class="ra-box">
    <div class="ra-head">
      <div>
        <div class="tags">
          <span class="tag">{{ $activity->competency_label }}</span>
          <span class="tag diff">{{ $activity->difficulty_tier }}</span>
          @if ($row['is_unlocked'])
            <span class="tag owned">Unlocked</span>
          @elseif ($listing->price_type === 'Free')
            <span class="tag free">Free</span>
          @else
            <span class="tag paid">&#8369;{{ number_format($listing->price) }}</span>
          @endif
        </div>
        <h2 id="ra-name-{{ $listing->id }}">{{ $activity->title }}</h2>
        <p class="note">{{ $activity->grade_level }} &middot; {{ $activity->typeLabel() }}@if ($activity->word_count) &middot; {{ $activity->word_count }} words @endif
          @if ($listing->teacher?->user)<br>Shared by {{ $listing->teacher->user->first_name }} {{ $listing->teacher->user->last_name }}@endif</p>
      </div>
      <button type="button" class="ra-close" data-ra-close aria-label="Close">&times;</button>
    </div>

    @if ($row['level_match'] || $row['pattern_match'])
      <p class="ra-why">@if ($row['level_match'])Matches {{ $selectedLearner->first_name }}'s reading level. @endif @if ($row['pattern_match'])Practises {{ $row['pattern_match'] }}. @endif</p>
    @endif

    @if ($row['is_unlocked'])
      <div class="eyebrow">What {{ $selectedLearner->first_name }} will read</div>
      <p class="ra-passage">{{ $activity->passage_text }}</p>
      <p class="note">{{ $selectedLearner->first_name }} opens it from their own login, in Reading, under Extra Practice.</p>
      @if ($row['receipt'])
        <div class="eyebrow">Receipt</div>
        <dl class="pay-record ra-receipt">
          <dt>Status</dt><dd><span class="tag {{ $row['receipt']->statusClass() }}">{{ $row['receipt']->statusLabel() }}</span></dd>
          <dt>Paid with</dt><dd>{{ $row['receipt']->methodLabel() }}</dd>
          <dt>Amount</dt><dd>&#8369;{{ number_format($row['receipt']->pesos(), 2) }} ({{ $row['receipt']->modeWord() }})</dd>
          <dt>Reference</dt><dd>{{ $row['receipt']->reference }}</dd>
          <dt>Date</dt><dd>{{ ($row['receipt']->paid_at ?? $row['receipt']->created_at)->timezone('Asia/Manila')->format('j M Y, g:i A') }}</dd>
        </dl>
        <a class="btn ghost" href="{{ route('parent.payments.index') }}">Payment history</a>
      @endif
    @else
      <p class="note">The words to read show here once it is unlocked for {{ $selectedLearner->first_name }}.</p>
      <div class="ra-price">{{ $listing->price_type === 'Free' ? 'Free' : '₱'.number_format($listing->price, 2) }}</div>
      @if ($listing->price_type === 'Free')
        <form method="POST" action="{{ route('parent.repository.unlock', $listing) }}">
          @csrf
          <input type="hidden" name="learner_id" value="{{ $selectedLearner->id }}">
          <button type="submit" class="btn">Get for free</button>
        </form>
      @elseif ($paymentsOn)
        <p class="ra-explain">
          @if ($paymentsDemo)
            You will go to TaraBasa's demo checkout. It runs inside TaraBasa. We never ask for a card or wallet number, and nothing is sent to any other company. No real money is charged.
          @else
            You will leave TaraBasa to pay on the payment provider's test page. We send them only the price, the activity title and a reference number.
          @endif
        </p>
        <form method="POST" action="{{ route('parent.payments.start', $listing) }}">
          @csrf
          <input type="hidden" name="learner_id" value="{{ $selectedLearner->id }}">
          <button type="submit" class="btn blue" @disabled(! $paymentsReady)>{{ $paymentsDemo ? 'Go to demo checkout' : 'Go to test payment' }}</button>
        </form>
      @else
        <p class="ra-explain">This is a simulated payment, so nothing is charged.</p>
        <form method="POST" action="{{ route('parent.repository.unlock', $listing) }}">
          @csrf
          <input type="hidden" name="learner_id" value="{{ $selectedLearner->id }}">
          <button type="submit" class="btn blue">Confirm unlock</button>
        </form>
      @endif
    @endif
  </div>
</dialog>
