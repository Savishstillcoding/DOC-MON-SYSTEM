{{--
    DOC-MON · Student Officer · Dashboard (Officer\DashboardController)
    From the controller: $organization (with its route), $stats, $tab, $cards (the newest
    tickets as <x-docmon.ticket-card> arrays), $activity (latest 5 events on my tickets).
--}}
@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $account = auth()->user();
    $tabs = App\Models\Ticket::STATUS_TABS;
@endphp

<x-docmon.officer-layout title="Dashboard" active="Dashboard" :live="true">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Dashboard</h1>
            <p class="dm-page-sub">{{ collect([$organization?->code, 'Student Officer'])->filter()->implode(' · ') }}</p>
        </div>
        {{-- Search goes to My Tickets, which lists every ticket (AUD-021) --}}
        <form method="GET" action="{{ url('/officer/tickets') }}" class="dm-header-actions">
            <label class="dm-search">
                <x-docmon.icon name="search" size="18" />
                <input type="search" name="q" placeholder="Search ticket, Ref No. or paper" aria-label="Search my tickets">
            </label>
            <x-docmon.notify-bell />
            <a href="{{ url('/officer/tickets/new') }}" class="dm-btn dm-btn-primary"><x-docmon.icon name="plus" size="16" /> New Ticket</a>
        </form>
    </header>

    {{-- Welcome card --}}
    <section class="dm-card so-hero">
        <div>
            <p class="so-hero-date">{{ strtoupper(now()->format('l, F j, Y')) }}</p>
            <h2 class="so-hero-title">{{ $greeting }}, {{ $account->position ?: $account->first_name }}!</h2>
            <p class="so-hero-text" data-live="hero">
                @if ($stats['total'] === 0)
                    No tickets yet. Submit your first one when your papers are ready.
                @else
                    {{ $stats['pending'] }} {{ Str::plural('ticket', $stats['pending']) }} {{ $stats['pending'] === 1 ? 'is' : 'are' }} waiting on signatories
                    · {{ $stats['revision'] }} {{ $stats['revision'] === 1 ? 'needs' : 'need' }} your revision
                @endif
            </p>
        </div>
        <img class="so-hero-icons" src="{{ asset('images/dashboard-hero-icons.png') }}" alt="">
    </section>

    {{-- Numbers --}}
    <section class="dm-stat-grid dm-stat-grid-5" data-live="stats">
        <x-docmon.stat-card label="Total Tickets" :value="$stats['total']" note="All your tickets" />
        <x-docmon.stat-card label="Pending" :value="$stats['pending']" note="Waiting for signatures" />
        {{-- A finished Batch with some papers rejected is "Partly Approved": no card of its own, noted here (AUD-057) --}}
        <x-docmon.stat-card label="Approved" :value="$stats['approved']"
            :note="'Fully signed' . ($stats['partly'] ? ' · +' . $stats['partly'] . ' partly approved' : '')" />
        <x-docmon.stat-card label="Needs Revision" :value="$stats['revision']" note="Returned to you" />
        <x-docmon.stat-card label="Rejected" :value="$stats['rejected']" note="Not approved" />
    </section>

    <div class="dm-columns" data-live="columns">
        {{-- My Tickets --}}
        <section class="dm-card dm-panel">
            <div class="dm-panel-head">
                <div>
                    <h2 class="dm-panel-title">My Tickets</h2>
                    <p class="dm-panel-sub">Track each document from submission to approval</p>
                </div>
                <a href="{{ url('/officer/tickets') }}" class="dm-btn dm-btn-outline dm-btn-sm">View all</a>
            </div>

            <nav class="dm-tabs">
                @foreach ($tabs as $label)
                    <a href="{{ url('/officer/dashboard') . ($label === 'All' ? '' : '?status=' . urlencode($label)) }}"
                       class="dm-tab {{ $label === $tab ? 'is-active' : '' }}">{{ $label }}</a>
                @endforeach
            </nav>

            @if ($cards->isEmpty())
                <p class="nt-note">
                    {{ $tab === 'All' ? 'No tickets yet.' : 'No tickets with this status.' }}
                    <a href="{{ url('/officer/tickets/new') }}">Submit a new ticket</a>
                </p>
            @else
                <div class="so-ticket-grid">
                    @foreach ($cards as $ticket)
                        <x-docmon.ticket-card :ticket="$ticket" />
                    @endforeach
                </div>
            @endif
        </section>

        <div class="dm-side">
            {{-- Signatory Availability --}}
            <section class="dm-card dm-panel">
                <h2 class="dm-panel-title">Signatory Availability</h2>
                <p class="dm-panel-sub">{{ $organization?->code }} approval route · live status</p>

                @if ($organization && $organization->signatories->isNotEmpty())
                    <div class="dm-route">
                        @foreach ($organization->signatories as $s)
                            <x-docmon.signatory-row :badge="$s->initials()" :name="$s->displayName()"
                                                    :role="'Step ' . $s->pivot->step . ' · ' . $s->pivot->title"
                                                    :status="$s->availability ?? 'Available'" :note="$s->back_at" />
                        @endforeach
                    </div>
                @else
                    <p class="nt-note">Your organization has no approval route yet. Ask the admin to add its signatories.</p>
                @endif
            </section>

            {{-- Recent Activity --}}
            <section class="dm-card dm-panel">
                <h2 class="dm-panel-title">Recent Activity</h2>
                @if ($activity->isEmpty())
                    <p class="nt-note">Submissions, approvals and returns on your tickets will show here.</p>
                @else
                    <ul class="dm-activity">
                        @foreach ($activity as $item)
                            <li class="dm-activity-{{ $item['color'] }}">
                                <p>{{ $item['text'] }}</p>
                                <span>{{ $item['at']->diffForHumans() }} · {{ $item['at']->format('M j, g:i A') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>

</x-docmon.officer-layout>
