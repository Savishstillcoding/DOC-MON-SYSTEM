{{--
    DOC-MON · Choose a new password (AUD-008), opened from the emailed link
    /reset-password/{token}?email=... → POST /reset-password (PasswordResetController@update)
    Same password rules as the Profile page (Password::defaults()).
--}}
<!DOCTYPE html>
<html lang="en">
<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  {{-- The token is in this page's address: don't send it to other sites --}}
  <meta name="referrer" content="no-referrer">
  <style>html { background: #1a1a1a; }</style>
  <link rel="preload" as="image" href="{{ asset('images/icon-grid.png') }}">
  <title>New Password – DOC-MON</title>
  <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter/inter-latin.woff2') }}" crossorigin>
  <link rel="stylesheet" href="{{ asset_v('css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/loginpage.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/loginform.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/wires.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/transitions.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/password-toggle.css') }}">
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
      </nav>

      <div class="content">
        <h1 class="title">DOC-MON<span class="sr-only"> – Choose a new password</span></h1>

        <p class="tagline">Choose a new password.<br>Then log in with it.</p>

        <form class="login-card" method="POST" action="{{ url('/reset-password') }}">
          @csrf
          <input type="hidden" name="token" value="{{ $token }}">

          <div class="field">
            <label for="email">Email on your account</label>
            <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email', $email) }}" required>
            @error('email')<p class="form-error" role="alert">{{ $message }} <a href="{{ url('/forgot-password') }}">Get a new link</a></p>@enderror
          </div>

          <div class="field">
            <label for="password">New password</label>
            <input id="password" name="password" type="password" aria-describedby="password_hint" autocomplete="new-password" minlength="8" required>
            <p class="field-hint" id="password_hint">At least 8 characters, with letters and numbers.</p>
            @error('password')<p class="form-error" role="alert">{{ $message }}</p>@enderror
          </div>

          <div class="field">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Type it again" autocomplete="new-password" required>
          </div>

          <button class="submit-btn" type="submit">Save new password</button>
        </form>
      </div>

      <p class="credits">made by: Labastida, Carusa, Ligutan, Magno</p>
    </section>
  </main>
  <script src="{{ asset_v('js/password-toggle.js') }}"></script>
</body>
</html>
