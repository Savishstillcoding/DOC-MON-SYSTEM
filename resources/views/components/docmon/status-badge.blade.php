{{--
    Colored status pill.
    Usage: <x-docmon.status-badge status="Pending" />
    Statuses: Pending, Approved, Partly Approved, Rejected, Needs Revision, Verified, For Review, Unverified, Deactivated, Connected,
    and the deadline badges from Ticket::deadlineStatus(): Overdue, Due Soon (AUD-077)
--}}
@props(['status'])

@php
    $variant = [
        'Pending'        => 'pending',
        'Approved'       => 'approved',
        'Partly Approved' => 'partial',
        'Rejected'       => 'rejected',
        'Needs Revision' => 'revision',
        'Verified'       => 'approved',
        'For Review'     => 'review',
        'Unverified'     => 'pending',
        'Deactivated'    => 'review',
        'Connected'      => 'approved',
        'Overdue'        => 'overdue',
        'Due Soon'       => 'due',
    ][$status] ?? 'review';
@endphp

<span class="dm-badge dm-badge-{{ $variant }}">{{ $status }}</span>
