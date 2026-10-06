{{-- Registration form for Student Officers and Signatories (from Figma:
     "Student Officer Sign-Up" / "Signatory Sign-Up"). One view for both roles;
     routes/web.php passes $roleLabel and $tagline. PROTOTYPE: nothing is saved yet. --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  {{-- Paint the page dark right away so switching pages never flashes white --}}
  <style>html { background: #1a1a1a; }</style>
  <link rel="preload" as="image" href="{{ asset('images/icon-grid.png') }}">
  <title>Signatory Sign Up – DOC-MON</title>
  {{-- Inter font, served from public/fonts (no Google Fonts request) --}}
  <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter/inter-latin.woff2') }}" crossorigin>
  <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
  {{-- shared page layout (same as the sign-up choices page) + the form's own styles --}}
  <link rel="stylesheet" href="{{ asset('css/signuppage.css') }}">
  <link rel="stylesheet" href="{{ asset('css/registerpage.css') }}">
  <link rel="stylesheet" href="{{ asset('css/wires.css') }}">
  <link rel="stylesheet" href="{{ asset('css/transitions.css') }}">
  <script src="{{ asset('js/icon-scroll.js') }}"></script>
</head>
<body>
  <main class="landing">
    <section class="left" aria-hidden="true">
      <div class="icon-track">
        @for ($i = 0; $i < 4; $i++)
          <img src="{{ asset('images/icon-grid.png') }}" alt="" decoding="sync">
        @endfor
      </div>
    </section>

    <section class="right">
      @include('partials.wires')

      <nav class="nav" aria-label="Main">{{-- TRY-OUT #11: names the menu for screen readers --}}
        <a href="{{ url('/login') }}">Log-In</a>
        <a href="{{ url('/signup') }}">Back</a>
      </nav>

      <div class="content">
        <h1 class="title">DOC-MON<span class="sr-only"> – Signatory sign up</span></h1>{{-- TRY-OUT #11: screen readers hear which page this is --}}

        <p class="tagline">New Signatory? Welcome Sir/Ma'am :D</p>

        {{-- enctype is needed so the School ID file is actually sent --}}
        <form class="register-card" method="POST" action="{{ url()->current() }}" enctype="multipart/form-data">
          @csrf

          <div class="field-grid">
            <div class="field">
              <label for="first_name">First Name</label>
              <input id="first_name" name="first_name" type="text" placeholder="e.x. Juan Carlos" autocomplete="given-name" value="{{ old('first_name') }}" required>
            </div>
            <div class="field">
              <label for="last_name">Last Name</label>
              <input id="last_name" name="last_name" type="text" placeholder="e.x. Manalo" autocomplete="family-name" value="{{ old('last_name') }}" required>
            </div>

            <div class="field">
              <label for="student_id">Employee ID</label>
              <input id="student_id" name="student_id" type="text" inputmode="numeric" placeholder="e.x. 1234567" value="{{ old('student_id') }}" required>
            </div>
            <div class="field">
              <label for="organization">Department Organization</label>
              <input id="organization" name="organization" type="text" placeholder="e.x. ODSOA" autocomplete="organization" value="{{ old('organization') }}" required>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" placeholder="e.x. juan.manalo@lnu.edu.ph" autocomplete="email" value="{{ old('email') }}" required>
            </div>
            <div class="field">
                <label for="phone">Phone Number</label>
                <input id="phone" name="phone" type="tel" inputmode="numeric" placeholder="e.x. 09123456789" autocomplete="tel" value="{{ old('phone') }}" required>
            </div>
            
            <div class="field">
              <label for="position">Position</label>
              <input id="position" name="position" type="text" placeholder="e.x. Department Head" autocomplete="organization-title" value="{{ old('position') }}" required>
            </div>
            <div class="field">
              <label for="password">Password</label>
              <input id="password" name="password" type="password" placeholder="e.x. Testpassword#1" autocomplete="new-password" required>
            </div>
          </div>

          <div class="field">
            <span class="field-label" id="school_id_label">Employee ID</span>
            {{-- The real file input is hidden; the whole box is its label, so clicking anywhere opens the file picker --}}
            <label class="upload" for="school_id">
              <svg class="upload-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>
              </svg>
              <span class="upload-text" data-upload-text>Click to upload</span>
            </label>
            <input class="upload-input" id="school_id" name="school_id" type="file" accept="image/*,.pdf" aria-labelledby="school_id_label" aria-describedby="school_id_note" required>
            <p class="field-note" id="school_id_note">Note: Upload your school ID for verification</p>
          </div>

          <button class="submit-btn" type="submit">Sign Up</button>
        </form>
      </div>

      <p class="credits">made by: Labastida, Carusa, Ligutan, Magno</p>
    </section>
  </main>

  <script src="{{ asset('js/register.js') }}"></script>
</body>
</html>
