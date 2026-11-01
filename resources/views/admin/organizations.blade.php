{{--
    DOC-MON · Admin · Organizations (Admin\OrganizationController@index)
    From the controller: $organizations (with officers_count, signatories_count, active_tickets_count),
    $search, $pilotId (the first organization ever added).
    Manage → /admin/organizations/{id}/edit · Signatory List → /admin/signatory-lists?org={id}
--}}
<x-docmon.admin-layout title="Organizations" active="Organizations">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Organizations</h1>
            <p class="dm-page-sub">Student organizations using DOC-MON and their signatory setup.</p>
        </div>
        <div class="dm-header-actions">
            <form method="GET" action="{{ url('/admin/organizations') }}">
                <label class="dm-search">
                    <x-docmon.icon name="search" size="18" />
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search organizations" aria-label="Search organizations">
                </label>
            </form>
            <a href="{{ url('/admin/organizations/new') }}" class="dm-btn dm-btn-primary"><x-docmon.icon name="plus" size="16" /> Add Organization</a>
        </div>
    </header>

    @if ($organizations->isEmpty())
        <p class="nt-note">
            {{ $search !== '' ? 'No organization matches "' . $search . '".' : 'No organizations yet.' }}
            <a href="{{ url('/admin/organizations/new') }}">Add one</a>
        </p>
    @else
        <section class="og-grid">
            @foreach ($organizations as $org)
                <article class="dm-card og-card">
                    <header class="og-head">
                        <span class="og-icon"><x-docmon.icon name="building" size="22" /></span>
                        <div class="og-title">
                            <h2>{{ $org->code }}</h2>
                            <p>{{ $org->name }}</p>
                        </div>
                        @if ((int) $org->id === (int) $pilotId)
                            <span class="og-pilot">Pilot</span>
                        @endif
                    </header>

                    <dl class="og-stats">
                        <div><dt>Officers</dt><dd>{{ $org->officers_count }}</dd></div>
                        <div><dt>Signatories</dt><dd>{{ $org->signatories_count }}</dd></div>
                        <div><dt>Active Tickets</dt><dd>{{ $org->active_tickets_count }}</dd></div>
                    </dl>

                    @if ($org->signatories_count === 0)
                        <p class="og-warn">No approval route yet: officers can't submit tickets.</p>
                    @endif

                    <div class="og-actions">
                        <a href="{{ url('/admin/organizations/' . $org->id . '/edit') }}" class="dm-btn dm-btn-outline dm-btn-sm">Manage</a>
                        <a href="{{ url('/admin/signatory-lists?org=' . $org->id) }}" class="dm-btn dm-btn-primary dm-btn-sm">Signatory List</a>
                    </div>
                </article>
            @endforeach
        </section>
    @endif

</x-docmon.admin-layout>
