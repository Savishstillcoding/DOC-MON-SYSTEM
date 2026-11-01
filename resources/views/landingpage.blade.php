<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  <style>html { background: #1a1a1a; }</style>
  <link rel="preload" as="image" href="{{ asset('images/icon-grid.png') }}">
  <title>DOC-MON</title>
  <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter/inter-latin.woff2') }}" crossorigin>
  <link rel="stylesheet" href="{{ asset_v('css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/landingpage.css') }}">
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
        @guest
          <a href="{{ url('/login') }}">Log-In</a>
          <a href="{{ url('/signup') }}">Sign-Up</a>
        @endguest
        {{-- Already logged in (AUD-045): straight to your dashboard, or log out.
             Log out is a POST form with a CSRF token, like the sidebar's, so no link
             or image elsewhere can log someone out without them clicking it --}}
        @auth
          <a href="{{ url('/' . auth()->user()->role . '/dashboard') }}" class="nav-auto">Dashboard</a>
          <form method="POST" action="{{ url('/logout') }}">
            @csrf
            <button type="submit" class="nav-link-btn">Log out</button>
          </form>
        @endauth
      </nav>

      <div class="content">
        <h1 class="title">DOC-MON</h1>

        <p class="tagline">
          The system made for student secretaries<br>
          Created for Student Organizations of LNU
        </p>

        <div class="logo-circle">
          <img src="{{ asset('images/logo.png') }}" alt="DOC-MON logo">
        </div>
      </div>

      <p class="credits">made by: Labastida, Carusa, Ligutan, Magno</p>
    </section>
  </main>

</body>
</html>
