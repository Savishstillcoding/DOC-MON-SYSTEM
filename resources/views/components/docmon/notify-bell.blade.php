{{--
    🔔 in a page header: a link to Profile → Telegram Notifications (AUD-059).
    A green dot shows that this user's Telegram is connected, so notifications are on.
    Usage: <x-docmon.notify-bell />
--}}
@php
    $user = auth()->user();
    $on = $user->telegram_chat_id && app(\App\Services\Telegram::class)->configured();
    $label = $on ? 'Telegram notifications are on' : 'Telegram notifications are off: connect on your Profile';
@endphp

<a href="{{ url('/'.$user->role.'/profile#telegram') }}" class="dm-icon-btn dm-bell" aria-label="{{ $label }}" title="{{ $label }}">
    <x-docmon.icon name="bell" size="18" />
    @if ($on)
        <span class="dm-bell-dot" aria-hidden="true"></span>
    @endif
</a>
