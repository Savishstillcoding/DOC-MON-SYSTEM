{{--
    DOC-MON · Privacy Notice (AUD-078), from PrivacyController@show:
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
  <main class="pv-page">
    <h1>Privacy Notice</h1>
    <p class="pv-sub">DOC-MON · Last updated November 2026</p>

    @if ($account && ! $account->privacy_accepted_at)
      <p class="pv-ask" role="status">Please read this notice and accept it at the bottom to continue using DOC-MON.</p>
    @endif

    <p>DOC-MON is a capstone project that tracks the approval of student organizations' physical papers at Leyte Normal University. This notice explains what personal information it collects and what happens to it, as required by the Data Privacy Act of 2012 (Republic Act No. 10173).</p>

    <h2>What we collect</h2>
    <ul>
      <li><strong>Account details:</strong> your name, Student or Employee ID, email, position, and organization. Your phone number is optional.</li>
      <li><strong>Your uploaded ID:</strong> used only to check that you are who you say you are. It is deleted as soon as the admin approves or rejects your account.</li>
      <li><strong>Your password:</strong> stored scrambled, so nobody can read it.</li>
      <li><strong>What you do in DOC-MON:</strong> tickets, approvals, returns, rejections, remarks, and your availability status.</li>
      <li><strong>Your Telegram chat ID:</strong> only if you choose to connect Telegram.</li>
    </ul>

    <h2>Why we collect it</h2>
    <ul>
      <li>To verify accounts, so nobody can pose as an officer or signatory.</li>
      <li>To route papers and show where each one is.</li>
      <li>To send notifications and password reset emails.</li>
      <li>To keep a record of who did what.</li>
    </ul>

    <h2>Who can see it</h2>
    <ul>
      <li><strong>The admin</strong> can see account details and the activity log, and your uploaded ID until it is deleted.</li>
      <li><strong>Officers of your organization</strong> can see its tickets and who filed them.</li>
      <li><strong>Signatories</strong> can see the tickets on their approval route, including the filer's name and any remarks.</li>
    </ul>

    <h2>Outside services we use</h2>
    <ul>
      <li><strong>Render</strong> hosts the website, and <strong>TiDB Cloud</strong> hosts the database.</li>
      <li><strong>Brevo</strong> delivers password reset emails, so it receives your email address.</li>
      <li><strong>Telegram</strong> delivers notifications if you connect it, so ticket details and remarks pass through it.</li>
    </ul>
    <p>We don't sell your information.</p>

    <h2>How long we keep it</h2>
    <p>Your account is kept for up to {{ $years }} years from sign-up. After that, the admin reviews it and turns it off if it is no longer needed. A turned-off account can no longer log in, but its details stay stored so the approval history remains complete.</p>
    <p>Tickets, approvals, and the names on them are the organization's records, and they are kept.</p>

    <h2>Your rights</h2>
    <p>You can ask to see or correct your information, object to how it is used, withdraw your consent, or ask for it to be removed. Some of it may have to stay as part of the organization's records. You can also complain to the National Privacy Commission.</p>

    <h2>Contact</h2>
    <p>
      MJ Bryan T. Labastida, DOC-MON team
      @if ($contactEmail)
        · <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
      @endif
    </p>

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
