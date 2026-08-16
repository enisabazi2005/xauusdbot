<?php

namespace App\Console\Commands;

use App\Models\NewsEvent;
use App\Services\TelegramService;
use App\Services\XauusdFilterNews;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ScrapeForexFactory extends Command
{
    protected $signature = 'forexfactory:scrape';

    protected $description =
        'Fetch Forex Factory news, detect XAUUSD relevant news and send new alerts to Telegram';

    public function handle(
        XauusdFilterNews $filter,
        TelegramService $telegram
    ): int {
        $this->info('Fetching Forex Factory news...');

        /*
         * ---------------------------------------------------------
         * FETCH NEWS
         * ---------------------------------------------------------
         */

        try {
            $apiKey = config('services.forexfactory.api_key');

            if (!$apiKey) {
                throw new \RuntimeException(
                    'FOREXFACTORY_API is not configured.'
                );
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'X-API-Key' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get(
                    'https://api.parse.bot/scraper/0d3aa2e2-80b6-42dc-986a-d7f0845f4deb/get_news_latest'
                );

            if (!$response->successful()) {
                throw new \RuntimeException(
                    'ForexFactory API returned HTTP ' .
                    $response->status()
                );
            }

            $json = $response->json();

        } catch (\Throwable $e) {
            $this->error(
                'ForexFactory API error: ' . $e->getMessage()
            );

            return self::FAILURE;
        }

        /*
         * ---------------------------------------------------------
         * VALIDATE API RESPONSE
         * ---------------------------------------------------------
         */

        if (
            !isset($json['status']) ||
            $json['status'] !== 'success'
        ) {
            $this->error(
                'ForexFactory API returned an unsuccessful response.'
            );

            return self::FAILURE;
        }

        $stories = $json['data']['stories'] ?? [];

        $this->info(
            'Received: ' . count($stories) . ' stories.'
        );

        if (empty($stories)) {
            $this->warn('No ForexFactory news found.');

            return self::SUCCESS;
        }

        /*
         * ---------------------------------------------------------
         * NORMALIZE STORIES
         * ---------------------------------------------------------
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
                 * The API does NOT provide publication time.
                 *
                 * Therefore this is the time our bot detected
                 * the article.
                 */
                'published_at' => now(),
            ];
        }

        /*
         * ---------------------------------------------------------
         * REMOVE DUPLICATES FROM API RESPONSE
         * ---------------------------------------------------------
         */

        $articles = collect($articles)
            ->unique('url')
            ->values()
            ->all();

        $this->info(
            'Unique stories: ' . count($articles)
        );

        if (empty($articles)) {
            $this->warn(
                'No valid articles after normalization.'
            );

            return self::SUCCESS;
        }

        /*
         * ---------------------------------------------------------
         * XAUUSD FILTER
         * ---------------------------------------------------------
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

        if (empty($relevant)) {
            $this->info(
                'No XAUUSD relevant news found.'
            );

            return self::SUCCESS;
        }

        /*
         * ---------------------------------------------------------
         * DATABASE DUPLICATE PROTECTION
         * ---------------------------------------------------------
         *
         * URL is the identity of the article.
         *
         * If this URL has already been sent,
         * NEVER send it again.
         */

/*
 * ---------------------------------------------------------
 * DATABASE DUPLICATE PROTECTION
 * ---------------------------------------------------------
 */

 $newArticles = [];

 foreach ($relevant as $article) {
 
     $url = trim($article['url']);
 
     /*
      * URL itself is unique.
      *
      * This is our primary duplicate protection.
      */
     $existing = NewsEvent::where('url', $url)->first();
 
     if ($existing) {
 
         $this->line(
             '⏭️ Already exists: ' .
             $article['title']
         );
 
         /*
          * If it was already sent, definitely don't send it.
          */
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
 
 if (empty($newArticles)) {
 
     $this->info(
         'Nothing new to send.'
     );
 
     return self::SUCCESS;
 }
 
 /*
  * ---------------------------------------------------------
  * SAVE + SEND
  * ---------------------------------------------------------
  */
 
 foreach ($newArticles as $article) {
 
     $url = trim($article['url']);
 
     /*
      * Generate a deterministic hash from the URL.
      *
      * This satisfies the unique url_hash column.
      */
     $urlHash = hash('sha256', $url);
 
     /*
      * Create database record BEFORE Telegram.
      */
     $newsEvent = NewsEvent::create([
         'external_id' => null,
 
         'url' => $url,
 
         'url_hash' => $urlHash,
 
         'headline' => $article['title'],
 
         'source' => $article['source'] ?? 'Forex Factory',
 
         'impact' => strtolower(
             $article['impact'] ?? 'unknown'
         ),
 
         'preview' => $article['preview'] ?? null,
 
         /*
          * IMPORTANT:
          *
          * The current API response does NOT provide
          * publication time.
          *
          * Therefore this represents when XAUWIRE
          * fetched/detected the article.
          */
         'published_at' => $article['published_at'] ?? now(),
 
         'fetched_at' => now(),
 
         'is_relevant' => true,
 
         'telegram_sent_at' => null,
 
         'telegram_message_id' => null,
     ]);
 
     try {
 
         $sent = $telegram->sendNews([
             'title' => $newsEvent->headline,
 
             'source' => $newsEvent->source,
 
             'url' => $newsEvent->url,
 
             'impact' => strtoupper(
                 $newsEvent->impact
             ),
 
             'published_at' => $newsEvent->published_at,
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
         * ---------------------------------------------------------
         * DONE
         * ---------------------------------------------------------
         */

        $this->newLine();

        $this->info(
            'ForexFactory scrape completed.'
        );

        return self::SUCCESS;
    }
}