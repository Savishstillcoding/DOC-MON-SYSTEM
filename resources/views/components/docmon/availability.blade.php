{{--
    Signatory availability with a colored dot.
    Usage: <x-docmon.availability status="In a Meeting" note="Back at 2:00 PM" />
    Statuses and colours come from User::AVAILABILITY: Available (green); In a Meeting,
    In Class, On Lunch Break (amber); On Leave, Out of Office (red). Anything else is amber.
--}}
@props(['status', 'note' => null, 'colored' => false])

@php
    $variant = App\Models\User::AVAILABILITY[$status] ?? 'busy';
@endphp

{{-- is-live: only real "Available" statuses pulse (not "Verified" or the radio options) --}}
<span class="dm-availability dm-availability-{{ $variant }} {{ $colored ? 'is-colored' : '' }} {{ $variant === 'available' ? 'is-live' : '' }}">
    <span class="dm-dot"></span><span class="dm-availability-text">{{ $status }}@if ($note) · {{ $note }}@endif</span>
</span>
