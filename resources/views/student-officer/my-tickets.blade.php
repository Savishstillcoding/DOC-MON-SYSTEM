{{--
    DOC-MON · Student Officer · My Tickets
    The real tickets of the officer's organization (TicketController@index), shared by its
    officers so the Deputy can carry on for the Secretary (AUD-075); each shows who filed it.
    From the controller: $tickets (filtered by the tab and the search), $counts (per tab), $tab (the active tab),
    $search (the search box, ?q=; also used by the Dashboard's search box)
    Under each ticket, a fold-out list of its documents: status, where each paper is,
    the signatory's remarks, and a Resubmit button for returned papers.
--}}
<x-docmon.officer-layout title="My Tickets" active="My Tickets" :live="true">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">My Tickets</h1>
            <p class="dm-page-sub">All tickets of your organization.</p>
        </div>
        <form method="GET" action="{{ url('/officer/tickets') }}" class="dm-header-actions">
            @if ($tab !== 'All') <input type="hidden" name="status" value="{{ $tab }}"> @endif
            <label class="dm-search">
                <x-docmon.icon name="search" size="18" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Search ticket, Ref No. or paper" aria-label="Search my tickets">
            </label>
            <a href="{{ url('/officer/tickets/new') }}" class="dm-btn dm-btn-primary"><x-docmon.icon name="plus" size="16" /> New Ticket</a>
        </form>
    </header>

    @error('resubmit') <p class="dm-form-alert" role="alert">{{ $message }}</p> @enderror

    <section class="dm-card dm-panel" data-live="tickets">
        <div class="dm-panel-head">
            <div>
                <h2 class="dm-panel-title">{{ $tab === 'All' ? 'All Tickets' : $tab }}</h2>
                <p class="dm-panel-sub">Newest first{{ $search !== '' ? ' · matching “'.$search.'”' : '' }}</p>
            </div>
        </div>

        <nav class="dm-tabs">
            @foreach ($counts as $label => $count)
                <a href="{{ url('/officer/tickets') . '?' . http_build_query(array_filter(['status' => $label === 'All' ? null : $label, 'q' => $search])) }}"
                   class="dm-tab {{ $label === $tab ? 'is-active' : '' }}">{{ $label }} ({{ $count }})</a>
            @endforeach
        </nav>

        @if ($tickets->isEmpty())
            @if ($search !== '')
                <p class="nt-note">No tickets match “{{ $search }}”. <a href="{{ url('/officer/tickets') }}">Clear the search</a></p>
            @else
                <p class="nt-note">
                    {{ $tab === 'All' ? 'No tickets yet.' : 'No tickets with this status.' }}
                    <a href="{{ url('/officer/tickets/new') }}">Submit a new ticket</a>
                </p>
            @endif
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
                            @php $holders = $t->holders(); @endphp
                            <tr>
                                <td class="dm-strong">{{ $t->code }}</td>
                                <td>
                                    <p class="dm-cell-main">{{ $t->title }}</p>
                                    <p class="dm-cell-sub">Ref No. {{ $t->ref_no }} · Filed by {{ $t->officer->displayName() }}</p>
                                </td>
                                <td>{{ $t->typeLabel() }}</td>
                                <td>
                                    @if ($holders->isEmpty())
                                        <p class="dm-cell-sub">Done</p>
                                    @elseif ($holders->count() === 1)
                                        {{-- All unfinished papers in one place: same as always --}}
                                        <p class="dm-cell-main">{{ $holders[0]['signatory']?->displayName() }}</p>
                                        <p class="dm-cell-sub">{{ $holders[0]['signatory']?->pivot->title }}</p>
                                    @else
                                        {{-- A split Batch (AUD-049): every place a paper is, with how many --}}
                                        <div class="mt-holders">
                                            @foreach ($holders as $h)
                                                @php $papers = $h['papers'] . ' ' . Str::plural('paper', $h['papers']); @endphp
                                                @if ($h['with_officer'])
                                                    <p class="dm-cell-main">Back with you</p>
                                                    <p class="dm-cell-sub">{{ $papers }} to revise, then to {{ $h['signatory']?->pivot->title }}</p>
                                                @else
                                                    <p class="dm-cell-main">{{ $h['signatory']?->displayName() }}</p>
                                                    <p class="dm-cell-sub">{{ $h['signatory']?->pivot->title }} · {{ $papers }}</p>
                                                @endif
                                            @endforeach
                                        </div>
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
                                    <details class="mt-docs" data-live-id="{{ $t->id }}" @if ($t->status() === 'Needs Revision') open @endif>
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

</x-docmon.officer-layout>
