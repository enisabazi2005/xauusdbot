<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Models\NewsEvent;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ForexFactorySession extends Command
{
    protected $signature = 'forexfactory:session
                            {--open : Send the 09:00 XAUUSD daily calendar}
                            {--new-york : Send the 14:00 New York opening analysis}';

    protected $description =
        'Send XAUUSD session messages based on today\'s news and calendar';

    public function handle(
        TelegramService $telegram
    ): int {
        $now = now();

        $this->info(
            'Session check: ' .
            $now->format('Y-m-d H:i:s')
        );

        if ($now->isWeekend()) {
            $this->info(
                'Weekend. Session notifications are disabled.'
            );

            return self::SUCCESS;
        }

        /*
        |--------------------------------------------------------------------------
        | OPEN
        |--------------------------------------------------------------------------
        */

        if ($this->option('open')) {
            return $this->sendOpeningMessage(
                $telegram,
                $now
            );
        }

        /*
        |--------------------------------------------------------------------------
        | NEW YORK
        |--------------------------------------------------------------------------
        */

        if ($this->option('new-york')) {
            return $this->sendNewYorkMessage(
                $telegram,
                $now
            );
        }

        $this->error(
            'Please specify --open or --new-york.'
        );

        return self::FAILURE;
    }

    /*
    |--------------------------------------------------------------------------
    | 09:00 OPENING MESSAGE
    |--------------------------------------------------------------------------
    */

    protected function sendOpeningMessage(
        TelegramService $telegram,
        Carbon $now
    ): int {
        $today = $now->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Today's economic events
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
            ->orderBy('event_at')
            ->get();

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

        $this->info(
            'Today calendar events: ' .
            $events->count()
        );

        $this->info(
            'Today XAUUSD news: ' .
            $news->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Telegram
        |--------------------------------------------------------------------------
        */

        try {
            $sent = $telegram->sendXauusdOpening(
                $events,
                $news,
                $now
            );
        } catch (\Throwable $e) {
            $this->error(
                'Opening Telegram error: ' .
                $e->getMessage()
            );

            return self::FAILURE;
        }

        if (!$sent) {
            $this->error(
                'Opening Telegram message failed.'
            );

            return self::FAILURE;
        }

        $this->info(
            '📨 XAUUSD opening message sent.'
        );

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | 14:00 NEW YORK MESSAGE
    |--------------------------------------------------------------------------
    */

    protected function sendNewYorkMessage(
        TelegramService $telegram,
        Carbon $now
    ): int {
        $today = $now->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Get today's relevant news
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
        | Get today's calendar events that have already happened
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
            'News available for New York analysis: ' .
            $news->count()
        );

        $this->info(
            'Calendar events available: ' .
            $events->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Calculate simple XAUUSD bias
        |--------------------------------------------------------------------------
        */

        $bias = $this->calculateBias(
            $news,
            $events
        );

        $this->info(
            'Calculated XAUUSD bias: ' .
            $bias
        );

        /*
        |--------------------------------------------------------------------------
        | Send
        |--------------------------------------------------------------------------
        */

        try {
            $sent = $telegram->sendNewYorkAnalysis(
                $news,
                $events,
                $bias,
                $now
            );
        } catch (\Throwable $e) {
            $this->error(
                'New York Telegram error: ' .
                $e->getMessage()
            );

            return self::FAILURE;
        }

        if (!$sent) {
            $this->error(
                'New York Telegram message failed.'
            );

            return self::FAILURE;
        }

        $this->info(
            '📨 New York opening analysis sent.'
        );

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | GOLD BIAS
    |--------------------------------------------------------------------------
    |
    | This is intentionally conservative.
    |
    | USD strength / hawkish Fed / higher yields
    | generally pressure gold.
    |
    | USD weakness / dovish Fed / lower yields
    | generally support gold.
    |
    */

    protected function calculateBias(
        $news,
        $events
    ): string {
        $buyScore = 0;
        $sellScore = 0;

        foreach ($news as $article) {
            $text = strtolower(
                ($article->headline ?? '') .
                ' ' .
                ($article->preview ?? '')
            );

            /*
            |--------------------------------------------------------------------------
            | SELL GOLD signals
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | BUY GOLD signals
            |--------------------------------------------------------------------------
            */

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

        /*
        |--------------------------------------------------------------------------
        | Calendar actual / forecast
        |--------------------------------------------------------------------------
        */

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

            /*
            |--------------------------------------------------------------------------
            | Convert numeric values when possible
            |--------------------------------------------------------------------------
            */

            $actualNumber = $this->numberFromString(
                $actual
            );

            $forecastNumber = $this->numberFromString(
                $forecast
            );

            if (
                $actualNumber !== null &&
                $forecastNumber !== null
            ) {
                if (
                    $actualNumber >
                    $forecastNumber
                ) {
                    /*
                     * Stronger economic data generally
                     * supports USD and pressures gold.
                     */
                    $sellScore++;
                }

                if (
                    $actualNumber <
                    $forecastNumber
                ) {
                    /*
                     * Weaker economic data generally
                     * pressures USD and supports gold.
                     */
                    $buyScore++;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Final decision
        |--------------------------------------------------------------------------
        */

        if ($buyScore > $sellScore) {
            return 'BUY';
        }

        if ($sellScore > $buyScore) {
            return 'SELL';
        }

        /*
        |--------------------------------------------------------------------------
        | No clear directional signal
        |--------------------------------------------------------------------------
        |
        | We still need BUY/SELL because this message is intended
        | to give a directional session bias.
        |
        | When neutral, default to BUY only when there are
        | no strong sell signals.
        */

        if ($sellScore === 0) {
            return 'BUY';
        }

        return 'SELL';
    }

    /*
    |--------------------------------------------------------------------------
    | Extract numeric value
    |--------------------------------------------------------------------------
    */

    protected function numberFromString(
        string $value
    ): ?float {
        /*
         * Remove percentage signs, commas,
         * currency symbols, etc.
         */

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
