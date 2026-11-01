{{--
    DOC-MON dashboard shell: dark sidebar + light main area.
    Every dashboard page wraps its content in this layout.

    Usage:
    <x-docmon.dashboard-layout title="Dashboard" :menu="$menu" active="Dashboard">
        ...page content...
    </x-docmon.dashboard-layout>

    $menu = [['label' => 'Dashboard', 'icon' => 'house', 'href' => '#'], ...]
    The sidebar card shows the logged-in user. A page may pass :user to override it:
    $user = ['name' => 'Labastida, MJ Bryan', 'initials' => 'MJ', 'subtitle' => 'Secretary · DIGITS']
    session('status') (e.g. "Profile saved.") is shown as a green bar above the content.

    Live updates (AUD-050): pass :live="true" and mark boxes with data-live="name".
    docmon-live.js then refreshes those boxes when the data behind them changes.
--}}
@props(['title' => 'DOC-MON', 'user' => null, 'menu' => [], 'active' => '', 'live' => false])

@php
    $account = auth()->user();
    $user ??= $account
        ? ['name' => $account->displayName(), 'initials' => $account->initials(), 'subtitle' => $account->subtitle()]
        : [];
    // The fingerprint this page was drawn with (null = this role has no live pages)
    $liveStamp = $live ? \App\Support\LiveStamp::for($account) : null;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} – DOC-MON</title>
    {{-- Inter font, served from public/fonts like the other pages (no Google Fonts request) --}}
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter/inter-latin.woff2') }}" crossorigin>
    <link rel="stylesheet" href="{{ asset_v('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/docmon-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/password-toggle.css') }}">
    @if ($liveStamp)
        <meta name="docmon-live" content="{{ url('/' . $account->role . '/live') }}" data-stamp="{{ $liveStamp }}">
    @endif
</head>
<body class="dm-body">
    <aside class="dm-sidebar">
        <div class="dm-brand">
            <span class="dm-brand-logo">
                <img src="{{ asset('images/logo.png') }}" alt="">
            </span>
            <span class="dm-brand-name">DOC-MON</span>
        </div>

        <div class="dm-user-card">
            <span class="dm-avatar dm-avatar-light">{{ $user['initials'] ?? '' }}</span>
            <div>
                <p class="dm-user-name">{{ $user['name'] ?? '' }}</p>
                <p class="dm-user-sub">{{ $user['subtitle'] ?? '' }}</p>
            </div>
        </div>

        <p class="dm-menu-label">MENU</p>
        <nav class="dm-menu">
            @foreach ($menu as $item)
                <a href="{{ $item['href'] ?? '#' }}" class="dm-menu-item {{ $item['label'] === $active ? 'is-active' : '' }}">
                    @if ($item['label'] === $active)
                        <span class="dm-menu-bar" aria-hidden="true"></span>
                    @endif
                    <x-docmon.icon :name="$item['icon']" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Logging out changes data (ends the session), so it's a POST form with
             a CSRF token, not a plain link that anything could trigger --}}
        <form method="POST" action="{{ url('/logout') }}" class="dm-logout">
            @csrf
            <button type="submit" class="dm-menu-item dm-logout-btn">
                <x-docmon.icon name="log-out" />
                <span>Log out</span>
            </button>
        </form>
    </aside>

    <main class="dm-main">
        @if (session('status'))
            <p class="dm-flash" role="status"><x-docmon.icon name="check" size="18" /> {{ session('status') }}</p>
        @endif

        {{ $slot }}
    </main>

    {{-- Live details (stat count-up). At the end of <body> so it runs before the first paint. --}}
    <script src="{{ asset_v('js/docmon-dashboard.js') }}"></script>
    {{-- Eye button in password boxes (Profile pages); does nothing on pages without one --}}
    <script src="{{ asset_v('js/password-toggle.js') }}"></script>
    @if ($liveStamp)
        <script src="{{ asset_v('js/docmon-live.js') }}"></script>
    @endif
</body>
</html>
