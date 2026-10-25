{{--
    DOC-MON · Student Officer · Profile
    Static placeholder data for now (not connected to the database yet).
    Field names are ready for the controller (same names as the sign-up form):
      Profile form:  first_name, last_name, student_id, position, email, phone, telegram,
                     notify_sms, notify_telegram (unticked checkboxes are not sent at all)
      Password form: current_password, password, password_confirmation
--}}
@php
    $user = ['name' => 'Labastida, MJ Bryan', 'initials' => 'MJ', 'subtitle' => 'Secretary · DIGITS'];

    $menu = [
        ['label' => 'Dashboard',   'icon' => 'house',     'href' => url('/officer/dashboard')],
        ['label' => 'New Ticket',  'icon' => 'file-plus', 'href' => url('/officer/tickets/new')],
        ['label' => 'My Tickets',  'icon' => 'file-text', 'href' => url('/officer/tickets')],
        ['label' => 'Signatories', 'icon' => 'users',     'href' => url('/officer/signatories')],
        ['label' => 'Profile',     'icon' => 'user',      'href' => url('/officer/profile')],
    ];

    $profile = [
        'first_name'   => 'MJ Bryan',
        'last_name'    => 'Labastida',
        'student_id'   => '2201234',
        'organization' => 'DIGITS',
        'position'     => 'Secretary',
        'email'        => 'mj.labastida@lnu.edu.ph',
        'phone'        => '09171234567',
        'telegram'     => '@mjbryan',
        'status'       => 'Verified',
        'member_since' => 'Aug 2026',
        'tickets'      => 24,
    ];

    $positions = ['Secretary', 'Deputy Secretary'];

    $notifications = [
        ['name' => 'notify_sms',      'label' => 'SMS',      'note' => 'Status updates sent to 0917 123 4567', 'on' => true,  'locked' => false],
        ['name' => 'notify_telegram', 'label' => 'Telegram', 'note' => 'Status updates sent to ' . $profile['telegram'], 'on' => true, 'locked' => false],
        // Email is only for password reset, so it can't be switched on for ticket updates
        ['name' => null,              'label' => 'Email',    'note' => 'Only used for password reset',          'on' => false, 'locked' => true],
    ];
@endphp

<x-docmon.dashboard-layout title="Profile" :user="$user" :menu="$menu" active="Profile">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Profile</h1>
            <p class="dm-page-sub">Your account details and notification settings.</p>
        </div>
    </header>

    <div class="pf-columns">
        {{-- Profile summary --}}
        <section class="dm-card pf-card">
            <span class="dm-avatar pf-avatar">{{ $user['initials'] }}</span>
            <h2 class="pf-name">{{ $user['name'] }}</h2>
            <p class="pf-sub">{{ $user['subtitle'] }}</p>
            <x-docmon.status-badge :status="$profile['status']" />

            <dl class="dm-facts">
                <div><dt>Student ID</dt><dd>{{ $profile['student_id'] }}</dd></div>
                <div><dt>Member since</dt><dd>{{ $profile['member_since'] }}</dd></div>
                <div><dt>Tickets submitted</dt><dd>{{ $profile['tickets'] }}</dd></div>
            </dl>
        </section>

        <div class="pf-stack">
            {{-- Personal Information + Notifications are one form, saved by "Save Changes" --}}
            <form method="POST" action="#" class="pf-stack">
                @csrf

                <section class="dm-card dm-panel">
                    <div class="dm-panel-head">
                        <div>
                            <h2 class="dm-panel-title">Personal Information</h2>
                            <p class="dm-panel-sub">Changing your name or ID needs admin re-verification</p>
                        </div>
                        <button type="submit" class="dm-btn dm-btn-primary dm-btn-sm">Save Changes</button>
                    </div>

                    <div class="pf-grid">
                        <div class="dm-field">
                            <label for="pf-first">First Name</label>
                            <input id="pf-first" name="first_name" type="text" autocomplete="given-name" value="{{ $profile['first_name'] }}">
                        </div>
                        <div class="dm-field">
                            <label for="pf-last">Last Name</label>
                            <input id="pf-last" name="last_name" type="text" autocomplete="family-name" value="{{ $profile['last_name'] }}">
                        </div>

                        <div class="dm-field">
                            <label for="pf-student-id">Student ID</label>
                            <input id="pf-student-id" name="student_id" type="text" inputmode="numeric" value="{{ $profile['student_id'] }}">
                        </div>
                        <div class="dm-field">
                            <label for="pf-org">Student Organization</label>
                            <input id="pf-org" type="text" value="{{ $profile['organization'] }}" readonly>
                        </div>

                        <div class="dm-field">
                            <label for="pf-position">Position</label>
                            <select id="pf-position" name="position" class="dm-select">
                                @foreach ($positions as $position)
                                    <option @if ($position === $profile['position']) selected @endif>{{ $position }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="dm-field">
                            <label for="pf-email">Email</label>
                            <input id="pf-email" name="email" type="email" autocomplete="email" value="{{ $profile['email'] }}">
                        </div>

                        <div class="dm-field">
                            <label for="pf-phone">Phone Number</label>
                            <input id="pf-phone" name="phone" type="tel" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" title="Enter an 11-digits number starting at 09" autocomplete="tel" value="{{ $profile['phone'] }}">
                        </div>
                        <div class="dm-field">
                            <label for="pf-telegram">Telegram</label>
                            <input id="pf-telegram" name="telegram" type="text" placeholder="e.x. @username" value="{{ $profile['telegram'] }}">
                        </div>
                    </div>
                </section>

                <section class="dm-card dm-panel">
                    <h2 class="dm-panel-title">Notifications</h2>
                    <p class="dm-panel-sub">How DOC-MON updates you about your tickets</p>

                    <div class="pf-notify">
                        @foreach ($notifications as $n)
                            <div class="pf-notify-row">
                                <div>
                                    <p class="pf-notify-label">{{ $n['label'] }}</p>
                                    <p class="pf-notify-note">{{ $n['note'] }}</p>
                                </div>
                                <label class="pf-switch">
                                    <input type="checkbox" value="1" aria-label="{{ $n['label'] }} notifications"
                                           @if ($n['name']) name="{{ $n['name'] }}" @endif
                                           @checked($n['on']) @disabled($n['locked'])>
                                    <span></span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </section>
            </form>

            {{-- Security: separate form so changing the password never saves the other fields --}}
            <form method="POST" action="#">
                @csrf

                <section class="dm-card dm-panel">
                    <h2 class="dm-panel-title">Security</h2>

                    <div class="pf-grid pf-grid-3">
                        <div class="dm-field">
                            <label for="pf-current">Current Password</label>
                            <input id="pf-current" name="current_password" type="password" autocomplete="current-password" placeholder="••••••••">
                        </div>
                        <div class="dm-field">
                            <label for="pf-new">New Password</label>
                            <input id="pf-new" name="password" type="password" autocomplete="new-password" placeholder="e.x. Testpassword#1">
                        </div>
                        <div class="dm-field">
                            <label for="pf-confirm">Confirm Password</label>
                            <input id="pf-confirm" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Re-type new password">
                        </div>
                    </div>

                    <button type="submit" class="dm-btn dm-btn-outline pf-submit">Update Password</button>
                </section>
            </form>
        </div>
    </div>

</x-docmon.dashboard-layout>
