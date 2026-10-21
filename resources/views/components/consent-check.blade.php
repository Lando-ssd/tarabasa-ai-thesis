{{--
  One consent tick box, the same everywhere a person is asked to agree (sign up, adding a child, linking to a child).
  Large and easy to tap, never ticked for the person (the box starts empty unless the form is being shown again with
  what they had ticked), with the error under it. The words go in the slot, so each form says exactly what is agreed to:

    <x-consent-check name="accepts_terms">I agree to the <a href="...">Terms</a>.</x-consent-check>

  Props: name (also the id unless id is given), required (default true), checked. Self contained style, once per page.
--}}
@props(['name', 'id' => null, 'required' => true, 'checked' => false])
@php $id = $id ?? $name; $err = $errors->first($name); @endphp
@once
<style>
  .consent-check{ margin:14px 0; }
  .consent-check label{ display:flex; gap:12px; align-items:flex-start; padding:12px 14px; border:2px solid #dbe7f3; border-radius:14px; background:#fff; cursor:pointer; font:500 14.5px/1.5 'Inter',system-ui,sans-serif; color:#15283a; text-align:left; }
  .consent-check label:hover{ border-color:#9ccbf5; }
  .consent-check input{ flex:none; width:24px; height:24px; margin:1px 0 0; accent-color:#1c7ed6; cursor:pointer; }
  .consent-check input:focus-visible{ outline:3px solid #1c7ed6; outline-offset:2px; }
  .consent-check a{ color:#0f5fae; font-weight:600; }
  .consent-check.has-error label{ border-color:#d64545; background:#fdecec; }
  .consent-check .consent-error{ margin:6px 2px 0; font:600 13.5px/1.4 'Inter',system-ui,sans-serif; color:#d64545; }
</style>
@endonce
<div class="consent-check @if ($err) has-error @endif">
  <label for="{{ $id }}">
    <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1" @checked(old($name, $checked)) @if ($required) required @endif {{ $attributes }}>
    <span>{{ $slot }}</span>
  </label>
  @if ($err)<p class="consent-error" role="alert">{{ $err }}</p>@endif
</div>
