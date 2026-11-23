{{--
    The Privacy Notice text itself, from the intro to Contact.
    Shown on the /privacy page AND in the pop-up on both sign-up forms,
    so both always say the same thing. Needs: $years, $contactEmail.
--}}
    <p>DOC-MON is a capstone project that tracks the approval of student organizations' physical papers at Leyte Normal University. This notice explains what personal information it collects and what happens to it, as required by the Data Privacy Act of 2012 (Republic Act No. 10173).</p>

    <h2>What we collect</h2>
    <ul>
      <li>Your name, Student or Employee ID, email, position, and organization. A phone number is optional.</li>
      <li>A photo of your school or employee ID, so the admin can check you are who you say you are. It is deleted as soon as the admin approves or rejects your account.</li>
      <li>Your password, stored only as a hash. Nobody can read it, not even the admin.</li>
      <li>The tickets you file or sign, your approvals, returns, rejections and remarks, and your availability status.</li>
      <li>When you log in, failed log-in attempts, and the IP address they came from, to spot password guessing.</li>
      <li>Your Telegram chat ID, only if you connect Telegram.</li>
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
      <li><strong>Have I Been Pwned</strong> checks whether a new password appeared in a known data leak. It receives only the first 5 characters of the password's hash, never the password itself or who you are.</li>
    </ul>
    <p>No other outside service receives your information.</p>

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
