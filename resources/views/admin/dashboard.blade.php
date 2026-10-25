{{--
    DOC-MON · Admin · Dashboard
    Static placeholder data for now (not connected to the database yet).
--}}
@php
    $user = ['name' => 'System Admin', 'initials' => 'SA', 'subtitle' => 'Administrator'];

    $menu = [
        ['label' => 'Dashboard',         'icon' => 'house',        'href' => url('/admin/dashboard')],
        ['label' => 'User Verification', 'icon' => 'shield-check', 'href' => '#'],
        ['label' => 'Users',             'icon' => 'users',        'href' => '#'],
        ['label' => 'Organizations',     'icon' => 'building',     'href' => '#'],
        ['label' => 'Signatory Lists',   'icon' => 'list',         'href' => '#'],
        ['label' => 'Activity Logs',     'icon' => 'clock',        'href' => '#'],
    ];

    $stats = [
        ['label' => 'Pending Verification', 'value' => 8,  'note' => 'Accounts waiting for review'],
        ['label' => 'Verified Users',       'value' => 42, 'note' => 'Officers and signatories'],
        ['label' => 'Organizations',        'value' => 1,  'note' => 'DIGITS (pilot)'],
        ['label' => 'Active Tickets',       'value' => 31, 'note' => 'DIGITS tickets in progress'],
    ];

    $tabs = ['All (8)', 'Student Officers (5)', 'Signatories (3)'];

    $pending = [
        ['name' => 'Labastida, MJ Bryan', 'email' => 'mj.labastida@lnu.edu.ph',  'role' => 'Student Officer', 'detail' => 'DIGITS · Secretary',        'id' => '2201234'],
        ['name' => 'Dela Cruz, Ana',      'email' => 'ana.delacruz@lnu.edu.ph',  'role' => 'Signatory',       'detail' => 'DIGITS · Adviser',          'id' => 'EMP-0451'],
        ['name' => 'Reyes, Paolo',        'email' => 'paolo.reyes@lnu.edu.ph',   'role' => 'Signatory',       'detail' => 'SSC President',             'id' => '2105678'],
        ['name' => 'Santos, Jessa',       'email' => 'jessa.santos@lnu.edu.ph',  'role' => 'Student Officer', 'detail' => 'DIGITS · Deputy Secretary', 'id' => '2209876'],
        ['name' => 'Garcia, Ramon',       'email' => 'ramon.garcia@lnu.edu.ph',  'role' => 'Signatory',       'detail' => 'ODSOA',                     'id' => 'EMP-0122'],
    ];

    $signatories = [
        ['badge' => '1', 'name' => 'Dela Cruz, Ana', 'role' => 'Step 1 · Adviser'],
        ['badge' => '2', 'name' => 'Reyes, Paolo',   'role' => 'Step 2 · SSC President'],
        ['badge' => '3', 'name' => 'Garcia, Ramon',  'role' => 'Step 3 · ODSOA'],
        ['badge' => '4', 'name' => 'Santos, Liza',   'role' => 'Step 4 · VPSD (final say)'],
    ];

    $activity = [
        ['color' => 'green', 'text' => 'Approved Santos, Liza (Signatory)',       'time' => '1 hour ago'],
        ['color' => 'red',   'text' => 'Rejected suspicious account “Juan Test”', 'time' => 'Today, 9:02 AM'],
        ['color' => 'dark',  'text' => 'Added ODSOA to DIGITS signatory list',    'time' => 'Yesterday, 3:40 PM'],
    ];
@endphp

<x-docmon.dashboard-layout title="Admin Dashboard" :user="$user" :menu="$menu" active="Dashboard">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Admin Dashboard</h1>
            <p class="dm-page-sub">Verify accounts so only real officers and signatories can use DOC-MON.</p>
        </div>
        <div class="dm-header-actions">
            <label class="dm-search">
                <x-docmon.icon name="search" size="18" />
                <input type="search" placeholder="Search users or ID No.">
            </label>
            <button type="button" class="dm-icon-btn" aria-label="Notifications"><x-docmon.icon name="bell" size="18" /></button>
        </div>
    </header>

    <section class="dm-stat-grid dm-stat-grid-4">
        @foreach ($stats as $stat)
            <x-docmon.stat-card :label="$stat['label']" :value="$stat['value']" :note="$stat['note']" />
        @endforeach
    </section>

    <div class="dm-columns">
        {{-- Pending Verification --}}
        <section class="dm-card dm-panel dm-align-start">
            <div class="dm-panel-head">
                <div>
                    <h2 class="dm-panel-title">Pending Verification</h2>
                    <p class="dm-panel-sub">Check the uploaded school ID before approving</p>
                </div>
                <a href="#" class="dm-btn dm-btn-outline dm-btn-sm">View all users</a>
            </div>

            <nav class="dm-tabs">
                @foreach ($tabs as $tab)
                    <a href="#" class="dm-tab {{ $loop->first ? 'is-active' : '' }}">{{ $tab }}</a>
                @endforeach
            </nav>

            <div class="dm-table-wrap">
                <table class="dm-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>ID No.</th>
                            <th>School ID</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pending as $p)
                            <tr>
                                <td>
                                    <p class="dm-cell-main">{{ $p['name'] }}</p>
                                    <p class="dm-cell-sub">{{ $p['email'] }}</p>
                                </td>
                                <td>
                                    <p class="dm-cell-main">{{ $p['role'] }}</p>
                                    <p class="dm-cell-sub">{{ $p['detail'] }}</p>
                                </td>
                                <td>{{ $p['id'] }}</td>
                                <td><button type="button" class="dm-btn dm-btn-outline dm-btn-xs">View ID</button></td>
                                <td>
                                    <div class="dm-actions">
                                        <button type="button" class="dm-btn dm-btn-success dm-btn-xs">Approve</button>
                                        <button type="button" class="dm-btn dm-btn-danger dm-btn-xs">Reject</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="dm-side">
            {{-- Signatory List --}}
            <section class="dm-card dm-panel">
                <div class="dm-panel-head">
                    <div>
                        <h2 class="dm-panel-title">Signatory List</h2>
                        <p class="dm-panel-sub">DIGITS · approval order</p>
                    </div>
                    <a href="#" class="dm-btn dm-btn-outline dm-btn-sm">Edit</a>
                </div>
                <div class="dm-route dm-route-plain">
                    @foreach ($signatories as $s)
                        <x-docmon.signatory-row :badge="$s['badge']" :name="$s['name']" :role="$s['role']" status="Verified" />
                    @endforeach
                </div>
            </section>

            {{-- Recent Activity --}}
            <section class="dm-card dm-panel">
                <h2 class="dm-panel-title">Recent Activity</h2>
                <ul class="dm-activity">
                    @foreach ($activity as $item)
                        <li class="dm-activity-{{ $item['color'] }}">
                            <p>{{ $item['text'] }}</p>
                            <span>{{ $item['time'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>

</x-docmon.dashboard-layout>
