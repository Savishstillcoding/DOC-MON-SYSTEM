{{--
    DOC-MON · Components preview (from 05-components)
    A reference page showing every shared component in one place.
    Handy for checking styles; not part of the real system.
--}}
@php
    $iconNames = ['house', 'file-plus', 'file-text', 'users', 'user', 'clock', 'shield-check', 'building', 'inbox',
                  'list', 'log-out', 'bell', 'search', 'plus', 'check', 'x', 'pencil', 'eye'];

    $statuses = ['Pending', 'Approved', 'Rejected', 'Needs Revision', 'Verified', 'For Review', 'Unverified'];

    $sampleTicket = [
        'code' => 'DM-2026-00123', 'ref' => '2026-09-008', 'status' => 'Pending',
        'title' => 'Activity Proposal – DIGITS', 'type' => 'Single',
        'officer' => 'Labastida, MJ Bryan', 'signatory' => 'Dela Cruz, Ana (Adviser)',
        'availability' => 'In a Meeting', 'availability_note' => 'Back at 2:00 PM',
        'submitted' => 'Sep 27, 2026', 'updated' => 'Sep 27, 12:40',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Components – DOC-MON</title>
    {{-- Inter font, served from public/fonts like the other pages (no Google Fonts request) --}}
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter/inter-latin.woff2') }}" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/docmon-dashboard.css') }}">
</head>
<body class="cp-body">
    <div class="cp-row">
        {{-- Icon set --}}
        <div class="cp-icons">
            @foreach ($iconNames as $icon)
                <x-docmon.icon :name="$icon" />
            @endforeach
        </div>

        {{-- Sidebar menu item: normal + active --}}
        <div class="cp-menu">
            <a href="#" class="dm-menu-item"><x-docmon.icon name="house" /><span>Dashboard</span></a>
            <a href="#" class="dm-menu-item is-active"><span class="dm-menu-bar" aria-hidden="true"></span><x-docmon.icon name="house" /><span>Dashboard</span></a>
        </div>

        {{-- Status badges --}}
        <div class="dm-card cp-stack">
            @foreach ($statuses as $status)
                <x-docmon.status-badge :status="$status" />
            @endforeach
        </div>

        {{-- Buttons --}}
        <div class="dm-card cp-stack">
            <button type="button" class="dm-btn dm-btn-primary">Button</button>
            <button type="button" class="dm-btn dm-btn-outline">Button</button>
            <button type="button" class="dm-btn dm-btn-danger">Button</button>
            <button type="button" class="dm-btn dm-btn-success">Button</button>
        </div>
    </div>

    <div class="cp-row">
        <div class="cp-fixed"><x-docmon.stat-card label="Pending" value="12" note="Waiting for signatures" /></div>
        <div class="cp-ticket"><x-docmon.ticket-card :ticket="$sampleTicket" /></div>
        <div class="cp-fixed-wide"><x-docmon.signatory-row badge="AD" name="Dela Cruz, Ana" role="Adviser" status="Available" /></div>
    </div>
</body>
</html>
