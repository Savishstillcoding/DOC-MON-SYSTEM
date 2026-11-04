{{--
    DOC-MON · Forgot password (AUD-008) → POST /forgot-password (PasswordResetController@store)
    The user types their log-in ID and the email on their account; a one-time reset link
    is emailed if both match a verified account. The answer is the same either way.
--}}
<!DOCTYPE html>
<html lang="en">
<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  <style>html { background: #1a1a1a; }</style>
  <link rel="preload" as="image" href="{{ asset('images/icon-grid.png') }}">
  <title>Forgot Password – DOC-MON</title>
  <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter/inter-latin.woff2') }}" crossorigin>
  <link rel="stylesheet" href="{{ asset_v('css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/loginpage.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/loginform.css') }}">
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
        <a href="{{ url('/login') }}">Back</a>
        <a href="{{ url('/signup') }}">Sign-Up</a>
      </nav>

      <div class="content">
        <h1 class="title">DOC-MON<span class="sr-only"> – Forgot password</span></h1>

        <p class="tagline">Forgot your password?<br>We'll email you a link to choose a new one.</p>

        <form class="login-card" method="POST" action="{{ url('/forgot-password') }}">
          @csrf
          @if (session('status'))<p class="form-status" role="status">{{ session('status') }}</p>@endif

          <div class="field">
            <label for="login_id">Student ID, Employee ID or username</label>
            <input id="login_id" name="login_id" type="text" placeholder="ex. 1234567" autocomplete="username" maxlength="30" value="{{ old('login_id') }}" required>
            @error('login_id')<p class="form-error" role="alert">{{ $message }}</p>@enderror
          </div>

          <div class="field">
            <label for="email">Email on your account</label>
            <input id="email" name="email" type="email" placeholder="ex. juan@gmail.com" autocomplete="email" maxlength="255" value="{{ old('email') }}" required>
            @error('email')<p class="form-error" role="alert">{{ $message }}</p>@enderror
          </div>

          <button class="submit-btn" type="submit">Email me a reset link</button>

          <a class="forgot-link" href="{{ url('/login') }}">Back to log in</a>
        </form>
      </div>

      <p class="credits">made by: Labastida, Carusa, Ligutan, Magno</p>
    </section>
  </main>
</body>
</html>
