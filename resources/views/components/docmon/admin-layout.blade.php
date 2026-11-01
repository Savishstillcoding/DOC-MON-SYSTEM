{{--
    The dashboard layout with the admin menu, defined once for every admin page.
    Usage: <x-docmon.admin-layout title="Users" active="Users"> ... </x-docmon.admin-layout>
--}}
@props(['title', 'active'])

@php
    $menu = [
        ['label' => 'Dashboard',         'icon' => 'house',        'href' => url('/admin/dashboard')],
        ['label' => 'User Verification', 'icon' => 'shield-check', 'href' => url('/admin/verification')],
        ['label' => 'Users',             'icon' => 'users',        'href' => url('/admin/users')],
        ['label' => 'Organizations',     'icon' => 'building',     'href' => url('/admin/organizations')],
        ['label' => 'Signatory Lists',   'icon' => 'list',         'href' => url('/admin/signatory-lists')],
        ['label' => 'Activity Logs',     'icon' => 'clock',        'href' => url('/admin/activity-logs')],
    ];
@endphp

<x-docmon.dashboard-layout :title="$title" :menu="$menu" :active="$active">
    {{ $slot }}
</x-docmon.dashboard-layout>
