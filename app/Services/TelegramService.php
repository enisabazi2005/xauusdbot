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
        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";

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

        $time = htmlspecialchars(
            $article['time'] ?? 'Unknown',
            ENT_QUOTES,
            'UTF-8'
        );

        $url = htmlspecialchars(
            $article['url'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        $message = "🚨 <b>XAUWIRE</b>\n\n";

        $message .= "📰 <b>XAUUSD NEWS</b>\n\n";

        $message .= "<b>{$title}</b>\n\n";

        $message .= "📡 Source: {$source}\n";
        $message .= "⏱ {$time}\n";

        if ($url) {
            $message .= "\n🔗 <a href=\"{$url}\">Forex Factory</a>";
        }

        return $this->send($message);
    }
}