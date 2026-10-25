{{--
    DOC-MON dashboard shell: dark sidebar + light main area.
    Every dashboard page wraps its content in this layout.

    Usage:
    <x-docmon.dashboard-layout title="Dashboard" :user="$user" :menu="$menu" active="Dashboard">
        ...page content...
    </x-docmon.dashboard-layout>

    $user = ['name' => 'Labastida, MJ Bryan', 'initials' => 'MJ', 'subtitle' => 'Secretary · DIGITS']
    $menu = [['label' => 'Dashboard', 'icon' => 'house', 'href' => '#'], ...]
--}}
@props(['title' => 'DOC-MON', 'user' => [], 'menu' => [], 'active' => ''])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} – DOC-MON</title>
    {{-- Inter font, served from public/fonts like the other pages (no Google Fonts request) --}}
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter/inter-latin.woff2') }}" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/docmon-dashboard.css') }}">
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
        {{ $slot }}
    </main>

    {{-- Live details (stat count-up). At the end of <body> so it runs before the first paint. --}}
    <script src="{{ asset('js/docmon-dashboard.js') }}"></script>
</body>
</html>
