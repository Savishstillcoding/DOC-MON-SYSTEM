{{-- Signatory log-in form.
     PROTOTYPE: nothing is checked yet. --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  <style>html { background: #1a1a1a; }</style>
  <link rel="preload" as="image" href="{{ asset('images/icon-grid.png') }}">
  <title>Signatory Log In – DOC-MON</title>
  <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter/inter-latin.woff2') }}" crossorigin>
  <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
  {{-- same layout as the log-in choices page + the form's own styles --}}
  <link rel="stylesheet" href="{{ asset('css/loginpage.css') }}">
  <link rel="stylesheet" href="{{ asset('css/loginform.css') }}">
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

      <nav class="nav" aria-label="Main">
        <a href="{{ url('/login') }}">Back</a>
        <a href="{{ url('/signup') }}">Sign-Up</a>
      </nav>

      <div class="content">
        <h1 class="title">DOC-MON<span class="sr-only"> – Signatory log in</span></h1>

        <p class="tagline">Welcome back, Sir/Ma’am!<br>Documents are waiting for your signature :)</p>

        <form class="login-card" method="POST" action="{{ url()->current() }}">
          @csrf

          <div class="field">
            <label for="employee_id">Employee ID</label>
            <input id="employee_id" name="employee_id" type="text" inputmode="numeric" placeholder="ex. 1234567" autocomplete="username" value="{{ old('employee_id') }}" required>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" placeholder="ex. Testpassword#1" autocomplete="current-password" required>
          </div>

          <button class="submit-btn" type="submit">Sign In</button>

          <a class="forgot-link" href="#">Forgot password?</a>
        </form>
      </div>

      <p class="credits">made by: Labastida, Carusa, Ligutan, Magno</p>
    </section>
  </main>
</body>
</html>
