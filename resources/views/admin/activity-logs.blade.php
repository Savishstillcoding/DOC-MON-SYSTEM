{{--
    DOC-MON · Admin · Activity Logs (Admin\ActivityLogController@index)
    From the controller: $logs (8 per page, newest first), $search, $range, $tab.
    The lines come from the activity_logs table, which can only be added to, never edited.
--}}
@php
    $ranges = App\Http\Controllers\Admin\ActivityLogController::RANGES;
    $tabs = ['all' => 'All'] + App\Models\ActivityLog::CATEGORIES;
    $query = fn (array $change) => url('/admin/activity-logs') . '?' . http_build_query(array_filter(array_merge(
        ['tab' => $tab === 'all' ? null : $tab, 'q' => $search, 'range' => $range === '7' ? null : $range], $change)));
@endphp

<x-docmon.admin-layout title="Activity Logs" active="Activity Logs">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Activity Logs</h1>
            <p class="dm-page-sub">A record of everything that happens in DOC-MON.</p>
        </div>
        <form method="GET" action="{{ url('/admin/activity-logs') }}" class="dm-header-actions">
            @if ($tab !== 'all') <input type="hidden" name="tab" value="{{ $tab }}"> @endif
            <label class="dm-search">
                <x-docmon.icon name="search" size="18" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Search logs" aria-label="Search logs">
            </label>
            <select name="range" class="dm-select al-range" aria-label="Date range" onchange="this.form.submit()">
                @foreach ($ranges as $key => [$label])
                    <option value="{{ $key }}" @selected($range === (string) $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </header>

    <section class="dm-card dm-panel">
        <div class="dm-panel-head">
            <div>
                <h2 class="dm-panel-title">Logs</h2>
                <p class="dm-panel-sub">
                    Newest first · {{ $ranges[$range][0] }}
                    @if ($search !== '') · matching "{{ $search }}" · <a href="{{ $query(['q' => null]) }}">clear</a> @endif
                </p>
            </div>
        </div>

        <nav class="dm-tabs">
            @foreach ($tabs as $key => $label)
                <a href="{{ $query(['tab' => $key === 'all' ? null : $key, 'page' => null]) }}"
                   class="dm-tab {{ $key === $tab ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>

        @if ($logs->isEmpty())
            <p class="nt-note">Nothing was recorded {{ $range === 'all' ? '' : 'in this period' }}{{ $search !== '' ? ' that matches your search' : '' }}.
                @if ($range !== 'all') <a href="{{ $query(['range' => 'all']) }}">Show all time</a> @endif
            </p>
        @else
            <div class="dm-table-wrap">
                <table class="dm-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td>
                                    <p class="dm-cell-main">{{ $log->created_at->format('M j') }}</p>
                                    <p class="dm-cell-sub">{{ $log->created_at->format('g:i A') }}</p>
                                </td>
                                <td>
                                    <p class="dm-cell-main">{{ $log->actor_name }}</p>
                                    <p class="dm-cell-sub">{{ $log->roleLabel() }}</p>
                                </td>
                                <td class="dm-strong">{{ $log->action }}</td>
                                <td>{{ $log->details }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <footer class="dm-table-foot">
                <span>Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ $logs->total() }}</span>
                <div class="dm-actions">
                    @if ($logs->onFirstPage())
                        <span class="dm-btn dm-btn-outline dm-btn-sm is-disabled" aria-disabled="true">‹ Prev</span>
                    @else
                        <a href="{{ $logs->previousPageUrl() }}" class="dm-btn dm-btn-outline dm-btn-sm">‹ Prev</a>
                    @endif
                    @if ($logs->hasMorePages())
                        <a href="{{ $logs->nextPageUrl() }}" class="dm-btn dm-btn-outline dm-btn-sm">Next ›</a>
                    @else
                        <span class="dm-btn dm-btn-outline dm-btn-sm is-disabled" aria-disabled="true">Next ›</span>
                    @endif
                </div>
            </footer>
        @endif
    </section>

</x-docmon.admin-layout>
