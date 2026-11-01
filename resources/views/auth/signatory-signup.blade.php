<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  <style>html { background: #1a1a1a; }</style>
  <link rel="preload" as="image" href="{{ asset('images/icon-grid.png') }}">
  <title>Signatory Sign Up – DOC-MON</title>
  <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter/inter-latin.woff2') }}" crossorigin>
  <link rel="stylesheet" href="{{ asset_v('css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/signuppage.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/registerpage.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/wires.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/transitions.css') }}">
  <script src="{{ asset_v('js/icon-scroll.js') }}"></script>
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

      <nav class="nav" aria-label="Main">
        <a href="{{ url('/login') }}">Log-In</a>
        <a href="{{ url('/signup') }}">Back</a>
      </nav>

      <div class="content">
        <h1 class="title">DOC-MON<span class="sr-only"> – Signatory sign up</span></h1>

        <p class="tagline">New Signatory? Welcome Sir/Ma'am :D</p>

        <form class="register-card" method="POST" action="{{ url()->current() }}" enctype="multipart/form-data">
          @csrf

          <div class="field-grid">
            <div class="field">
              <label for="first_name">First Name</label>
              <input id="first_name" name="first_name" type="text" placeholder="e.x. Juan Carlos" autocomplete="given-name" value="{{ old('first_name') }}" required>
              @error('first_name')<p class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="field">
              <label for="last_name">Last Name</label>
              <input id="last_name" name="last_name" type="text" placeholder="e.x. Manalo" autocomplete="family-name" value="{{ old('last_name') }}" required>
              @error('last_name')<p class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="field">
              <label for="employee_id">Employee ID</label>
              <input id="employee_id" name="employee_id" type="text" maxlength="30" placeholder="e.x. EMP-0451" value="{{ old('employee_id') }}" required>
              @error('employee_id')<p class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="field">
              <label for="position">Position</label>
              <input id="position" name="position" type="text" placeholder="e.x. Adviser, VPSD" autocomplete="organization-title" value="{{ old('position') }}" required>
              @error('position')<p class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" placeholder="e.x. juan.manalo@lnu.edu.ph" autocomplete="email" value="{{ old('email') }}" required>
              @error('email')<p class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="phone">Phone Number</label>
                <input id="phone" name="phone" type="tel" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" title="Enter an 11-digit number starting with 09" placeholder="e.x. 09123456789" autocomplete="tel" value="{{ old('phone') }}" required>
              @error('phone')<p class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="field">
              <label for="password">Password</label>
              <input id="password" name="password" type="password" minlength="8" placeholder="At least 8 characters" autocomplete="new-password" required>
              @error('password')<p class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>
          </div>

          <div class="field">
            <span class="field-label" id="id_document_label">Employee ID</span>
            <label class="upload" for="id_document">
              <svg class="upload-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>
              </svg>
              <span class="upload-text" data-upload-text>Click to upload</span>
            </label>
            <input class="upload-input" id="id_document" name="id_document" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" aria-labelledby="id_document_label" aria-describedby="id_document_note id_document_error" required>
            <p class="field-note" id="id_document_note">Note: Upload your employee ID for verification. Photo or PDF, max 2 MB (big photos are shrunk automatically).</p>
            <p class="form-error" id="id_document_error" role="alert" hidden></p>
            @error('id_document')<p class="form-error" role="alert">{{ $message }}</p>@enderror
          </div>

          <button class="submit-btn" type="submit">Sign Up</button>
        </form>
      </div>

      <p class="credits">made by: Labastida, Carusa, Ligutan, Magno</p>
    </section>
  </main>

  <script src="{{ asset_v('js/register.js') }}"></script>
</body>
</html>
