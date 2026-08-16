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
    // public function sendWeeklyCalendar(
    //     array $events
    // ): bool {
    //     $message = "🚨 <b>XAUWIRE</b>\n\n";
    //     $message .= "📅 <b>WEEKLY USD CALENDAR</b>\n\n";
    
    //     foreach ($events as $event) {
    //         $impact = strtoupper(
    //             $event['impact'] ?? 'UNKNOWN'
    //         );
    
    //         $name = htmlspecialchars(
    //             $event['name'] ?? 'Unknown',
    //             ENT_QUOTES,
    //             'UTF-8'
    //         );
    
    //         $date = htmlspecialchars(
    //             $event['display_date'] ?? '',
    //             ENT_QUOTES,
    //             'UTF-8'
    //         );
    
    //         $time = htmlspecialchars(
    //             $event['display_time'] ?? '',
    //             ENT_QUOTES,
    //             'UTF-8'
    //         );
    
    //         $forecast = htmlspecialchars(
    //             $event['forecast'] ?? '-',
    //             ENT_QUOTES,
    //             'UTF-8'
    //         );
    
    //         $previous = htmlspecialchars(
    //             $event['previous'] ?? '-',
    //             ENT_QUOTES,
    //             'UTF-8'
    //         );
    
    //         $message .=
    //             "<b>{$impact} | {$name}</b>\n" .
    //             "Date: {$date}\n" .
    //             "Time: {$time}\n" .
    //             "Forecast: {$forecast}\n" .
    //             "Previous: {$previous}\n\n";
    //     }
    
    //     return $this->send($message);
    // }
    // public function sendCalendarToday(
    //     array $event
    // ): bool {
    //     $name = htmlspecialchars(
    //         $event['name'] ?? 'Unknown',
    //         ENT_QUOTES,
    //         'UTF-8'
    //     );
    
    //     $impact = strtoupper(
    //         $event['impact'] ?? 'UNKNOWN'
    //     );
    
    //     $time = htmlspecialchars(
    //         $event['display_time'] ?? '',
    //         ENT_QUOTES,
    //         'UTF-8'
    //     );
    
    //     $forecast = htmlspecialchars(
    //         $event['forecast'] ?? '-',
    //         ENT_QUOTES,
    //         'UTF-8'
    //     );
    
    //     $previous = htmlspecialchars(
    //         $event['previous'] ?? '-',
    //         ENT_QUOTES,
    //         'UTF-8'
    //     );
    
    //     $message =
    //         "🚨 <b>XAUWIRE</b>\n\n" .
    //         "📅 <b>{$impact} | {$name} TODAY</b>\n\n" .
    //         "Time: {$time}\n" .
    //         "Forecast: {$forecast}\n" .
    //         "Previous: {$previous}";
    
    //     return $this->send($message);
    // }
    public function sendNewsWithMarket(
        array $article,
        ?array $gold
    ): bool {
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
    
        $impact = strtoupper(
            $article['impact'] ?? 'UNKNOWN'
        );
    
        $message =
            "🚨 <b>XAUWIRE</b>\n\n";
    
        $message .=
            "<b>{$impact} | {$title}</b>\n\n";
    
        $message .=
            "Source: {$source}\n";
    
        $message .=
            "News Time: {$time}\n";
    
        $message .=
            "Current Time: " .
            now()->format('h:i A') .
            "\n";
    
        if ($url) {
            $message .=
                "URL: <a href=\"{$url}\">Open Article</a>\n";
        }
    
        /*
         * ---------------------------------------------------------
         * GOLD MARKET
         * ---------------------------------------------------------
         */
    
        if ($gold) {
            $metrics =
                $gold['metrics'] ?? [];
    
            $m5 =
                $metrics['M5'] ?? null;
    
            $m15 =
                $metrics['M15'] ?? null;
    
            $message .=
                "\n━━━━━━━━━━━━━━\n";
    
            $message .=
                "🥇 <b>GOLD/USD</b>\n";
    
            if ($m5) {
                $message .=
                    "M5 Price: " .
                    ($m5['price'] ?? '-') .
                    "\n";
    
                $message .=
                    "M5 High: " .
                    ($m5['high'] ?? '-') .
                    "\n";
    
                $message .=
                    "M5 Low: " .
                    ($m5['low'] ?? '-') .
                    "\n";
    
                $message .=
                    "M5 Spread: " .
                    ($m5['spread'] ?? '-') .
                    "\n";
            }
    
            if ($m15) {
                $message .=
                    "M15 Price: " .
                    ($m15['price'] ?? '-') .
                    "\n";
            }
        }
    
        return $this->send(
            $message
        );
    }
    public function sendWeeklyCalendar(array $events): bool
{
    if (empty($events)) {
        return false;
    }

    $message = "🚨 <b>XAUWIRE — WEEK AHEAD</b>\n\n";
    $message .= "📅 <b>USD Economic Calendar</b>\n\n";

    foreach ($events as $event) {
        $impact = strtoupper(
            $event['impact'] ?? 'UNKNOWN'
        );

        $emoji = match ($impact) {
            'HIGH' => '🔴',
            'MEDIUM' => '🟠',
            default => '⚪',
        };

        $name = htmlspecialchars(
            $event['name'] ?? 'Unknown',
            ENT_QUOTES,
            'UTF-8'
        );

        $date = htmlspecialchars(
            $event['display_date'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        $time = htmlspecialchars(
            $event['display_time'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        $forecast = htmlspecialchars(
            $event['forecast'] ?? '-',
            ENT_QUOTES,
            'UTF-8'
        );

        $previous = htmlspecialchars(
            $event['previous'] ?? '-',
            ENT_QUOTES,
            'UTF-8'
        );

        $message .= "{$emoji} <b>{$date}</b>\n";
        $message .= "⏱ {$time}\n";
        $message .= "📊 <b>{$name}</b>\n";
        $message .= "Impact: <b>{$impact}</b>\n";
        $message .= "Forecast: {$forecast}\n";
        $message .= "Previous: {$previous}\n\n";
    }

    $message .= "⚠️ <b>XAUUSD WATCH</b>\n";
    $message .= "USD high/medium-impact events can create ";
    $message .= "significant volatility in gold.\n\n";
    $message .= "━━━━━━━━━━━━━━\n";
    $message .= "XAUWIRE";

    return $this->send($message);
}

public function sendCalendarToday(array $events): bool
{
    if (empty($events)) {
        return false;
    }

    $currentTime = now()
        ->timezone('Europe/Belgrade')
        ->format('H:i');

    $currentDate = now()
        ->timezone('Europe/Belgrade')
        ->format('d.m.Y');

    $message =
        "🚨 <b>XAUWIRE</b>\n\n";

    $message .=
        "🥇 <b>TODAY'S NEWS THAT WILL MOVE GOLD</b>\n\n";

    foreach ($events as $event) {

        $impact = strtoupper(
            $event['impact'] ?? 'UNKNOWN'
        );

        $emoji = match ($impact) {
            'HIGH' => '🔴',
            'MEDIUM' => '🟠',
            default => '⚪',
        };

        $name = htmlspecialchars(
            $event['name'] ?? 'Unknown',
            ENT_QUOTES,
            'UTF-8'
        );

        /*
        |--------------------------------------------------------------------------
        | Event time
        |--------------------------------------------------------------------------
        */

        $eventTime = '-';

        if (
            !empty($event['event_at'])
        ) {
            try {

                $eventAt = $event['event_at'];

                if (!$eventAt instanceof \Carbon\Carbon) {
                    $eventAt = \Carbon\Carbon::parse(
                        $eventAt
                    );
                }

                $eventTime = $eventAt
                    ->timezone('Europe/Belgrade')
                    ->format('H:i');

            } catch (\Throwable $e) {
                $eventTime = '-';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | URL
        |--------------------------------------------------------------------------
        */

        $url = $event['url'] ?? null;

        if ($url) {
            $url = htmlspecialchars(
                $url,
                ENT_QUOTES,
                'UTF-8'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Event
        |--------------------------------------------------------------------------
        */

        $message .=
            "{$emoji} <b>{$name}</b>\n";

        $message .=
            "News time: {$eventTime} UTC+2\n";

        $message .=
            "Current time: {$currentTime} UTC+2\n";

        if ($url) {
            $message .=
                "URL: <a href=\"{$url}\">Open Calendar</a>\n";
        }

        $message .= "\n";
    }

    $message .=
        "━━━━━━━━━━━━━━\n";

    $message .=
        "📅 {$currentDate}\n";

    $message .=
        "🥇 <b>XAUWIRE</b>";

    return $this->send($message);
}

public function sendXauusdOpening(
    $events,
    $news,
    $now
): bool {
    $message =
        "🚨 <b>XAUWIRE</b>\n\n";

    $message .=
        "🥇 <b>TODAY'S NEWS THAT WILL MOVE GOLD</b>\n\n";

    if ($events->isEmpty() && $news->isEmpty()) {
        $message .=
            "No relevant XAUUSD news or USD economic events found for today.\n\n";
    }

    foreach ($events as $event) {
        $name = htmlspecialchars(
            $event->name ?? 'Unknown',
            ENT_QUOTES,
            'UTF-8'
        );

        $time = $event->event_at
            ? $event->event_at->format('H:i')
            : ($event->display_time ?? '-');

        $impact = strtoupper(
            $event->impact ?? 'UNKNOWN'
        );

        $message .=
            "📅 <b>{$impact} | {$name}</b>\n";

        $message .=
            "News time: {$time} UTC+2\n";

        $message .=
            "Current Time: " .
            $now->format('H:i') .
            " UTC+2\n\n";
    }

    foreach ($news as $article) {
        $title = htmlspecialchars(
            $article->headline ?? 'Unknown',
            ENT_QUOTES,
            'UTF-8'
        );

        $url = htmlspecialchars(
            $article->url ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        $newsTime = $article->published_at
            ? $article->published_at->format('H:i')
            : '-';

        $message .=
            "📰 <b>{$title}</b>\n";

        $message .=
            "News time: {$newsTime} UTC+2\n";

        $message .=
            "Current Time: " .
            $now->format('H:i') .
            " UTC+2\n";

        if ($url) {
            $message .=
                "URL: <a href=\"{$url}\">Open Article</a>\n";
        }

        $message .= "\n";
    }

    $message .=
        "━━━━━━━━━━━━━━\n";

    $message .=
        "XAUWIRE";

    return $this->send($message);
}

public function sendNewYorkAnalysis(
    $news,
    $events,
    string $bias,
    $now
): bool {
    $message =
        "🚨 <b>XAUWIRE</b>\n\n";

    $message .=
        "🇺🇸 <b>NEW YORK IS ABOUT TO OPEN IN 30 MIN</b>\n\n";

    $message .=
        "Based on today's XAUUSD-relevant news:\n\n";

    if ($news->isEmpty() && $events->isEmpty()) {
        $message .=
            "No major relevant news has been recorded today.\n\n";
    } else {
        foreach ($news->take(8) as $article) {
            $title = htmlspecialchars(
                $article->headline ?? 'Unknown',
                ENT_QUOTES,
                'UTF-8'
            );

            $message .=
                "📰 {$title}\n";
        }

        foreach ($events as $event) {
            $name = htmlspecialchars(
                $event->name ?? 'Unknown',
                ENT_QUOTES,
                'UTF-8'
            );

            $message .=
                "📅 {$name}\n";
        }

        $message .= "\n";
    }

    $message .=
        "🥇 <b>XAUUSD BIAS: {$bias}</b>\n\n";

    $message .=
        "Current Time: " .
        $now->format('H:i') .
        " UTC+2\n\n";

    $message .=
        "━━━━━━━━━━━━━━\n";

    $message .=
        "XAUWIRE";

    return $this->send($message);
}
public function sendSessionClose(
    $news,
    $events,
    string $bias,
    $now
): bool {
    $message =
        "🚨 <b>XAUWIRE</b>\n\n";

    $message .=
        "📊 <b>BASED ON TODAY'S NEWS</b>\n\n";

    $message .=
        "🥇 <b>XAUUSD BIAS: {$bias}</b>\n\n";

    if (!$news->isEmpty()) {
        $message .=
            "Today's relevant news:\n";

        foreach ($news->take(10) as $article) {
            $title = htmlspecialchars(
                $article->headline ?? 'Unknown',
                ENT_QUOTES,
                'UTF-8'
            );

            $message .=
                "• {$title}\n";
        }

        $message .= "\n";
    }

    $message .=
        "Current Time: " .
        $now->format('H:i') .
        " UTC+2\n\n";

    $message .=
        "Good Luck 🍀\n\n";

    $message .=
        "━━━━━━━━━━━━━━\n";

    $message .=
        "XAUWIRE";

    return $this->send($message);
}


}