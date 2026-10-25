{{--
    DOC-MON · Student Officer · Signatories
    Static placeholder data for now (not connected to the database yet).
    Shows the organization's approval route, each signatory's availability,
    and how many of this officer's tickets are with each one.
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

    // In approval order. pending/approved/returned = this officer's tickets per signatory.
    $signatories = [
        ['step' => 1, 'badge' => 'AD', 'name' => 'Dela Cruz, Ana', 'role' => 'Adviser',       'status' => 'In a Meeting',  'back_at' => '2:00 PM',  'with_them' => 3, 'pending' => 3, 'approved' => 18, 'returned' => 2],
        ['step' => 2, 'badge' => 'PR', 'name' => 'Reyes, Paolo',   'role' => 'SSC President', 'status' => 'Available',     'back_at' => null,       'with_them' => 1, 'pending' => 1, 'approved' => 16, 'returned' => 1],
        ['step' => 3, 'badge' => 'RG', 'name' => 'Garcia, Ramon',  'role' => 'ODSOA',         'status' => 'Out of Office', 'back_at' => 'Tomorrow', 'with_them' => 1, 'pending' => 1, 'approved' => 15, 'returned' => 0],
        ['step' => 4, 'badge' => 'LS', 'name' => 'Santos, Liza',   'role' => 'VPSD',          'status' => 'Available',     'back_at' => null,       'with_them' => 1, 'pending' => 1, 'approved' => 14, 'returned' => 0],
    ];
@endphp

<x-docmon.dashboard-layout title="Signatories" :user="$user" :menu="$menu" active="Signatories">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Signatories</h1>
            <p class="dm-page-sub">DIGITS approval route and who is available right now.</p>
        </div>
        <div class="dm-header-actions">
            <button type="button" class="dm-icon-btn" aria-label="Notifications"><x-docmon.icon name="bell" size="18" /></button>
        </div>
    </header>

    {{-- Approval order, built from the list so it always matches the cards below --}}
    <p class="sl-banner">
        <x-docmon.icon name="list" size="20" />
        <span>
            Tickets move in this order:
            @foreach ($signatories as $s)
                {{ $s['role'] }}@if (! $loop->last) <x-docmon.icon name="arrow-right" size="16" class="sl-arrow" />@else.@endif
            @endforeach
            The {{ last($signatories)['role'] }} has the final say.
        </span>
    </p>

    <section class="sl-grid">
        @foreach ($signatories as $s)
            <article class="dm-card sl-card">
                <div class="sl-card-top">
                    <span class="sl-step">STEP {{ $s['step'] }}</span>
                    <x-docmon.availability :status="$s['status']" />
                </div>

                <span class="dm-avatar sl-avatar">{{ $s['badge'] }}</span>
                <h2 class="sl-name">{{ $s['name'] }}</h2>
                <p class="sl-role">{{ $s['role'] }}</p>

                <dl class="dm-facts">
                    <div><dt>Back at</dt><dd>{{ $s['back_at'] ?? '—' }}</dd></div>
                    <div><dt>Your tickets with them</dt><dd>{{ $s['with_them'] }}</dd></div>
                </dl>
            </article>
        @endforeach
    </section>

    <section class="dm-card dm-panel">
        <h2 class="dm-panel-title">Tickets per Signatory</h2>
        <p class="dm-panel-sub">Where your tickets are right now</p>

        <div class="dm-table-wrap sl-table">
            <table class="dm-table">
                <thead>
                    <tr>
                        <th>Signatory</th>
                        <th class="sl-num">Pending</th>
                        <th class="sl-num">Approved</th>
                        <th class="sl-num">Returned</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($signatories as $s)
                        <tr>
                            <td>
                                <p class="dm-cell-main">{{ $s['name'] }}</p>
                                <p class="dm-cell-sub">Step {{ $s['step'] }} · {{ $s['role'] }}</p>
                            </td>
                            <td class="sl-num">{{ $s['pending'] }}</td>
                            <td class="sl-num">{{ $s['approved'] }}</td>
                            <td class="sl-num">{{ $s['returned'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

</x-docmon.dashboard-layout>
