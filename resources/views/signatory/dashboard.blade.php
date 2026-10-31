{{--
    DOC-MON · Signatory · Dashboard (Signatory\DashboardController)
    From the controller: $documents (waiting for this signatory, oldest first, filtered by $tab),
    $counts (per tab), $stats, $recent (this signatory's last 5 actions).
    Each row posts to /signatory/documents/{id}/action; return and reject need remarks.
    The availability form: PUT /signatory/availability (availability, back_at).
--}}
@php
    $availabilityOptions = [
        ['label' => 'Available',     'variant' => 'available'],
        ['label' => 'In a Meeting',  'variant' => 'busy'],
        ['label' => 'In Class',      'variant' => 'busy'],
        ['label' => 'Out of Office', 'variant' => 'away'],
    ];
    $account = auth()->user();
    $currentAvailability = old('availability', $account->availability ?? 'Available');

@endphp

<x-docmon.signatory-layout title="Dashboard" active="Dashboard">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Good day, {{ $account->position ?: $account->first_name }}!</h1>
            <p class="dm-page-sub">
                @if ($stats['waiting'] === 0)
                    No documents are waiting for your signature.
                @else
                    You have {{ $stats['waiting'] }} {{ Str::plural('document', $stats['waiting']) }} waiting for your signature.
                @endif
            </p>
        </div>
        <div class="dm-header-actions">
            @php $pillVariant = collect($availabilityOptions)->firstWhere('label', $account->availability ?? 'Available')['variant'] ?? 'available'; @endphp
            <span class="sg-status-pill is-{{ $pillVariant }}">
                <span class="dm-dot"></span>{{ $account->availability ?? 'Available' }}@if ($account->back_at) · {{ $account->back_at }}@endif
                <x-docmon.icon name="chevron-down" size="14" />
            </span>
            <label class="dm-search">
                <x-docmon.icon name="search" size="18" />
                <input type="search" placeholder="Search ticket or Ref No.">
            </label>
            <button type="button" class="dm-icon-btn" aria-label="Notifications"><x-docmon.icon name="bell" size="18" /></button>
        </div>
    </header>

    <section class="dm-stat-grid dm-stat-grid-4">
        <x-docmon.stat-card label="For Review" :value="$stats['waiting']" note="Waiting for your action" />
        <x-docmon.stat-card label="Signed This Week" :value="$stats['signedThisWeek']" note="Documents you approved" />
        <x-docmon.stat-card label="Returned" :value="$stats['withOfficer']" note="With the officer for revision" />
        <x-docmon.stat-card label="Rejected" :value="$stats['rejected']" note="All time" />
    </section>

    <div class="dm-columns">
        {{-- Documents for Review --}}
        <section class="dm-card dm-panel dm-align-start">
            <div class="dm-panel-head">
                <div>
                    <h2 class="dm-panel-title">Documents for Review</h2>
                    <p class="dm-panel-sub">Oldest submissions first</p>
                </div>
                <a href="{{ url('/signatory/review') }}" class="dm-btn dm-btn-outline dm-btn-sm">Open For Review</a>
            </div>

            @error('action') <p class="dm-form-alert" role="alert">{{ $message }}</p> @enderror

            <nav class="dm-tabs">
                @foreach ($counts as $label => $count)
                    <a href="{{ url('/signatory/dashboard') . ($label === 'All' ? '' : '?type=' . $label) }}"
                       class="dm-tab {{ $label === $tab ? 'is-active' : '' }}">{{ $label }} ({{ $count }})</a>
                @endforeach
            </nav>

            @if ($documents->isEmpty())
                <p class="nt-note">Nothing to sign right now. New papers appear here when they reach your step.</p>
            @else
            <div class="dm-table-wrap">
                <table class="dm-table">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Document</th>
                            <th>Waiting Since</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $doc)
                            @php $isMine = (int) old('document_id') === $doc->id; @endphp
                            <tr>
                                <td>
                                    <p class="dm-cell-main">{{ $doc->ticket->code }}</p>
                                    <p class="dm-cell-sub">Ref No. {{ $doc->ticket->ref_no }}</p>
                                </td>
                                <td>
                                    <p class="dm-cell-main">{{ $doc->type }}: {{ $doc->title }}</p>
                                    <p class="dm-cell-sub">
                                        {{ $doc->ticket->documents_count > 1 ? 'Batch (' . $doc->ticket->documents_count . ')' : 'Single' }}
                                        · {{ $doc->ticket->officer->displayName() }}
                                        @if ($doc->ref_no) · {{ $doc->ref_no }} @endif
                                    </p>
                                </td>
                                <td>
                                    <p class="dm-cell-main dm-cell-regular">{{ $doc->updated_at->format('M j') }}</p>
                                    <p class="dm-cell-sub">{{ $doc->updated_at->format('g:i A') }}</p>
                                </td>
                                <td>
                                    <form method="POST" action="{{ url('/signatory/documents/' . $doc->id . '/action') }}" class="sg-review-form">
                                        @csrf
                                        <input type="hidden" name="document_id" value="{{ $doc->id }}">
                                        <input type="text" name="remarks" maxlength="1000" aria-label="Remarks for {{ $doc->title }}"
                                               placeholder="Remarks (needed to revise or reject)" value="{{ $isMine ? old('remarks') : '' }}">
                                        @if ($isMine) @error('remarks') <p class="dm-field-error">{{ $message }}</p> @enderror @endif
                                        <div class="dm-actions">
                                            <button type="submit" name="action" value="approve" class="dm-btn dm-btn-success dm-btn-xs">Approve</button>
                                            <button type="submit" name="action" value="return" class="dm-btn dm-btn-outline dm-btn-xs">Revise</button>
                                            <button type="submit" name="action" value="reject" class="dm-btn dm-btn-danger dm-btn-xs"
                                                    onclick="return confirm('Reject is final: the officer must file a new ticket. Reject this document?')">Reject</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>

        <div class="dm-side">
            {{-- My Availability (the sidebar's "Availability" jumps here) --}}
            <section class="dm-card dm-panel" id="availability">
                <h2 class="dm-panel-title">My Availability</h2>
                <p class="dm-panel-sub">Officers see this on their tickets</p>

                <form method="POST" action="{{ url('/signatory/availability') }}" class="sg-availability-form">
                    @csrf
                    @method('PUT')
                    @foreach ($availabilityOptions as $option)
                        <label class="sg-option">
                            <input type="radio" name="availability" value="{{ $option['label'] }}"
                                   @if ($option['label'] === $currentAvailability) checked @endif>
                            <span class="dm-availability dm-availability-{{ $option['variant'] }}"><span class="dm-dot"></span></span>
                            {{ $option['label'] }}
                        </label>
                    @endforeach

                    <div class="dm-field">
                        <label for="sg-back-at">Back at (optional)</label>
                        <div class="sg-time">
                            <x-docmon.icon name="clock" size="18" />
                            <input id="sg-back-at" name="back_at" type="text" maxlength="30" placeholder="e.x. 2:00 PM" value="{{ old('back_at', $account->back_at) }}">
                        </div>
                        @error('back_at') <p class="dm-field-error">{{ $message }}</p> @enderror
                    </div>
                    @error('availability') <p class="dm-field-error">{{ $message }}</p> @enderror

                    <button type="submit" class="dm-btn dm-btn-primary dm-btn-block">Update Status</button>
                </form>
            </section>

            {{-- Recently Signed --}}
            <section class="dm-card dm-panel">
                <h2 class="dm-panel-title">Recently Signed</h2>
                @if ($recent->isEmpty())
                    <p class="nt-note">Your approvals, returns, and rejections will show here.</p>
                @else
                    <ul class="dm-activity">
                        @foreach ($recent as $item)
                            @php
                                [$color, $verb] = [
                                    'approved' => ['green', 'Approved'],
                                    'returned' => ['blue', 'Returned for revision'],
                                    'rejected' => ['red', 'Rejected'],
                                ][$item->action] ?? ['blue', ucfirst($item->action)];
                            @endphp
                            <li class="dm-activity-{{ $color }}">
                                <p>{{ $item->document->ticket->code }} · {{ $item->document->title }}</p>
                                <span>{{ $verb }} · {{ $item->created_at->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>

</x-docmon.signatory-layout>
