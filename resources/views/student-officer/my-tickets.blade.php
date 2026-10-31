{{--
    DOC-MON · Student Officer · My Tickets
    The logged-in officer's real tickets (TicketController@index).
    From the controller: $tickets (filtered by the tab), $counts (per tab), $tab (the active tab)
    Under each ticket, a fold-out list of its documents: status, where each paper is,
    the signatory's remarks, and a Resubmit button for returned papers.
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

    @error('resubmit') <p class="dm-form-alert" role="alert">{{ $message }}</p> @enderror

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
                            <tr class="mt-docs-row">
                                <td colspan="6">
                                    @php $route = $t->organization->signatories; @endphp
                                    <details class="mt-docs" @if ($t->status() === 'Needs Revision') open @endif>
                                        <summary>{{ Str::plural('Document', $t->documents->count()) }} ({{ $t->documents->count() }})</summary>
                                        <ul>
                                            @foreach ($t->documents as $doc)
                                                @php $holder = $route->firstWhere('pivot.step', $doc->current_step); @endphp
                                                <li>
                                                    <div class="mt-doc-main">
                                                        <p class="dm-cell-main">{{ $doc->type }}: {{ $doc->title }}</p>
                                                        <p class="dm-cell-sub">
                                                            @if ($doc->ref_no) Ref No. {{ $doc->ref_no }} · @endif
                                                            @switch($doc->status)
                                                                @case(App\Models\Document::PENDING)
                                                                    With {{ $holder?->displayName() }} ({{ $holder?->pivot->title }}), step {{ $doc->current_step }} of {{ $route->count() }}
                                                                    @break
                                                                @case(App\Models\Document::RETURNED)
                                                                    Returned by {{ $holder?->displayName() }} ({{ $holder?->pivot->title }})
                                                                    @break
                                                                @case(App\Models\Document::REJECTED)
                                                                    Rejected by {{ $holder?->displayName() }} ({{ $holder?->pivot->title }}). File a new ticket for this paper.
                                                                    @break
                                                                @default
                                                                    Fully signed. You can collect the paper.
                                                            @endswitch
                                                        </p>
                                                        @if (in_array($doc->status, [App\Models\Document::RETURNED, App\Models\Document::REJECTED]) && $doc->latestAction?->remarks)
                                                            <p class="mt-remarks">"{{ $doc->latestAction->remarks }}"</p>
                                                        @endif
                                                    </div>
                                                    <div class="mt-doc-side">
                                                        <x-docmon.status-badge :status="$doc->statusLabel()" />
                                                        @if ($doc->status === App\Models\Document::RETURNED)
                                                            <form method="POST" action="{{ url('/officer/documents/' . $doc->id . '/resubmit') }}">
                                                                @csrf
                                                                <button type="submit" class="dm-btn dm-btn-primary dm-btn-xs">Resubmit</button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
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
