<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Services\ForexFactoryScrapper;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ForexFactoryCalendar extends Command
{
    // protected $signature = 'forexfactory:calendar';
    protected $signature = 'forexfactory:calendar {--notify : Send today XAUUSD calendar notification}';



    protected $description =
        'Sync Forex Factory economic calendar and send weekly/today alerts';

        public function handle(
            ForexFactoryScrapper $scrapper,
            TelegramService $telegram
        ): int {
            $now = now();
        
            $this->info(
                'Calendar check: ' .
                $now->format('Y-m-d H:i:s')
            );
        
            /*
            |--------------------------------------------------------------------------
            | Only Monday-Friday
            |--------------------------------------------------------------------------
            */
        
            if ($now->isWeekend()) {
                $this->info(
                    'Weekend. Calendar notifications are disabled.'
                );
        
                return self::SUCCESS;
            }
        
            /*
            |--------------------------------------------------------------------------
            | Current week Monday
            |--------------------------------------------------------------------------
            */
        
            $weekStart = $now->copy()
                ->startOfWeek(Carbon::MONDAY);
        
            $week = strtolower(
                $weekStart->format('M') .
                $weekStart->format('d') .
                '.' .
                $weekStart->format('Y')
            );
        
            $this->info(
                'Calendar week: ' . $week
            );
        
            /*
            |--------------------------------------------------------------------------
            | Fetch calendar
            |--------------------------------------------------------------------------
            */
        
            try {
                $result = $scrapper->getCalendarFiltered(
                    $week,
                    'USD',
                    'high,medium'
                );
        
                $events = $result['events'] ?? [];
        
            } catch (\Throwable $e) {
                $this->error(
                    'Calendar API error: ' .
                    $e->getMessage()
                );
        
                return self::FAILURE;
            }
        
            $this->info(
                'Received calendar events: ' .
                count($events)
            );
        
            /*
            |--------------------------------------------------------------------------
            | Store events
            |--------------------------------------------------------------------------
            */
        
            foreach ($events as $event) {
        
                $this->storeEvent(
                    $event,
                    $weekStart
                );
        
                $this->line(
                    '📅 ' .
                    ($event['name'] ?? 'Unknown') .
                    ' | ' .
                    ($event['date'] ?? 'Unknown') .
                    ' | ' .
                    ($event['time'] ?? 'Unknown') .
                    ' | ' .
                    strtoupper(
                        $event['impact'] ?? 'unknown'
                    )
                );
            }
        
            /*
            |--------------------------------------------------------------------------
            | TODAY
            |--------------------------------------------------------------------------
            */
        
            // $this->sendTodayEvents(
            //     $telegram,
            //     $now
            // );
            if ($this->option('notify')) {
                $this->sendTodayEvents(
                    $telegram,
                    $now
                );
            }
        
            $this->newLine();
        
            $this->info(
                'Calendar processing completed.'
            );
        
            return self::SUCCESS;
        }
        

    /*
    |--------------------------------------------------------------------------
    | STORE EVENT
    |--------------------------------------------------------------------------
    */

    protected function storeEvent(
        array $event,
        Carbon $weekStart
    ): void {
        /*
        |--------------------------------------------------------------------------
        | External ID
        |--------------------------------------------------------------------------
        */

        $externalId = isset($event['id'])
            ? (string) $event['id']
            : null;

        /*
        |--------------------------------------------------------------------------
        | Stable fallback ID
        |--------------------------------------------------------------------------
        */

        if (!$externalId) {
            $externalId = sha1(
                implode('|', [
                    $event['name'] ?? '',
                    $event['date'] ?? '',
                    $event['time'] ?? '',
                    $event['currency'] ?? '',
                ])
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Parse event datetime
        |--------------------------------------------------------------------------
        */

        $eventAt = $this->parseEventDateTime(
            $event,
            $weekStart
        );

        /*
        |--------------------------------------------------------------------------
        | Save
        |--------------------------------------------------------------------------
        */

        CalendarEvent::updateOrCreate(
            [
                'external_id' => $externalId,
            ],
            [
                'name' =>
                    $event['name'] ?? 'Unknown',
        
                'country' =>
                    $event['country'] ?? 'US',
        
                'currency' =>
                    $event['currency'] ?? 'USD',
        
                'impact' =>
                    strtolower(
                        $event['impact'] ?? 'unknown'
                    ),
        
                'url' =>
                    $event['url'] ?? null,
        
                'event_at' =>
                    $eventAt,
        
                'display_date' =>
                    $event['date'] ?? null,
        
                'display_time' =>
                    $event['time'] ?? null,
        
                'actual' =>
                    $event['actual'] ?? null,
        
                'forecast' =>
                    $event['forecast'] ?? null,
        
                'previous' =>
                    $event['previous'] ?? null,
        
                'week_start' =>
                    $weekStart->toDateString(),
            ]
        );
        

        $this->line(
            '   ↳ Stored: ' .
            ($event['name'] ?? 'Unknown')
        );

        if ($eventAt) {
            $this->line(
                '   ↳ Event time: ' .
                $eventAt->format('Y-m-d H:i:s')
            );
        } else {
            $this->warn(
                '   ↳ Could not parse event datetime.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PARSE EVENT DATETIME
    |--------------------------------------------------------------------------
    */

    protected function parseEventDateTime(
        array $event,
        Carbon $weekStart
    ): ?Carbon {
        $date = trim(
            (string) ($event['date'] ?? '')
        );

        $time = trim(
            (string) ($event['time'] ?? '')
        );

        if ($date === '') {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | All-day / tentative events
        |--------------------------------------------------------------------------
        */

        if (
            $time === '' ||
            strtolower($time) === 'all day' ||
            strtolower($time) === 'tentative'
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Try several formats.
        |--------------------------------------------------------------------------
        */

        $formats = [
            'D M d Y g:i A',
            'D M d Y h:i A',
            'D M d Y H:i',
            'M d Y g:i A',
            'M d Y h:i A',
            'M d Y H:i',
        ];

        foreach ($formats as $format) {
            try {
                $value = Carbon::createFromFormat(
                    $format,
                    $date .
                    ' ' .
                    $weekStart->year .
                    ' ' .
                    $time,
                    config('app.timezone')
                );

                if ($value !== false) {
                    return $value;
                }
            } catch (\Throwable $e) {
                // Try next format.
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Final fallback
        |--------------------------------------------------------------------------
        */

        try {
            return Carbon::parse(
                $date .
                ' ' .
                $time .
                ' ' .
                $weekStart->year,
                config('app.timezone')
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | WEEKLY TELEGRAM
    |--------------------------------------------------------------------------
    */

    protected function sendWeeklyOverview(
        TelegramService $telegram,
        Carbon $weekStart
    ): void {
        $events = CalendarEvent::query()
            ->whereDate(
                'week_start',
                $weekStart->toDateString()
            )
            ->whereIn(
                'impact',
                ['high', 'medium']
            )
            ->orderBy('event_at')
            ->get();

        if ($events->isEmpty()) {
            $this->info(
                'No weekly calendar events.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Only unsent events
        |--------------------------------------------------------------------------
        */

        $unsent = $events->filter(
            fn ($event) =>
                $event->weekly_notified_at === null
        );

        if ($unsent->isEmpty()) {
            $this->info(
                'Weekly calendar already sent.'
            );

            return;
        }

        $payload = $unsent
            ->map(
                fn ($event) => [
                    'name' =>
                        $event->name,

                    'impact' =>
                        $event->impact,

                    'display_date' =>
                        $event->event_at
                            ? $event->event_at->format('D M d')
                            : $event->display_date,

                    'display_time' =>
                        $event->display_time,

                    'forecast' =>
                        $event->forecast,

                    'previous' =>
                        $event->previous,
                ]
            )
            ->all();

        try {
            $sent = $telegram->sendWeeklyCalendar(
                $payload
            );
        } catch (\Throwable $e) {
            $this->error(
                'Weekly Telegram error: ' .
                $e->getMessage()
            );

            return;
        }

        if ($sent) {
            foreach ($unsent as $event) {
                $event->update([
                    'weekly_notified_at' => now(),
                ]);
            }

            $this->info(
                '📨 Weekly calendar sent.'
            );
        } else {
            $this->error(
                '❌ Weekly calendar Telegram failed.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TODAY TELEGRAM
    |--------------------------------------------------------------------------
    */

    protected function sendTodayEvents(
        TelegramService $telegram,
        Carbon $now
    ): void {
        $events = CalendarEvent::query()
            ->whereDate(
                'event_at',
                $now->toDateString()
            )
            ->whereIn(
                'impact',
                ['high', 'medium']
            )
            ->orderBy('event_at')
            ->get();
    
        /*
        |--------------------------------------------------------------------------
        | Filter specifically for GOLD
        |--------------------------------------------------------------------------
        */
    
        $goldEvents = $events->filter(
            fn ($event) =>
                $this->isGoldRelevant($event)
        );
    
        if ($goldEvents->isEmpty()) {
            $this->info(
                'No XAUUSD-relevant events today.'
            );
    
            return;
        }
    
        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate daily notification
        |--------------------------------------------------------------------------
        */
    
        $unsent = $goldEvents->filter(
            fn ($event) =>
                $event->today_notified_at === null
        );
    
        if ($unsent->isEmpty()) {
            $this->info(
                'TODAY XAUUSD calendar already sent.'
            );
    
            return;
        }
    
        /*
        |--------------------------------------------------------------------------
        | Build Telegram payload
        |--------------------------------------------------------------------------
        */
    
        $payload = $unsent
            ->map(
                fn ($event) => [
                    'name' =>
                        $event->name,
    
                    'impact' =>
                        $event->impact,
    
                    'event_at' =>
                        $event->event_at,
    
                    'url' =>
                        $event->url ?? null,
                ]
            )
            ->values()
            ->all();
    
        /*
        |--------------------------------------------------------------------------
        | Send ONE Telegram message
        |--------------------------------------------------------------------------
        */
    
        try {
            $sent = $telegram->sendCalendarToday(
                $payload
            );
    
        } catch (\Throwable $e) {
    
            $this->error(
                'TODAY Telegram error: ' .
                $e->getMessage()
            );
    
            return;
        }
    
        /*
        |--------------------------------------------------------------------------
        | Mark as notified
        |--------------------------------------------------------------------------
        */
    
        if ($sent) {
    
            foreach ($unsent as $event) {
                $event->update([
                    'today_notified_at' => now(),
                ]);
            }
    
            $this->info(
                '📨 TODAY XAUUSD calendar sent: ' .
                count($unsent) .
                ' events.'
            );
    
        } else {
    
            $this->error(
                '❌ TODAY XAUUSD calendar Telegram failed.'
            );
        }
    }
    
    protected function isGoldRelevant(
        CalendarEvent $event
    ): bool {
        $name = strtolower($event->name);
    
        $keywords = [
            'cpi',
            'inflation',
            'core cpi',
            'ppi',
            'core ppi',
            'retail sales',
            'core retail sales',
            'nonfarm',
            'non-farm',
            'nfp',
            'unemployment',
            'jobless',
            'employment',
            'payroll',
            'average hourly earnings',
            'fed',
            'fomc',
            'interest rate',
            'fed chair',
            'powell',
            'consumer confidence',
            'consumer sentiment',
            'inflation expectations',
            'gdp',
            'pce',
            'core pce',
            'personal spending',
            'personal income',
            'ism manufacturing',
            'ism services',
            'manufacturing pmi',
            'services pmi',
            'jolts',
            'adp employment',
            'treasury',
            'president trump speaks',
        ];
    
        foreach ($keywords as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }
    
        return false;
    }
    
}
