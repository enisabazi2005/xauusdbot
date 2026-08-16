<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected string $token;
    protected string $chatId;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
    }

    public function send(string $message): bool
    {
        $url =
            "https://api.telegram.org/bot{$this->token}/sendMessage";

        $response = Http::timeout(10)->post($url, [
            'chat_id' => $this->chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);

        return $response->successful();
    }

    public function sendNews(array $article): bool
    {
        $title = htmlspecialchars(
            $article['title'] ?? 'Unknown',
            ENT_QUOTES,
            'UTF-8'
        );

        $source = htmlspecialchars(
            $article['source'] ?? 'Unknown',
            ENT_QUOTES,
            'UTF-8'
        );

        $impact = strtoupper(
            $article['impact'] ?? 'LOW'
        );

        $url = htmlspecialchars(
            $article['url'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        /*
         * This is when XAUWIRE detected the article.
         *
         * Laravel is configured for Europe/Belgrade,
         * so this is our UTC+2 local time during summer.
         */
        $newsTime = isset($article['published_at'])
            ? $article['published_at']->format('h:i A')
            : now()->format('h:i A');

        /*
         * This is the exact moment the Telegram message
         * is being generated/sent.
         */
        $currentTime = now()->format('h:i A');

        $message = "🚨 <b>XAUWIRE</b>\n\n";

        $message .=
            "<b>{$impact} | {$title}</b>\n\n";

        $message .=
            "Source: {$source}\n";

        $message .=
            "Time: {$newsTime}\n";

        $message .=
            "Current time: {$currentTime}\n";

        if ($url) {
            $message .=
                "URL: <a href=\"{$url}\">Open Article</a>";
        }

        return $this->send($message);
    }
}