{{--
    DOC-MON · Signatory · History (Signatory\HistoryController)
    From the controller: $actions (paginated, newest first), $counts (per tab), $tab, $search.
    One row per action: a Batch shows one row per document the signatory acted on.
--}}
@php
    $badge = ['approved' => 'Approved', 'returned' => 'Needs Revision', 'rejected' => 'Rejected'];
    $tabUrl = fn ($label) => url('/signatory/history') . '?' . http_build_query(array_filter(['tab' => $label === 'All' ? null : $label, 'q' => $search]));
@endphp

<x-docmon.signatory-layout title="History" active="History">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">History</h1>
            <p class="dm-page-sub">Every document you have acted on.</p>
        </div>
        <form method="GET" action="{{ url('/signatory/history') }}" class="dm-header-actions">
            @if ($tab !== 'All') <input type="hidden" name="tab" value="{{ $tab }}"> @endif
            <label class="dm-search">
                <x-docmon.icon name="search" size="18" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Search ticket, Ref No. or officer" aria-label="Search history">
            </label>
        </form>
    </header>

    <section class="dm-card dm-panel">
        <div class="dm-panel-head">
            <div>
                <h2 class="dm-panel-title">Document History</h2>
                <p class="dm-panel-sub">
                    {{ $counts['All'] }} {{ Str::plural('action', $counts['All']) }}
                    @if ($search !== '') matching "{{ $search }}" · <a href="{{ url('/signatory/history') }}">clear search</a> @endif
                </p>
            </div>
        </div>

        <nav class="dm-tabs">
            @foreach ($counts as $label => $count)
                <a href="{{ $tabUrl($label) }}" class="dm-tab {{ $label === $tab ? 'is-active' : '' }}">{{ $label }} ({{ $count }})</a>
            @endforeach
        </nav>

        @if ($actions->isEmpty())
            <p class="nt-note">{{ $search !== '' ? 'Nothing matches your search.' : 'Documents you approve, return, or reject will show here.' }}</p>
        @else
            <div class="dm-table-wrap">
                <table class="dm-table">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Document</th>
                            <th>Officer</th>
                            <th>Your Action</th>
                            <th>Date</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($actions as $a)
                            @php $t = $a->document->ticket; @endphp
                            <tr>
                                <td class="dm-strong">{{ $t->code }}</td>
                                <td>
                                    <p class="dm-cell-main">{{ $a->document->title }}</p>
                                    <p class="dm-cell-sub">{{ $t->documents_count > 1 ? 'Batch (' . $t->documents_count . ')' : 'Single' }} · Step {{ $a->step }}</p>
                                </td>
                                <td>{{ $t->officer->displayName() }}</td>
                                <td><x-docmon.status-badge :status="$badge[$a->action]" /></td>
                                <td>
                                    <p class="dm-cell-main dm-cell-regular">{{ $a->created_at->format('M j') }}</p>
                                    <p class="dm-cell-sub">{{ $a->created_at->format('g:i A') }}</p>
                                </td>
                                <td>{{ $a->remarks ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <footer class="dm-table-foot">
                <span>Showing {{ $actions->firstItem() }}–{{ $actions->lastItem() }} of {{ $actions->total() }}</span>
                <div class="dm-actions">
                    @if ($actions->onFirstPage())
                        <span class="dm-btn dm-btn-outline dm-btn-sm is-disabled" aria-disabled="true">‹ Prev</span>
                    @else
                        <a href="{{ $actions->previousPageUrl() }}" class="dm-btn dm-btn-outline dm-btn-sm">‹ Prev</a>
                    @endif
                    @if ($actions->hasMorePages())
                        <a href="{{ $actions->nextPageUrl() }}" class="dm-btn dm-btn-outline dm-btn-sm">Next ›</a>
                    @else
                        <span class="dm-btn dm-btn-outline dm-btn-sm is-disabled" aria-disabled="true">Next ›</span>
                    @endif
                </div>
            </footer>
        @endif
    </section>

</x-docmon.signatory-layout>
