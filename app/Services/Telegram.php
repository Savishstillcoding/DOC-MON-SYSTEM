<?php

namespace App\Services;

/*
| READING GUIDE 14/15: Telegram
|
| WHAT: a service: the connection to DOC-MON's Telegram bot.
| USED FOR: actually sending the messages, and linking a user's Telegram
|           account.
| KEY IDEA: if Telegram fails, the approval is still saved; the error is only logged.
*/

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Telegram = the connection to DOC-MON's Telegram bot.
 * The bot's token and username come from the server settings (TELEGRAM_BOT_*).
 * If Telegram is down, nothing breaks: every call has a short time limit, and
 * errors are written to the log instead of stopping the user.
 */
class Telegram
{
    /** configured() = are the bot's token and username set on this server? */
    public function configured(): bool
    {
        return filled(config('services.telegram.token')) && filled(config('services.telegram.username'));
    }

    /** startLink() = the link that opens our bot in Telegram with a one-time code. */
    public function startLink(string $code): string
    {
        return 'https://t.me/'.config('services.telegram.username').'?start='.$code;
    }

    /** send() = sends one message. Returns false (and logs why) if it couldn't. */
    public function send(?string $chatId, string $text): bool
    {
        if (blank($chatId) || ! $this->configured()) {
            return false;
        }

        $response = $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $text]);

        return $response !== null;
    }

    /**
     * findStart() = finds WHICH Telegram chat pressed Start on our link.
     * How it works:
     *   1. Read the bot's newest messages.
     *   2. Look for "/start <the one-time code>".
     *   3. Found → return that chat's ID (saved on the account). Not found → nothing.
     */
    public function findStart(string $code): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        // offset -100 = read only the bot's newest 100 messages; older ones are thrown away,
        // so the bot's inbox never fills up (Telegram keeps unread messages for 24 hours)
        $updates = $this->call('getUpdates', ['offset' => -100, 'allowed_updates' => ['message']]) ?? [];

        foreach (array_reverse($updates) as $update) {
            $message = $update['message'] ?? [];

            if (($message['chat']['type'] ?? null) === 'private' && ($message['text'] ?? null) === "/start {$code}") {
                return (string) $message['chat']['id'];
            }
        }

        return null;
    }

    /**
     * call() = one request to Telegram (3 s to connect, 5 s max).
     * Returns Telegram's answer, or nothing if it failed (the error is logged).
     */
    private function call(string $method, array $data): mixed
    {
        $token = config('services.telegram.token');

        try {
            $response = Http::connectTimeout(3)->timeout(5)
                ->post("https://api.telegram.org/bot{$token}/{$method}", $data);

            if ($response->json('ok') === true) {
                return $response->json('result');
            }

            $error = $response->json('description') ?? "HTTP {$response->status()}";
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        // The bot's secret token is part of the URL, and error messages repeat the URL,
        // so replace the token with *** before writing the error to the log file
        Log::warning("Telegram {$method} failed: ".str_replace($token, '***', $error));

        return null;
    }
}
