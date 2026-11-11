{{--
    Ticket card shown on the Student Officer dashboard.
    Usage: <x-docmon.ticket-card :ticket="$ticket" />

    $ticket = [
        'code' => 'DM-2026-00123', 'ref' => '2026-09-008', 'status' => 'Pending',
        'title' => 'Activity Proposal – DIGITS Week', 'type' => 'Single',
        'officer' => 'Labastida, MJ Bryan', 'signatory' => 'Dela Cruz, Ana (Adviser)',
        'availability' => 'In a Meeting', 'availability_note' => 'Back at 2:00 PM',
        'submitted' => 'Sep 27, 2026', 'updated' => 'Sep 27, 12:40',
    ]
    Optional (AUD-077): 'needed' => 'Oct 3, 2026' (the Date Needed), 'deadline' => 'Overdue' or 'Due Soon'
    Optional 'holders' (a split Batch, AUD-049): one line per place instead of 'signatory', e.g.
        [['text' => 'With Reyes, Paolo (SSC President): 1 paper', 'availability' => 'Available', 'note' => null],
         ['text' => 'Back with you: 1 paper to revise, then to Adviser', 'availability' => null, 'note' => null]]
--}}
@props(['ticket'])

<article class="dm-ticket-card">
    <header class="dm-ticket-head">
        <div>
            <p class="dm-ticket-code">{{ $ticket['code'] }}</p>
            <p class="dm-ticket-ref">Ref No.: {{ $ticket['ref'] }}</p>
        </div>
        <div class="dm-badges">
            <x-docmon.status-badge :status="$ticket['status']" />
            @if ($ticket['deadline'] ?? null)
                <x-docmon.status-badge :status="$ticket['deadline']" />
            @endif
        </div>
    </header>

    <div class="dm-ticket-section">
        <p class="dm-ticket-title">{{ $ticket['title'] }}</p>
        <p class="dm-ticket-type">Type: {{ $ticket['type'] }}</p>
        @if ($ticket['needed'] ?? null)
            <p class="dm-ticket-type">Needed by: {{ $ticket['needed'] }}</p>
        @endif
    </div>

    <div class="dm-ticket-section dm-ticket-people">
        <p>Filed by: {{ $ticket['officer'] }}</p>
        @if (! empty($ticket['holders']))
            @foreach ($ticket['holders'] as $holder)
                <p class="dm-ticket-holder">{{ $holder['text'] }}</p>
                @if ($holder['availability'])
                    <x-docmon.availability :status="$holder['availability']" :note="$holder['note']" :colored="true" />
                @endif
            @endforeach
        @else
            <p>Signatory: {{ $ticket['signatory'] }}</p>
            {{-- No availability once every paper is done (nobody holds them anymore) --}}
            @if ($ticket['availability'] ?? null)
                <x-docmon.availability :status="$ticket['availability']" :note="$ticket['availability_note'] ?? null" :colored="true" />
            @endif
        @endif
    </div>

    <footer class="dm-ticket-foot">
        <span>Submitted: {{ $ticket['submitted'] }}</span>
        <span>Updated: {{ $ticket['updated'] }}</span>
    </footer>
</article>
