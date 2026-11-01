{{--
    The dashboard layout with the student officer menu, so the menu is defined once
    for every officer page (instead of being copied into each view, see AUD-019).

    Usage: <x-docmon.officer-layout title="My Tickets" active="My Tickets" :live="true"> ... </x-docmon.officer-layout>
    live = refresh the page's data-live boxes when the data changes (AUD-050)
--}}
@props(['title', 'active', 'live' => false])

@php
    $menu = [
        ['label' => 'Dashboard',   'icon' => 'house',     'href' => url('/officer/dashboard')],
        ['label' => 'New Ticket',  'icon' => 'file-plus', 'href' => url('/officer/tickets/new')],
        ['label' => 'My Tickets',  'icon' => 'file-text', 'href' => url('/officer/tickets')],
        ['label' => 'Signatories', 'icon' => 'users',     'href' => url('/officer/signatories')],
        ['label' => 'Profile',     'icon' => 'user',      'href' => url('/officer/profile')],
    ];
@endphp

<x-docmon.dashboard-layout :title="$title" :menu="$menu" :active="$active" :live="$live">
    {{ $slot }}
</x-docmon.dashboard-layout>
