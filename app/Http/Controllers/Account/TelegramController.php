<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "Telegram Notifications" on the Profile page, shared by officers and signatories.
 * Connecting takes three clicks, so DOC-MON learns the user's Telegram chat
 * without anyone typing a chat ID:
 *   1. connect: makes a one-time code (kept in the session for 10 minutes)
 *   2. the user opens t.me/<bot>?start=<code> and presses Start in Telegram
 *   3. check:   finds "/start <code>" in the bot's messages and saves that chat
 * Only this user's session knows the code, so nobody can link someone else's account.
 */
class TelegramController extends Controller
{
    private const SESSION_KEY = 'telegram_connect';

    public function connect(Request $request, Telegram $telegram): RedirectResponse
    {
        if (! $telegram->configured()) {
            return back()->withErrors(['telegram' => 'Telegram isn\'t set up on this server yet.'], 'telegram');
        }

        // Letters and digits only: Telegram allows A-Z, a-z, 0-9, _ and - in a start code
        $request->session()->put(self::SESSION_KEY, [
            'code' => Str::random(32),
            'expires' => now()->addMinutes(10)->getTimestamp(),
        ]);

        return back();
    }

    public function check(Request $request, Telegram $telegram): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! $pending || $pending['expires'] < now()->getTimestamp()) {
            $request->session()->forget(self::SESSION_KEY);

            return back()->withErrors(['telegram' => 'That link expired. Click Connect Telegram again.'], 'telegram');
        }

        $chatId = $telegram->findStart($pending['code']);

        if ($chatId === null) {
            return back()->withErrors(['telegram' => 'We can\'t see your Start yet. Open Telegram with the button, press Start, then try again.'], 'telegram');
        }

        $user = $request->user();
        $user->forceFill(['telegram_chat_id' => $chatId])->save();
        $request->session()->forget(self::SESSION_KEY);
        ActivityLog::record($user, 'accounts', 'Connected Telegram', 'From the Profile page');

        $telegram->send($chatId, "✅ Your DOC-MON account ({$user->displayName()}) is connected. Updates about your papers will arrive here.");

        return back()->with('status', 'Telegram connected. We sent you a test message.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['telegram_chat_id' => null])->save();
        $request->session()->forget(self::SESSION_KEY);
        ActivityLog::record($user, 'accounts', 'Disconnected Telegram', 'From the Profile page');

        return back()->with('status', 'Telegram disconnected. You won\'t get messages anymore.');
    }
}
