{{--
    Colored status pill.
    Usage: <x-docmon.status-badge status="Pending" />
    Statuses: Pending, Approved, Partly Approved, Rejected, Needs Revision, Verified, For Review, Unverified
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
    ][$status] ?? 'review';
@endphp

<span class="dm-badge dm-badge-{{ $variant }}">{{ $status }}</span>
