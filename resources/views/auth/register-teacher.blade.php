@extends('layouts.auth')
@section('title', 'Teacher Registration — TaraBasa AI')
@section('content')

  <h1>Teacher Registration</h1>
  <p class="sub">You can log in right away — some features unlock once your school verifies you.</p>

  @if ($errors->any())
    <div class="form-error-banner">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;margin-top:1px;">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
        <path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <span>Please fix the highlighted fields below.</span>
    </div>
  @endif

  <form method="POST" action="{{ route('register.teacher.submit') }}" id="regForm">
    @csrf

    <div class="name-row">
      <div class="field">
        <label for="first_name">First Name</label>
        <input type="text" name="first_name" id="first_name" placeholder="Juana"
               value="{{ old('first_name') }}" class="@error('first_name') error @enderror" required>
        @error('first_name') <span class="field-error">{{ $message }}</span> @enderror
      </div>
      <div class="field">
        <label for="last_name">Last Name</label>
        <input type="text" name="last_name" id="last_name" placeholder="Reyes"
               value="{{ old('last_name') }}" class="@error('last_name') error @enderror" required>
        @error('last_name') <span class="field-error">{{ $message }}</span> @enderror
      </div>
    </div>

    <div class="field">
      <label for="email">Email</label>
      <input type="email" name="email" id="email" placeholder="you@deped.gov.ph"
             value="{{ old('email') }}" class="@error('email') error @enderror" required>
      @error('email') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input type="password" name="password" id="password" placeholder="At least 8 characters"
             class="@error('password') error @enderror" required>
      @error('password') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div class="field">
      <label for="password_confirmation">Confirm Password</label>
      <input type="password" name="password_confirmation" id="password_confirmation"
             placeholder="Re-type your password" required>
    </div>

    <div class="divider-label">School Verification</div>

    <div class="verify-note">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      <span>These two fields confirm you're real school staff. An Admin checks them before you get access to real class rosters and student data.</span>
    </div>

    <div class="field">
      <label for="school_name">School Name</label>
      <input type="text" name="school_name" id="school_name" placeholder="e.g. Rizal Elementary School"
             value="{{ old('school_name') }}" class="@error('school_name') error @enderror" required>
      @error('school_name') <span class="field-error">{{ $message }}</span> @enderror
    </div>
    <div class="field">
      <label for="employee_id">DepEd Employee ID</label>
      <input type="text" name="employee_id" id="employee_id" placeholder="e.g. EMP-2024-003"
             value="{{ old('employee_id') }}" class="@error('employee_id') error @enderror" required>
      @error('employee_id') <span class="field-error">{{ $message }}</span> @enderror
    </div>
    <div class="field">
      <label for="contact_number">Contact Number <span class="opt">optional</span></label>
      <input type="text" name="contact_number" id="contact_number" placeholder="e.g. 0917 123 4567"
             value="{{ old('contact_number') }}">
    </div>

    <button type="submit" class="submit-btn" id="submitBtn">Register</button>
  </form>

  <p class="switch-note">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>

  <script>
    // Real (not fake) loading state: disables the button the instant the
    // form is submitted, so it can't be double-clicked while the request
    // is actually in flight to the server.
    document.getElementById('regForm').addEventListener('submit', function () {
      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.textContent = 'Creating your account…';
    });
  </script>
@endsection
