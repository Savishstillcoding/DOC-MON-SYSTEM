{{--
    DOC-MON · Student Officer · My Tickets
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

    $tabs = ['All (24)', 'Pending (6)', 'Approved (14)', 'Needs Revision (3)', 'Rejected (1)'];

    $tickets = [
        ['code' => 'DM-2026-00130', 'title' => 'Sem-End Report – 1st Semester',          'ref' => '2026-10-003', 'type' => 'Single',     'signatory' => 'Dela Cruz, Ana', 'role' => 'Adviser',       'status' => 'Pending',        'date' => 'Oct 2',  'time' => '8:47 AM'],
        ['code' => 'DM-2026-00128', 'title' => 'Narrative Reports – Sept Activities',    'ref' => '2026-10-001', 'type' => 'Batch (4)', 'signatory' => 'Dela Cruz, Ana', 'role' => 'Adviser',       'status' => 'Pending',        'date' => 'Oct 1',  'time' => '10:30 AM'],
        ['code' => 'DM-2026-00126', 'title' => 'Resolution – Venue Reservation',        'ref' => '2026-09-011', 'type' => 'Single',     'signatory' => 'Reyes, Paolo',   'role' => 'SSC President', 'status' => 'Pending',        'date' => 'Sep 29', 'time' => '2:02 PM'],
        ['code' => 'DM-2026-00123', 'title' => 'Activity Proposal – DIGITS Week',  'ref' => '2026-09-008', 'type' => 'Single',     'signatory' => 'Dela Cruz, Ana', 'role' => 'Adviser',       'status' => 'Pending',        'date' => 'Sep 27', 'time' => '12:40 PM'],
        ['code' => 'DM-2026-00119', 'title' => 'Resolution + Budgetary – Tech Summit',     'ref' => '2026-09-005', 'type' => 'Batch (3)',  'signatory' => 'Reyes, Paolo',   'role' => 'SSC President', 'status' => 'Needs Revision', 'date' => 'Sep 25', 'time' => '4:12 PM'],
        ['code' => 'DM-2026-00114', 'title' => 'Resolution – Election Guidelines',        'ref' => '2026-09-002', 'type' => 'Single',     'signatory' => 'Santos, Liza',   'role' => 'VPSD (final)',  'status' => 'Approved',       'date' => 'Sep 19', 'time' => '9:30 AM'],
        ['code' => 'DM-2026-00110', 'title' => 'Activity Proposal – Outreach Program',          'ref' => '2026-08-031', 'type' => 'Single',     'signatory' => 'Garcia, Ramon',  'role' => 'ODSOA',         'status' => 'Rejected',       'date' => 'Sep 12', 'time' => '2:05 PM'],
        ['code' => 'DM-2026-00105', 'title' => 'Resolution – Membership Fee 2026',          'ref' => '2026-08-024', 'type' => 'Single',     'signatory' => 'Santos, Liza',   'role' => 'VPSD (final)',  'status' => 'Approved',       'date' => 'Sep 5',  'time' => '11:10 AM'],
    ];
@endphp

<x-docmon.dashboard-layout title="My Tickets" :user="$user" :menu="$menu" active="My Tickets">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">My Tickets</h1>
            <p class="dm-page-sub">All tickets you have submitted this semester.</p>
        </div>
        <div class="dm-header-actions">
            <label class="dm-search">
                <x-docmon.icon name="search" size="18" />
                <input type="search" placeholder="Search ticket or Ref No.">
            </label>
            <a href="{{ url('/officer/tickets/new') }}" class="dm-btn dm-btn-primary"><x-docmon.icon name="plus" size="16" /> New Ticket</a>
        </div>
    </header>

    <section class="dm-card dm-panel">
        <div class="dm-panel-head">
            <div>
                <h2 class="dm-panel-title">All Tickets</h2>
                <p class="dm-panel-sub">24 tickets this semester</p>
            </div>
            <select class="dm-select dm-select-sm" aria-label="Sort tickets">
                <option>Sort: Newest</option>
                <option>Sort: Oldest</option>
            </select>
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
                        <th>Ticket</th>
                        <th>Document</th>
                        <th>Type</th>
                        <th>Current Signatory</th>
                        <th>Status</th>
                        <th>Last Update</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tickets as $t)
                        <tr>
                            <td class="dm-strong">{{ $t['code'] }}</td>
                            <td>
                                <p class="dm-cell-main">{{ $t['title'] }}</p>
                                <p class="dm-cell-sub">Ref No. {{ $t['ref'] }}</p>
                            </td>
                            <td>{{ $t['type'] }}</td>
                            <td>
                                <p class="dm-cell-main">{{ $t['signatory'] }}</p>
                                <p class="dm-cell-sub">{{ $t['role'] }}</p>
                            </td>
                            <td><x-docmon.status-badge :status="$t['status']" /></td>
                            <td>
                                <p class="dm-cell-main dm-cell-regular">{{ $t['date'] }}</p>
                                <p class="dm-cell-sub">{{ $t['time'] }}</p>
                            </td>
                            <td>
                                @if ($t['status'] === 'Needs Revision')
                                    <a href="#" class="dm-btn dm-btn-primary dm-btn-xs">Resubmit</a>
                                @else
                                    <a href="#" class="dm-btn dm-btn-outline dm-btn-xs">View</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <footer class="dm-table-foot">
            <span>Showing 1–8 of 24</span>
            <div class="dm-pager">
                <a href="#" class="dm-btn dm-btn-outline dm-btn-xs">‹ Prev</a>
                <a href="#" class="dm-btn dm-btn-outline dm-btn-xs">Next ›</a>
            </div>
        </footer>
    </section>

</x-docmon.dashboard-layout>
