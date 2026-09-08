{{--
  Shared +/- passage-text size control, used by both activity-found.
  blade.php and diagnostic-passage.blade.php. A real preference, not a
  scoring/assessment concern, so it deliberately breaks from this app's
  usual plain-form-submit convention: applies the new size to
  `.passage-card` instantly on tap via a data attribute, and separately
  fires a quiet background save (see LearnerAuthController::
  updateReadingFontStep()) — a failed save never blocks the interaction,
  it just means the choice won't persist past this visit.

  Self-contained styling (own <style> block) rather than requiring the
  including page to define these classes, since this control isn't
  visually tied to anything already on either page — it does read the
  including page's own :root color tokens (--owl-orange-500/600,
  --line, --surface, --bg-0, --navy-900, --slate-600), which both
  including pages already define identically.

  The 5 steps are a real, fixed scale — not unbounded incrementing —
  expressed as multipliers on --passage-font-base (set per breakpoint
  by the including page's own responsive rules) via calc(), so "Large"
  stays proportionally the same bump whether on a phone or a laptop
  instead of needing a separate step table per breakpoint.

  Expects: $initialStep (int, 1-5) — the Learner's real starting step
  from Learner::effectiveReadingFontStep().
--}}
<style>
  .font-control{
    display:inline-flex; align-items:center; gap:6px; background:var(--surface); border:2px solid var(--line);
    border-radius:999px; padding:6px 8px; margin:0 auto 16px; box-shadow:0 1px 2px rgba(19,31,43,0.06);
  }
  .font-btn{
    width:38px; height:38px; border-radius:50%; border:1.5px solid var(--line); background:var(--bg-0); color:var(--navy-900);
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    transition:border-color .15s ease, color .15s ease, transform .15s ease;
  }
  .font-btn:hover:not(:disabled){ border-color:var(--owl-orange-500); color:var(--owl-orange-600); transform:scale(1.08); }
  .font-btn:disabled{ opacity:.35; cursor:not-allowed; }
  .font-step-label{ font:700 13px/1 'Inter',sans-serif; color:var(--slate-600); padding:0 6px; min-width:150px; text-align:center; }

  .passage-card[data-font-step="1"]{ font-size:calc(var(--passage-font-base) * 0.82); }
  .passage-card[data-font-step="2"]{ font-size:calc(var(--passage-font-base) * 0.91); }
  .passage-card[data-font-step="3"]{ font-size:var(--passage-font-base); }
  .passage-card[data-font-step="4"]{ font-size:calc(var(--passage-font-base) * 1.15); }
  .passage-card[data-font-step="5"]{ font-size:calc(var(--passage-font-base) * 1.32); }
</style>
{{-- Icon buttons, not "A−"/"A+" glyphs — matches this app's own
     established icon-in-circle button language (e.g. the mic button)
     more closely than text glyphs would, per the visual reference
     shared for this control. --}}
<div class="font-control" role="group" aria-label="Reading text size">
  <button type="button" class="font-btn" id="fontStepMinus" aria-label="Smaller text">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M5 12h14" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
  </button>
  <span class="font-step-label" id="fontStepLabel"></span>
  <button type="button" class="font-btn" id="fontStepPlus" aria-label="Larger text">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
  </button>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Deferred to DOMContentLoaded, not run inline: this partial is
    // included ABOVE .passage-card in the page's own source order (so
    // it appears visually before the text it controls), which means
    // .passage-card doesn't exist in the DOM yet if this ran
    // synchronously as the browser parses past this <script> tag.
    const STEP_NAMES = ['Small', 'Medium−', 'Medium', 'Large', 'Extra Large'];
    let step = {{ (int) $initialStep }};

    const passageCard = document.querySelector('.passage-card');
    const label = document.getElementById('fontStepLabel');
    const minusBtn = document.getElementById('fontStepMinus');
    const plusBtn = document.getElementById('fontStepPlus');

    function render() {
      passageCard.setAttribute('data-font-step', step);
      label.textContent = 'Text size: ' + STEP_NAMES[step - 1];
      minusBtn.disabled = step <= 1;
      plusBtn.disabled = step >= 5;
    }

    function save(newStep) {
      const token = document.querySelector('meta[name="csrf-token"]')?.content;
      fetch('{{ route('learner.reading-preferences.font-step') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({ step: newStep }),
      }).catch(() => {});
    }

    minusBtn.addEventListener('click', function () {
      if (step <= 1) return;
      step--;
      render();
      save(step);
    });

    plusBtn.addEventListener('click', function () {
      if (step >= 5) return;
      step++;
      render();
      save(step);
    });

    render();
  });
</script>
