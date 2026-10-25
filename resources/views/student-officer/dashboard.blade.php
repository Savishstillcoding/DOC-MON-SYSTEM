{{--
    DOC-MON · Student Officer · Dashboard
    Static placeholder data for now (not connected to the database yet).
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

    $stats = [
        ['label' => 'Total Tickets',  'value' => 24, 'note' => 'This semester'],
        ['label' => 'Pending',        'value' => 6,  'note' => 'Waiting for signatures'],
        ['label' => 'Approved',       'value' => 14, 'note' => 'Fully signed'],
        ['label' => 'Needs Revision', 'value' => 3,  'note' => 'Returned to you'],
        ['label' => 'Rejected',       'value' => 1,  'note' => 'Not approved'],
    ];

    $tickets = [
        ['code' => 'DM-2026-00123', 'ref' => '2026-09-008', 'status' => 'Pending',
         'title' => 'Activity Proposal – DIGITS Week', 'type' => 'Single',
         'officer' => 'Labastida, MJ Bryan', 'signatory' => 'Dela Cruz, Ana (Adviser)',
         'availability' => 'In a Meeting', 'availability_note' => 'Back at 2:00 PM',
         'submitted' => 'Sep 27, 2026', 'updated' => 'Sep 27, 12:40'],
        ['code' => 'DM-2026-00119', 'ref' => '2026-09-005', 'status' => 'Needs Revision',
         'title' => 'Resolution + Budgetary – Tech Summit', 'type' => 'Batch · 3 documents',
         'officer' => 'Labastida, MJ Bryan', 'signatory' => 'Reyes, Paolo (SSC President)',
         'availability' => 'Available', 'availability_note' => null,
         'submitted' => 'Sep 22, 2026', 'updated' => 'Sep 25, 16:12'],
        ['code' => 'DM-2026-00114', 'ref' => '2026-09-002', 'status' => 'Approved',
         'title' => 'Resolution – Election Guidelines', 'type' => 'Single',
         'officer' => 'Labastida, MJ Bryan', 'signatory' => 'Santos, Liza (VPSD)',
         'availability' => 'Available', 'availability_note' => null,
         'submitted' => 'Sep 15, 2026', 'updated' => 'Sep 19, 09:30'],
        ['code' => 'DM-2026-00110', 'ref' => '2026-08-031', 'status' => 'Rejected',
         'title' => 'Activity Proposal – Outreach Program', 'type' => 'Single',
         'officer' => 'Labastida, MJ Bryan', 'signatory' => 'Garcia, Ramon (ODSOA)',
         'availability' => 'Out of Office', 'availability_note' => 'Back tomorrow',
         'submitted' => 'Sep 10, 2026', 'updated' => 'Sep 12, 14:05'],
    ];

    $signatories = [
        ['badge' => 'AD', 'name' => 'Dela Cruz, Ana', 'role' => 'Step 1 · Adviser',       'status' => 'Meeting', 'note' => '2 PM'],
        ['badge' => 'PR', 'name' => 'Reyes, Paolo',   'role' => 'Step 2 · SSC President', 'status' => 'Available',    'note' => null],
        ['badge' => 'RG', 'name' => 'Garcia, Ramon',  'role' => 'Step 3 · ODSOA',         'status' => 'Out of Office','note' => null],
        ['badge' => 'LS', 'name' => 'Santos, Liza',   'role' => 'Step 4 · VPSD',          'status' => 'Available',    'note' => null],
    ];

    $activity = [
        ['color' => 'green', 'text' => 'DM-00114 approved by VPSD',                  'time' => '2 hours ago'],
        ['color' => 'blue',  'text' => 'DM-00119 returned for revision by SSC President', 'time' => 'Yesterday, 4:12 PM'],
        ['color' => 'dark',  'text' => 'DM-00123 submitted to Adviser',              'time' => 'Sep 27, 12:40 PM'],
    ];

    $tabs = ['All', 'Pending', 'Approved', 'Needs Revision', 'Rejected'];
@endphp

<x-docmon.dashboard-layout title="Dashboard" :user="$user" :menu="$menu" active="Dashboard">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Dashboard</h1>
            <p class="dm-page-sub">DIGITS · Student Officer</p>
        </div>
        <div class="dm-header-actions">
            <label class="dm-search">
                <x-docmon.icon name="search" size="18" />
                <input type="search" placeholder="Search ticket or Ref No.">
            </label>
            <button type="button" class="dm-icon-btn" aria-label="Notifications"><x-docmon.icon name="bell" size="18" /></button>
            <a href="{{ url('/officer/tickets/new') }}" class="dm-btn dm-btn-primary"><x-docmon.icon name="plus" size="16" /> New Ticket</a>
        </div>
    </header>

    {{-- Welcome card --}}
    <section class="dm-card so-hero">
        <div>
            <p class="so-hero-date">FRIDAY, OCTOBER 16, 2026</p>
            <h2 class="so-hero-title">Good morning, Secretary!</h2>
            <p class="so-hero-text">3 tickets are waiting on signatories · 1 needs your revision</p>
        </div>
        <img class="so-hero-icons" src="{{ asset('images/dashboard-hero-icons.png') }}" alt="">
    </section>

    {{-- Numbers --}}
    <section class="dm-stat-grid dm-stat-grid-5">
        @foreach ($stats as $stat)
            <x-docmon.stat-card :label="$stat['label']" :value="$stat['value']" :note="$stat['note']" />
        @endforeach
    </section>

    <div class="dm-columns">
        {{-- My Tickets --}}
        <section class="dm-card dm-panel">
            <div class="dm-panel-head">
                <div>
                    <h2 class="dm-panel-title">My Tickets</h2>
                    <p class="dm-panel-sub">Track each document from submission to approval</p>
                </div>
                <a href="{{ url('/officer/tickets') }}" class="dm-btn dm-btn-outline dm-btn-sm">View all</a>
            </div>

            <nav class="dm-tabs">
                @foreach ($tabs as $tab)
                    <a href="#" class="dm-tab {{ $loop->first ? 'is-active' : '' }}">{{ $tab }}</a>
                @endforeach
            </nav>

            <div class="so-ticket-grid">
                @foreach ($tickets as $ticket)
                    <x-docmon.ticket-card :ticket="$ticket" />
                @endforeach
            </div>
        </section>

        <div class="dm-side">
            {{-- Signatory Availability --}}
            <section class="dm-card dm-panel">
                <h2 class="dm-panel-title">Signatory Availability</h2>
                <p class="dm-panel-sub">DIGITS approval route · live status</p>

                <div class="dm-route">
                    @foreach ($signatories as $s)
                        <x-docmon.signatory-row :badge="$s['badge']" :name="$s['name']" :role="$s['role']" :status="$s['status']" :note="$s['note']" />
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
