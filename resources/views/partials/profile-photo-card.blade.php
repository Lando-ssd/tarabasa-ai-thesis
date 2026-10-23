{{--
  "Profile photo" card for a Teacher's or Parent's own My Profile page (the same card on both, in each role's own colours).
  Expects: $user. The photo is the person's own and only they ever see it (App\Http\Controllers\UserPhotoController).

  The page shrinks the chosen picture to a 480 by 480 square before it is sent, so it is small and never stretched, and shows the
  result in the circle before anything is saved. The server still checks that it is a real picture of at most 1 MB.
--}}
@php
    $photo = $user->photoUrl();
    $initials = mb_strtoupper(mb_substr($user->first_name, 0, 1)).mb_strtoupper(mb_substr($user->last_name, 0, 1));
@endphp
<div class="card photo-card" id="photo" style="margin-bottom:16px">
  <p class="card-title">Profile photo</p>
  <p class="card-sub">Only you see your photo. It appears next to your name on your own screens, never to children, parents, teachers or the Administrator.</p>

  @if ($errors->has('photo'))
    <div class="form-error-banner">{{ $errors->first('photo') }}</div>
  @endif

  <div class="ph-row">
    <div class="ph-circle" id="phCircle">{{ $initials }}@if ($photo)<img src="{{ $photo }}" alt="Your photo" onerror="this.style.display='none'">@endif</div>
    <div class="ph-actions">
      <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data" id="phForm">
        @csrf
        <input type="file" name="photo" id="phInput" accept="image/jpeg,image/png,image/webp" hidden>
        <div class="ph-btns">
          <button type="button" class="btn small" id="phChoose">{{ $photo ? 'Change photo' : 'Choose a photo' }}</button>
          <button type="submit" class="btn small" id="phSave" hidden>Save photo</button>
          <button type="button" class="btn small ghost" id="phCancel" hidden>Cancel</button>
        </div>
        <p class="note" id="phNote" style="margin:8px 0 0">A square picture works best. jpg, png or webp.</p>
      </form>
      @if ($photo)
        <form method="POST" action="{{ route('profile.photo.remove') }}" data-busy="Removing" style="margin-top:8px">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn small ghost">Remove photo</button>
        </form>
      @endif
    </div>
  </div>
</div>

<style>
  .photo-card .ph-row{ display:flex; gap:20px; align-items:center; flex-wrap:wrap; margin-top:6px; }
  .photo-card .ph-circle{ position:relative; overflow:hidden; flex:none; width:96px; height:96px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:linear-gradient(180deg,#3d97ea,#1c7ed6); color:#fff; font-weight:700; font-size:32px; line-height:1; box-shadow:0 3px 0 rgba(0,0,0,.12); }
  .photo-card .ph-circle img{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
  .photo-card .ph-btns{ display:flex; gap:10px; flex-wrap:wrap; }
</style>
<script>
(function () {
  var input = document.getElementById('phInput');
  if (!input) return;
  var choose = document.getElementById('phChoose'), save = document.getElementById('phSave'), cancel = document.getElementById('phCancel');
  var note = document.getElementById('phNote'), circle = document.getElementById('phCircle'), form = document.getElementById('phForm');
  var originalCircle = circle.innerHTML, originalNote = note.textContent;

  function reset() {
    input.value = '';
    save.hidden = true;
    cancel.hidden = true;
    choose.hidden = false;
    circle.innerHTML = originalCircle;
    note.textContent = originalNote;
  }

  choose.addEventListener('click', function () { input.click(); });
  cancel.addEventListener('click', reset);

  input.addEventListener('change', function () {
    var file = input.files && input.files[0];
    if (!file) return;
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
      note.textContent = 'Choose a jpg, png or webp photo.';
      input.value = '';
      return;
    }
    var url = URL.createObjectURL(file);
    var img = new Image();
    img.onload = function () {
      // The middle square of the picture, shrunk to 480 by 480, as a JPEG.
      var side = Math.min(img.naturalWidth, img.naturalHeight);
      var canvas = document.createElement('canvas');
      canvas.width = canvas.height = 480;
      var ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, 480, 480);
      ctx.drawImage(img, (img.naturalWidth - side) / 2, (img.naturalHeight - side) / 2, side, side, 0, 0, 480, 480);
      URL.revokeObjectURL(url);
      canvas.toBlob(function (blob) {
        if (!blob) { note.textContent = 'That photo could not be prepared. Try another one.'; input.value = ''; return; }
        var small = new File([blob], 'photo.jpg', { type: 'image/jpeg' });
        try {
          var dt = new DataTransfer();
          dt.items.add(small);
          input.files = dt.files;
        } catch (e) { /* an old browser keeps the original file; the server still accepts it when it is under 1 MB */ }
        circle.innerHTML = '';
        var shown = document.createElement('img');
        shown.alt = 'Your new photo';
        shown.src = URL.createObjectURL(small);
        circle.appendChild(shown);
        choose.hidden = true;
        save.hidden = false;
        cancel.hidden = false;
        note.textContent = 'This is how it will look. Press Save photo, or Cancel.';
      }, 'image/jpeg', 0.85);
    };
    img.onerror = function () {
      URL.revokeObjectURL(url);
      note.textContent = 'That photo could not be read. Try a jpg or png.';
      input.value = '';
    };
    img.src = url;
  });

  form.addEventListener('submit', function () {
    save.disabled = true;
    save.textContent = 'Saving...';
  });
})();
</script>
