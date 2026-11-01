{{--
    DOC-MON · Admin · Dashboard (Admin\DashboardController)
    From the controller: $pending (accounts waiting, filtered by $tab), $counts (per tab), $stats,
    $organizations (with their approval routes), $recent (latest verify/reject decisions).
      View ID → GET  /admin/users/{id}/id-document (opens the uploaded photo/PDF in a new tab)
      Verify  → POST /admin/users/{id}/verify      Reject → POST /admin/users/{id}/reject
    The ID file is deleted after Verify or Reject.
--}}
@php
    $menu = [
        ['label' => 'Dashboard',         'icon' => 'house',        'href' => url('/admin/dashboard')],
        ['label' => 'User Verification', 'icon' => 'shield-check', 'href' => url('/admin/dashboard') . '#pending'],
        ['label' => 'Users',             'icon' => 'users',        'href' => '#'],
        ['label' => 'Organizations',     'icon' => 'building',     'href' => '#'],
        ['label' => 'Signatory Lists',   'icon' => 'list',         'href' => '#'],
        ['label' => 'Activity Logs',     'icon' => 'clock',        'href' => '#'],
    ];
    $roleLabel = ['officer' => 'Student Officer', 'signatory' => 'Signatory'];
@endphp

<x-docmon.dashboard-layout title="Admin Dashboard" :menu="$menu" active="Dashboard">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Admin Dashboard</h1>
            <p class="dm-page-sub">Verify accounts so only real officers and signatories can use DOC-MON.</p>
        </div>
    </header>

    <section class="dm-stat-grid dm-stat-grid-4">
        <x-docmon.stat-card label="Pending Verification" :value="$stats['pending']" note="Accounts waiting for review" />
        <x-docmon.stat-card label="Verified Users" :value="$stats['verified']" note="Officers and signatories" />
        <x-docmon.stat-card label="Organizations" :value="$stats['organizations']" :note="$organizations->pluck('code')->implode(', ') ?: 'None yet'" />
        <x-docmon.stat-card label="Active Tickets" :value="$stats['activeTickets']" note="Papers still being signed" />
    </section>

    @error('verify') <p class="dm-form-alert" role="alert">{{ $message }}</p> @enderror

    <div class="dm-columns">
        {{-- Pending Verification --}}
        <section class="dm-card dm-panel dm-align-start" id="pending">
            <div class="dm-panel-head">
                <div>
                    <h2 class="dm-panel-title">Pending Verification</h2>
                    <p class="dm-panel-sub">Open the uploaded ID and compare it with the name and ID number before verifying</p>
                </div>
            </div>

            <nav class="dm-tabs">
                @foreach ($counts as $label => $count)
                    <a href="{{ url('/admin/dashboard') . ($label === 'All' ? '' : '?tab=' . urlencode($label)) }}#pending"
                       class="dm-tab {{ $label === $tab ? 'is-active' : '' }}">{{ $label }} ({{ $count }})</a>
                @endforeach
            </nav>

            @if ($pending->isEmpty())
                <p class="nt-note">No accounts are waiting for verification.</p>
            @else
                <div class="dm-table-wrap">
                    <table class="dm-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>ID No.</th>
                                <th>Uploaded ID</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pending as $p)
                                <tr>
                                    <td>
                                        <p class="dm-cell-main">{{ $p->displayName() }}</p>
                                        <p class="dm-cell-sub">{{ collect([$p->email, $p->phone])->filter()->implode(' · ') }}</p>
                                    </td>
                                    <td>
                                        <p class="dm-cell-main">{{ $roleLabel[$p->role] }}</p>
                                        <p class="dm-cell-sub">{{ collect([$p->organization?->code, $p->position])->filter()->implode(' · ') }}</p>
                                    </td>
                                    <td>{{ $p->login_id }}</td>
                                    <td>
                                        @if ($p->idDocument)
                                            <a href="{{ url('/admin/users/' . $p->id . '/id-document') }}" target="_blank" rel="noopener"
                                               class="dm-btn dm-btn-outline dm-btn-xs">View ID</a>
                                            <p class="dm-cell-sub">{{ $p->idDocument->mime === 'application/pdf' ? 'PDF' : 'Photo' }} · {{ number_format($p->idDocument->size / 1024) }} KB</p>
                                        @else
                                            <p class="dm-cell-sub">No ID uploaded</p>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="dm-actions">
                                            <form method="POST" action="{{ url('/admin/users/' . $p->id . '/verify') }}">
                                                @csrf
                                                <button type="submit" class="dm-btn dm-btn-success dm-btn-xs">Verify</button>
                                            </form>
                                            <form method="POST" action="{{ url('/admin/users/' . $p->id . '/reject') }}">
                                                @csrf
                                                <button type="submit" class="dm-btn dm-btn-danger dm-btn-xs"
                                                        onclick="return confirm('Reject this account? Their ID will be deleted and they will have to sign up again.')">Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="dm-side">
            {{-- Each organization's approval route --}}
            @forelse ($organizations as $org)
                <section class="dm-card dm-panel">
                    <h2 class="dm-panel-title">Signatory List</h2>
                    <p class="dm-panel-sub">{{ $org->code }} · approval order</p>
                    <div class="dm-route dm-route-plain">
                        @forelse ($org->signatories as $s)
                            <x-docmon.signatory-row :badge="$s->pivot->step" :name="$s->displayName()"
                                                    :role="'Step ' . $s->pivot->step . ' · ' . $s->pivot->title . ($loop->last ? ' (final say)' : '')"
                                                    :status="ucfirst($s->status)" />
                        @empty
                            <p class="nt-note">No signatories on this route yet.</p>
                        @endforelse
                    </div>
                </section>
            @empty
                <section class="dm-card dm-panel">
                    <h2 class="dm-panel-title">Signatory List</h2>
                    <p class="nt-note">No organizations yet.</p>
                </section>
            @endforelse

            {{-- Recent decisions --}}
            <section class="dm-card dm-panel">
                <h2 class="dm-panel-title">Recent Activity</h2>
                @if ($recent->isEmpty())
                    <p class="nt-note">Accounts you verify or reject will show here.</p>
                @else
                    <ul class="dm-activity">
                        @foreach ($recent as $u)
                            <li class="dm-activity-{{ $u->status === 'verified' ? 'green' : 'red' }}">
                                <p>{{ $u->status === 'verified' ? 'Verified' : 'Rejected' }} {{ $u->displayName() }} ({{ $roleLabel[$u->role] ?? $u->role }})</p>
                                <span>{{ $u->reviewed_at->diffForHumans() }}@if ($u->reviewer) · by {{ $u->reviewer->displayName() }}@endif</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>

</x-docmon.dashboard-layout>
