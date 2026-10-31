{{--
    The dashboard layout with the signatory menu, so the menu is defined once
    for every signatory page (instead of being copied into each view, see AUD-019).

    Usage: <x-docmon.signatory-layout title="History" active="History"> ... </x-docmon.signatory-layout>
--}}
@props(['title', 'active'])

@php
    $menu = [
        ['label' => 'Dashboard',    'icon' => 'house', 'href' => url('/signatory/dashboard')],
        ['label' => 'For Review',   'icon' => 'inbox', 'href' => url('/signatory/review')],
        ['label' => 'History',      'icon' => 'list',  'href' => url('/signatory/history')],
        ['label' => 'Availability', 'icon' => 'clock', 'href' => url('/signatory/dashboard') . '#availability'],
        ['label' => 'Profile',      'icon' => 'user',  'href' => url('/signatory/profile')],
    ];
@endphp

<x-docmon.dashboard-layout :title="$title" :menu="$menu" :active="$active">
    {{ $slot }}
</x-docmon.dashboard-layout>
