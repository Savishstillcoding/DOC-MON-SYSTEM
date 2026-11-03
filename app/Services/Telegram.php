<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Talks to DOC-MON's Telegram bot (Bot API: https://core.telegram.org/bots/api).
 * The token and the bot's username come from config/services.php (TELEGRAM_BOT_*).
 *
 * A failed message must never break what the user was doing: approving a paper
 * still saves even if Telegram is down. So every call has a short timeout, and
 * errors are written to the log instead of being thrown.
 */
class Telegram
{
    public function configured(): bool
    {
        return filled(config('services.telegram.token')) && filled(config('services.telegram.username'));
    }

    /** The link that opens the bot in Telegram and sends it "/start <code>" when the user presses Start. */
    public function startLink(string $code): string
    {
        return 'https://t.me/'.config('services.telegram.username').'?start='.$code;
    }

    /** Sends a plain-text message. Returns false (and logs why) when it couldn't be sent. */
    public function send(?string $chatId, string $text): bool
    {
        if (blank($chatId) || ! $this->configured()) {
            return false;
        }

        $response = $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $text]);

        return $response !== null;
    }

    /**
     * Which chat pressed Start on startLink($code)? Looks through the bot's latest
     * messages for "/start <code>" and returns that chat's ID, or null if not found.
     */
    public function findStart(string $code): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        // offset -100 = only the newest 100 messages; older ones are dropped, so the
        // bot's mailbox never fills up (Telegram keeps unread messages for 24 hours)
        $updates = $this->call('getUpdates', ['offset' => -100, 'allowed_updates' => ['message']]) ?? [];

        foreach (array_reverse($updates) as $update) {
            $message = $update['message'] ?? [];

            if (($message['chat']['type'] ?? null) === 'private' && ($message['text'] ?? null) === "/start {$code}") {
                return (string) $message['chat']['id'];
            }
        }

        return null;
    }

    /** One Bot API call. Returns its "result", or null on any failure. */
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

        // The token is part of the URL, and connection errors repeat the URL: hide it
        Log::warning("Telegram {$method} failed: ".str_replace($token, '***', $error));

        return null;
    }
}
