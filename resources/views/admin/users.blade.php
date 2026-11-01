{{--
    DOC-MON · Admin · Users (Admin\UserController@index)
    From the controller: $users (8 per page), $counts (per tab), $tab, $search, $orgId, $organizations.
    Edit → /admin/users/{id}/edit. Deactivate / Reactivate → POST /admin/users/{id}/deactivate|reactivate.
    No "Add User": everyone joins through sign-up and the ID check.
--}}
@php
    $query = fn (array $change) => url('/admin/users') . '?' . http_build_query(array_filter(array_merge(
        ['tab' => $tab === 'All' ? null : $tab, 'q' => $search, 'org' => $orgId ?: null], $change)));
@endphp

<x-docmon.admin-layout title="Users" active="Users">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Users</h1>
            <p class="dm-page-sub">Everyone with a DOC-MON account.</p>
        </div>
        <form method="GET" action="{{ url('/admin/users') }}" class="dm-header-actions">
            @if ($tab !== 'All') <input type="hidden" name="tab" value="{{ $tab }}"> @endif
            @if ($orgId) <input type="hidden" name="org" value="{{ $orgId }}"> @endif
            <label class="dm-search">
                <x-docmon.icon name="search" size="18" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Search name, ID No. or email" aria-label="Search users">
            </label>
        </form>
    </header>

    @error('user') <p class="dm-form-alert" role="alert">{{ $message }}</p> @enderror

    <section class="dm-card dm-panel">
        <div class="dm-panel-head">
            <div>
                <h2 class="dm-panel-title">All Users</h2>
                <p class="dm-panel-sub">
                    {{ $counts['All'] }} {{ Str::plural('account', $counts['All']) }}
                    @if ($search !== '') matching "{{ $search }}" · <a href="{{ $query(['q' => null]) }}">clear</a> @endif
                </p>
            </div>
            {{-- Organization filter: a plain GET form, applied when the choice changes --}}
            <form method="GET" action="{{ url('/admin/users') }}">
                @if ($tab !== 'All') <input type="hidden" name="tab" value="{{ $tab }}"> @endif
                @if ($search !== '') <input type="hidden" name="q" value="{{ $search }}"> @endif
                <select name="org" class="dm-select us-org" aria-label="Filter by organization" onchange="this.form.submit()">
                    <option value="">All organizations</option>
                    @foreach ($organizations as $org)
                        <option value="{{ $org->id }}" @selected($orgId === $org->id)>{{ $org->code }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="dm-btn dm-btn-outline dm-btn-sm">Filter</button></noscript>
            </form>
        </div>

        <nav class="dm-tabs">
            @foreach ($counts as $label => $count)
                <a href="{{ $query(['tab' => $label === 'All' ? null : $label, 'page' => null]) }}"
                   class="dm-tab {{ $label === $tab ? 'is-active' : '' }}">{{ $label }} ({{ $count }})</a>
            @endforeach
        </nav>

        @if ($users->isEmpty())
            <p class="nt-note">No accounts match.</p>
        @else
            <div class="dm-table-wrap">
                <table class="dm-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Organization</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $u)
                            <tr>
                                <td>
                                    <p class="dm-cell-main">{{ $u->displayName() }}</p>
                                    <p class="dm-cell-sub">{{ $u->email }}</p>
                                </td>
                                <td>{{ $u->roleLabel() }}</td>
                                <td>
                                    {{ $u->role === 'signatory'
                                        ? ($u->signatoryOrganizations->pluck('code')->implode(', ') ?: '—')
                                        : ($u->organization?->code ?? '—') }}
                                </td>
                                <td><x-docmon.status-badge :status="$u->statusLabel()" /></td>
                                <td>{{ $u->created_at->format('M Y') }}</td>
                                <td>
                                    <div class="dm-actions">
                                        <a href="{{ url('/admin/users/' . $u->id . '/edit') }}" class="dm-btn dm-btn-outline dm-btn-xs">Edit</a>
                                        @if ($u->status === 'deactivated')
                                            <form method="POST" action="{{ url('/admin/users/' . $u->id . '/reactivate') }}">
                                                @csrf
                                                <button type="submit" class="dm-btn dm-btn-outline dm-btn-xs">Reactivate</button>
                                            </form>
                                        @elseif ($u->status === 'verified' && $u->role !== 'admin' && ! $u->is(auth()->user()))
                                            <form method="POST" action="{{ url('/admin/users/' . $u->id . '/deactivate') }}">
                                                @csrf
                                                <button type="submit" class="dm-btn dm-btn-danger dm-btn-xs"
                                                        {{-- @js(): the name came from a sign-up form, so it must be safely encoded for JavaScript --}}
                                                        onclick="return confirm(@js('Deactivate ' . $u->displayName() . '? They will be logged out and cannot log in until reactivated.'))">Deactivate</button>
                                            </form>
                                        @elseif ($u->status === 'pending')
                                            <a href="{{ url('/admin/verification?user=' . $u->id) }}" class="dm-btn dm-btn-primary dm-btn-xs">Review</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <footer class="dm-table-foot">
                <span>Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}</span>
                <div class="dm-actions">
                    @if ($users->onFirstPage())
                        <span class="dm-btn dm-btn-outline dm-btn-sm is-disabled" aria-disabled="true">‹ Prev</span>
                    @else
                        <a href="{{ $users->previousPageUrl() }}" class="dm-btn dm-btn-outline dm-btn-sm">‹ Prev</a>
                    @endif
                    @if ($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}" class="dm-btn dm-btn-outline dm-btn-sm">Next ›</a>
                    @else
                        <span class="dm-btn dm-btn-outline dm-btn-sm is-disabled" aria-disabled="true">Next ›</span>
                    @endif
                </div>
            </footer>
        @endif
    </section>

</x-docmon.admin-layout>
