{{--
    DOC-MON · Privacy Notice, from PrivacyController@show:
    $account (the logged-in user, or null), $contactEmail, $years.
    Describes what DOC-MON really does, so change it when that changes.
    Someone logged in who hasn't accepted yet gets the Accept button → POST /privacy/accept.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Privacy Notice – DOC-MON</title>
  <link rel="stylesheet" href="{{ asset_v('css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset_v('css/privacy.css') }}">
</head>
<body>
  {{-- Same dark strip and logo as the log-in pages, so it reads as part of DOC-MON --}}
  <header class="pv-top">
    <a href="{{ url('/') }}" class="pv-brand">
      <img src="{{ asset('images/logo.png') }}" alt="" width="32" height="32">
      DOC-MON
    </a>
  </header>

  <main class="pv-page">
    <h1>Privacy Notice</h1>
    <p class="pv-sub">Last updated November 2026</p>

    @if ($account && ! $account->privacy_accepted_at)
      <p class="pv-ask" role="status">Please read this notice and accept it at the bottom to continue using DOC-MON.</p>
    @endif

    @include('partials.privacy-notice-text')

    @if ($account && ! $account->privacy_accepted_at)
      <form method="POST" action="{{ url('/privacy/accept') }}" class="pv-accept">
        @csrf
        <button type="submit" class="pv-btn">I have read this and I accept</button>
      </form>
      <form method="POST" action="{{ url('/logout') }}" class="pv-accept">
        @csrf
        <button type="submit" class="pv-link">Log out instead</button>
      </form>
    @elseif ($account)
      <p class="pv-back"><a href="{{ url('/'.$account->role.'/dashboard') }}">Back to your dashboard</a></p>
    @else
      <p class="pv-back"><a href="{{ url('/') }}">Back to DOC-MON</a></p>
    @endif
  </main>
</body>
</html>
