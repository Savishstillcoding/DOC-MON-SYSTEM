{{--
    DOC-MON · Student Officer · Dashboard (Officer\DashboardController)
    From the controller: $organization (with its route), $stats, $tab, $cards (tickets as
    <x-docmon.ticket-card> arrays, most urgent first), $todo + $todoMore (the To Do box,
    AUD-097), $activity (latest 5 events on my tickets).
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
            {{-- The greeting lives here now; the big welcome banner made way for To Do (AUD-097) --}}
            <p class="dm-page-sub">{{ collect([$greeting . ', ' . ($account->position ?: $account->first_name), $organization?->code, now()->format('l, F j, Y')])->filter()->implode(' · ') }}</p>
        </div>
        {{-- Search goes to My Tickets, which lists every ticket (AUD-021) --}}
        <form method="GET" action="{{ url('/officer/tickets') }}" class="dm-header-actions">
            <label class="dm-search">
                <x-docmon.icon name="search" size="18" />
                <input type="search" name="q" placeholder="Search tickets" aria-label="Search my tickets">
            </label>
            <x-docmon.notify-bell />
            <a href="{{ url('/officer/tickets/new') }}" class="dm-btn dm-btn-primary"><x-docmon.icon name="plus" size="16" /> New Ticket</a>
        </form>
    </header>

    {{-- To Do (AUD-097): what this officer has to act on, before anything else --}}
    <section class="dm-card dm-panel so-todo" data-live="todo" aria-labelledby="todo-title">
        <div class="dm-panel-head">
            <h2 class="dm-panel-title" id="todo-title">
                To Do @if ($todo->isNotEmpty())<span class="so-todo-count">· {{ $todo->count() + $todoMore }}</span>@endif
            </h2>
            @if ($todoMore > 0)
                <a href="{{ url('/officer/tickets?status=' . urlencode('Needs Revision')) }}" class="so-todo-more">{{ $todoMore }} more on My Tickets</a>
            @endif
        </div>

        @if ($todo->isEmpty())
            <p class="so-todo-clear">
                @if ($stats['total'] === 0)
                    No tickets yet. Submit your first one when your papers are ready.
                @else
                    <x-docmon.icon name="check" size="18" />
                    All clear: nothing to revise and nothing overdue.
                @endif
            </p>
        @else
            <ul class="so-todo-list">
                @foreach ($todo as $item)
                    <li class="so-todo-item">
                        <div class="dm-badges so-todo-badges">
                            @if ($item['kind'] === 'revision')
                                <x-docmon.status-badge status="Needs Revision" />
                                @if ($item['overdue'])
                                    <x-docmon.status-badge status="Overdue" />
                                @endif
                            @else
                                <x-docmon.status-badge status="Overdue" />
                            @endif
                        </div>

                        <div class="so-todo-main">
                            <p class="so-todo-paper">{{ $item['paper'] }}</p>
                            <p class="so-todo-ticket">{{ $item['ticket'] }}</p>
                            @if ($item['kind'] === 'revision')
                                <p class="so-todo-line">
                                    {{ $item['by'] }}@if ($item['remark']): <q>{{ $item['remark'] }}</q>@else returned it for revision.@endif
                                </p>
                            @elseif ($item['waiting'])
                                <p class="so-todo-line">{{ $item['waiting'] }}</p>
                                <x-docmon.availability :status="$item['availability']" :note="$item['note']" :colored="true" />
                            @endif
                        </div>

                        @if ($item['kind'] === 'revision')
                            <form method="POST" action="{{ url('/officer/documents/' . $item['document_id'] . '/resubmit') }}" class="so-todo-action">
                                @csrf
                                <button type="submit" class="dm-btn dm-btn-primary dm-btn-sm">Resubmit</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Counts (AUD-101): one line instead of five number cards. Each count opens My Tickets on that
         tab; the dot is the same colour as that status's badge. "Partly Approved" (a finished Batch with
         some papers rejected, AUD-057) only shows when there is one. --}}
    @php
        $countLinks = collect([
            ['Pending', 'Pending', 'pending', $stats['pending']],
            ['Needs Revision', 'Needs revision', 'revision', $stats['revision']],
            ['Approved', 'Approved', 'approved', $stats['approved']],
            ['Partly Approved', 'Partly approved', 'partial', $stats['partly']],
            ['Rejected', 'Rejected', 'rejected', $stats['rejected']],
        ])->reject(fn ($c) => $c[0] === 'Partly Approved' && ! $c[3]);
    @endphp
    <nav class="so-counts" data-live="stats" aria-label="My tickets by status">
        <a href="{{ url('/officer/tickets') }}" class="so-counts-total"><b>{{ $stats['total'] }}</b> {{ Str::plural('ticket', $stats['total']) }}</a>
        @foreach ($countLinks as [$tabName, $label, $variant, $count])
            <a href="{{ url('/officer/tickets?status=' . urlencode($tabName)) }}" class="so-count">
                <span class="so-count-dot so-count-{{ $variant }}" aria-hidden="true"></span><b>{{ $count }}</b> {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="dm-columns" data-live="columns">
        {{-- My Tickets --}}
        <section class="dm-card dm-panel">
            <div class="dm-panel-head">
                <div>
                    <h2 class="dm-panel-title">My Tickets</h2>
                    <p class="dm-panel-sub">Most urgent first: to revise, overdue, due soon, then newest</p>
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
                                                    :status="$s->availability ?? 'Available'" :note="$s->availabilityNote()" />
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
