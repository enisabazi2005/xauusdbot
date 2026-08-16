<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Models\NewsEvent;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ForexFactorySessionClose extends Command
{
    protected $signature =
        'forexfactory:session-close';

    protected $description =
        'Send XAUUSD end-of-day BUY/SELL analysis';

    public function handle(
        TelegramService $telegram
    ): int {
        $now = now();

        $this->info(
            'Session close check: ' .
            $now->format('Y-m-d H:i:s')
        );

        if ($now->isWeekend()) {
            $this->info(
                'Weekend. Closing message disabled.'
            );

            return self::SUCCESS;
        }

        $today = $now->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Today's XAUUSD news
        |--------------------------------------------------------------------------
        */

        $news = NewsEvent::query()
            ->whereDate(
                'published_at',
                $today
            )
            ->where(
                'is_relevant',
                true
            )
            ->orderBy('published_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Today's calendar events
        |--------------------------------------------------------------------------
        */

        $events = CalendarEvent::query()
            ->whereDate(
                'event_at',
                $today
            )
            ->whereIn(
                'impact',
                ['high', 'medium']
            )
            ->where(
                'event_at',
                '<=',
                $now
            )
            ->orderBy('event_at')
            ->get();

        $this->info(
            'Today XAUUSD news: ' .
            $news->count()
        );

        $this->info(
            'Today calendar events: ' .
            $events->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Calculate bias
        |--------------------------------------------------------------------------
        */

        $buyScore = 0;
        $sellScore = 0;

        foreach ($news as $article) {
            $text = strtolower(
                ($article->headline ?? '') .
                ' ' .
                ($article->preview ?? '')
            );

            $sellKeywords = [
                'dollar strength',
                'usd strength',
                'strong dollar',
                'higher yields',
                'yield rises',
                'yields rise',
                'hawkish fed',
                'rate hike',
                'higher rates',
                'hot inflation',
                'inflation rises',
                'strong jobs',
                'strong employment',
                'strong retail sales',
                'strong economic data',
                'gold falls',
                'gold decline',
                'gold drops',
                'gold slides',
            ];

            foreach ($sellKeywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $sellScore++;
                }
            }

            $buyKeywords = [
                'dollar weakness',
                'usd weakness',
                'weak dollar',
                'lower yields',
                'yield falls',
                'yields fall',
                'dovish fed',
                'rate cut',
                'lower rates',
                'cooling inflation',
                'inflation falls',
                'weak jobs',
                'weak employment',
                'weak retail sales',
                'weak economic data',
                'gold rises',
                'gold rally',
                'gold gains',
                'gold climbs',
            ];

            foreach ($buyKeywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $buyScore++;
                }
            }
        }

        foreach ($events as $event) {
            $actual = trim(
                strtolower(
                    (string) $event->actual
                )
            );

            $forecast = trim(
                strtolower(
                    (string) $event->forecast
                )
            );

            if (
                $actual === '' ||
                $forecast === ''
            ) {
                continue;
            }

            $actualNumber =
                $this->numberFromString($actual);

            $forecastNumber =
                $this->numberFromString($forecast);

            if (
                $actualNumber !== null &&
                $forecastNumber !== null
            ) {
                if (
                    $actualNumber >
                    $forecastNumber
                ) {
                    $sellScore++;
                }

                if (
                    $actualNumber <
                    $forecastNumber
                ) {
                    $buyScore++;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Final BUY / SELL
        |--------------------------------------------------------------------------
        */

        if ($buyScore > $sellScore) {
            $bias = 'BUY';
        } elseif ($sellScore > $buyScore) {
            $bias = 'SELL';
        } elseif ($sellScore === 0) {
            $bias = 'BUY';
        } else {
            $bias = 'SELL';
        }

        $this->info(
            'Final XAUUSD bias: ' .
            $bias
        );

        /*
        |--------------------------------------------------------------------------
        | Telegram
        |--------------------------------------------------------------------------
        */

        try {
            $sent = $telegram->sendSessionClose(
                $news,
                $events,
                $bias,
                $now
            );
        } catch (\Throwable $e) {
            $this->error(
                'Closing Telegram error: ' .
                $e->getMessage()
            );

            return self::FAILURE;
        }

        if (!$sent) {
            $this->error(
                'Closing Telegram message failed.'
            );

            return self::FAILURE;
        }

        $this->info(
            '📨 Closing XAUUSD message sent.'
        );

        return self::SUCCESS;
    }

    protected function numberFromString(
        string $value
    ): ?float {
        $value = str_replace(
            [',', '%', '$', '€', '£'],
            '',
            $value
        );

        if (
            preg_match(
                '/-?\d+(?:\.\d+)?/',
                $value,
                $matches
            )
        ) {
            return (float) $matches[0];
        }

        return null;
    }
}
