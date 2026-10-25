{{--
    Signatory availability with a colored dot.
    Usage: <x-docmon.availability status="In a Meeting" note="Back at 2:00 PM" />
    Statuses: Available (green), In a Meeting / Meeting / In Class (amber), Out of Office (red)
--}}
@props(['status', 'note' => null, 'colored' => false])

@php
    $variant = match (true) {
        $status === 'Available'                      => 'available',
        in_array($status, ['Out of Office', 'On Leave']) => 'away',
        default                                      => 'busy',
    };
@endphp

{{-- is-live: only real "Available" statuses pulse (not "Verified" or the radio options) --}}
<span class="dm-availability dm-availability-{{ $variant }} {{ $colored ? 'is-colored' : '' }} {{ $variant === 'available' ? 'is-live' : '' }}">
    <span class="dm-dot"></span>{{ $status }}@if ($note) · {{ $note }}@endif
</span>
