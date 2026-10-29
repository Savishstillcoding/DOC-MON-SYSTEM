{{--
    DOC-MON · Signatory · Dashboard
    The stats and review table are still placeholder data.
    The availability form is real: PUT /signatory/availability (availability, back_at),
    and shows the logged-in signatory's current status.
--}}
@php
    $menu = [
        ['label' => 'Dashboard',    'icon' => 'house', 'href' => url('/signatory/dashboard')],
        ['label' => 'For Review',   'icon' => 'inbox', 'href' => '#'],
        ['label' => 'History',      'icon' => 'list',  'href' => '#'],
        ['label' => 'Availability', 'icon' => 'clock', 'href' => '#'],
        ['label' => 'Profile',      'icon' => 'user',  'href' => '#'],
    ];

    $stats = [
        ['label' => 'For Review',        'value' => 5,  'note' => 'Waiting for your action'],
        ['label' => 'Signed This Week',  'value' => 12, 'note' => 'Approved documents'],
        ['label' => 'Returned',          'value' => 2,  'note' => 'Sent back for revision'],
        ['label' => 'Rejected',          'value' => 1,  'note' => 'This semester'],
    ];

    $tabs = ['All (5)', 'Single (3)', 'Batch (2)'];

    $reviews = [
        ['code' => 'DM-2026-00123', 'ref' => '2026-09-008', 'title' => 'Activity Proposal',            'meta' => 'Single · Labastida, MJ',     'date' => 'Sep 27', 'time' => '12:40 PM'],
        ['code' => 'DM-2026-00125', 'ref' => '2026-09-010', 'title' => 'Resolution + Budgetary – Acquaintance Party', 'meta' => 'Batch (3) · Labastida, MJ',  'date' => 'Sep 28', 'time' => '9:15 AM'],
        ['code' => 'DM-2026-00126', 'ref' => '2026-09-011', 'title' => 'Resolution – Venue Reservation',    'meta' => 'Single · Labastida, MJ',     'date' => 'Sep 29', 'time' => '2:02 PM'],
        ['code' => 'DM-2026-00128', 'ref' => '2026-10-001', 'title' => 'Narrative Reports – Sept Activities','meta' => 'Batch (4) · Labastida, MJ', 'date' => 'Oct 1',  'time' => '10:30 AM'],
        ['code' => 'DM-2026-00130', 'ref' => '2026-10-003', 'title' => 'Sem-End Report – 1st Semester',      'meta' => 'Single · Labastida, MJ',     'date' => 'Oct 2',  'time' => '8:47 AM'],
    ];

    $availabilityOptions = [
        ['label' => 'Available',     'variant' => 'available'],
        ['label' => 'In a Meeting',  'variant' => 'busy'],
        ['label' => 'In Class',      'variant' => 'busy'],
        ['label' => 'Out of Office', 'variant' => 'away'],
    ];
    $account = auth()->user();
    $currentAvailability = old('availability', $account->availability ?? 'Available');

    $recent = [
        ['color' => 'green', 'text' => 'DM-00114 · Resolution – Election Guidelines',        'time' => 'Approved · 2 hours ago'],
        ['color' => 'blue',  'text' => 'DM-00119 · Resolution + Budgetary – Tech Summit',     'time' => 'Returned for revision · Yesterday'],
        ['color' => 'red',   'text' => 'DM-00110 · Activity Proposal – Outreach Program',          'time' => 'Rejected · Sep 12'],
    ];
@endphp

<x-docmon.dashboard-layout title="Dashboard" :menu="$menu" active="Dashboard">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Good day, {{ $account->position ?: $account->first_name }}!</h1>
            <p class="dm-page-sub">You have 5 documents waiting for your signature.</p>
        </div>
        <div class="dm-header-actions">
            @php $pillVariant = collect($availabilityOptions)->firstWhere('label', $account->availability ?? 'Available')['variant'] ?? 'available'; @endphp
            <span class="sg-status-pill is-{{ $pillVariant }}">
                <span class="dm-dot"></span>{{ $account->availability ?? 'Available' }}@if ($account->back_at) · {{ $account->back_at }}@endif
                <x-docmon.icon name="chevron-down" size="14" />
            </span>
            <label class="dm-search">
                <x-docmon.icon name="search" size="18" />
                <input type="search" placeholder="Search ticket or Ref No.">
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
        {{-- Documents for Review --}}
        <section class="dm-card dm-panel dm-align-start">
            <div class="dm-panel-head">
                <div>
                    <h2 class="dm-panel-title">Documents for Review</h2>
                    <p class="dm-panel-sub">Oldest submissions first</p>
                </div>
                <a href="#" class="dm-btn dm-btn-outline dm-btn-sm">View history</a>
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
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reviews as $r)
                            <tr>
                                <td>
                                    <p class="dm-cell-main">{{ $r['code'] }}</p>
                                    <p class="dm-cell-sub">{{ $r['ref'] }}</p>
                                </td>
                                <td>
                                    <p class="dm-cell-main">{{ $r['title'] }}</p>
                                    <p class="dm-cell-sub">{{ $r['meta'] }}</p>
                                </td>
                                <td>
                                    <p class="dm-cell-main dm-cell-regular">{{ $r['date'] }}</p>
                                    <p class="dm-cell-sub">{{ $r['time'] }}</p>
                                </td>
                                <td>
                                    <div class="dm-actions">
                                        <button type="button" class="dm-btn dm-btn-success dm-btn-xs">Approve</button>
                                        <button type="button" class="dm-btn dm-btn-outline dm-btn-xs">Revise</button>
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
            {{-- My Availability --}}
            <section class="dm-card dm-panel">
                <h2 class="dm-panel-title">My Availability</h2>
                <p class="dm-panel-sub">Officers see this on their tickets</p>

                <form method="POST" action="{{ url('/signatory/availability') }}" class="sg-availability-form">
                    @csrf
                    @method('PUT')
                    @foreach ($availabilityOptions as $option)
                        <label class="sg-option">
                            <input type="radio" name="availability" value="{{ $option['label'] }}"
                                   @if ($option['label'] === $currentAvailability) checked @endif>
                            <span class="dm-availability dm-availability-{{ $option['variant'] }}"><span class="dm-dot"></span></span>
                            {{ $option['label'] }}
                        </label>
                    @endforeach

                    <div class="dm-field">
                        <label for="sg-back-at">Back at (optional)</label>
                        <div class="sg-time">
                            <x-docmon.icon name="clock" size="18" />
                            <input id="sg-back-at" name="back_at" type="text" maxlength="30" placeholder="e.x. 2:00 PM" value="{{ old('back_at', $account->back_at) }}">
                        </div>
                        @error('back_at') <p class="dm-field-error">{{ $message }}</p> @enderror
                    </div>
                    @error('availability') <p class="dm-field-error">{{ $message }}</p> @enderror

                    <button type="submit" class="dm-btn dm-btn-primary dm-btn-block">Update Status</button>
                </form>
            </section>

            {{-- Recently Signed --}}
            <section class="dm-card dm-panel">
                <h2 class="dm-panel-title">Recently Signed</h2>
                <ul class="dm-activity">
                    @foreach ($recent as $item)
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
