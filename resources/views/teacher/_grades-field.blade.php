{{--
  "Which grades do you handle?" Used by Teacher sign up and Teacher Profile.
  One grade, or two or more for a multigrade class (in the Philippines one teacher can handle two or
  more grades in one classroom, so the app must not assume one grade per teacher). Class creation
  offers only these grades; the server enforces it, this form only makes it easy.

  Variables: $selected (array of "Grade N"), optional $idp (id prefix, default "gr").
--}}
@php
    $idp = $idp ?? 'gr';
    $selected = old('grades_handled', $selected ?? []);
    $mode = old('grades_mode', count($selected) > 1 ? 'multi' : (count($selected) === 1 ? 'single' : null));
@endphp
<div class="field gr-field" id="{{ $idp }}-box">
  <label>Which grades do you handle?</label>
  <div class="gr-opts" role="radiogroup" aria-label="One grade or more than one grade">
    <label class="gr-opt {{ $mode === 'single' ? 'on' : '' }}">
      <input type="radio" name="grades_mode" value="single" @checked($mode === 'single') required>
      <span class="gr-rb" aria-hidden="true"></span>
      <span>One grade<small>A single-grade class</small></span>
    </label>
    <label class="gr-opt {{ $mode === 'multi' ? 'on' : '' }}">
      <input type="radio" name="grades_mode" value="multi" @checked($mode === 'multi')>
      <span class="gr-rb" aria-hidden="true"></span>
      <span>More than one grade (multigrade)<small>Two or more grades under one teacher</small></span>
    </label>
  </div>
  <div class="gr-chk" id="{{ $idp }}-chk" @if(! $mode) hidden @endif>
    @foreach (['Grade 1', 'Grade 2', 'Grade 3'] as $g)
      <label class="gr-c {{ in_array($g, $selected, true) ? 'on' : '' }}">
        <input type="checkbox" name="grades_handled[]" value="{{ $g }}" @checked(in_array($g, $selected, true))>
        <b aria-hidden="true"></b>{{ $g }}
      </label>
    @endforeach
  </div>
  <p class="gr-hint">Class creation will offer only these grades. You can change them later in Profile.</p>
  @error('grades_handled') <span class="field-error">{{ $message }}</span> @enderror
  @error('grades_mode') <span class="field-error">{{ $message }}</span> @enderror
</div>
<style>
  .gr-opts{ display:flex; flex-direction:column; gap:8px; margin:2px 0 4px; }
  .gr-opt{ position:relative; display:flex; align-items:flex-start; gap:10px; padding:10px 12px; border:1.5px solid #e3ebf2; border-radius:12px; background:#fff; font-size:14px; font-weight:600; cursor:pointer; }
  .gr-opt input, .gr-c input{ position:absolute; opacity:0; pointer-events:none; }
  .gr-opt.on{ border-color:#1c7ed6; background:#eef6ff; }
  .gr-opt small{ display:block; font-weight:500; color:#5b6b7a; font-size:12px; margin-top:2px; }
  .gr-rb{ width:18px; height:18px; border-radius:50%; border:2px solid #b7c8d8; flex:none; margin-top:1px; background:#fff; }
  .gr-opt.on .gr-rb{ border-color:#1c7ed6; background:radial-gradient(circle,#1c7ed6 0 45%,#fff 50%); }
  .gr-chk{ display:flex; gap:8px; flex-wrap:wrap; margin:8px 0 2px; }
  .gr-c{ position:relative; display:inline-flex; align-items:center; gap:7px; padding:8px 14px; border:1.5px solid #e3ebf2; border-radius:11px; font-size:14px; font-weight:700; background:#fff; cursor:pointer; }
  .gr-c.on{ border-color:#1c7ed6; background:#eef6ff; color:#0f5fae; }
  .gr-c b{ width:16px; height:16px; border-radius:4px; border:2px solid #b7c8d8; display:inline-block; }
  .gr-c.on b{ background:#1c7ed6; border-color:#1c7ed6; box-shadow:inset 0 0 0 2px #fff; }
  .gr-hint{ margin:8px 0 0; font-size:12px; color:#5b6b7a; font-weight:500; line-height:1.4; }
  .gr-field [hidden]{ display:none !important; }
</style>
<script>
(function () {
  var box = document.getElementById(@json($idp.'-box'));
  if (!box) return;
  var radios = box.querySelectorAll('input[name="grades_mode"]');
  var checks = box.querySelectorAll('input[name="grades_handled[]"]');
  var wrap = document.getElementById(@json($idp.'-chk'));

  function mode() { var r = box.querySelector('input[name="grades_mode"]:checked'); return r ? r.value : null; }
  function paint() {
    radios.forEach(function (r) { r.parentNode.classList.toggle('on', r.checked); });
    checks.forEach(function (c) { c.parentNode.classList.toggle('on', c.checked); });
  }
  function apply(changed) {
    var m = mode();
    wrap.hidden = !m;
    if (m === 'single') {
      // One grade: ticking a second grade moves the tick instead of adding to it.
      if (changed && changed.checked) checks.forEach(function (c) { if (c !== changed) c.checked = false; });
      var on = Array.prototype.filter.call(checks, function (c) { return c.checked; });
      if (on.length > 1) on.slice(1).forEach(function (c) { c.checked = false; });
    }
    paint();
  }
  radios.forEach(function (r) { r.addEventListener('change', function () { apply(null); }); });
  checks.forEach(function (c) { c.addEventListener('change', function () { apply(c); }); });
  apply(null);
})();
</script>
