{{--
    DOC-MON · Student Officer · Profile
    From ProfileController@edit: $account (the logged-in user), $positions, $ticketCount
      Profile form  → PUT /officer/profile:          position, email, phone
      Password form → PUT /officer/profile/password: current_password, password, password_confirmation
    Name and Student ID are shown but not sent: the admin verified them against the uploaded ID.
    Telegram and the notification switches return when notifications are built.
--}}
@php
    $menu = [
        ['label' => 'Dashboard',   'icon' => 'house',     'href' => url('/officer/dashboard')],
        ['label' => 'New Ticket',  'icon' => 'file-plus', 'href' => url('/officer/tickets/new')],
        ['label' => 'My Tickets',  'icon' => 'file-text', 'href' => url('/officer/tickets')],
        ['label' => 'Signatories', 'icon' => 'users',     'href' => url('/officer/signatories')],
        ['label' => 'Profile',     'icon' => 'user',      'href' => url('/officer/profile')],
    ];
@endphp

<x-docmon.dashboard-layout title="Profile" :menu="$menu" active="Profile">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Profile</h1>
            <p class="dm-page-sub">Your account details and password.</p>
        </div>
    </header>

    <div class="pf-columns">
        {{-- Profile summary --}}
        <section class="dm-card pf-card">
            <span class="dm-avatar pf-avatar">{{ $account->initials() }}</span>
            <h2 class="pf-name">{{ $account->displayName() }}</h2>
            <p class="pf-sub">{{ $account->subtitle() }}</p>
            <x-docmon.status-badge :status="ucfirst($account->status)" />

            <dl class="dm-facts">
                <div><dt>Student ID</dt><dd>{{ $account->login_id }}</dd></div>
                <div><dt>Member since</dt><dd>{{ $account->created_at->format('M Y') }}</dd></div>
                <div><dt>Tickets submitted</dt><dd>{{ $ticketCount }}</dd></div>
            </dl>
        </section>

        <div class="pf-stack">
            <form method="POST" action="{{ url('/officer/profile') }}" class="pf-stack">
                @csrf
                @method('PUT')

                <section class="dm-card dm-panel">
                    <div class="dm-panel-head">
                        <div>
                            <h2 class="dm-panel-title">Personal Information</h2>
                            <p class="dm-panel-sub">Name and Student ID were verified by the admin. Ask the admin to change them.</p>
                        </div>
                        <button type="submit" class="dm-btn dm-btn-primary dm-btn-sm">Save Changes</button>
                    </div>

                    <div class="pf-grid">
                        <div class="dm-field">
                            <label for="pf-first">First Name</label>
                            <input id="pf-first" type="text" value="{{ $account->first_name }}" readonly>
                        </div>
                        <div class="dm-field">
                            <label for="pf-last">Last Name</label>
                            <input id="pf-last" type="text" value="{{ $account->last_name }}" readonly>
                        </div>

                        <div class="dm-field">
                            <label for="pf-student-id">Student ID</label>
                            <input id="pf-student-id" type="text" value="{{ $account->login_id }}" readonly>
                        </div>
                        <div class="dm-field">
                            <label for="pf-org">Student Organization</label>
                            <input id="pf-org" type="text" value="{{ $account->organization?->code }}" readonly>
                        </div>

                        <div class="dm-field">
                            <label for="pf-position">Position</label>
                            <select id="pf-position" name="position" class="dm-select">
                                @foreach ($positions as $position)
                                    <option @selected($position === old('position', $account->position))>{{ $position }}</option>
                                @endforeach
                            </select>
                            @error('position') <p class="dm-field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="dm-field">
                            <label for="pf-email">Email</label>
                            <input id="pf-email" name="email" type="email" maxlength="255" autocomplete="email" value="{{ old('email', $account->email) }}" required>
                            @error('email') <p class="dm-field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="dm-field">
                            <label for="pf-phone">Phone Number</label>
                            <input id="pf-phone" name="phone" type="tel" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" title="Enter an 11-digit number starting with 09" autocomplete="tel" value="{{ old('phone', $account->phone) }}">
                            @error('phone') <p class="dm-field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>
            </form>

            {{-- Separate form so changing the password never saves the other fields.
                 Its errors are in their own bag ("password"), so they show only here. --}}
            <form method="POST" action="{{ url('/officer/profile/password') }}">
                @csrf
                @method('PUT')

                <section class="dm-card dm-panel">
                    <h2 class="dm-panel-title">Security</h2>

                    <div class="pf-grid pf-grid-3">
                        <div class="dm-field">
                            <label for="pf-current">Current Password</label>
                            <input id="pf-current" name="current_password" type="password" autocomplete="current-password" placeholder="••••••••" required>
                            @error('current_password', 'password') <p class="dm-field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="dm-field">
                            <label for="pf-new">New Password</label>
                            <input id="pf-new" name="password" type="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" required>
                            @error('password', 'password') <p class="dm-field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="dm-field">
                            <label for="pf-confirm">Confirm Password</label>
                            <input id="pf-confirm" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Re-type new password" required>
                        </div>
                    </div>

                    <button type="submit" class="dm-btn dm-btn-outline pf-submit">Update Password</button>
                </section>
            </form>
        </div>
    </div>

</x-docmon.dashboard-layout>
