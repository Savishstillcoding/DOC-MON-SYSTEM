{{--
    "Telegram Notifications" panel on both Profile pages (Account\TelegramController).
    Usage: <x-docmon.telegram-panel :account="$account" />
    Four states:
      - Telegram not set up on this server (no TELEGRAM_BOT_* values): a short note
      - connected (telegram_chat_id saved): "Connected" + Disconnect
      - connecting (a code in the session): 1. Open Telegram  2. I pressed Start
      - not connected: what they'll get + Connect Telegram
--}}
@props(['account'])

@php
    $telegram = app(\App\Services\Telegram::class);
    $base = url('/'.$account->role.'/telegram');
    $pending = session('telegram_connect');
    $pending = $pending && $pending['expires'] >= now()->getTimestamp() ? $pending : null;
    $what = $account->role === 'signatory'
        ? 'Get a Telegram message when a paper is waiting for your signature.'
        : 'Get a Telegram message when a signatory approves, returns, or rejects your papers.';
@endphp

<section class="dm-card dm-panel" id="telegram">
    <div class="dm-panel-head">
        <div>
            <h2 class="dm-panel-title">Telegram Notifications</h2>
            <p class="dm-panel-sub">{{ $what }}</p>
        </div>
        @if ($account->telegram_chat_id && $telegram->configured())
            <x-docmon.status-badge status="Connected" />
        @endif
    </div>

    @error('telegram', 'telegram') <p class="dm-form-alert" role="alert">{{ $message }}</p> @enderror

    @if (! $telegram->configured())
        <p class="pf-notify-note">Telegram isn't set up on this server yet.</p>
    @elseif ($account->telegram_chat_id)
        <form method="POST" action="{{ $base }}" class="tg-row">
            @csrf
            @method('DELETE')
            <p class="pf-notify-note">Updates go to your Telegram. Disconnect to stop them.</p>
            <button type="submit" class="dm-btn dm-btn-danger dm-btn-sm">Disconnect</button>
        </form>
    @elseif ($pending)
        <ol class="tg-steps">
            <li>
                <a class="dm-btn dm-btn-primary dm-btn-sm" href="{{ $telegram->startLink($pending['code']) }}" target="_blank" rel="noopener">Open Telegram</a>
                <span class="pf-notify-note">and press <strong>Start</strong> in the bot's chat.</span>
            </li>
            <li>
                <form method="POST" action="{{ $base.'/check' }}">
                    @csrf
                    <button type="submit" class="dm-btn dm-btn-outline dm-btn-sm">I pressed Start</button>
                </form>
                <span class="pf-notify-note">Come back here and click this. The link works for 10 minutes.</span>
            </li>
        </ol>
    @else
        <form method="POST" action="{{ $base.'/connect' }}" class="tg-row">
            @csrf
            <p class="pf-notify-note">You need the Telegram app on your phone or computer.</p>
            <button type="submit" class="dm-btn dm-btn-primary dm-btn-sm">Connect Telegram</button>
        </form>
    @endif
</section>
