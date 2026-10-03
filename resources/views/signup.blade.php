<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  <style>html { background: #1a1a1a; }</style>
  <link rel="preload" as="image" href="{{ asset('images/icon-grid.png') }}">
  <title>Sign Up – DOC-MON</title>
  <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter/inter-latin.woff2') }}" crossorigin>
  <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset('css/signuppage.css') }}">
  <link rel="stylesheet" href="{{ asset('css/wires.css') }}">
  <link rel="stylesheet" href="{{ asset('css/transitions.css') }}">
  <script src="{{ asset('js/icon-scroll.js') }}"></script>
  <script src="{{ asset('js/page-transitions.js') }}"></script>

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
        <a href="{{ url('/') }}">Back</a>
      </nav>

      <div class="content">
        <h1 class="title">DOC-MON<span class="sr-only"> – Sign up</span></h1>
        <p class="tagline">Greetings! Ready to handle the documents?</p>

        <!-- Pick which kind of account to create -->
        <div class="role-card">
          <a class="role-btn" href="{{ url('/register/officer') }}">Student Officer</a>
          <a class="role-btn" href="{{ url('/register/signatory') }}">Signatory</a>
          <a class="role-btn" href="{{ url('/register/admin') }}">Admin</a>
        </div>
      </div>

      <p class="credits">made by: Labastida, Carusa, Ligutan, Magno</p>
    </section>
  </main>

</body>
</html>
