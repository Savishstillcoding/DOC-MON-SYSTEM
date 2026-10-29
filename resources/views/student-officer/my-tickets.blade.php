{{--
    DOC-MON · Student Officer · My Tickets
    The logged-in officer's real tickets (TicketController@index).
    From the controller: $tickets (filtered by the tab), $counts (per tab), $tab (the active tab)
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

<x-docmon.dashboard-layout title="My Tickets" :menu="$menu" active="My Tickets">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">My Tickets</h1>
            <p class="dm-page-sub">All tickets you have submitted.</p>
        </div>
        <div class="dm-header-actions">
            <a href="{{ url('/officer/tickets/new') }}" class="dm-btn dm-btn-primary"><x-docmon.icon name="plus" size="16" /> New Ticket</a>
        </div>
    </header>

    <section class="dm-card dm-panel">
        <div class="dm-panel-head">
            <div>
                <h2 class="dm-panel-title">{{ $tab === 'All' ? 'All Tickets' : $tab }}</h2>
                <p class="dm-panel-sub">Newest first</p>
            </div>
        </div>

        <nav class="dm-tabs">
            @foreach ($counts as $label => $count)
                <a href="{{ url('/officer/tickets') . ($label === 'All' ? '' : '?status=' . urlencode($label)) }}"
                   class="dm-tab {{ $label === $tab ? 'is-active' : '' }}">{{ $label }} ({{ $count }})</a>
            @endforeach
        </nav>

        @if ($tickets->isEmpty())
            <p class="nt-note">
                {{ $tab === 'All' ? 'No tickets yet.' : 'No tickets with this status.' }}
                <a href="{{ url('/officer/tickets/new') }}">Submit a new ticket</a>
            </p>
        @else
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
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $t)
                            @php $signatory = $t->currentSignatory(); @endphp
                            <tr>
                                <td class="dm-strong">{{ $t->code }}</td>
                                <td>
                                    <p class="dm-cell-main">{{ $t->title }}</p>
                                    <p class="dm-cell-sub">Ref No. {{ $t->ref_no }}</p>
                                </td>
                                <td>{{ $t->typeLabel() }}</td>
                                <td>
                                    @if ($signatory)
                                        <p class="dm-cell-main">{{ $signatory->displayName() }}</p>
                                        <p class="dm-cell-sub">{{ $signatory->pivot->title }}</p>
                                    @else
                                        <p class="dm-cell-sub">Done</p>
                                    @endif
                                </td>
                                <td><x-docmon.status-badge :status="$t->status()" /></td>
                                <td>
                                    <p class="dm-cell-main dm-cell-regular">{{ $t->updated_at->format('M j') }}</p>
                                    <p class="dm-cell-sub">{{ $t->updated_at->format('g:i A') }}</p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <footer class="dm-table-foot">
                <span>Showing {{ $tickets->count() }} {{ Str::plural('ticket', $tickets->count()) }}</span>
            </footer>
        @endif
    </section>

</x-docmon.dashboard-layout>
