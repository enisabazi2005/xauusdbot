<?php

namespace App\Console\Commands;

use App\Models\NewsEvent;
use App\Services\ForexFactoryScrapper;
use App\Services\TelegramService;
use App\Services\XauusdFilterNews;
use Illuminate\Console\Command;

class ScrapeForexFactory extends Command
{
    protected $signature = 'forexfactory:scrape';

    protected $description =
        'Fetch Forex Factory news, detect XAUUSD relevant news, process calendar events and send new alerts';

    public function handle(
        ForexFactoryScrapper $scrapper,
        XauusdFilterNews $filter,
        TelegramService $telegram
    ): int {
        $this->info('Fetching Forex Factory news...');

        /*
         * =========================================================
         * 1. FETCH LATEST FOREX FACTORY NEWS
         * =========================================================
         */

        try {
            $stories = $scrapper->getLatestNews();
        } catch (\Throwable $e) {
            $this->error(
                'ForexFactory news API error: ' .
                $e->getMessage()
            );

            return self::FAILURE;
        }

        $this->info(
            'Received: ' . count($stories) . ' stories.'
        );

        if (empty($stories)) {
            $this->warn('No ForexFactory news found.');
        }

        /*
         * =========================================================
         * 2. NORMALIZE STORIES
         * =========================================================
         */

        $articles = [];

        foreach ($stories as $story) {
            $url = trim($story['url'] ?? '');
            $headline = trim($story['headline'] ?? '');

            if (!$url || !$headline) {
                continue;
            }

            $articles[] = [
                'title' => $headline,

                'url' => $url,

                'impact' => strtoupper(
                    $story['impact'] ?? 'low'
                ),

                'preview' => $story['preview'] ?? '',

                'source' => 'Forex Factory',

                /*
                 * The current news endpoint does not provide
                 * the original publication timestamp.
                 *
                 * This is therefore the detection/fetch time.
                 */
                'published_at' => now(),
            ];
        }

        /*
         * =========================================================
         * 3. REMOVE DUPLICATES FROM API RESPONSE
         * =========================================================
         */

        $articles = collect($articles)
            ->unique('url')
            ->values()
            ->all();

        $this->info(
            'Unique stories: ' . count($articles)
        );

        /*
         * =========================================================
         * 4. XAUUSD RELEVANCE FILTER
         * =========================================================
         */

        $relevant = [];

        foreach ($articles as $article) {
            $isRelevant = $filter->isRelevant($article);

            $this->line(
                ($isRelevant ? '✅' : '❌') .
                ' [' .
                $article['impact'] .
                '] ' .
                $article['title']
            );

            if ($isRelevant) {
                $relevant[] = $article;
            }
        }

        $this->newLine();

        $this->info(
            'XAUUSD relevant: ' . count($relevant)
        );

        /*
         * =========================================================
         * 5. DATABASE DUPLICATE PROTECTION
         * =========================================================
         *
         * The URL identifies the article.
         *
         * If NewsEvent already contains this URL,
         * it is NEVER sent again.
         */

        $newArticles = [];

        foreach ($relevant as $article) {
            $url = trim($article['url']);

            $existing = NewsEvent::where(
                'url',
                $url
            )->first();

            if ($existing) {
                $this->line(
                    '⏭️ Already exists: ' .
                    $article['title']
                );

                if ($existing->telegram_sent_at) {
                    $this->line(
                        '   ↳ Already sent to Telegram.'
                    );
                }

                continue;
            }

            $newArticles[] = $article;
        }

        $this->newLine();

        $this->info(
            'New XAUUSD news: ' .
            count($newArticles)
        );

        /*
         * =========================================================
         * 6. SAVE + SEND NEW NEWS
         * =========================================================
         */

        foreach ($newArticles as $article) {
            $url = trim($article['url']);

            $urlHash = hash(
                'sha256',
                $url
            );

            /*
             * Save BEFORE Telegram.
             */
            $newsEvent = NewsEvent::create([
                'external_id' => null,

                'url' => $url,

                'url_hash' => $urlHash,

                'headline' => $article['title'],

                'source' => $article['source'] ??
                    'Forex Factory',

                'impact' => strtolower(
                    $article['impact'] ?? 'unknown'
                ),

                'preview' => $article['preview'] ?? null,

                'published_at' =>
                    $article['published_at'] ?? now(),

                'fetched_at' => now(),

                'is_relevant' => true,

                'telegram_sent_at' => null,

                'telegram_message_id' => null,
            ]);

            try {
                $sent = $telegram->sendNews([
                    'title' =>
                        $newsEvent->headline,

                    'source' =>
                        $newsEvent->source,

                    'url' =>
                        $newsEvent->url,

                    'impact' =>
                        strtoupper(
                            $newsEvent->impact
                        ),

                    'published_at' =>
                        $newsEvent->published_at,
                ]);

                if ($sent) {
                    $newsEvent->update([
                        'telegram_sent_at' => now(),
                    ]);

                    $this->info(
                        '📨 Sent: ' .
                        $newsEvent->headline
                    );
                } else {
                    $this->error(
                        '❌ Telegram failed: ' .
                        $newsEvent->headline
                    );
                }

            } catch (\Throwable $e) {
                $this->error(
                    'Telegram error: ' .
                    $e->getMessage()
                );
            }
        }

        /*
         * =========================================================
         * 7. CALENDAR
         * =========================================================
         *
         * Calendar processing is intentionally kept separate
         * from the news processing above.
         *
         * The calendar command/service is responsible for:
         *
         * - weekly calendar overview
         * - TODAY notifications
         * - avoiding duplicate calendar notifications
         *
         * We DO NOT mix calendar events into NewsEvent.
         */

        $this->newLine();

        $this->info(
            'News processing completed.'
        );

        $this->info(
            'Calendar processing remains handled by the calendar flow.'
        );

        /*
         * =========================================================
         * DONE
         * =========================================================
         */

        $this->newLine();

        $this->info(
            'ForexFactory scrape completed.'
        );

        return self::SUCCESS;
    }
}